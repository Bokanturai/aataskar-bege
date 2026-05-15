<?php

namespace App\Http\Controllers\Agency;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\AgentService;
use App\Models\Services1 as Service;
use App\Models\ServiceField;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Http\Controllers\Controller;
use App\Services\CrmService;

class BvncrmController extends Controller
{
    /**
     * Display the service form and submission history for CRM.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $serviceKey = 'CRM';

        // Query only this user's submissions
        $submissions = AgentService::with('transaction')
            ->where('user_id', $user->id)
            ->where('service_name', $serviceKey)
            ->when($request->filled('search'), fn($q) =>
                $q->where('batch_id', 'like', "%{$request->search}%"))
            ->when($request->filled('status'), fn($q) =>
                $q->where('status', $request->status))
            ->orderByRaw("
                CASE
                    WHEN status = 'pending' THEN 1
                    WHEN status = 'processing' THEN 2
                    WHEN status = 'successful' THEN 3
                    WHEN status = 'query' THEN 4
                    ELSE 99
                END
            ")->orderByDesc('submission_date')
            ->paginate(10)
            ->withQueryString();

        // Load active service and its fields
        $service = Service::where('name', $serviceKey)
            ->where('is_active', true)
            ->with(['fields' => fn($q) => $q->where('is_active', true), 'prices'])
            ->first();

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0.00, 'status' => 'active']
        );

        $fields = $service?->fields ?? collect();
        $prices = $service?->prices ?? collect();

        return view('bvn.crm', [
            'fieldname'     => $fields,
            'services'      => Service::where('is_active', true)->get(),
            'serviceName'   => $serviceKey,
            'submissions'   => $submissions,
            'servicePrices' => $prices,
            'wallet'        => $wallet,
        ]);
    }

    /**
     * Store submission for CRM.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $serviceKey = 'CRM';

        // 1. Validation
        $rules = [
            'field_code' => 'required|exists:service_fields,id',
            'ticket_id'  => 'required|string|size:8|regex:/^[0-9]{8}$/',
            'batch_id'   => 'required|string|size:7|regex:/^[0-9]{7}$/',
        ];

        $validated = $request->validate($rules);

        // 2. Fetch Service Field and Price
        $serviceField = ServiceField::with('service')->findOrFail($validated['field_code']);
        $serviceName = $serviceField->service->name ?? 'Unknown Service';
        $fieldName = $serviceField->field_name;

        // Check for duplicate pending/processing requests for the same ticket/batch
        $existing = AgentService::where('user_id', $user->id)
            ->where('service_name', 'CRM')
            ->where('batch_id', $validated['batch_id'])
            ->where('ticket_id', $validated['ticket_id'])
            ->whereIn('status', ['pending', 'processing'])
            ->first();

        if ($existing) {
            return back()->with([
                'status' => 'error',
                'message' => 'A request for this Batch/Ticket ID is already being processed.',
            ])->withInput();
        }

        $servicePrice = $serviceField->getPriceForUserType($user->role);

        if ($servicePrice === null) {
            return back()->with([
                'status'  => 'error',
                'message' => 'Service price not configured for your account type.'
            ])->withInput();
        }

        DB::beginTransaction();

        try {
            // 3. Lock Wallet and Check Balance
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            // 4. Check Balance
            if ($wallet->balance < $servicePrice) {
                throw new \Exception('Insufficient balance. You need NGN ' . number_format($servicePrice - $wallet->balance, 2) . ' more.');
            }

            $reference = 'CRM' . date('is') . strtoupper(substr(uniqid(mt_rand(), true), -5));
            $performedBy = trim($user->first_name . ' ' . $user->last_name);

            // 5. Create Transaction Record
            $transaction = Transaction::create([
                'referenceId'         => $reference,
                'user_id'             => $user->id,
                'amount'              => $servicePrice,
                'service_type'        => 'BVN CRM',
                'service_description' => "{$serviceName} Request for {$fieldName}",
                'type'                => 'debit',
                'status'              => 'Approved',
            ]);

            // 6. Create AgentService Record
            AgentService::create([
                'reference'       => $reference,
                'user_id'         => $user->id,
                'service_id'      => $serviceField->service_id,
                'service_field_id' => $serviceField->id,
                'field_code'      => $serviceField->field_code,
                'service_name'    => $serviceName,
                'field_name'      => $fieldName,
                'ticket_id'       => $validated['ticket_id'],
                'batch_id'        => $validated['batch_id'],
                'amount'          => $servicePrice,
                'performed_by'    => $performedBy,
                'transaction_id'  => $transaction->id,
                'submission_date' => now(),
                'status'          => 'pending',
                'service_type'    => $serviceName,
            ]);

            // 7. Deduct Wallet Balance
            $wallet->decrement('balance', $servicePrice);

            // 8. Store initial submission data for reference
            Log::channel('crm')->info('New CRM Request Initiated', [
                'user_id'   => $user->id,
                'reference' => $reference,
                'type'      => $serviceField->field_code
            ]);

            // 8. Call API
            $apiToken = env('AREWA_API_TOKEN');
            $baseUrl = env('AREWA_BASE_URL', 'https://arewa-api.com/api');

            $response = Http::withToken($apiToken)
                ->withoutVerifying()
                ->timeout(60)
                ->post("{$baseUrl}/bvn/crm", [
                    'field_code' => $serviceField->field_code,
                    'batch_id'  => $validated['batch_id'],
                    'ticket_id' => $validated['ticket_id'],
                    'reference' => $reference,
                ]);

            $decodedData = $response->json();

            if (!$response->successful()) {
                Log::channel('crm')->error('CRM API Error', [
                    'reference' => $reference,
                    'status'    => $response->status(),
                    'body'      => $response->body()
                ]);
                throw new \Exception($decodedData['message'] ?? 'API Submission failed');
            }

            Log::channel('crm')->info('CRM API Success', [
                'reference' => $reference,
                'response'  => $decodedData
            ]);

            // 10. Update records with API Result and Commit
            $apiReference = $decodedData['data']['reference'] ?? $reference;
            
            // If API returned a different reference, update the records
            if ($apiReference !== $reference) {
                $transaction->update(['referenceId' => $apiReference]);
                $agentService = AgentService::where('reference', $reference)->first();
                if ($agentService) {
                    $agentService->update(['reference' => $apiReference]);
                }
            }

            // Optionally update with API response info if needed, 
            // but Transaction model doesn't have metadata column.
            // We can store it in AgentService instead if needed.
            if (isset($decodedData['message'])) {
                $agentService = AgentService::where('reference', $apiReference)->first();
                if ($agentService) {
                    $agentService->update(['comment' => $decodedData['message']]);
                }
            }

            DB::commit();

            return redirect()->route('user.bvn-crm')->with([
                'status'  => 'success',
                'message' => "CRM request submitted successfully. Ref: {$apiReference}. Charged: ₦" . number_format($servicePrice, 2),
            ]);

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error('BVN CRM Store Exception', [
                'user_id' => $user->id,
                'error'   => $e->getMessage()
            ]);

            return back()->with([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ])->withInput();
        }
    }

    /**
     * Check the status of a CRM submission.
     */
    public function checkStatus($id, CrmService $crmService)
    {
        $submission = AgentService::findOrFail($id);

        // Prevent checking status for completed/failed requests multiple times
        if (in_array($submission->status, ['successful', 'failed'])) {
            return back()->with([
                'status'  => 'info',
                'message' => "This request is already " . ucfirst($submission->status) . ".",
            ]);
        }

        $result = $crmService->checkStatus($submission);

        return back()->with([
            'status'  => $result['status'],
            'message' => $result['message'],
        ]);
    }

    /**
     * Normalize API status to internal status.
     */
    private function normalizeStatus($status): string
    {
        $s = strtolower(trim((string) $status));
        
        return match ($s) {
            'successful', 'success', 'resolved', 'approved', 'completed' => 'successful',
            'processing', 'in_progress', 'in-progress', 'submitted', 'new' => 'processing',
            'failed', 'rejected', 'error', 'declined', 'invalid', 'no record' => 'failed',
            'query', 'queried' => 'query',
            default => 'pending',
        };
    }
}

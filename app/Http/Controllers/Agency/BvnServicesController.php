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
use App\Http\Controllers\Traits\Refundable;

class BvnServicesController extends Controller
{
    use Refundable;
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
        $serviceField = ServiceField::with(['service', 'prices'])->findOrFail($validated['field_code']);
        $serviceName = $serviceField->service->name;
        $fieldName = $serviceField->field_name;

        $servicePrice = $serviceField->prices
            ->where('user_type', $user->role)
            ->first()?->price ?? $serviceField->base_price;

        if ($servicePrice === null) {
            return back()->with([
                'status'  => 'error',
                'message' => 'Service price not configured for your account type.'
            ])->withInput();
        }

        DB::beginTransaction();

        try {
            // 3. Lock Wallet and Check Wallet Status
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            // 4. Check Balance
            if ($wallet->balance < $servicePrice) {
                throw new \Exception('Insufficient balance. You need NGN ' . number_format($servicePrice - $wallet->balance, 2) . ' more.');
            }

            $reference = 'CRM' . date('is') . strtoupper(substr(uniqid(mt_rand(), true), -5));
            $performedBy = trim($user->first_name . ' ' . ($user->last_name ?? $user->surname));

            // 5. Create Transaction Record
            $transaction = Transaction::create([
                'transaction_ref' => $reference,
                'user_id'         => $user->id,
                'amount'          => $servicePrice,
                'performed_by'    => $performedBy,
                'description'     => "{$serviceName} Request for {$fieldName}",
                'type'            => 'debit',
                'status'          => 'approved',
                'metadata'        => [
                    'service_key'   => $serviceKey,
                    'field_details' => [
                        'id'   => $serviceField->id,
                        'name' => $fieldName,
                        'code' => $serviceField->field_code,
                    ],
                    'request_data'  => $validated,
                ],
            ]);

            // 6. Create AgentService Record
            $agentService = AgentService::create([
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

            DB::commit();

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

        // 8. Call API
        try {
            $apiKey = env('AREWA_API_TOKEN');
            $apiBaseUrl = env('AREWA_BASE_URL');
            $apiUrl = rtrim($apiBaseUrl, '/') . '/bvn/crm';

            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->post($apiUrl, [
                    'field_code' => $serviceField->field_code,
                    'ticket_id'  => $validated['ticket_id'],
                    'batch_id'   => $validated['batch_id'],
                ]);

            $decodedData = $response->json();

            // 9. Handle API Failure
            if (!$response->successful() || (isset($decodedData['success']) && $decodedData['success'] === false) || (isset($decodedData['status']) && $decodedData['status'] === 'error')) {
                Log::error('BVN CRM API Failure', [
                    'reference' => $reference,
                    'response'  => $decodedData,
                    'status'    => $response->status()
                ]);
                throw new \Exception('Transaction failed: ' . ($decodedData['message'] ?? 'Could not complete API request.'));
            }

            // 10. Update records with API Result
            $apiReference = $decodedData['data']['reference'] ?? $reference;
            
            // If API returned a different reference, update the records
            if ($apiReference !== $reference) {
                $transaction->update(['transaction_ref' => $apiReference]);
                $agentService->update(['reference' => $apiReference]);
            }

            $transactionMetadata = $transaction->metadata;
            $transactionMetadata['api_response'] = $decodedData;
            $transaction->update(['metadata' => $transactionMetadata]);

            return redirect()->route('user.bvn-crm')->with([
                'status'  => 'success',
                'message' => "CRM request submitted successfully. Ref: {$apiReference}. Charged: ₦" . number_format($servicePrice, 2),
            ]);

        } catch (\Exception $e) {
            Log::error('BVN CRM Store Exception', [
                'user_id' => $user->id,
                'error'   => $e->getMessage()
            ]);

            // Execute Refund
            DB::beginTransaction();
            try {
                $lockedService = AgentService::where('id', $agentService->id)->lockForUpdate()->first();
                if ($lockedService && !in_array($lockedService->status, ['failed', 'rejected'])) {
                    $refundRef = 'REF_' . $lockedService->reference;
                    $refundExists = Transaction::where('transaction_ref', $refundRef)->where('type', 'credit')->exists();
                    if (!$refundExists) {
                        $lockedWallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
                        if ($lockedWallet) {
                            $lockedWallet->increment('balance', $servicePrice);
                            Transaction::create([
                                'transaction_ref' => $refundRef,
                                'user_id'         => $user->id,
                                'amount'          => $servicePrice,
                                'performed_by'    => 'System Auto-Refund',
                                'description'     => "Refund for failed {$serviceName} Request: {$lockedService->reference}",
                                'type'            => 'credit',
                                'status'          => 'approved',
                            ]);
                        }
                    }
                    $lockedService->update([
                        'status' => 'failed',
                        'comment' => 'API Submission failed: ' . $e->getMessage() . ' (Refunded)'
                    ]);
                }
                DB::commit();
            } catch (\Exception $refundEx) {
                DB::rollBack();
                Log::error('BVN CRM Auto-Refund Error', ['error' => $refundEx->getMessage()]);
            }

            return redirect()->route('user.bvn-crm')->with([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ])->withInput();
        }
    }

    /**
     * Check the status of a CRM submission.
     */
    public function checkStatus($id)
    {
        $submission = AgentService::findOrFail($id);

        try {
            $apiKey = env('AREWA_API_TOKEN');
            $apiBaseUrl = 'https://api.arewasmart.com.ng/api/v1'; // Correct API base URL
            $apiUrl = rtrim($apiBaseUrl, '/') . '/bvn/crm';

            // Polling is possible using reference, batch_id, or ticket_id.
            // We'll prioritize reference as it's the most specific.
            $params = [];
            if ($submission->reference) {
                $params['reference'] = $submission->reference;
            } elseif ($submission->batch_id) {
                $params['batch_id'] = $submission->batch_id;
            } elseif ($submission->ticket_id) {
                $params['ticket_id'] = $submission->ticket_id;
            }

            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->get($apiUrl, $params);

            $apiResponse = $response->json();

            // The API documentation says we poll status using GET.
            // We update the database with the response message comment and status.
            if ($response->successful()) {
                $data = $apiResponse['data'] ?? [];
                
                $updateData = [];

                // Update status if present in data
                if (isset($data['status'])) {
                    $updateData['status'] = $this->normalizeStatus($data['status']);
                }
                
                // Update comment/message from response.
                // Priority: comment > message > reason
                if (isset($data['comment'])) {
                    $updateData['comment'] = $data['comment'];
                } elseif (isset($apiResponse['message'])) {
                    $updateData['comment'] = $apiResponse['message'];
                } elseif (isset($data['reason'])) {
                    $updateData['comment'] = $data['reason'];
                }

                // Map file url if provided
                if (isset($data['file_url'])) {
                    $updateData['file_url'] = $data['file_url'];
                }

                if (!empty($updateData)) {
                    $this->updateStatusAndRefund($submission, $updateData);
                }

                return back()->with([
                    'status' => 'success',
                    'message' => 'Status updated successfully. Current status: ' . ucfirst($submission->status)
                ]);
            }

            return back()->with([
                'status' => 'error',
                'message' => 'Unable to fetch status: ' . ($apiResponse['message'] ?? 'Unknown error.')
            ]);

        } catch (\Exception $e) {
            Log::error('BVN CRM Status Check Error', ['error' => $e->getMessage()]);
            return back()->with([
                'status' => 'error',
                'message' => 'Connection Error: Unable to reach service provider.'
            ]);
        }
    }

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

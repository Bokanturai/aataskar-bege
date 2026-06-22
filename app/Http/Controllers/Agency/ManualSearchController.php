<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\ServiceField;
use App\Models\AgentService;
use App\Models\Transaction;
use App\Models\Services1 as Service;
use App\Models\Wallet;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class ManualSearchController extends Controller
{
    /**
     * Display phone number submission page with submission history.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Ensure wallet exists
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0.00, 'status' => 'active']
        );

        // Fetch all valid submissions (number not null/empty)
          $query = AgentService::where('user_id', $user->id)
        ->where('service_type', 'bvn_search');

        // Apply search filter
        if ($request->filled('search')) {
            $query->where('number', 'like', '%' . $request->search . '%');
        }

        // Apply status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Custom ordering: pending → processing → others
        $query->orderByRaw("
            CASE 
                WHEN status = 'pending' THEN 1
                WHEN status = 'processing' THEN 2
                ELSE 3
            END
        ")->orderByDesc('submission_date');

        // Paginate results
        $crmSubmissions = $query->paginate(5)->withQueryString();

        // Fetch active phone search service
        $phoneService = Service::where('name', 'BVN SEARCH')
            ->where('is_active', true)
            ->first();

        // Load active fields for this service
        $serviceFields = $phoneService
            ? ServiceField::where('service_id', $phoneService->id)
                ->where('is_active', true)
                ->get()
            : collect();

        return view('bvn.phone-search', [
            'serviceFields'  => $serviceFields,
            'crmSubmissions' => $crmSubmissions,
            'services'       => Service::where('is_active', true)->get(),
            'bvnService'     => $phoneService,
            'wallet'         => $wallet,
        ]);
    }

    /**
     * Handle phone number submission and charge user based on selected service and role.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'service_field_id' => 'required|exists:service_fields,id',
            'number' => 'required|string|size:11|regex:/^[0-9]{11}$/',
        ]);

        $serviceField = ServiceField::with('service')->findOrFail($validated['service_field_id']);
        $serviceName = $serviceField->service->name ?? 'Unknown Service';

        // Check for duplicate pending/processing requests for the same number
        $existing = AgentService::where('user_id', $user->id)
            ->where('service_name', 'BVN SEARCH')
            ->where('number', $validated['number'])
            ->whereIn('status', ['pending', 'processing'])
            ->first();

        if ($existing) {
            return back()->with([
                'status' => 'error',
                'message' => 'A request for this phone number is already being processed. Please wait for the result.',
            ])->withInput();
        }

        $servicePrice = $serviceField->getPriceForUserType($user->role);

        if ($servicePrice === null) {
            return back()->with([
                'status' => 'error',
                'message' => 'Service price not configured for your user role.',
            ])->withInput();
        }

        DB::beginTransaction();

        try {
            // Lock wallet and check balance
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();

            if (!$wallet) {
                throw new \Exception('Wallet not found. Please contact support.');
            }

            if ($wallet->balance < $servicePrice) {
                throw new \Exception('Insufficient wallet balance. You need NGN ' . number_format($servicePrice - $wallet->balance, 2) . ' more.');
            }

            $transactionRef = 'P1' . date('is') . strtoupper(Str::random(5));
            $performedBy = trim($user->first_name . ' ' . $user->last_name);

            // Create transaction record
            $transaction = Transaction::create([
                'referenceId' => $transactionRef,
                'user_id' => $user->id,
                'amount' => $servicePrice,
                'service_description' => "{$serviceName} for {$serviceField->field_name}",
                'type' => 'debit',
                'status' => 'Approved',
                'service_type'        => 'BVN Search',
                'payer_name' => $performedBy,
            ]);

            // Create submission record
            $agentService = AgentService::create([
                'reference' => $transactionRef,
                'user_id' => $user->id,
                'service_field_id' => $serviceField->id,
                'service_id' => $serviceField->service_id,
                'field_code' => $serviceField->field_code,
                'field_name' => $serviceField->field_name,
                'amount' => $servicePrice,
                'service_name' => $serviceName,
                'number' => $validated['number'],
                'transaction_id' => $transaction->id,
                'performed_by' => $performedBy,
                'submission_date' => now(),
                'status' => 'pending',
                'service_type' => 'bvn_search',
                'comment' => 'Initiated search request',
            ]);

            // Deduct from wallet
            $wallet->decrement('balance', $servicePrice);

            DB::commit();

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error('Manual Search Store Exception', [
                'user_id' => $user->id,
                'error'   => $e->getMessage()
            ]);

            return back()->with([
                'status' => 'error',
                'message' => 'Submission failed: ' . $e->getMessage(),
            ])->withInput();
        }

        // 4. API Submission to Arewa Smart (OUTSIDE the transaction)
        try {
            $apiToken = env('AREWA_API_TOKEN');
            $baseUrl = env('AREWA_BASE_URL', 'https://api.arewasmart.com.ng/api/v1');
            $apiUrl = rtrim($baseUrl, '/') . '/bvn/phone-search';

            $payload = [
                'field_code' => $serviceField->field_code,
                'phone_number' => $validated['number'],
                'reference' => $transactionRef,
            ];

            Log::channel('crm')->info('Manual Search API Initiation', ['reference' => $transactionRef, 'payload' => $payload]);

            $response = Http::withToken($apiToken)
                ->timeout(60)
                ->post($apiUrl, $payload);

            $data = $response->json();

            if (!$response->successful()) {
                Log::channel('crm')->error('Manual Search API Error', ['reference' => $transactionRef, 'response' => $data]);
                throw new \Exception('API Submission Failed: ' . ($data['message'] ?? 'Unknown Provider Error'));
            }

            Log::channel('crm')->info('Manual Search API Success', ['reference' => $transactionRef, 'response' => $data]);

            // Update submission
            $apiReference = $data['data']['reference'] ?? $transactionRef;

            if ($apiReference !== $transactionRef) {
                $transaction->update(['referenceId' => $apiReference]);
                $agentService->update(['reference' => $apiReference]);
            }

            $agentService->update([
                'status' => 'processing',
                'comment' => $data['message'] ?? 'Submitted to Arewa API',
            ]);

            return redirect()->route('user.phone.search.index')->with([
                'status' => 'success',
                'message' => 'BVN Search request submitted successfully. Ref: ' . $apiReference . '. Charged NGN ' . number_format($servicePrice, 2),
            ]);

        } catch (\Exception $e) {
            Log::channel('crm')->error('Manual Search Exception', ['reference' => $transactionRef, 'error' => $e->getMessage()]);

            // Execute Refund
            DB::beginTransaction();
            try {
                $lockedService = AgentService::where('id', $agentService->id)->lockForUpdate()->first();
                if ($lockedService && !in_array($lockedService->status, ['failed', 'rejected'])) {
                    $refundRef = 'REF_' . $lockedService->reference;
                    $refundExists = Transaction::where('referenceId', $refundRef)->where('type', 'credit')->exists();
                    if (!$refundExists) {
                        $lockedWallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
                        if ($lockedWallet) {
                            $lockedWallet->increment('balance', $servicePrice);
                            Transaction::create([
                                'referenceId' => $refundRef,
                                'user_id' => $user->id,
                                'amount' => $servicePrice,
                                'service_description' => "Refund for failed BVN Search Request: {$lockedService->reference}",
                                'type' => 'credit',
                                'status' => 'Approved',
                                'payer_name' => 'System Auto-Refund',
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
                Log::error('Manual Search Auto-Refund Error', ['error' => $refundEx->getMessage()]);
            }

            return redirect()->route('user.phone.search.index')->with([
                'status' => 'error',
                'message' => 'Submission failed: ' . $e->getMessage() . '. Wallet refunded.',
            ]);
        }
    }

    /**
     * Check status of a BVN Search request for the user.
     */
    public function checkStatus($id, \App\Services\CrmService $crmService)
    {
        try {
            $enrollment = AgentService::where('id', $id)
                ->where('user_id', Auth::id())
                ->where('service_name', 'BVN SEARCH')
                ->firstOrFail();

            // Prevent checking status for completed/failed requests multiple times
            if (in_array($enrollment->status, ['successful', 'failed', 'rejected'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'This request is already ' . ucfirst($enrollment->status),
                    'data' => $enrollment
                ]);
            }

            $result = $crmService->checkStatus($enrollment);

            if ($result['status'] === 'success') {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'data' => $enrollment->fresh()
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fetch dynamic service field price based on user role.
     */
    public function getFieldPrice(Request $request)
    {
        $request->validate([
            'field_id' => 'required|exists:service_fields,id',
        ]);

        $user = Auth::user();
        $field = ServiceField::findOrFail($request->field_id);
        $price = $field->getPriceForUserType($user->role);

        return response()->json([
            'success' => true,
            'price' => $price,
            'formatted_price' => 'NGN ' . number_format($price, 2),
            'field_name' => $field->field_name,
            'base_price' => $field->base_price,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\Verification;
use App\Models\Services1 as Service;
use App\Models\ServiceField;
use App\Models\Transaction;
use App\Models\Wallet;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TinRegistrationController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        // Ensure wallet exists
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0.00, 'status' => 'active']
        );

        $submissions = Verification::with('transaction')
            ->where('user_id', $user->id)
            ->when($request->filled('search'), fn($q) =>
                $q->where('reference', 'like', "%{$request->search}%")
                  ->orWhere('field_name', 'like', "%{$request->search}%"))
            ->orderByDesc('submission_date')
            ->paginate(10)
            ->withQueryString();

        $service = Service::where('name', 'TIN REGISTRATION')
            ->first();

        $fields = $service?->fields()->get() ?? collect();

        // Fetch prices for Download
        // 614 = Individual/Standard
        // 615 = Corporate/Premium
        $individualField = ServiceField::where('field_code', 614)->first();
        $corporateField  = ServiceField::where('field_code', 615)->first();

        $individualPrice = $individualField ? $individualField->getPriceForUserType($user->role) : 0.00;
        $corporatePrice  = $corporateField  ? $corporateField->getPriceForUserType($user->role) : 0.00;

        $downloadPrices = [
            'individual' => $individualPrice,
            'corporate' => $corporatePrice,
        ];

        return view('tin.index', [
            'fields'        => $fields,
            'service'       => $service,
            'submissions'   => $submissions,
            'wallet'        => $wallet,
            'downloadPrices'=> $downloadPrices,
        ]);
    }

    /**
     * Handle TIN validation with charging BEFORE API call
     */
    public function validateTin(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'type' => 'required|in:individual,corporate',
            'field_code' => 'required|in:800,801',
        ]);

        // Fetch service field
        $serviceField = ServiceField::where('field_code', $request->field_code)
            ->whereHas('service')
            ->with('service')
            ->first();

        if (!$serviceField) {
            return back()->with([
                'status' => 'error',
                'message' => "Service field not found."
            ]);
        }

        $service = $serviceField->service;

        // Get price based on user role
        $servicePrice = $serviceField->getPriceForUserType($user->role);

        if ($servicePrice === null) {
            return back()->with([
                'status' => 'error',
                'message' => 'Service price not configured for your user role.'
            ]);
        }

        // Validate additional inputs based on type
        if ($request->type === 'individual') {
            $request->validate([
                'nin' => 'required|digits:11',
                'first_name' => 'required|string',
                'last_name' => 'required|string',
                'date_of_birth' => 'required|date',
            ]);
        } else {
            $request->validate([
                'rc_number' => 'required',
                'org_type' => 'required',
            ]);
        }

        DB::beginTransaction();
        try {
            // Lock wallet and check balance
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            if ($wallet->balance < $servicePrice) {
                throw new \Exception('You do not have sufficient fund in your wallet. You need NGN ' .
                    number_format($servicePrice - $wallet->balance, 2) . ' more.');
            }

            // CHARGE USER BEFORE API CALL
            $transactionRef = 'TIN' . date('is') . strtoupper(Str::random(5));
            $performedBy = trim($user->first_name . ' ' . $user->last_name);
            $serviceType = $request->type === 'individual' ? 'TIN Individual' : 'TIN Corporate';

            // Create PENDING transaction
            $transaction = Transaction::create([
                'referenceId' => $transactionRef,
                'user_id' => $user->id,
                'amount' => $servicePrice,
                'service_description' => "TIN Validation - {$serviceField->field_name}",
                'type' => 'approved',
                'status' => 'Pending',
                'service_type' => $serviceType,
                'performed_by' => $performedBy,
                'metadata' => [
                    'service' => 'tin_validation',
                    'service_name' => $service->name,
                    'service_field' => $serviceField->field_name,
                    'field_code' => $serviceField->field_code,
                    'type' => $request->type,
                    'input_data' => $request->except(['_token']),
                ],
            ]);

            // Create PENDING agent service record
            $agentService = Verification::create([
                'reference' => $transactionRef,
                'user_id' => $user->id,
                'service_field_id' => $serviceField->id,
                'service_id' => $service->id,
                'field_code' => $serviceField->field_code,
                'field_name' => $serviceField->field_name,
                'amount' => $servicePrice,
                'service_name' => $service->name,
                'description' => "TIN Validation - {$request->type}",
                'modification_data' => $request->all(),
                'transaction_id' => $transaction->id,
                'performed_by' => $performedBy,
                'submission_date' => now(),
                'firstname' => $request->first_name,
                'surname' => $request->last_name,
                'birthdate' => $request->date_of_birth,
                'nin' => $request->nin,
                'idno' => $request->nin
            ]);

            // Deduct from wallet immediately
            $wallet->decrement('balance', $servicePrice);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with([
                'status' => 'error',
                'message' => $e->getMessage()
            ])->withInput();
        }

        // Call API outside transaction
        try {
            // Prepare API payload
            $token = config('services.arewa.token');
            $url = config('services.arewa.base_url') . '/tin/verify';
            $payload = [];

            if ($request->type === 'individual') {
                $payload = [
                    'nin' => trim($request->nin),
                    'firstName' => trim($request->first_name),
                    'lastName' => trim($request->last_name),
                    'dateOfBirth' => date('Y-m-d', strtotime($request->date_of_birth)), // YYYY-MM-DD
                ];
            } else {
                $payload = [
                    'rc' => trim($request->rc_number),
                    'type' => (string) $request->org_type, // CAC Type ID
                ];
            }

            Log::info('TIN Validation API Request', [
                'user_id' => $user->id,
                'reference' => $transactionRef,
                'url' => $url,
                'payload' => $payload,
            ]);

            // Make API call
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ])->timeout(35)->post($url, $payload);

            $data = $response->json();

            Log::info('TIN Validation API Response', [
                'user_id' => $user->id,
                'reference' => $transactionRef,
                'response' => $data,
            ]);

            if ($response->successful() && isset($data['status']) && $data['status'] === 'success') {
                $responseData = $data['data'] ?? [];

                DB::beginTransaction();
                try {
                    // Update transaction and agent service to SUCCESS
                    $transaction->update([
                        'status' => 'Approved',
                        'metadata' => array_merge($transaction->metadata ?? [], [
                            'api_response' => $responseData,
                            'api_status' => 'success',
                        ])
                    ]);

                    $agentService->update([
                        'status' => 'successful',
                        'modification_data' => array_merge($agentService->modification_data ?? [], [
                            'api_response' => $responseData,
                            'tin' => $responseData['tin'] ?? $responseData['taxIdentificationNumber'] ?? null,
                        ])
                    ]);

                    DB::commit();

                    // Store verification data in session for PDF download
                    session()->flash('verification', [
                        'success' => true,
                        'data' => $responseData,
                        'type' => $request->type,
                        'field_code' => $request->field_code,
                        'input_data' => $request->all(),
                        'transaction_ref' => $transactionRef,
                        'tin_number' => $responseData['tin'] ?? $responseData['taxIdentificationNumber'] ?? null,
                    ]);

                    return redirect()->back()->with([
                        'status' => 'success',
                        'message' => 'TIN validation successful! You can now download your slip.',
                    ]);

                } catch (\Exception $dbEx) {
                    DB::rollBack();
                    throw $dbEx;
                }

            } else {
                $errorMessage = $response->json('message') ?? 'API validation failed';
                throw new \Exception($errorMessage);
            }

        } catch (\Exception $e) {
            // Auto refund
            DB::beginTransaction();
            try {
                $refundRef = 'REF_' . $transactionRef;
                $refundExists = Transaction::where('referenceId', $refundRef)->where('type', 'credit')->exists();
                if (!$refundExists) {
                    $lockedWallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
                    if ($lockedWallet) {
                        $lockedWallet->increment('balance', $servicePrice);
                        Transaction::create([
                            'referenceId' => $refundRef,
                            'user_id' => $user->id,
                            'amount' => $servicePrice,
                            'service_type'    => 'Refund',
                            'service_description' => "Refund for failed TIN verification: {$transactionRef}",
                            'type' => 'credit',
                            'status' => 'Approved',
                            'performed_by' => 'System Auto-Refund',
                        ]);
                    }
                }

                $transaction->update([
                    'status' => 'Rejected',
                    'metadata' => array_merge($transaction->metadata ?? [], [
                        'api_status' => 'failed',
                        'error' => $e->getMessage(),
                    ])
                ]);

                $agentService->update([
                    'status' => 'failed',
                    'modification_data' => array_merge($agentService->modification_data ?? [], [
                        'api_error' => $e->getMessage(),
                    ])
                ]);

                DB::commit();
            } catch (\Exception $refundEx) {
                DB::rollBack();
                Log::error('TIN Validation Auto-Refund Error', ['error' => $refundEx->getMessage()]);
            }

            return back()->with([
                'status' => 'error',
                'message' => 'Validation failed: ' . $e->getMessage() . '. Your wallet has been refunded.',
            ])->withInput();
        }
    }

    /**
     * Download TIN slip (Charged Service)
     */
    public function downloadSlip(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'transaction_ref' => 'required|string',
            'type'            => 'required|in:individual,corporate'
        ]);

        $reference = $request->transaction_ref;
        $type = $request->type;
        
        // Determine Service Field Code based on Type
        // Individual = 614, Corporate = 615
        $fieldCode = ($type === 'individual') ? 614 : 615;

        // 1. Retrieve the validated service record
        $agentService = Verification::where('reference', $reference)
            ->where('user_id', $user->id)
            ->where('status', 'successful')
            ->first();

        if (!$agentService) {
            return back()->with([
                'status' => 'error',
                'message' => 'Validation record not found or invalid.'
            ]);
        }

        // 2. Fetch Service Price for Download
        $serviceField = ServiceField::where('field_code', $fieldCode)
            ->whereHas('service')
            ->with('service')
            ->first();

        if (!$serviceField) {
            return back()->with([
                'status' => 'error',
                'message' => "Download service (Code: $fieldCode) not available."
            ]);
        }

        $price = $serviceField->getPriceForUserType($user->role);

        if ($price === null) {
            return back()->with([
                'status' => 'error',
                'message' => 'Download price not configured for your role.'
            ]);
        }

        DB::beginTransaction();
        try {
            // Lock Wallet
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            if ($wallet->balance < $price) {
                throw new \Exception('Insufficient funds. You need NGN ' . number_format($price - $wallet->balance, 2) . ' more to download.');
            }

            $transactionRef = 'TINDL' . date('is') . strtoupper(Str::random(5));
            $performedBy = trim($user->first_name . ' ' . $user->last_name);

            // Create Debit Transaction
            Transaction::create([
                'referenceId' => $transactionRef,
                'user_id' => $user->id,
                'amount' => $price,
                'service_description' => "TIN Download - {$type}",
                'type' => 'debit',
                'status' => 'Approved', // Instant charge
                'service_type' => 'TIN Download',
                'performed_by' => $performedBy,
                'metadata' => [
                    'related_ref' => $reference,
                    'type' => $type,
                    'field_code' => $fieldCode
                ],
            ]);

            // Deduct Wallet
            $wallet->decrement('balance', $price);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('TIN Download Logic Error', ['error' => $e->getMessage()]);

            return back()->with([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }

        // 4. Generate PDF outside the transaction
        try {
            if ($type === 'individual') {
                // Use NIN_PDF_Repository for Individual Slip
                $ninRepository = new \App\Repositories\NIN_PDF_Repository();
                return $ninRepository->individualSlip($agentService, $reference);

            } else {
                // Corporate Certificate
                $data = $agentService->modification_data;
                $apiResponse = $data['api_response'] ?? [];
                
                $enrollmentInfo = new \stdClass();
                $enrollmentInfo->last_name = $apiResponse['company_name'] ?? $apiResponse['business_name'] ?? 'N/A';
                $enrollmentInfo->first_name = '';
                $enrollmentInfo->middle_name = '';
                $enrollmentInfo->nin = $apiResponse['tax_id'] ?? $apiResponse['tin'] ?? $apiResponse['jtb_tin'] ?? 'N/A';
                
                $address = $apiResponse['office_address'] ?? $apiResponse['address'] ?? 'N/A';
                $enrollmentInfo->street_name = $address;
                $enrollmentInfo->lga = $apiResponse['lga'] ?? '';
                $enrollmentInfo->state = $apiResponse['state'] ?? '';

                $qrContent = json_encode([
                    'Type' => 'Corporate TIN',
                    'Name' => $enrollmentInfo->last_name,
                    'TIN' => $enrollmentInfo->nin,
                    'RC' => $apiResponse['rc'] ?? $apiResponse['rc_number'] ?? 'N/A',
                    'Date' => now()->format('Y-m-d')
                ]);
                
                $qrcode = QrCode::format('svg')->size(200)->generate($qrContent);

                $pdf = Pdf::loadView('tin.pdf.corporate', [
                    'enrollmentInfo' => $enrollmentInfo,
                    'qrcode' => $qrcode
                ])->setPaper('a4', 'landscape');

                return $pdf->download('TIN_Certificate_' . $reference . '.pdf');
            }
        } catch (\Exception $e) {
            Log::error('TIN Download PDF Generation Error', ['error' => $e->getMessage()]);
            return back()->with([
                'status' => 'error',
                'message' => 'Failed to generate PDF document: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * AJAX endpoint to get field price
     */
    public function getFieldPrice(Request $request)
    {
        $request->validate([
            'field_code' => 'required|exists:service_fields,field_code',
        ]);

        $user = Auth::user();
        $field = ServiceField::where('field_code', $request->field_code)
            ->where('is_active', true)
            ->firstOrFail();

        $price = $field->getPriceForUserType($user->role);

        if ($price === null) {
            return response()->json([
                'success' => false,
                'message' => 'Price not configured for your role'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'price' => $price,
            'formatted_price' => 'NGN ' . number_format($price, 2),
            'field_name' => $field->field_name,
            'field_code' => $field->field_code,
        ]);
    }

    /**
     * Check wallet balance before validation
     */
    public function checkBalance(Request $request)
    {
        $request->validate([
            'field_code' => 'required|exists:service_fields,field_code',
        ]);

        $user = Auth::user();
        $field = ServiceField::where('field_code', $request->field_code)
            ->where('is_active', true)
            ->firstOrFail();

        $price = $field->getPriceForUserType($user->role);

        if ($price === null) {
            return response()->json([
                'success' => false,
                'message' => 'Price not configured'
            ], 400);
        }

        $wallet = Wallet::where('user_id', $user->id)->firstOrFail();

        $hasSufficientFunds = $wallet->balance >= $price;
        $shortfall = $price - $wallet->balance;

        return response()->json([
            'success' => true,
            'has_sufficient_funds' => $hasSufficientFunds,
            'required_amount' => $price,
            'current_balance' => $wallet->balance,
            'shortfall' => max(0, $shortfall),
            'message' => $hasSufficientFunds
                ? 'Sufficient funds available'
                : 'Insufficient funds. You need NGN ' . number_format($shortfall, 2) . ' more.',
        ]);
    }

    /**
     * Clean API response
     */
    private function cleanApiResponse($response): string
    {
        if (is_array($response)) {
            $jsonString = json_encode($response, JSON_PRETTY_PRINT);
        } else {
            $jsonString = (string) $response;
        }

        $cleanResponse = str_replace(['{', '}', '"', "'"], '', $jsonString);
        $cleanResponse = preg_replace('/\s+/', ' ', $cleanResponse);

        return trim($cleanResponse);
    }
}

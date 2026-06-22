<?php

namespace App\Http\Controllers\Verification;

use App\Http\Controllers\Controller;

use App\Helpers\ServiceManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use App\Models\Verification;
use App\Models\Transaction;
use App\Models\Service;
use App\Models\Services1;
use App\Models\ServiceField;
use App\Models\Wallet;
use App\Repositories\NIN_PDF_Repository;
use Carbon\Carbon;
use Illuminate\Support\Str;

class NINDemoVerificationController extends Controller
{
    /**
     * Show Demographic verification page
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Get Verification Service from DB
        $service = Services1::where('name', 'Verification')->first();
        
        // Get Prices
        $demoPrice = 0;
        $freeSlipPrice = 0;
        $regularSlipPrice = 0;
        $standardSlipPrice = 0;
        $premiumSlipPrice = 0;

        if ($service) {
            $demoField = $service->fields()->where('field_code', 'V100')->first();
            $freeField = $service->fields()->where('field_code', 'V101')->first();
            $regularField = $service->fields()->where('field_code', 'V102')->first();
            $standardField = $service->fields()->where('field_code', '611')->first();
            $premiumField = $service->fields()->where('field_code', '612')->first();

            $vninField = $service->fields()->where('field_code', '616')->first();

            $demoPrice = $demoField ? $demoField->getPriceForUserType($user->role) : 0;
            $freeSlipPrice = $freeField ? $freeField->getPriceForUserType($user->role) : 0;
            $regularSlipPrice = $regularField ? $regularField->getPriceForUserType($user->role) : 0;
            $standardSlipPrice = $standardField ? $standardField->getPriceForUserType($user->role) : 0;
            $premiumSlipPrice = $premiumField ? $premiumField->getPriceForUserType($user->role) : 0;
            $vninSlipPrice = $vninField ? $vninField->getPriceForUserType($user->role) : 0;
        }

        $wallet = Wallet::where('user_id', $user->id)->first();

        return view('verification.nin-demo-verification', [
            'wallet' => $wallet,
            'demoPrice' => $demoPrice,
            'basicSlipPrice' => $freeSlipPrice,
            'regularSlipPrice' => $regularSlipPrice,
            'standardSlipPrice' => $standardSlipPrice,
            'premiumSlipPrice' => $premiumSlipPrice,
            'vninSlipPrice' => $vninSlipPrice ?? 0,
        ]);
    }

    /**
     * Store new Demographic verification request
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'firstName' => 'required|string',
            'lastName' => 'required|string',
            'gender' => 'required|string|in:M,F',
            'dateOfBirth' => 'required|string', 
        ]);

        // 1. Get Verification Service from DB
        $service = Services1::where('name', 'Verification')->first();

        if (!$service) {
            return back()->with([
                'status' => 'error',
                'message' => 'Verification service not available.'
            ]);
        }

        // 2. Get ServiceField (V100)
        $serviceField = $service->fields()
            ->where('field_code', 'V100')
            ->where('is_active', true)
            ->first();

        if (!$serviceField) {
            return back()->with([
                'status' => 'error',
                'message' => 'Demographic verification service is not available.'
            ]);
        }

        // 3. Determine service price based on user role
        $servicePrice = $serviceField->getPriceForUserType($user->role);

        DB::beginTransaction();
        try {
            // Lock wallet and check balance
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            if ($wallet->balance < $servicePrice) {
                throw new \Exception('Insufficient wallet balance. You need NGN ' . number_format($servicePrice - $wallet->balance, 2) . ' more.');
            }

            $transactionRef = 'D1' . (time() % 1000000000) . '-' . mt_rand(100, 999);
            $performedBy = $user->first_name . ' ' . $user->last_name;

            // Create initial debit transaction
            $transaction = Transaction::create([
                'referenceId' => $transactionRef,
                'user_id' => $user->id,
                'amount' => $servicePrice,
                'service_type'    => 'NIN Demographic Verification',
                'service_description' => "NIN Demographic Verification - {$serviceField->field_name}",
                'type' => 'debit',
                'status' => "Approved",
            ]);

            // Deduct wallet balance
            $wallet->decrement('balance', $servicePrice);

            DB::commit();

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return back()->with([
                'status' => 'error',
                'message' => $e->getMessage()
            ])->withInput();
        }

        // Call API outside transaction
        try {
            $apiKey = env('AREWA_API_TOKEN');
            $baseUrl = env('AREWA_BASE_URL');
            $url = rtrim($baseUrl, '/') . '/nin/demo';

            $payload = [
                'firstName' => $request->firstName,
                'lastName' => $request->lastName,
                'gender' => $request->gender,
                'dateOfBirth' => $request->dateOfBirth,
                'ref' => 'REF-' . Str::random(10),
            ];

            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(30)
                ->post($url, $payload);

            $data = $response->json();

            // Check for successful response
            if ($response->successful() && isset($data['status']) && $data['status'] === true) {
                if (isset($data['api_response']['status']) && $data['api_response']['status'] === true) {
                    
                    DB::beginTransaction();
                    try {
                        // Extract data from API response
                        $dataArray = $data['api_response']['data']['data'] ?? [];
                        
                        $ninData = [];
                        if (is_array($dataArray) && !empty($dataArray)) {
                            $ninData = isset($dataArray[0]) ? $dataArray[0] : $dataArray;
                        }
                        
                        if (empty($ninData)) {
                            \Log::warning('NIN Demo Verification - No data in response', [
                                'user_id' => $user->id,
                                'response' => $data
                            ]);
                        }
                        
                        // Check for masked/suspended NIN data (indicated by ****)
                        $isSuspended = false;
                        $suspendedFields = [];
                        
                        $criticalFields = ['firstname', 'surname', 'nin', 'telephoneno'];
                        foreach ($criticalFields as $field) {
                            if (isset($ninData[$field]) && str_contains($ninData[$field], '****')) {
                                $isSuspended = true;
                                $suspendedFields[] = $field;
                            }
                        }
                        
                        if ($isSuspended) {
                            \Log::warning('NIN Demo Verification - Suspended NIN Detected', [
                                'user_id' => $user->id,
                                'suspended_fields' => $suspendedFields,
                                'nin_data' => $ninData
                            ]);
                        }

                        Verification::create([
                            'user_id' => $user->id,
                            'service_field_id' => $serviceField->id,
                            'service_id' => $service->id,
                            'transaction_id' => $transaction->id,
                            'reference' => $transactionRef,
                            'number_nin' => $ninData['nin'] ?? null,
                            'idno' => $ninData['nin'] ?? null,
                            'firstname' => $ninData['firstname'] ?? null,
                            'middlename' => $ninData['middlename'] ?? null,
                            'surname' => $ninData['surname'] ?? null,
                            'birthdate' =>  $ninData['birthdate'] ?? null,
                            'gender' => $ninData['gender'] ?? null,
                            'telephoneno' => $ninData['telephoneno'] ?? null,
                            'photo_path' => $ninData['photo'] ?? null,
                            'signature_path' => $ninData['signature'] ?? null,
                            'residence_state' => $ninData['residence_state'] ?? null,
                            'residence_lga' => $ninData['residence_lga'] ?? null,
                            'residence_town' => $ninData['residence_town'] ?? null,
                            'residence_address' => $ninData['residence_AdressLine1'] ?? null,
                            'self_origin_state' => $ninData['self_origin_state'] ?? null,
                            'trackingId' => $ninData['trackingId'] ?? null,
                            'performed_by'    => $performedBy,
                            'submission_date' => Carbon::now(),
                            'status' => 'pending',
                        ]);

                        DB::commit();

                        // Flash normalized verification data for Blade
                        session()->flash('verification', [
                            'data' => [
                                'nin' => $ninData['nin'] ?? 'N/A',
                                'firstName' => $ninData['firstname'] ?? 'N/A',
                                'surname' => $ninData['surname'] ?? 'N/A',
                                'middleName' => $ninData['middlename'] ?? 'N/A',
                                'birthDate' => $ninData['birthdate'] ?? 'N/A',
                                'gender' => $ninData['gender'] ?? 'N/A',
                                'telephoneNo' => $ninData['telephoneno'] ?? 'N/A',
                                'photo' => $ninData['photo'] ?? null,
                            ]
                        ]);

                        // Prepare success message with warning if suspended
                        $message = "NIN Demographic Verification successful. Reference: {$transactionRef}. Charged: NGN " . number_format($servicePrice, 2);
                        
                        if ($isSuspended) {
                            $message .= " | WARNING: This NIN appears to be suspended or restricted. The verification service returned masked data (****). Please contact NIMC for assistance.";
                        }

                        return redirect()->route('user.nin.demo.index')->with([
                            'status' => $isSuspended ? 'warning' : 'success',
                            'message' => $message,
                        ]);

                    } catch (\Exception $dbEx) {
                        DB::rollBack();
                        throw $dbEx;
                    }
                }
            }

            // Handle different error scenarios
            $errorMessage = $data['message'] ?? 'Verification failed. Please check your details and try again.';
            throw new \Exception($errorMessage);

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
                            'service_description' => "Refund for failed NIN Demographic Verification: {$transactionRef}",
                            'type' => 'credit',
                            'status' => 'Approved',
                            'performed_by' => 'System Auto-Refund',
                        ]);
                    }
                }
                DB::commit();
            } catch (\Exception $refundEx) {
                DB::rollBack();
                Log::error('NIN Demo Verification Auto-Refund Error', ['error' => $refundEx->getMessage()]);
            }

            return back()->with([
                'status' => 'error',
                'message' => 'Verification failed: ' . $e->getMessage() . '. Wallet refunded.'
            ])->withInput();
        }
    }

    /**
     * Process successful transaction (Charge + Verification Record)
     */
    private function processSuccessTransaction($wallet, $servicePrice, $user, $serviceField, $service, $apiResponse)
    {
        // Deprecated: logic moved to store()
        return redirect()->route('user.nin.demo.index');
    }

    /**
     * Charge for Slip Download
     */
    private function chargeForSlip($user, $fieldCode)
    {
         $service = Services1::where('name', 'Verification')->first();

        if (!$service) {
            throw new \Exception('Verification service not available.');
        }

        $serviceField = $service->fields()
            ->where('field_code', $fieldCode)
            ->where('is_active', true)
            ->first();

        if (!$serviceField) {
             throw new \Exception('Slip service not available.');
        }

        $servicePrice = $serviceField->getPriceForUserType($user->role);
        $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();

        if ($wallet->balance < $servicePrice) {
             throw new \Exception('Insufficient wallet balance.');
        }
        
        $transactionRef = 'Slip-' . (time() % 1000000000) . '-' . mt_rand(100, 999);

        Transaction::create([
             'referenceId' => $transactionRef,
             'user_id' => $user->id,
             'amount' => $servicePrice,
             'service_type' => 'Slip Download',
             'service_description' => "Slip Download: {$serviceField->field_name}",
             'type' => 'debit',
             'status' => 'Approved',
        ]);

        $wallet->decrement('balance', $servicePrice);
        
        return true;
    }

    public function freeSlip($nin_no)
    {
        return $this->basicSlip($nin_no);
    }

    public function basicSlip($nin_no)
    {
        try {
            $this->chargeForSlip(Auth::user(), 'V101');
            $repObj = new NIN_PDF_Repository();
            return $repObj->basicPDF($nin_no);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function regularSlip($nin_no)
    {
        try {
            $this->chargeForSlip(Auth::user(), 'V102');
            $repObj = new NIN_PDF_Repository();
            return $repObj->regularPDF($nin_no);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function standardSlip($nin_no)
    {
        try {
            $this->chargeForSlip(Auth::user(), '611');
            $repObj = new NIN_PDF_Repository();
            return $repObj->standardPDF($nin_no);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function premiumSlip($nin_no)
    {
        try {
            $this->chargeForSlip(Auth::user(), '612');
            $repObj = new NIN_PDF_Repository();
            return $repObj->premiumPDF($nin_no);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function vninSlip($nin_no)
    {
        try {
            $this->chargeForSlip(Auth::user(), '616');
            $repObj = new NIN_PDF_Repository();
            return $repObj->vninPDF($nin_no);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}

<?php

namespace App\Http\Controllers\Verification;

use App\Http\Controllers\Controller;

use App\Helpers\ServiceManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Verification;
use App\Models\Transaction;
use App\Models\Service;
use App\Models\Services1;
use App\Models\ServiceField;
use App\Models\Wallet;
use App\Repositories\NIN_PDF_Repository;
use Carbon\Carbon;

class NINverificationController extends Controller
{
    /**
     * Show NIN verification page
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Get Verification Service from DB
        $service = Services1::where('name', 'Verification')->first();
        
        // Get Prices
        $verificationPrice = 0;
        $basicSlipPrice = 0;
        $regularSlipPrice = 0;
        $standardSlipPrice = 0;
        $premiumSlipPrice = 0;
        $vninSlipPrice = 0;

        if ($service) {
            $verificationField = $service->fields()->where('field_code', '610')->first();
            $basicSlipField = $service->fields()->where('field_code', 'V101')->first();
            $regularSlipField = $service->fields()->where('field_code', 'V102')->first();
            $standardSlipField = $service->fields()->where('field_code', '611')->first();
            $premiumSlipField = $service->fields()->where('field_code', '612')->first();
            $vninSlipField = $service->fields()->where('field_code', '616')->first();

            $verificationPrice = $verificationField ? $verificationField->getPriceForUserType($user->role) : 0;
            $basicSlipPrice = $basicSlipField ? $basicSlipField->getPriceForUserType($user->role) : 0;
            $regularSlipPrice = $regularSlipField ? $regularSlipField->getPriceForUserType($user->role) : 0;
            $standardSlipPrice = $standardSlipField ? $standardSlipField->getPriceForUserType($user->role) : 0;
            $premiumSlipPrice = $premiumSlipField ? $premiumSlipField->getPriceForUserType($user->role) : 0;
            $vninSlipPrice = $vninSlipField ? $vninSlipField->getPriceForUserType($user->role) : 0;
        }

        $wallet = Wallet::where('user_id', $user->id)->first();

        return view('verification.nin-verification', [
            'wallet' => $wallet,
            'verificationPrice' => $verificationPrice,
            'basicSlipPrice' => $basicSlipPrice,
            'regularSlipPrice' => $regularSlipPrice,
            'standardSlipPrice' => $standardSlipPrice,
            'premiumSlipPrice' => $premiumSlipPrice,
            'vninSlipPrice' => $vninSlipPrice,
        ]);
    }

    /**
     * Store new NIN verification request
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'number_nin' => 'required|string|size:11|regex:/^[0-9]{11}$/',
        ]);

        // Check for duplicate in last 10 minutes (to prevent double charging)
        $recentVerification = Verification::where('user_id', $user->id)
            ->where('number_nin', $request->number_nin)
            ->where('submission_date', '>=', Carbon::now()->subMinutes(10))
            ->latest()
            ->first();

        if ($recentVerification) {
            // Reconstruct the response data for the Blade view
            $reconstructedData = [
                'status' => 'success',
                'data' => [
                    'nin' => $recentVerification->number_nin,
                    'firstName' => $recentVerification->firstname,
                    'middleName' => $recentVerification->middlename,
                    'surname' => $recentVerification->surname,
                    'birthDate' => $recentVerification->birthdate,
                    'gender' => $recentVerification->gender,
                    'telephoneNo' => $recentVerification->telephoneno,
                    'photo' => $recentVerification->photo_path,
                ]
            ];

            session()->flash('verification', $reconstructedData);

            return redirect()->route('user.nin.verification.index')->with([
                'status' => 'success',
                'message' => "Result retrieved from history (Verified at {$recentVerification->submission_date}). No additional charge.",
            ]);
        }

        // 1. Get Verification Service from DB
        $service = Services1::where('name', 'Verification')->first();

        if (!$service) {
            return back()->with(['status' => 'error', 'message' => 'Verification service not available.']);
        }

        // 2. Get NIN Verification ServiceField (610)
        $serviceField = $service->fields()
            ->where('field_code', '610')
            ->where('is_active', true)
            ->first();

        if (!$serviceField) {
            return back()->with(['status' => 'error', 'message' => 'NIN verification service is not available.']);
        }

        // 3. Determine service price based on user role
        $servicePrice = $serviceField->getPriceForUserType($user->role);

        // 4. Check wallet
        $wallet = Wallet::where('user_id', $user->id)->firstOrFail();

        if ($wallet->balance < $servicePrice) {
            return back()->with([
                'status' => 'error',
                'message' => 'Insufficient wallet balance. You need NGN ' . number_format($servicePrice - $wallet->balance, 2)
            ]);
        }

        try {
            $apiKey = env('AREWA_API_TOKEN');
            $apiBaseUrl = env('AREWA_BASE_URL');
            $apiUrl = rtrim($apiBaseUrl, '/') . '/nin/verify';

            $response = Http::timeout(30)->withoutVerifying()
                ->withToken($apiKey)
                ->acceptJson()
                ->post($apiUrl, [
                    'nin' => $request->number_nin,
                ]);

            $decodedData = $response->json();

            // Log the response for debugging
            Log::info('NIN Verification Status: ' . $response->status(), [
                'response' => $decodedData
            ]);

            // Success is ONLY when HTTP status is 200, API returns success status, and data is present
            if ($response->status() === 200 && 
                (isset($decodedData['status']) && $decodedData['status'] === 'success') && 
                !empty($decodedData['data'])) {
                
                // Successful -> Proceed to Charge + Create Records
                return $this->processSuccessTransaction(
                    $wallet,
                    $servicePrice,
                    $user,
                    $serviceField,
                    $service,
                    $decodedData
                );
            }

            // All other responses are unsuccessful
            $errorMessage = $decodedData['message'] ?? 'Verification failed or invalid response from API.';
            
            if ($response->status() === 400) {
                $errorMessage = 'NIN do not exist.';
            } elseif ($response->status() !== 200) {
                $errorMessage = "API Error (Code {$response->status()}): " . $errorMessage;
            }

            return back()->with([
                'status' => 'error',
                'message' => $errorMessage
            ]);

        } catch (\Exception $e) {
            Log::error('NIN Verification System Error', ['message' => $e->getMessage()]);
            return back()->with([
                'status' => 'error',
                'message' => 'System Error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Process successful transaction (Charge + Verification Record)
     */
    private function processSuccessTransaction($wallet, $servicePrice, $user, $serviceField, $service, $ninData)
    {
        DB::beginTransaction();

        try {
            $transactionRef = 'Ver-' . (time() % 1000000000) . '-' . mt_rand(100, 999);
            $performedBy = $user->first_name . ' ' . $user->last_name;

            $transaction = Transaction::create([
                'referenceId' => $transactionRef,
                'user_id' => $user->id,
                'amount' => $servicePrice,
                'service_type'    => 'NIN Verification',
                'service_description' => "NIN Verification - {$serviceField->field_name}",
                'type' => 'debit',
                'status' => 'Approved',
                'performed_by'    => $performedBy,
                'metadata' => [
                    'service' => 'verification',
                    'service_field' => $serviceField->field_name,
                    'field_code' => $serviceField->field_code,
                    'nin' => $ninData['data']['nin'] ?? 'N/A', // Should exist on success
                    'user_role' => $user->role,
                    'price_details' => [
                        'base_price' => $serviceField->base_price,
                        'user_price' => $servicePrice,
                    ],
                    'source' => 'API',
                    'api_response' => $ninData
                ],
            ]);

            // Deduct wallet balance
            $wallet->decrement('balance', $servicePrice);

            $apiData = $ninData['data'] ?? [];

            Verification::create([
                'user_id' => $user->id,
                'service_field_id' => $serviceField->id,
                'service_id' => $service->id,
                'transaction_id' => $transaction->id,
                'reference' => $transactionRef,
                'idno' => $apiData['nin'] ?? ($apiData['number_nin'] ?? ''),
                'number_nin' => $apiData['nin'] ?? ($apiData['number_nin'] ?? ''),
                'firstname' => $apiData['firstName'] ?? ($apiData['first_name'] ?? ''),
                'middlename' => $apiData['middleName'] ?? ($apiData['middle_name'] ?? ''),
                'surname' => $apiData['surname'] ?? ($apiData['last_name'] ?? ''),
                'birthdate' =>  $apiData['birthDate'] ?? ($apiData['dob'] ?? ($apiData['birthday'] ?? '')),
                'gender' => $apiData['gender'] ?? '',
                'telephoneno' => $apiData['telephoneNo'] ?? ($apiData['phone'] ?? ($apiData['phoneNumber'] ?? '')),
                'photo_path' => $apiData['photo'] ?? '',
                'performed_by'    => $performedBy,
                'submission_date' => Carbon::now()
            ]);

            DB::commit();

            // Flash normalized verification data for Blade
            session()->flash('verification', $ninData);

            return redirect()->route('user.nin.verification.index')->with([
                'status' => 'success',
                'message' => "NIN Verification successful. Reference: {$transactionRef}. Charged: NGN " . number_format($servicePrice, 2),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->with([
                'status' => 'error',
                'message' => 'Transaction failed: ' . $e->getMessage()
            ]);
        }
    }
    /**
     * Charge for Slip Download
     */
    private function chargeForSlip($user, $fieldCode)
    {
         // 1. Get Verification Service from DB
         $service = Services1::where('name', 'Verification')->first();

        if (!$service) {
            throw new \Exception('Verification service not available.');
        }

        // 2. Get ServiceField
        $serviceField = $service->fields()
            ->where('field_code', $fieldCode)
            ->where('is_active', true)
            ->first();

        if (!$serviceField) {
             throw new \Exception('Slip service not available.');
        }

        // 3. Determine service price based on user role
        $servicePrice = $serviceField->getPriceForUserType($user->role);

        // 4. Check wallet
        $wallet = Wallet::where('user_id', $user->id)->firstOrFail();

        if ($wallet->balance < $servicePrice) {
             throw new \Exception('Insufficient wallet balance.');
        }
        
        $transactionRef = 'Slip-' . (time() % 1000000000) . '-' . mt_rand(100, 999);

        Transaction::create([
            'referenceId' => $transactionRef,
            'user_id' => $user->id,
            'amount' => $servicePrice,
            'service_type' => 'Slip Download',
            'service_description' => "Slip Download - {$serviceField->field_name}",
            'type' => 'debit',
            'status' => 'Approved',
        ]);

         // Deduct wallet balance
         $wallet->decrement('balance', $servicePrice);
         
         return true;
    }

    /**
     * Download NIN slips
     */
    public function basicSlip($nin_no)
    {
        DB::beginTransaction();
        try {
            $this->chargeForSlip(Auth::user(), 'V101'); // Charge for Basic Slip
            
            $repObj = new NIN_PDF_Repository();
            $pdf = $repObj->basicPDF($nin_no);
            
            DB::commit();
            return $pdf;
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    public function regularSlip($nin_no)
    {
        DB::beginTransaction();
        try {
            $this->chargeForSlip(Auth::user(), 'V102'); // Charge for Regular Slip
            
            $repObj = new NIN_PDF_Repository();
            $pdf = $repObj->regularPDF($nin_no);
            
            DB::commit();
            return $pdf;
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    public function standardSlip($nin_no)
    {
        DB::beginTransaction();
        try {
            $this->chargeForSlip(Auth::user(), '611'); // Charge for Standard Slip
            
            $repObj = new NIN_PDF_Repository();
            $pdf = $repObj->standardPDF($nin_no);
            
            DB::commit();
            return $pdf;
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    public function premiumSlip($nin_no)
    {
        DB::beginTransaction();
        try {
            $this->chargeForSlip(Auth::user(), '612'); // Charge for Premium Slip
            
            $repObj = new NIN_PDF_Repository();
            $pdf = $repObj->premiumPDF($nin_no);
            
            DB::commit();
            return $pdf;
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    public function vninSlip($nin_no)
    {
        DB::beginTransaction();
        try {
            $this->chargeForSlip(Auth::user(), '616'); // Charge for VNIN Slip
            
            $repObj = new NIN_PDF_Repository();
            $pdf = $repObj->vninPDF($nin_no);
            
            DB::commit();
            return $pdf;
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }
}

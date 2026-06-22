<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\AgentService;
use App\Models\Services1 as Service;
use App\Models\ServiceField;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Http\Controllers\Traits\Refundable;

class IpeController extends Controller
{
    use Refundable;
    public function index(Request $request)
    {
        $ipeService = Service::where('name', 'IPE')->first();
        $ipeFields = $ipeService ? $ipeService->fields : collect();

        $services = collect();
        $user = Auth::user();
        $role = $user->role ?? 'user';
        
        foreach ($ipeFields as $field) {
            $price = $field->getPriceForUserType($role);
            $services->push([
                'id' => $field->id,
                'name' => $field->field_name,
                'price' => $price,
                'type' => 'ipe',
                'service_id' => $field->service_id,
                'field_code' => $field->field_code ?? '002'
            ]);
        }
        
        $wallet = Wallet::where('user_id', Auth::id())->first();
        
        $query = AgentService::where('user_id', Auth::id())
            ->where('service_type', 'IPE');

        if ($request->has('search') && $request->search != '') {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('tracking_id', 'like', "%{$searchTerm}%")
                  ->orWhere('reference', 'like', "%{$searchTerm}%");
            });
        }

        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }

        $submissions = $query->orderByRaw("
            CASE status 
                WHEN 'pending' THEN 1 
                WHEN 'processing' THEN 2 
                WHEN 'successful' THEN 3 
                WHEN 'failed' THEN 4 
                WHEN 'resolved' THEN 5 
                WHEN 'rejected' THEN 6 
                ELSE 7 
            END
        ")->orderBy('created_at', 'desc')->paginate(10)->withQueryString();

        return view('nin.ipe', compact('services', 'wallet', 'submissions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_field' => 'required',
            'tracking_id' => 'required|string|min:10|max:50',
            'description' => 'nullable|string|max:255',
        ]);

        $fieldId = $request->service_field;
        $serviceField = ServiceField::with('service')->findOrFail($fieldId);
        
        $user = Auth::user();
        $role = $user->role ?? 'user';
        
        $servicePrice = $serviceField->getPriceForUserType($role);

        DB::beginTransaction();
        try {
            // Lock Wallet
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            if ($wallet->balance < $servicePrice) {
                throw new \Exception('Insufficient wallet balance.');
            }

            // Debit Wallet
            $wallet->decrement('balance', $servicePrice);

            $transactionRef = 'TRX-' . strtoupper(Str::random(10));
            $performedBy = $user->first_name . ' ' . $user->last_name;

            // Create Transaction record (debit)
            $transaction = Transaction::create([
                'referenceId' => $transactionRef,
                'user_id' => $user->id,
                'amount' => $servicePrice,
                'service_type'    => 'IPE Clearance',
                'service_description' => "IPE Clearance for {$serviceField->field_name}",
                'type' => 'debit',
                'status' => 'Approved',
                'performed_by' => $performedBy,
                'metadata' => [
                    'service' => $serviceField->service->name,
                    'service_field' => $serviceField->field_name,
                    'tracking_id' => $request->tracking_id,
                ],
            ]);

            // Create AgentService record
            $agentService = AgentService::create([
                'reference' => 'REF-' . strtoupper(Str::random(10)),
                'user_id' => $user->id,
                'service_id' => $serviceField->service_id,
                'service_field_id' => $serviceField->id,
                'field_code' => $serviceField->field_code,
                'transaction_id' => $transaction->id,
                'service_type' => 'IPE',
                'tracking_id' => $request->tracking_id,
                'amount' => $servicePrice,
                'status' => 'processing',
                'submission_date' => now(),
                'service_field_name' => $serviceField->field_name,
                'description' => $request->description ?? $serviceField->field_name,
                'performed_by' => $performedBy,
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }

        // Call API outside transaction
        try {
            $apiKey = env('AREWA_API_TOKEN');
            $apiBaseUrl = env('AREWA_BASE_URL');
            $apiUrl = rtrim($apiBaseUrl, '/') . '/nin/ipe';

            $payload = [
                'field_code' => $serviceField->field_code ?? '002',
                'tracking_id' => $request->tracking_id,
                'description' => $request->description ?? 'My Reference',
            ];

            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->post($apiUrl, $payload);
            
            $data = $response->json();

            if (!$response->successful() || !($data['success'] ?? false)) {
                throw new \Exception($data['message'] ?? 'API submission failed');
            }

            // Success API update
            $cleanResponse = $this->cleanApiResponse($data);
            $status = $this->normalizeStatus($data['data']['status'] ?? $data['status'] ?? 'processing');

            $agentService->update([
                'reference' => $data['data']['reference'] ?? $agentService->reference,
                'status' => $status,
                'comment' => $cleanResponse,
            ]);

            // Update Transaction reference as well if needed
            if (isset($data['data']['reference'])) {
                $transaction->update(['referenceId' => $data['data']['reference']]);
            }

            return back()->with('success', 'IPE request submitted successfully. Status: ' . $status);

        } catch (\Exception $e) {
            Log::error('IPE API Submission / System Error', ['error' => $e->getMessage()]);

            // Secure refund using the trait
            $this->updateStatusAndRefund($agentService, [
                'status' => 'failed',
                'comment' => 'API Error: ' . $e->getMessage(),
            ]);

            return back()->with('error', 'API Submission Failed: ' . $e->getMessage() . '. Wallet refunded.');
        }
    }

    /**
     * FIXED: Check Status Method - Corrected API call format
     */
    public function checkStatus(Request $request, $id)
    {
        try {
            $agentService = AgentService::where('id', $id)
                ->where('user_id', Auth::id())
                ->where('service_type', 'IPE')
                ->firstOrFail();

            $apiKey = env('AREWA_API_TOKEN');
            $apiBaseUrl = env('AREWA_BASE_URL');
            
            // FIXED: Use query parameter in URL as per API documentation
            $url = rtrim($apiBaseUrl, '/') . '/nin/ipe?tracking_id=' . urlencode($agentService->tracking_id);
            
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(30)
                ->get($url);
            
            $apiResponse = $response->json();

            if (!$response->successful()) {
                $message = $apiResponse['message'] ?? 'Failed to check status';
                
                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => $message
                    ], 422);
                }
                return back()->with('error', $message);
            }

            // FIXED: Properly handle the API response structure
            $status = $apiResponse['status'] ?? 'processing';
            $comment = $apiResponse['comment'] ?? json_encode($apiResponse);
            
            // Parse comment to extract NIN and name if available
            $details = null;
            if (strpos($comment, 'nin:') !== false || strpos($comment, 'name:') !== false) {
                preg_match('/nin:\s*(\d+)/', $comment, $ninMatches);
                preg_match('/name:\s*([^,]+)/', $comment, $nameMatches);
                preg_match('/dob:\s*([^,]+)/', $comment, $dobMatches);
                
                $details = json_encode([
                    'nin' => $ninMatches[1] ?? null,
                    'name' => $nameMatches[1] ?? null,
                    'dob' => $dobMatches[1] ?? null,
                    'checked_at' => now()->toDateTimeString()
                ]);
            }
            
            $updateData = [
                'comment' => $comment,
                'status' => $this->normalizeStatus($status),
            ];
            
            if ($details) {
                $updateData['details'] = $details;
            }

            $this->updateStatusAndRefund($agentService, $updateData);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Status checked successfully.',
                    'status' => $agentService->status,
                    'comment' => $agentService->comment,
                    'details' => $agentService->details
                ]);
            }

            return back()->with('success', 'Status checked successfully. Current status: ' . $agentService->status);

        } catch (\Exception $e) {
            Log::error('IPE Status Check Error: ' . $e->getMessage());
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to complete the status check. Please try again.'
                ], 500);
            }
            return back()->with('error', 'Unable to complete the status check. Please try again.');
        }
    }

    public function details($id)
    {
        try {
            $submission = AgentService::where('id', $id)
                ->where('user_id', Auth::id())
                ->where('service_type', 'IPE')
                ->firstOrFail();

            return response()->json([
                'id' => $submission->id,
                'tracking_id' => $submission->tracking_id,
                'reference' => $submission->reference,
                'service_field_name' => $submission->service_field_name,
                'status' => $submission->status,
                'amount' => $submission->amount,
                'description' => $submission->description,
                'comment' => $submission->comment,
                'details' => $submission->details ?? null,
                'created_at' => $submission->created_at,
                'last_checked_at' => $submission->updated_at,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Submission not found'], 404);
        }
    }

    /**
     * FIXED: Batch Check Method - Corrected API call format and logic
     */
    public function batchCheck()
    {
        try {
            // FIXED: Only check user's own submissions
            $pendingSubmissions = AgentService::where('service_type', 'IPE')
                ->where('user_id', Auth::id())
                ->whereIn('status', ['pending', 'processing'])
                ->orderBy('updated_at', 'asc')
                ->limit(20)
                ->get();

            if ($pendingSubmissions->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No pending submissions to check.',
                    'checked' => 0
                ]);
            }

            $apiKey = env('AREWA_API_TOKEN');
            $apiBaseUrl = env('AREWA_BASE_URL');
            
            $checked = 0;
            $updated = [];
            $failed = [];

            foreach ($pendingSubmissions as $submission) {
                try {
                    // FIXED: Correct URL format with query parameter
                    $url = rtrim($apiBaseUrl, '/') . '/nin/ipe?tracking_id=' . urlencode($submission->tracking_id);
                    
                    $response = Http::withToken($apiKey)
                        ->acceptJson()
                        ->timeout(30)
                        ->get($url);

                    $apiResponse = $response->json();

                    if ($response->successful() && isset($apiResponse['status'])) {
                        $status = $this->normalizeStatus($apiResponse['status']);
                        $comment = $apiResponse['comment'] ?? json_encode($apiResponse);
                        
                        $updateData = [
                            'status' => $status,
                            'comment' => $comment,
                        ];
                        
                        // Parse details if available
                        if (strpos($comment, 'nin:') !== false) {
                            preg_match('/nin:\s*(\d+)/', $comment, $ninMatches);
                            preg_match('/name:\s*([^,]+)/', $comment, $nameMatches);
                            
                            if (isset($ninMatches[1]) || isset($nameMatches[1])) {
                                $updateData['details'] = json_encode([
                                    'nin' => $ninMatches[1] ?? null,
                                    'name' => $nameMatches[1] ?? null,
                                    'checked_at' => now()->toDateTimeString()
                                ]);
                            }
                        }
                        
                        $this->updateStatusAndRefund($submission, $updateData);
                        
                        $checked++;
                        $updated[] = $submission->tracking_id;
                    } else {
                        $failed[] = $submission->tracking_id;
                    }
                    
                    // Add small delay to avoid rate limiting
                    usleep(200000); // 0.2 seconds
                    
                } catch (\Exception $e) {
                    Log::error('Batch check error for tracking ID ' . $submission->tracking_id . ': ' . $e->getMessage());
                    $failed[] = $submission->tracking_id;
                    continue;
                }
            }

            $message = "Batch check completed. ";
            $message .= "Updated: " . count($updated) . " submission(s). ";
            if (!empty($failed)) {
                $message .= "Failed: " . count($failed) . " submission(s).";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'checked' => $checked,
                'updated' => $updated,
                'failed' => $failed
            ]);

        } catch (\Exception $e) {
            Log::error('Batch Check Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Batch check failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function cleanApiResponse($response): string
    {
        if (is_array($response)) {
            $toKeep = array_diff_key($response, array_flip(['status', 'message', 'response', 'success']));
            return json_encode($toKeep);
        }
        return (string) $response;
    }

    private function normalizeStatus($status): string
    {
        $s = strtolower(trim((string) $status));
        return match ($s) {
            'successful', 'success', 'resolved', 'approved', 'completed' => 'successful',
            'processing', 'in_progress', 'in-progress', 'pending', 'submitted', 'new' => 'processing',
            'failed', 'rejected', 'error', 'declined', 'invalid', 'no record' => 'failed',
            default => 'pending',
        };
    }
}
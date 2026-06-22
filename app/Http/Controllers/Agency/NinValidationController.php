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

class NinValidationController extends Controller
{
    use Refundable;
    public function index(Request $request)
    {
        $validationService = Service::where('name', 'Validation')->first();
        $validationFields = $validationService ? $validationService->fields : collect();

        $services = collect();
        $user = Auth::user();
        $role = $user->role ?? 'user';
        
        foreach ($validationFields as $field) {
            $price = $field->getPriceForUserType($role);
            $services->push([
                'id' => $field->id,
                'name' => $field->field_name,
                'price' => $price,
                'type' => 'validation',
                'service_id' => $field->service_id
            ]);
        }
        
        $wallet = Wallet::where('user_id', Auth::id())->first();
        
        $query = AgentService::where('user_id', Auth::id())
            ->where('service_type', 'NIN_VALIDATION'); // Specific to Validation

        if ($request->has('search') && $request->search != '') {
            $searchTerm = $request->search;
            $query->where('nin', 'like', "%{$searchTerm}%");
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

        return view('nin.validation', compact('services', 'wallet', 'submissions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_field' => 'required',
            'nin' => 'required|digits:11',
        ]);

        $fieldId = $request->service_field;
        $serviceField = ServiceField::with('service')->findOrFail($fieldId);
        
        $user = Auth::user();
        $role = $user->role ?? 'user';
        
        $servicePrice = $serviceField->getPriceForUserType($role);

        DB::beginTransaction();
        try {
            // Lock wallet and check balance
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            if ($wallet->balance < $servicePrice) {
                throw new \Exception('Insufficient wallet balance.');
            }

            // Debit wallet
            $wallet->decrement('balance', $servicePrice);

            $transactionRef = 'TRX-' . strtoupper(Str::random(10));
            $performedBy = $user->first_name . ' ' . $user->last_name;

            // Create Transaction record (debit)
            $transaction = Transaction::create([
                'referenceId' => $transactionRef,
                'user_id' => $user->id,
                'amount' => $servicePrice,
                'service_type'    => 'NIN Validation',
                'service_description' => "NIN Validation for {$serviceField->field_name}",
                'type' => 'debit',
                'status' => 'Approved',
                'performed_by' => $performedBy,
                'metadata' => [
                    'service' => $serviceField->service->name,
                    'service_field' => $serviceField->field_name,
                    'nin' => $request->nin,
                ],
            ]);

            // Create AgentService record
            $agentService = AgentService::create([
                'reference' => 'REF-' . strtoupper(Str::random(10)),
                'user_id' => $user->id,
                'service_id' => $serviceField->service_id,
                'service_field_id' => $serviceField->id,
                'field_code' => '015',
                'transaction_id' => $transaction->id,
                'service_type' => 'NIN_VALIDATION',
                'nin' => $request->nin,
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
            $apiUrl = rtrim($apiBaseUrl, '/') . '/nin/validation';

            $payload = [
                'description' => $request->description ?? "My Reference",
                'nin' => $request->nin,
                'field_code' => '015', // Code for Validation
            ];

            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->post($apiUrl, $payload);
            
            $data = $response->json();

            if (!$response->successful() || (isset($data['status']) && $data['status'] == 'error')) {
                throw new \Exception($data['message'] ?? 'API submission failed');
            }

            // API Success - normal update
            $cleanResponse = $this->cleanApiResponse($data);
            $status = $this->normalizeStatus($data['status'] ?? 'processing');

            $agentService->update([
                'status' => $status,
                'comment' => $cleanResponse,
            ]);

            return back()->with('success', 'NIN Validation Request submitted successfully. Status: ' . $status);

        } catch (\Exception $e) {
            Log::error('NIN Validation Store API/System Error', ['error' => $e->getMessage()]);

            // Auto refund using the trait
            $this->updateStatusAndRefund($agentService, [
                'status' => 'failed',
                'comment' => 'API Error: ' . $e->getMessage(),
            ]);

            return back()->with('error', 'API Submission Failed: ' . $e->getMessage() . '. Wallet refunded.');
        }
    }

    public function checkStatus(Request $request, $id = null)
    {
        try {
            if ($id) {
                $agentService = AgentService::findOrFail($id);
            } else {
                $request->validate([
                    'nin' => 'required|string',
                ]);
                $agentService = AgentService::where('nin', $request->nin)
                    ->orderBy('created_at', 'desc')
                    ->firstOrFail();
            }

            $apiKey = env('AREWA_API_TOKEN');
            $apiBaseUrl = env('AREWA_BASE_URL');
            $url = rtrim($apiBaseUrl, '/') . '/nin/validation';
            
            $payload = [
                'description' => $agentService->description ?? "Status Check",
                'nin' => $agentService->nin,
                'field_code' => '015'
            ];

            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->get($url, $payload);
            
            $apiResponse = $response->json();
            $cleanResponse = $this->cleanApiResponse($apiResponse);

            $updateData = [
                'comment' => $cleanResponse,
            ];

            if (isset($apiResponse['status'])) {
                $updateData['status'] = $this->normalizeStatus($apiResponse['status']);
            } elseif (isset($apiResponse['response'])) {
                $updateData['status'] = $this->normalizeStatus($apiResponse['response']);
            }

            $this->updateStatusAndRefund($agentService, $updateData);

            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => true,
                    'nin' => $agentService->nin,
                    'status' => $agentService->status,
                    'response' => $apiResponse,
                ]);
            }

            return back()->with('success', 'Status checked successfully. Current status: ' . $agentService->status);

        } catch (\Exception $e) {
            Log::error('Status Check Error: ' . $e->getMessage());
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to check status: ' . $e->getMessage(),
                ], 500);
            }
            return back()->with('error', 'Unable to complete the status check. Please try again.');
        }
    }

    public function webhook(Request $request)
    {
        $data = $request->all();
        Log::info('NIN Validation Webhook Received', $data);

        $identifier = $data['nin'] ?? null;

        if ($identifier) {
            $submission = AgentService::where('nin', $identifier)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($submission) {
                $cleanResponse = $this->cleanApiResponse($data);
                
                $updateData = [
                    'comment' => $cleanResponse,
                ];

                if (isset($data['status'])) {
                    $updateData['status'] = $this->normalizeStatus($data['status']);
                }

                $this->updateStatusAndRefund($submission, $updateData);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Webhook received successfully'
        ]);
    }

    private function cleanApiResponse($response): string
    {
        if (is_array($response)) {
            $toKeep = array_diff_key($response, array_flip(['status', 'message', 'response']));
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
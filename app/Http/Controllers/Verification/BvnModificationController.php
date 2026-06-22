<?php

namespace App\Http\Controllers\Verification;

use App\Models\ServiceField;
use App\Models\AgentService;
use App\Models\Transaction;
use App\Models\Services1;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\Refundable;

class BvnModificationController extends Controller
{
    use Refundable;
    public function index(Request $request)
    {
        $user = auth()->user();

        // Fetch only bank-related services
        $bankServices = Services1::with(['fields' => function ($query) {
                $query->where('is_active', 1);
            }])
            ->where('name', 'like', '%BANK%')
            ->get();

        $query = AgentService::where('user_id', $user->id)
            ->where('service_type', 'bvn_modification');

        // Apply optional filters
        if ($request->filled('search')) {
            $query->where('bvn', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('bank')) {
            $query->where('bank', $request->bank);
        }

        // Paginate results
        $crmSubmissions = $query->orderByDesc('submission_date')
            ->paginate(10)
            ->withQueryString();

        // Distinct user-specific banks (for dropdown)
        $userBanks = AgentService::where('user_id', $user->id)
            ->whereNotNull('bank')
            ->where('bank', '<>', '')
            ->distinct()
            ->pluck('bank');

        // Ensure wallet exists
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0.00,]
        );

        // Fetch affidavit field and price for the user
        $role = $user->role ?? 'user';
        $affidavitField = ServiceField::where('field_code', '900')->first();
        $affidavitPrice = 0;
        if ($affidavitField) {
            $affidavitPrice = $affidavitField->prices()
                ->where('user_type', $role)
                ->value('price') ?? $affidavitField->base_price;
        }

        // Return view with data
        return view('bvn.modification', compact(
            'userBanks',
            'crmSubmissions',
            'bankServices',
            'wallet',
            'affidavitPrice'
        ));
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $rules = [
            'enrolment_bank' => 'required|exists:services,id',
            'service_field'  => 'required|exists:service_fields,id',
            'bank'           => 'nullable|string|max:255',
            'bvn'            => 'required|string|size:11',
            'nin'            => 'required|string|size:11',
            'affidavit'      => 'required|in:available,not_available',
            'affidavit_file' => 'nullable|file|mimes:pdf|max:5120',
        ];
        $rules['description'] = 'required|string|max:500';

        $validated = $request->validate($rules);

        $role = $user->role ?? 'user';
        $service = Services1::findOrFail($validated['enrolment_bank']);
        $serviceField = ServiceField::findOrFail($validated['service_field']);

        // Calculate prices
        $modificationFee = $serviceField->getPriceForUserType($role);

        $affidavitField = ServiceField::where('field_code', '900')->firstOrFail();

        $affidavitFee = $affidavitField->getPriceForUserType($role);

        $affidavitUploaded = $request->hasFile('affidavit_file');
        $chargeAffidavit = !$affidavitUploaded;

        $totalAmount = $modificationFee + ($chargeAffidavit ? $affidavitFee : 0);

        // Handle affidavit upload (before transaction)
        $fileName = null;
        $fileUrl = null;
        if ($affidavitUploaded) {
            $file = $request->file('affidavit_file');
            $fileName = 'affidavit_' . Str::slug($user->email) . '_' . time() . '.' . $file->getClientOriginalExtension();
            
            // Store in storage/app/public/uploads/affidavits
            $path = $file->storeAs('uploads/affidavits', $fileName, 'public');
            $fileUrl = Storage::disk('public')->url($path);
        }

        DB::beginTransaction();
        try {
            // Lock wallet and check balance
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            if ($wallet->balance < $totalAmount) {
                throw new \Exception("Insufficient wallet balance. Required: NGN " . number_format($totalAmount, 2));
            }

            // Debit wallet
            $wallet->decrement('balance', $totalAmount);

            $transactionRef = 'M1' . date('is') . strtoupper(Str::random(5));
            $performedBy = trim("{$user->first_name} {$user->last_name}");

            $transaction = Transaction::create([
                'referenceId' => $transactionRef,
                'user_id' => $user->id,
                'amount' => $totalAmount,
                'service_type'    => 'BVN modification',
                'service_description' => "BVN modification for {$serviceField->field_name}",
                'type' => 'debit',
                'status' => 'Approved',
                'performed_by' => $performedBy,
                'metadata' => [
                    'service' => $service->name,
                    'service_field' => $serviceField->field_name,
                    'field_code'    => $serviceField->field_code,
                    'bvn' => $validated['bvn'],
                    'nin' => $validated['nin'],
                    'price_details' => [
                        'modification_fee' => $modificationFee,
                        'affidavit_fee' => $chargeAffidavit ? $affidavitFee : 0,
                    ],
                ],
            ]);

            // Store submission
            $agentService = AgentService::create([
                'reference' => $transactionRef,
                'user_id' => $user->id,
                'service_id' => $serviceField->service_id,
                'service_field_id' => $serviceField->id,
                'service_name' => $service->name,
                'field_code' => $serviceField->field_code,
                'service_field_name' => $serviceField->field_name, // Fixed field name mapping
                'bank' => $service->name,
                'bvn' => $validated['bvn'],
                'nin' => $validated['nin'],
                'description' => $validated['description'] ?? '',
                'amount' => $totalAmount,
                'affidavit_file' => $fileName,
                'affidavit' => $validated['affidavit'],
                'affidavit_file_url' => $fileUrl,
                'transaction_id' => $transaction->id,
                'submission_date' => now(),
                'status' => 'pending',
                'service_type' => 'bvn_modification',
                'performed_by' => $performedBy,
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            // Clean up uploaded file if exists
            if ($affidavitUploaded && isset($fileName)) {
                Storage::disk('public')->delete('uploads/affidavits/' . $fileName);
            }
            return redirect()->route('user.modification')->withErrors(['wallet' => $e->getMessage()])->withInput();
        }

        // API Call to Arewa Smart outside transaction
        try {
            $apiKey = env('AREWA_API_TOKEN');
            $apiBaseUrl = env('AREWA_BASE_URL');
            $apiUrl = rtrim($apiBaseUrl, '/') . '/bvn/modification';

            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->post($apiUrl, [
                    'field_code'  => $serviceField->field_code,
                    'bvn'         => $validated['bvn'],
                    'nin'         => $validated['nin'],
                    'description' => $validated['description'] ?? '',
                ]);

            $apiData = $response->json();

            if (!$response->successful() || (isset($apiData['success']) && $apiData['success'] === false)) {
                Log::error('Arewa Smart API BVN Modification Failed', [
                    'response' => $apiData,
                    'payload' => [
                        'field_code' => $serviceField->field_code,
                        'nin' => $validated['nin']
                    ]
                ]);
                throw new \Exception('API Submission Failed: ' . ($apiData['message'] ?? 'Unknown API error.'));
            }

            // Sync references and store metadata
            $updates = [
                'comment' => $apiData['message'] ?? null,
            ];
            if (isset($apiData['data']['reference'])) {
                $updates['reference'] = $apiData['data']['reference'];
                $transaction->update(['referenceId' => $apiData['data']['reference']]);
            }

            $metadata = $transaction->metadata;
            $metadata['api_response'] = $apiData;
            $transaction->update(['metadata' => $metadata]);

            $agentService->update($updates);

            $msg = "BVN Modification Submitted Successfully. Charged: NGN " . number_format($totalAmount, 2);
            return redirect()->route('user.modification')->with([
                'status' => 'success',
                'message' => $msg,
            ]);

        } catch (\Exception $e) {
            Log::error('BVN Modification Submission API Error', ['error' => $e->getMessage()]);

            // Secure refund using trait
            $this->updateStatusAndRefund($agentService, [
                'status' => 'failed',
                'comment' => 'Submission Failed: ' . $e->getMessage(),
            ]);

            return redirect()->route('user.modification')->withErrors([
                'error' => 'API submission failed: ' . $e->getMessage() . '. Wallet refunded.',
            ])->withInput();
        }
    }

    // AJAX endpoint: fetch active service fields for a given service
    public function getServiceFields($serviceId)
    {
        $role = auth()->user()->role ?? 'user';

        $fields = ServiceField::where('service_id', $serviceId)
            ->where('is_active', 1)
            ->get();

        // Fallback: If no fields, look for Agency Banking
        if ($fields->isEmpty()) {
            $agencyBanking = Services1::where('name', 'Agency Banking')->first();
            if ($agencyBanking && $agencyBanking->id != $serviceId) {
                $fields = ServiceField::where('service_id', $agencyBanking->id)
                    ->where('is_active', 1)
                    ->get();
            }
        }

        $mappedFields = $fields->map(function ($field) use ($role) {
            $price = $field->getPriceForUserType($role);
            return [
                'id' => $field->id,
                'field_name' => $field->field_name,
                'description' => $field->description,
                'price' => $price,
            ];
        });

        return response()->json($mappedFields);
    }

    /**
     * Check Status of BVN Modification.
     */
    public function checkStatus(Request $request, $id)
    {
        $agentService = AgentService::findOrFail($id);

        $apiKey = env('AREWA_API_TOKEN');
        $apiBaseUrl = env('AREWA_BASE_URL');
        $apiUrl = rtrim($apiBaseUrl, '/') . '/bvn/modification';

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->get($apiUrl, [
                    'reference' => $agentService->reference,
                ]);

            $apiResponse = $response->json();

            if ($response->successful() && isset($apiResponse['success']) && $apiResponse['success']) {
                $data = $apiResponse['data'] ?? [];
                
                $updateData = [];

                // Map basic identifiers if present
                if (isset($data['bvn'])) $updateData['bvn'] = $data['bvn'];
                if (isset($data['nin'])) $updateData['nin'] = $data['nin'];
                
                // Map status
                if (isset($data['status'])) {
                    $updateData['status'] = $this->normalizeStatus($data['status']);
                }
                
                // Map comment (check for 'comment' first, then 'reason' as fallback)
                if (isset($data['comment'])) {
                    $updateData['comment'] = $data['comment'];
                } elseif (isset($data['reason'])) {
                    $updateData['comment'] = $data['reason'];
                }

                // Map description if provided by API
                if (isset($data['description'])) {
                    $updateData['description'] = is_array($data['description']) ? json_encode($data['description']) : $data['description'];
                }

                // Map file url
                if (isset($data['file_url'])) {
                    $updateData['file_url'] = $data['file_url'];
                }

                if (!empty($updateData)) {
                    $this->updateStatusAndRefund($agentService, $updateData);
                }

                return back()->with('success', 'Status updated successfully. Current status: ' . ucfirst($agentService->status));
            }

            return back()->with('error', 'Unable to fetch status: ' . ($apiResponse['message'] ?? 'Unknown error.'));

        } catch (\Exception $e) {
            Log::error('BVN Modification Status Check Error', ['error' => $e->getMessage()]);
            return back()->with('error', 'Connection Error: Unable to reach service provider.');
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
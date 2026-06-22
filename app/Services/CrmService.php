<?php

namespace App\Services;

use App\Models\AgentService;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CrmService
{
    /**
     * Check and update the status of a CRM submission.
     *
     * @param AgentService $submission
     * @return array
     */
    public function checkStatus(AgentService $submission)
    {
        $apiToken = env('AREWA_API_TOKEN');
        $baseUrl = env('AREWA_BASE_URL', 'https://api.arewasmart.com.ng/api/v1');

        try {
            // Determine endpoint based on service type
            if ($submission->service_name === 'BVN SEARCH') {
                $response = Http::withToken($apiToken)
                    ->withoutVerifying()
                    ->timeout(30)
                    ->get("{$baseUrl}/bvn/phone-search", [
                        'reference' => $submission->reference
                    ]);
            } else {
                // Default to CRM - Using the same base URL as other services for consistency
                $response = Http::withToken($apiToken)
                    ->withoutVerifying()
                    ->timeout(30)
                    ->get("{$baseUrl}/bvn/crm", [
                        'reference' => $submission->reference
                    ]);
            }

            if ($response->successful()) {
                $data = $response->json();
                
                Log::channel('crm')->info('Service Status Check Success', [
                    'reference' => $submission->reference,
                    'service' => $submission->service_name,
                    'response' => $data
                ]);

                // The data structure might differ slightly between services
                $apiData = $data['data'] ?? $data;
                $status = $apiData['status'] ?? $data['status'] ?? null;

                if ($status) {
                    $newStatus = $this->normalizeStatus($status);
                    $updateData = [
                        'status' => $newStatus,
                        'comment' => $apiData['message'] ?? $apiData['comment'] ?? $data['message'] ?? $submission->comment,
                    ];

                    if (isset($apiData['bvn'])) {
                        $updateData['bvn'] = $apiData['bvn'];
                    }

                    if (isset($apiData['file_url'])) {
                        $updateData['file_url'] = $apiData['file_url'];
                    }

                    // Handle Rejection and Refund
                    if ($newStatus === 'failed') {
                        DB::transaction(function () use ($submission, &$updateData) {
                            // 1. Lock the submission record for update to prevent concurrent updates
                            $lockedSubmission = AgentService::where('id', $submission->id)->lockForUpdate()->first();
                            
                            if ($lockedSubmission && !in_array($lockedSubmission->status, ['failed', 'rejected'])) {
                                // 2. Double check refund transaction existence
                                $refundRef = 'REF_' . $lockedSubmission->reference;
                                $refundExists = Transaction::where('referenceId', $refundRef)
                                    ->where('type', 'credit')
                                    ->exists();
                                
                                if (!$refundExists) {
                                    $wallet = Wallet::where('user_id', $lockedSubmission->user_id)->lockForUpdate()->first();
                                    if ($wallet) {
                                        $wallet->increment('balance', $lockedSubmission->amount);

                                        Transaction::create([
                                            'referenceId' => $refundRef,
                                            'user_id' => $lockedSubmission->user_id,
                                            'amount' => $lockedSubmission->amount,
                                            'service_type' => 'Refund',
                                            'service_description' => "Refund for failed {$lockedSubmission->service_name} Request: {$lockedSubmission->reference}",
                                            'type' => 'credit',
                                            'status' => 'Approved',
                                            'payer_name' => 'System Auto-Refund',
                                        ]);
                                        
                                        $updateData['comment'] = ($updateData['comment'] ?? 'Failed submission') . ' (Refunded)';
                                    }
                                }
                                $lockedSubmission->update($updateData);
                            } else {
                                // Submission is already failed or rejected in the database, just update other fields (e.g. comment) but don't refund
                                $cleanUpdateData = array_diff_key($updateData, ['status' => '']);
                                if (!empty($cleanUpdateData) && $lockedSubmission) {
                                    $lockedSubmission->update($cleanUpdateData);
                                }
                            }
                        });
                    } else {
                        $submission->update($updateData);
                    }

                    return [
                        'status' => 'success',
                        'message' => "Status updated to: " . ucfirst($newStatus),
                        'data' => $submission
                    ];
                }
            }

            Log::channel('crm')->warning('Service Status Check API Failure', [
                'reference' => $submission->reference,
                'response' => $response->body()
            ]);

            return [
                'status' => 'error',
                'message' => 'API returned unsuccessful response.'
            ];

        } catch (\Exception $e) {
            Log::channel('crm')->error('Service Status Check Exception', [
                'reference' => $submission->reference,
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'message' => 'An error occurred: ' . $e->getMessage()
            ];
        }
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

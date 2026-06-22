<?php

namespace App\Http\Controllers\Traits;

use App\Models\AgentService;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait Refundable
{
    /**
     * Securely update status of an AgentService submission and process refund if status transitions to failed/rejected.
     * Prevents duplicate refunds by locking the rows and checking if a refund transaction already exists.
     *
     * @param AgentService $submission
     * @param array $updateData
     * @return bool
     */
    protected function updateStatusAndRefund(AgentService $submission, array $updateData): bool
    {
        $newStatus = $updateData['status'] ?? $submission->status;

        // If transitioning to failed or rejected
        if (in_array($newStatus, ['failed', 'rejected'])) {
            return DB::transaction(function () use ($submission, $updateData) {
                // 1. Lock the submission record for update to prevent race conditions
                $lockedSubmission = AgentService::where('id', $submission->id)
                    ->lockForUpdate()
                    ->first();

                if (!$lockedSubmission) {
                    return false;
                }

                // 2. Prevent duplicate refund if it's already failed or rejected in the DB
                if (in_array($lockedSubmission->status, ['failed', 'rejected'])) {
                    // Update any non-status fields (e.g. comment) but don't refund
                    $cleanUpdateData = array_diff_key($updateData, ['status' => '']);
                    if (!empty($cleanUpdateData)) {
                        $lockedSubmission->update($cleanUpdateData);
                    }
                    return true;
                }

                // 3. Double check: Ensure we haven't already created a refund transaction for this submission
                $refundRef = 'REF_' . $lockedSubmission->reference;
                $refundExists = Transaction::where('referenceId', $refundRef)
                    ->where('type', 'credit')
                    ->exists();

                if ($refundExists) {
                    Log::warning('Refund skipped: Refund transaction already exists for reference ' . $lockedSubmission->reference);
                    $lockedSubmission->update($updateData);
                    return true;
                }

                // 4. Lock the user's wallet
                $wallet = Wallet::where('user_id', $lockedSubmission->user_id)
                    ->lockForUpdate()
                    ->first();

                if ($wallet) {
                    // 5. Refund the wallet balance
                    $wallet->increment('balance', $lockedSubmission->amount);

                    // 6. Create the refund transaction
                    Transaction::create([
                        'referenceId'         => $refundRef,
                        'user_id'             => $lockedSubmission->user_id,
                        'amount'              => $lockedSubmission->amount,
                        'service_type'        => 'Refund',
                        'service_description' => "Refund for failed {$lockedSubmission->service_name} Request: {$lockedSubmission->reference}",
                        'type'                => 'credit',
                        'status'              => 'Approved',
                        'payer_name'          => 'System Auto-Refund',
                    ]);

                    // Append refund note to the comment
                    $comment = $updateData['comment'] ?? $lockedSubmission->comment;
                    $updateData['comment'] = $comment ? $comment . ' (Refunded)' : 'Failed submission (Refunded)';
                }

                // 7. Update status to failed/rejected
                $lockedSubmission->update($updateData);

                // Sync the state on the original object in case it is reused
                $submission->status = $updateData['status'];
                $submission->comment = $updateData['comment'] ?? null;

                return true;
            });
        }

        // Normal update if status is not failed/rejected
        return $submission->update($updateData);
    }
}

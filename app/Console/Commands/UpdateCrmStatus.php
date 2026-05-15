<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AgentService;
use App\Services\CrmService;
use Illuminate\Support\Facades\Log;

class UpdateCrmStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:update-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically check and update the status of pending CRM requests';

    /**
     * Execute the console command.
     */
    public function handle(CrmService $crmService)
    {
        $this->info('Starting CRM status update...');
        Log::channel('crm')->info('Background Status Update Started');

        $pendingSubmissions = AgentService::whereIn('service_name', ['CRM', 'BVN SEARCH'])
            ->whereIn('status', ['pending', 'processing', 'query'])
            ->get();

        if ($pendingSubmissions->isEmpty()) {
            $this->info('No pending CRM submissions found.');
            return;
        }

        $this->info("Found {$pendingSubmissions->count()} pending submissions. Processing...");

        foreach ($pendingSubmissions as $submission) {
            $this->comment("Checking reference: {$submission->reference}");
            $result = $crmService->checkStatus($submission);
            
            if ($result['status'] === 'success') {
                $this->info("Successfully updated {$submission->reference}");
            } else {
                $this->error("Failed to update {$submission->reference}: " . $result['message']);
            }
        }

        $this->info('CRM status update completed.');
        Log::channel('crm')->info('Background Status Update Completed');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentService;
use App\Services\CrmService;
use Illuminate\Http\Request;

class BvncrmController extends Controller
{
    /**
     * Display a listing of all CRM submissions.
     */
    public function index(Request $request)
    {
        $query = AgentService::with('user', 'transaction')
            ->whereIn('service_name', ['CRM', 'BVN SEARCH']);

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhere('ticket_id', 'like', "%{$search}%")
                  ->orWhere('batch_id', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($u) use ($search) {
                      $u->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Status Filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $submissions = $query->latest()->paginate(20);

        return view('admin.bvn.crm', compact('submissions'));
    }

    public function checkStatus($id, CrmService $crmService)
    {
        $submission = AgentService::findOrFail($id);
        $result = $crmService->checkStatus($submission);

        return back()->with([
            'status'  => $result['status'],
            'message' => "Admin Check: " . $result['message'],
        ]);
    }

    /**
     * Batch check status for pending CRM requests (10 at a time).
     */
    public function batchCheck(CrmService $crmService)
    {
        $submissions = AgentService::whereIn('service_name', ['CRM', 'BVN SEARCH'])
            ->whereIn('status', ['pending', 'processing', 'query'])
            ->limit(10)
            ->get();

        if ($submissions->isEmpty()) {
            return back()->with([
                'status'  => 'info',
                'message' => 'No pending CRM requests found to check.',
            ]);
        }

        $successCount = 0;
        $errorCount = 0;

        foreach ($submissions as $submission) {
            $result = $crmService->checkStatus($submission);
            if ($result['status'] === 'success') {
                $successCount++;
            } else {
                $errorCount++;
            }
        }

        return back()->with([
            'status'  => 'success',
            'message' => "Batch process completed: {$successCount} successful, {$errorCount} failed.",
        ]);
    }

    /**
     * Manually update status (Admin Override).
     */
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,processing,successful,query,resolved,rejected,remark,failed',
            'comment' => 'nullable|string',
        ]);

        $submission = AgentService::findOrFail($id);
        $submission->update($validated);

        return back()->with([
            'status' => 'success',
            'message' => "Request #{$submission->reference} status manually updated to " . ucfirst($validated['status']),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentService;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Verification;
use App\Models\Wallet;
use App\Services\PalmPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    protected $palmPayService;

    public function __construct(PalmPayService $palmPayService)
    {
        $this->palmPayService = $palmPayService;
    }

    public function index()
    {
        $user = auth()->user();

        // Admin Stats (System Wide)
        $stats = [
            'totalUsers' => User::count(),
            'activeUsers' => User::where('is_active', true)->count(),
            'totalWalletBalance' => Wallet::sum('balance'),
            'totalVerification' => Verification::count(),
            'totalAgencyRequest' => AgentService::count(),
        ];

        // Most Used Service
        $mostUsedService = Transaction::select('service_type', DB::raw('count(*) as count'))
            ->whereNotNull('service_type')
            ->groupBy('service_type')
            ->orderBy('count', 'desc')
            ->first();

        $stats['mostUsedService'] = $mostUsedService ? $mostUsedService->service_type : 'N/A';

        // PalmPay Balance
        $palmPayResponse = $this->palmPayService->getMerchantBalance();
        $stats['palmPayBalance'] = $palmPayResponse['success'] ? $palmPayResponse['balance'] : 0;
        $stats['palmPaySuccess'] = $palmPayResponse['success'];

        // Global Recent Transactions for Admin View
        $recentTransactions = Transaction::with('user')->latest()->limit(10)->get();

        // --- USER DASHBOARD DATA ---
        $status = $user->kyc_status;
        $kycPending = $user->kyc_status == 'Pending';
        $userTransactions = $user->transactions()->latest()->paginate(10);
        
        return view('admin.dashboard', compact(
            'stats', 
            'recentTransactions', 
            'status', 
            'kycPending', 
            'userTransactions'
        ));
    }
}

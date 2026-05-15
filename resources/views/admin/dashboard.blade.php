@extends('layouts.dashboard')

@section('title', 'Admin Unified Dashboard')

@push('styles')
    <style>
        .stat-card {
            transition: all 0.3s ease;
            border: none;
            border-radius: 15px;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
        }
        .icon-box-admin {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
        }
        .bg-soft-primary { background-color: rgba(13, 110, 253, 0.1); color: #0d6efd; }
        .bg-soft-success { background-color: rgba(25, 135, 84, 0.1); color: #198754; }
        .bg-soft-warning { background-color: rgba(255, 193, 7, 0.1); color: #ffc107; }
        .bg-soft-danger { background-color: rgba(220, 53, 69, 0.1); color: #dc3545; }
        .bg-soft-info { background-color: rgba(13, 202, 240, 0.1); color: #0dcaf0; }
        .bg-soft-purple { background-color: rgba(111, 66, 193, 0.1); color: #6f42c1; }

        .bg-purple { background-color: #6f42c1 !important; }

        /* User Dashboard Styles */
        .price { font-size: 1.5rem; font-weight: 700; }
        .service-card { transition: all 0.3s ease; border-radius: 15px; }
        .service-card:hover { transform: scale(1.05); box-shadow: 0 8px 15px rgba(0,0,0,0.1); }
        .icon-box-media {
            width: 55px; height: 55px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 50%; margin: 0 auto 10px;
        }
        
        .nav-pills .nav-link {
            border-radius: 50px;
            padding: 10px 25px;
            font-weight: 600;
            color: #6c757d;
            background: #fff;
            margin-right: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .nav-pills .nav-link.active {
            background: #5e2572 !important;
            color: #fff !important;
        }
    </style>
    <!-- Latest Material Design Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">
@endpush

@section('content')
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1">Unified Command Center</h4>
                <p class="text-muted small">Manage system health and access your personal services.</p>
            </div>
            <div class="d-none d-md-block">
                <span class="badge bg-soft-primary p-2">Admin Access: Granted</span>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-pills mb-4" id="pills-tab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="pills-admin-tab" data-bs-toggle="pill" data-bs-target="#pills-admin" type="button" role="tab">
                <i class="mdi mdi-shield-crown me-2"></i>Admin Overview
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="pills-user-tab" data-bs-toggle="pill" data-bs-target="#pills-user" type="button" role="tab">
                <i class="mdi mdi-account-star me-2"></i>My Services
            </button>
        </li>
    </ul>

    <div class="tab-content" id="pills-tabContent">
        <!-- ADMIN OVERVIEW TAB -->
        <div class="tab-pane fade show active" id="pills-admin" role="tabpanel">
            <div class="row g-3 mb-5">
                <!-- Stats Cards -->
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="card stat-card shadow-sm h-100">
                        <div class="card-body p-3 text-center">
                            <div class="icon-box-admin bg-soft-primary mx-auto mb-2">
                                <i class="mdi mdi-account-group"></i>
                            </div>
                            <h6 class="text-muted small mb-1">Users</h6>
                            <h5 class="fw-bold mb-0">{{ number_format($stats['totalUsers']) }}</h5>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="card stat-card shadow-sm h-100">
                        <div class="card-body p-3 text-center">
                            <div class="icon-box-admin bg-soft-warning mx-auto mb-2">
                                <i class="mdi mdi-wallet"></i>
                            </div>
                            <h6 class="text-muted small mb-1">Liabilities</h6>
                            <h5 class="fw-bold mb-0">₦{{ number_format($stats['totalWalletBalance'], 2) }}</h5>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="card stat-card shadow-sm h-100">
                        <div class="card-body p-3 text-center">
                            <div class="icon-box-admin bg-soft-success mx-auto mb-2">
                                <i class="mdi mdi-bank"></i>
                            </div>
                            <h6 class="text-muted small mb-1">PalmPay</h6>
                            <h5 class="fw-bold mb-0">₦{{ number_format($stats['palmPayBalance'], 2) }}</h5>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="card stat-card shadow-sm h-100">
                        <div class="card-body p-3 text-center">
                            <div class="icon-box-admin bg-soft-info mx-auto mb-2">
                                <i class="mdi mdi-shield-check"></i>
                            </div>
                            <h6 class="text-muted small mb-1">Verified</h6>
                            <h5 class="fw-bold mb-0">{{ number_format($stats['totalVerification']) }}</h5>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="card stat-card shadow-sm h-100">
                        <div class="card-body p-3 text-center">
                            <div class="icon-box-admin bg-soft-purple mx-auto mb-2">
                                <i class="mdi mdi-account-tie"></i>
                            </div>
                            <h6 class="text-muted small mb-1">Agency</h6>
                            <h5 class="fw-bold mb-0">{{ number_format($stats['totalAgencyRequest']) }}</h5>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="card stat-card shadow-sm h-100">
                        <div class="card-body p-3 text-center">
                            <div class="icon-box-admin bg-soft-danger mx-auto mb-2">
                                <i class="mdi mdi-trending-up"></i>
                            </div>
                            <h6 class="text-muted small mb-1">Top Svc</h6>
                            <h6 class="fw-bold mb-0 small">{{ $stats['mostUsedService'] }}</h6>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Global Transactions -->
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0">Global Recent Transactions</h5>
                        <a href="{{ route('admin.transactions') }}" class="btn btn-sm btn-link text-decoration-none">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>User</th>
                                    <th>Service</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentTransactions as $transaction)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-xs bg-soft-primary rounded-circle p-2 me-2 d-flex align-items-center justify-content-center">
                                                    <i class="mdi mdi-account small"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold small">{{ $transaction->user->name }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="small">{{ $transaction->service_type }}</td>
                                        <td class="fw-bold small">₦{{ number_format($transaction->amount, 2) }}</td>
                                        <td>
                                            <span class="badge {{ $transaction->status == 'Approved' ? 'bg-success' : 'bg-warning' }} rounded-pill" style="font-size: 0.6rem;">
                                                {{ strtoupper($transaction->status) }}
                                            </span>
                                        </td>
                                        <td class="small text-muted">{{ $transaction->created_at->format('M d, H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- MY SERVICES TAB (User Dashboard) -->
        <div class="tab-pane fade" id="pills-user" role="tabpanel">
            <!-- Wallets Row -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="card bg-primary text-white border-0 shadow-sm rounded-4 h-100" style="background: linear-gradient(45deg, #5e2572, #8e44ad) !important;">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <p class="mb-1 opacity-75">Main Wallet Balance</p>
                                    <h1 class="fw-bold mb-0">₦{{ number_format(auth()->user()->wallet->balance ?? 0, 2) }}</h1>
                                </div>
                                <div class="bg-white bg-opacity-25 rounded-circle p-3">
                                    <i class="mdi mdi-wallet-outline mdi-24px"></i>
                                </div>
                            </div>
                            <button data-bs-toggle="modal" data-bs-target="#walletModal" class="btn btn-light btn-sm rounded-pill px-4">Fund Wallet</button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card bg-danger text-white border-0 shadow-sm rounded-4 h-100" style="background: linear-gradient(45deg, #e74c3c, #c0392b) !important;">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <p class="mb-1 opacity-75">Bonus Wallet</p>
                                    <h1 class="fw-bold mb-0">₦{{ number_format(auth()->user()->wallet->bonus ?? 0, 2) }}</h1>
                                </div>
                                <div class="bg-white bg-opacity-25 rounded-circle p-3">
                                    <i class="mdi mdi-gift-outline mdi-24px"></i>
                                </div>
                            </div>
                            <a href="{{ route('user.wallet') }}" class="btn btn-light btn-sm rounded-pill px-4 text-danger">Claim Bonus</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Service Icons -->
            <div class="row g-3 mb-5">
                <div class="col-12"><h5 class="fw-bold mb-3">Quick Services</h5></div>
                @php
                    $services = [
                        ['icon' => 'bi-telephone', 'title' => 'Buy Airtime', 'route' => 'user.airtime', 'color' => 'bg-primary'],
                        ['icon' => 'bi-database', 'title' => 'Buy Data', 'route' => 'user.buy-data', 'color' => 'bg-info'],
                        ['icon' => 'bi-wifi', 'title' => 'Buy SME Data', 'route' => 'user.buy-sme-data', 'color' => 'bg-purple'],
                        ['icon' => 'bi-person-check', 'title' => 'NIN Verification', 'route' => 'user.nin.verification.index', 'color' => 'bg-success'],
                        ['icon' => 'bi-phone', 'title' => 'NIN Phone Verify', 'route' => 'user.nin.phone.index', 'color' => 'bg-warning'],
                        ['icon' => 'bi-shield-check', 'title' => 'BVN Verification', 'route' => 'user.bvn-verification', 'color' => 'bg-danger'],
                        ['icon' => 'bi-person-gear', 'title' => 'NIN Modification', 'route' => 'user.nin.modification.index', 'color' => 'bg-dark'],
                        ['icon' => 'bi-percent', 'title' => 'TIN Verification', 'route' => 'user.tin.index', 'color' => 'bg-secondary'],
                    ];
                @endphp
                @foreach($services as $svc)
                    <div class="col-3 col-md-2 text-center">
                        <a href="{{ route($svc['route']) }}" class="text-decoration-none">
                            <div class="card service-card shadow-sm border-0 mb-2">
                                <div class="card-body p-3">
                                    <div class="icon-box-media {{ $svc['color'] }} text-white shadow-sm">
                                        <i class="bi {{ $svc['icon'] }} mdi-24px"></i>
                                    </div>
                                    <p class="mb-0 text-dark small fw-bold">{{ $svc['title'] }}</p>
                                </div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>

            <!-- Personal Transactions -->
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body">
                    <h5 class="fw-bold mb-4">My Recent Transactions</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Ref</th>
                                    <th>Service</th>
                                    <th>Amount</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($userTransactions as $data)
                                    <tr>
                                        <td class="small">{{ strtoupper($data->referenceId ?? 'N/A') }}</td>
                                        <td class="small">{{ $data->service_type }}</td>
                                        <td class="fw-bold small">₦{{ number_format($data->amount, 2) }}</td>
                                        <td class="text-center">
                                            <span class="badge {{ $data->status == 'Approved' ? 'bg-success' : 'bg-warning' }} rounded-pill" style="font-size: 0.6rem;">
                                                {{ strtoupper($data->status) }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals from User Dashboard -->
    @include('user.dashboard_modals') 

@endsection

@push('scripts')
    <script>
        // Modal and Copy scripts if needed
    </script>
@endpush

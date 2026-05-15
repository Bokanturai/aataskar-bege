@extends('layouts.dashboard')

@section('title', 'All Transactions')

@push('styles')
    <style>
        .pagination .page-link {
            min-width: 36px;
            text-align: center;
            border-radius: 6px;
            margin: 0 2px;
        }

        .transaction-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
        }

        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: #495057;
            padding: 15px;
        }

        .table tbody td {
            padding: 15px;
            vertical-align: middle;
            font-size: 0.875rem;
            color: #333;
        }

        .ref-code {
            font-family: 'Courier New', Courier, monospace;
            font-weight: 600;
            color: #0d6efd;
            background: rgba(13, 110, 253, 0.05);
            padding: 4px 8px;
            border-radius: 4px;
            text-decoration: none;
        }

        .amount-text {
            font-weight: 700;
            color: #212529;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-success-soft { background-color: rgba(25, 135, 84, 0.1); color: #198754; }
        .badge-danger-soft { background-color: rgba(220, 53, 69, 0.1); color: #dc3545; }
        .badge-warning-soft { background-color: rgba(255, 193, 7, 0.1); color: #ffc107; }

        .search-container .input-group {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .search-container input {
            border: none;
            padding: 12px 20px;
        }

        .search-container button {
            border-radius: 0 10px 10px 0 !important;
            padding: 0 20px;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-info .name { font-weight: 600; }
        .user-info .sub { font-size: 0.75rem; color: #6c757d; }

        @media (max-width: 768px) {
            .table-responsive { border-radius: 15px; }
        }
    </style>
@endpush

@section('content')
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="fw-bold mb-1">Transaction History</h4>
            <p class="text-muted">Monitor and manage all system transactions.</p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <!-- Filter Section -->
            <div class="search-container mb-4">
                <form method="GET" action="{{ url()->current() }}">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-6 col-lg-4">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-0"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" name="search" value="{{ request('search') }}"
                                    class="form-control" placeholder="Reference, Service, Status...">
                                <button class="btn btn-primary" type="submit">Search</button>
                            </div>
                        </div>
                        @if(request('search'))
                            <div class="col-auto">
                                <a href="{{ url()->current() }}" class="btn btn-link text-decoration-none text-muted">Clear Filters</a>
                            </div>
                        @endif
                    </div>
                </form>
            </div>

            @include('common.message')

            <div class="card transaction-card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        @if ($transactions->count())
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th width="50">#</th>
                                        <th>Date</th>
                                        <th>Reference</th>
                                        <th>Service Details</th>
                                        <th>Amount</th>
                                        <th>Payer Information</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($transactions as $data)
                                        <tr>
                                            <td class="text-muted small">
                                                {{ ($transactions->currentPage() - 1) * $transactions->perPage() + $loop->iteration }}
                                            </td>
                                            <td>
                                                <div class="fw-bold">{{ $data->created_at->format('d M, Y') }}</div>
                                                <small class="text-muted">{{ $data->created_at->format('H:i') }}</small>
                                            </td>
                                            <td>
                                                @if($data->referenceId)
                                                    <a target="_blank" href="{{ route('admin.reciept', $data->referenceId) }}" class="ref-code">
                                                        {{ strtoupper($data->referenceId) }}
                                                    </a>
                                                @else
                                                    <span class="badge bg-light text-muted">N/A</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="fw-bold text-primary">{{ $data->service_type }}</div>
                                                <div class="sub text-muted small" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $data->service_description }}">
                                                    {{ $data->service_description }}
                                                </div>
                                            </td>
                                            <td>
                                                <div class="amount-text fs-5">₦{{ number_format($data->amount, 2) }}</div>
                                            </td>
                                            <td>
                                                <div class="user-info">
                                                    <span class="name">{{ $data->payer_name ?: ($data->user->name ?? 'Unknown User') }}</span>
                                                    <span class="sub"><i class="bi bi-envelope me-1"></i> {{ $data->payer_email ?: ($data->user->email ?? 'N/A') }}</span>
                                                    <span class="sub"><i class="bi bi-phone me-1"></i> {{ $data->payer_phone ?: ($data->user->phone_number ?? 'N/A') }}</span>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                @php
                                                    $statusClass = match($data->status) {
                                                        'Approved', 'Successful', 'success' => 'badge-success-soft',
                                                        'Rejected', 'Failed', 'failed' => 'badge-danger-soft',
                                                        default => 'badge-warning-soft'
                                                    };
                                                @endphp
                                                <span class="status-badge {{ $statusClass }}">
                                                    {{ strtoupper($data->status) }}
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                @if($data->referenceId)
                                                    <a target="_blank" href="{{ route('admin.reciept', $data->referenceId) }}" 
                                                       class="btn btn-icon btn-outline-primary btn-sm rounded-circle" 
                                                       title="Download Receipt">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                @else
                                                    <button disabled class="btn btn-icon btn-outline-secondary btn-sm rounded-circle">
                                                        <i class="bi bi-slash-circle"></i>
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            <!-- Pagination -->
                            <div class="p-4 border-top bg-light">
                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                                    <div class="text-muted small">
                                        Showing {{ $transactions->firstItem() }} to {{ $transactions->lastItem() }} of {{ $transactions->total() }} entries
                                    </div>
                                    <div>
                                        {{ $transactions->appends(request()->query())->links('vendor.pagination.bootstrap-5') }}
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="text-center py-5">
                                <img width="200" src="{{ asset('assets/images/no-transaction.gif') }}" alt="No transactions" class="mb-3 opacity-50">
                                <h5 class="fw-bold">No Records Found</h5>
                                <p class="text-muted">We couldn't find any transactions matching your search.</p>
                                <a href="{{ url()->current() }}" class="btn btn-primary btn-sm">Clear All Filters</a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

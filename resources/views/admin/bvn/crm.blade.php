@extends('layouts.dashboard')

@section('title', 'Admin - BVN CRM Management')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet" />
<style>
    .ql-editor {
        min-height: 200px;
    }
</style>
@endpush

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-white"><i class="mdi mdi-account-details-outline me-2"></i>Admin - BVN CRM & Search Requests</h5>
                <div class="d-flex gap-2 align-items-center">
                    <form action="{{ route('admin.services.bvn-crm.batch-check') }}" method="POST" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-light text-primary fw-bold shadow-sm">
                            <i class="mdi mdi-refresh-circle me-1"></i> Batch Check (10)
                        </button>
                    </form>
                    <span class="badge bg-light text-primary">{{ $submissions->total() }} Total Requests</span>
                </div>
            </div>
            
            <div class="card-body p-4">
                {{-- Alerts --}}
                @if (session('status'))
                    <div class="alert alert-{{ session('status') === 'success' ? 'success' : 'danger' }} alert-dismissible fade show border-0 shadow-sm mb-4">
                        {{ session('message') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <!-- Filter Form -->
                <form method="GET" class="row g-3 mb-4 bg-light p-3 rounded-3 border">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Search</label>
                        <input class="form-control" name="search" type="text" placeholder="Reference, Email, ID..." value="{{ request('search') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Statuses</option>
                            @foreach(['pending','processing','successful','query','resolved','rejected','remark','failed'] as $status)
                                <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>
                                    {{ ucfirst($status) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-primary w-100 shadow-sm" type="submit">
                            <i class="mdi mdi-filter"></i> Filter
                        </button>
                    </div>
                </form>

                <!-- Table -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Ref / Date</th>
                                <th>User</th>
                                <th>Details</th>
                                <th>Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($submissions as $submission)
                                <tr>
                                    <td>
                                        <small class="text-primary fw-bold">{{ $submission->reference }}</small><br>
                                        <small class="text-muted">{{ $submission->created_at->format('M d, Y H:i') }}</small>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="fw-bold">{{ $submission->user->name }}</span>
                                            <small class="text-muted">{{ $submission->user->email }}</small>
                                        </div>
                                    </td>
                                    <td>
                                        @if($submission->service_name == 'BVN SEARCH')
                                            <small><strong>Phone:</strong> {{ $submission->number }}</small>
                                        @else
                                            <small><strong>Batch:</strong> {{ $submission->batch_id }}</small><br>
                                            <small><strong>Ticket:</strong> {{ $submission->ticket_id }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill bg-{{ match($submission->status) {
                                            'resolved', 'successful' => 'success',
                                            'processing'             => 'primary',
                                            'rejected', 'failed'     => 'danger',
                                            'query'                  => 'info',
                                            default                  => 'warning'
                                        } }}">
                                            {{ strtoupper($submission->status) }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex gap-2 justify-content-center">
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-primary"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#adminCommentModal{{ $submission->id }}">
                                                <i class="mdi mdi-eye"></i>
                                            </button>
                                            
                                            <a href="{{ route('admin.services.bvn-crm.check', $submission->id) }}" 
                                               class="btn btn-sm btn-info text-white" 
                                               title="Force Status Check">
                                                <i class="mdi mdi-refresh"></i>
                                            </a>
                                        </div>

                                        <!-- Modal for details -->
                                        <div class="modal fade" id="adminCommentModal{{ $submission->id }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content text-start">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Request Details</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <form action="{{ route('admin.services.bvn-crm.update-status', $submission->id) }}" method="POST" class="status-form">
                                                            @csrf
                                                            <div class="mb-3 text-start">
                                                                <label class="form-label small fw-bold">Manual Status Override</label>
                                                                <select name="status" class="form-select form-select-sm">
                                                                    @foreach(['pending','processing','successful','query','resolved','rejected','remark','failed'] as $st)
                                                                        <option value="{{ $st }}" {{ $submission->status == $st ? 'selected' : '' }}>{{ strtoupper($st) }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                            <div class="mb-3 text-start">
                                                                <label class="form-label small fw-bold">Comment / Reason</label>
                                                                <div class="quill-editor bg-white">{!! $submission->comment !!}</div>
                                                                <input type="hidden" name="comment" class="comment-input" value="{{ $submission->comment }}">
                                                            </div>
                                                            <button type="submit" class="btn btn-primary btn-sm w-100 mb-3">Update Request</button>
                                                        </form>

                                                        @if($submission->file_url)
                                                            <hr>
                                                            <a href="{{ $submission->file_url }}" target="_blank" class="btn btn-outline-primary btn-sm w-100">
                                                                View Downloadable File
                                                            </a>
                                                        @endif
                                                        
                                                        <hr>
                                                        <p class="small text-muted mb-0 text-center">Submission ID: #{{ $submission->id }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        No CRM requests found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-4 d-flex justify-content-center">
                    {{ $submissions->appends(request()->query())->links('vendor.pagination.custom') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const forms = document.querySelectorAll('.status-form');
        
        forms.forEach(form => {
            const editorContainer = form.querySelector('.quill-editor');
            const hiddenInput = form.querySelector('.comment-input');
            
            const quill = new Quill(editorContainer, {
                theme: 'snow',
                placeholder: 'Enter reason for manual update...',
                modules: {
                    toolbar: [
                        ['bold', 'italic', 'underline'],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        ['clean']
                    ]
                }
            });
            
            form.addEventListener('submit', function() {
                hiddenInput.value = quill.root.innerHTML;
            });
        });
    });
</script>
@endpush

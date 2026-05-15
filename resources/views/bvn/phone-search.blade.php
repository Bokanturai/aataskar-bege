<x-app-layout>
    <title>Arewa Smart - {{ $title ?? 'Search BVN by Phone' }}</title>

    <div class="page-body">
        <div class="container-fluid">
            <div class="page-title mb-3">
                <div class="row">
                    <div class="col-sm-6 col-12">
                        <h3 class="fw-bold text-primary">BVN Search by Phone</h3>
                        <p class="text-muted small mb-0">Retrieve BVN information using the registered phone number.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="container-fluid mt-3">
            <div class="row">
                <!-- Phone Validation Form -->
                <div class="col-xl-6 mb-4">
                    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                        <div class="card-header bg-primary text-white py-3">
                            <h5 class="mb-0 fw-bold"><i class="bi bi-phone-fill me-2"></i>Manual BVN Search</h5>
                        </div>

                        <div class="card-body p-4">
                            {{-- Alert Messages --}}
                            @if (session('status'))
                                <div class="alert alert-{{ session('status') === 'success' ? 'success' : 'danger' }} alert-dismissible fade show border-0 shadow-sm mb-4">
                                    <i class="bi bi-{{ session('status') === 'success' ? 'check-circle' : 'exclamation-triangle' }} me-2"></i>
                                    {{ session('message') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            @endif

                            @if ($errors->any())
                                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4">
                                    <ul class="mb-0 small">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            @endif

                            <div class="text-center mb-4">
                                <p class="text-muted">
                                    Ensure the phone number provided is the one registered with the BVN.
                                </p>
                            </div>

                            {{-- Request Form --}}
                            <form method="POST" action="{{ route('user.phone.search.store') }}" class="row g-4">
                                @csrf

                                <!-- Service Field (Hidden as there is only one type) -->
                                @if($serviceFields->count() > 0)
                                    <input type="hidden" name="service_field_id" value="{{ $serviceFields->first()->id }}">
                                    @php
                                        $field = $serviceFields->first();
                                        $price = $field->getPriceForUserType(auth()->user()->role);
                                    @endphp
                                    <script>
                                        document.addEventListener("DOMContentLoaded", function() {
                                            const priceDisplay = document.getElementById('phone-field-price');
                                            const descDisplay = document.getElementById('phone-field-description');
                                            if(priceDisplay) priceDisplay.innerText = '₦{{ number_format($price, 2) }}';
                                            if(descDisplay) descDisplay.innerText = '{{ $field->description }}';
                                        });
                                    </script>
                                @endif


                                <!-- Phone Number -->
                                <div class="col-md-12">
                                    <label class="form-label fw-bold">Phone Number <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="bi bi-hash"></i></span>
                                        <input class="form-control" name="number" type="text" required
                                            placeholder="Enter 11-digit phone number"
                                            value="{{ old('number') }}" maxlength="11" minlength="11"
                                            pattern="[0-9]{11}">
                                        <button type="button" class="btn btn-outline-secondary"
                                            data-bs-toggle="modal" data-bs-target="#sampleInfoModal">
                                            <i class="bi bi-question-circle"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Pricing Info -->
                                <div class="col-md-6 text-center">
                                    <label class="form-label fw-bold">Service Fee</label>
                                    <div class="alert alert-secondary py-2 mb-0 border-0 shadow-sm">
                                        <span class="h5 fw-bold mb-0 text-primary" id="phone-field-price">₦0.00</span>
                                    </div>
                                </div>

                                <div class="col-md-6 text-center">
                                    <label class="form-label fw-bold">Wallet Balance</label>
                                    <div class="alert alert-soft-success py-2 mb-0 border-0 shadow-sm">
                                        <span class="h5 fw-bold mb-0 text-success">₦{{ number_format($wallet->balance ?? 0, 2) }}</span>
                                    </div>
                                </div>



                                <!-- Submit -->
                                <div class="col-12 d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg shadow-sm hover-up">
                                        <i class="bi bi-search me-2"></i> Process Search
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Submission History -->
                <div class="col-xl-6">
                    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                        <div class="card-header bg-primary text-white py-3 d-flex align-items-center justify-content-between">
                            <h5 class="fw-bold mb-0 text-white">
                                <i class="bi bi-clock-history me-2"></i> Submission History
                            </h5>
                        </div>

                        <div class="card-body p-4">
                            <form class="row g-3 mb-4 bg-light p-3 rounded-3 border" method="GET" action="{{ route('user.phone.search.index') }}">
                                <div class="col-md-5">
                                    <input class="form-control border-0 shadow-sm" name="search" type="text" placeholder="Phone Number..." value="{{ request('search') }}">
                                </div>
                                <div class="col-md-4">
                                    <select class="form-select border-0 shadow-sm" name="status">
                                        <option value="">All Statuses</option>
                                        @foreach(['pending', 'processing', 'verified', 'resolved', 'rejected'] as $status)
                                            <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>
                                                {{ ucfirst($status) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button class="btn btn-primary w-100 shadow-sm" type="submit">
                                        <i class="bi bi-filter"></i> Filter
                                    </button>
                                </div>
                            </form>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="bg-primary text-white">
                                        <tr>
                                            <th>#</th>
                                            <th>Reference</th>
                                            <th>Phone Number</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($crmSubmissions as $submission)
                                            <tr>
                                                <td class="fw-bold text-muted">{{ $loop->iteration + $crmSubmissions->firstItem() - 1 }}</td>
                                                <td><span class="text-primary fw-medium">{{ $submission->reference }}</span></td>
                                                <td><span class="badge bg-light text-dark border">{{ $submission->number }}</span></td>
                                                <td>
                                                    <span class="badge rounded-pill bg-{{ match($submission->status) {
                                                        'resolved', 'successful' => 'success',
                                                        'processing' => 'primary',
                                                        'verified' => 'info',
                                                        'rejected' => 'danger',
                                                        default => 'warning'
                                                    } }}">{{ ucfirst($submission->status) }}</span>
                                                </td>
                                                <td>
                                                    @php
                                                        $fileUrl = '';
                                                        if (!empty($submission->file_url)) {
                                                            $f = $submission->file_url;
                                                            if (preg_match('/^https?:\/\//', $f)) {
                                                                $fileUrl = $f;
                                                            } elseif (str_starts_with($f, '/storage') || str_starts_with($f, 'storage')) {
                                                                $fileUrl = asset(ltrim($f, '/'));
                                                            } else {
                                                                $fileUrl = \Illuminate\Support\Facades\Storage::url($f);
                                                            }
                                                        }
                                                    @endphp
                                                    @if(in_array($submission->status, ['pending', 'processing']))
                                                        <button type="button"
                                                            class="btn btn-sm btn-icon btn-outline-info sync-status-btn"
                                                            data-id="{{ $submission->id }}"
                                                            title="Sync Status">
                                                            <i class="bi bi-arrow-repeat"></i>
                                                        </button>
                                                    @endif
                                                    <button type="button"
                                                        class="btn btn-sm btn-icon btn-outline-primary"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#commentModal"
                                                        data-comment="{{ $submission->comment ?? 'No comment yet.' }}"
                                                        data-file-url="{{ $fileUrl }}">
                                                        <i class="bi bi-eye-fill"></i>
                                                    </button>

                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-5">
                                                    <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                                    No search submissions found.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-4 d-flex justify-content-center">
                                {{ $crmSubmissions->withQueryString()->links('vendor.pagination.custom') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Comment Modal --}}
    @include('pages.comment')

    {{-- Guidelines Modal --}}
    <div class="modal fade" id="sampleInfoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header bg-primary text-white py-3">
                    <h5 class="modal-title fw-bold"><i class="bi bi-info-circle me-2"></i>Search Guidelines</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="bg-primary-subtle p-3 rounded-3 mb-4">
                        <h6 class="fw-bold text-primary mb-2">Important Instructions:</h6>
                        <ul class="list-unstyled mb-0">
                            <li class="mb-1"><i class="bi bi-check-circle-fill text-success me-2"></i> The phone number must be exactly 11 digits.</li>
                            <li class="mb-1"><i class="bi bi-check-circle-fill text-success me-2"></i> Manual search typically completes within 24 business hours.</li>
                            <li><i class="bi bi-check-circle-fill text-success me-2"></i> A processing fee applies regardless of the final outcome.</li>
                        </ul>
                    </div>
                    <div class="alert alert-warning border-0 shadow-sm d-flex">
                        <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                        <div>
                            <strong>Policy Note:</strong> Failed searches due to incorrect phone numbers may still incur a service charge (20% of fee).
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-dismiss="modal">Got it!</button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .hover-up:hover { transform: translateY(-3px); transition: all 0.3s ease; }
        .alert-soft-success { background-color: #d1e7dd; color: #0f5132; }
        .btn-icon { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; }
        .table thead th { font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; }
    </style>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.querySelectorAll('.sync-status-btn').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const btn = this;
                const originalHtml = btn.innerHTML;
                
                Swal.fire({
                    title: 'Syncing...',
                    text: 'Fetching latest status from the provider.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

                let url = "{{ route('user.phone.search.check', ':id') }}".replace(':id', id);

                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success',
                                text: data.message,
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Failed',
                                text: data.message
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An error occurred: ' + error.message
                        });
                    })
                    .finally(() => {
                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                    });
            });
        });
    </script>
</x-app-layout>


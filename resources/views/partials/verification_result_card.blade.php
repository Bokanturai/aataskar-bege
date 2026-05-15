@if (session('verification'))
    <div class="alert alert-soft-success border-0 rounded-3 mb-4 d-flex align-items-center" style="background-color: #e8f5e9; color: #2e7d32;">
        <i class="mdi mdi-check-decagram fs-4 me-2"></i>
        <strong>Verification Successful!</strong>
    </div>

    @php
        $verificationData = session('verification')['data'] ?? [];
        $nin = $verificationData['nin'] ?? 'N/A';
        $surname = $verificationData['surname'] ?? 'N/A';
        $firstName = $verificationData['firstName'] ?? 'N/A';
        $qrData = "NIN: $nin | Name: $surname $firstName | Status: Verified";
        $qrUrl = "https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=" . urlencode($qrData);
    @endphp

    <div class="row align-items-start">
        <!-- Photo and QR Code -->
        <div class="col-md-4 text-center mb-4 mb-md-0">
            <div class="position-relative d-inline-block p-2 border border-2 border-primary rounded-4 bg-white shadow-sm overflow-hidden mb-3" style="width: 160px; height: 180px;">
                @if (!empty($verificationData['photo']))
                    <img src="data:image/jpeg;base64,{{ $verificationData['photo'] }}"
                        alt="ID Photo" class="w-100 h-100 rounded-3"
                        style="object-fit: cover;">
                @else
                    <div class="w-100 h-100 bg-light d-flex align-items-center justify-content-center">
                        <i class="mdi mdi-account-outline fs-1 text-muted"></i>
                    </div>
                @endif
            </div>
            
            <!-- QR Code Section -->
            <div class="d-inline-block p-2 border rounded-3 bg-white shadow-sm mb-2" style="width: 100px;">
                <img src="{{ $qrUrl }}" alt="QR Code" class="img-fluid">
                <div class="mt-1 small text-muted fw-bold" style="font-size: 0.6rem;">VERIFIED</div>
            </div>
            <div class="mt-1 fw-bold text-uppercase small text-muted">Identity Assets</div>
        </div>
        
        <!-- Details Table -->
        <div class="col-md-8">
            <div class="table-responsive rounded-3 overflow-hidden border shadow-sm">
                <table class="table table-hover mb-0">
                    <tbody class="small">
                        <tr>
                            <th class="bg-light w-40 text-muted py-2 ps-3 border-end">NIN Number</th>
                            <td class="fw-bold text-primary py-2">{{ $nin }}</td>
                        </tr>
                        <tr>
                            <th class="bg-light text-muted py-2 ps-3 border-end">Surname</th>
                            <td class="fw-semibold py-2 text-uppercase">{{ $surname }}</td>
                        </tr>
                        <tr>
                            <th class="bg-light text-muted py-2 ps-3 border-end">First Name</th>
                            <td class="fw-semibold py-2">{{ $firstName }}</td>
                        </tr>
                        <tr>
                            <th class="bg-light text-muted py-2 ps-3 border-end">Middle Name</th>
                            <td class="fw-semibold py-2">{{ $verificationData['middleName'] ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th class="bg-light text-muted py-2 ps-3 border-end">DOB</th>
                            <td class="fw-semibold py-2">
                                {{ !empty($verificationData['birthDate'])
                                    ? \Carbon\Carbon::parse($verificationData['birthDate'])->format('d M, Y')
                                    : 'N/A' }}
                            </td>
                        </tr>
                        <tr>
                            <th class="bg-light text-muted py-2 ps-3 border-end">Gender</th>
                            <td class="fw-semibold py-2">{{ strtoupper($verificationData['gender'] ?? 'N/A') }}</td>
                        </tr>
                        <tr>
                            <th class="bg-light text-muted py-2 ps-3 border-end">Phone</th>
                            <td class="fw-semibold py-2">{{ $verificationData['telephoneNo'] ?? 'N/A' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Slip Downloads Section -->
    <div class="mt-4 pt-4 border-top">
        <h6 class="fw-bold mb-3 text-center text-muted small text-uppercase"><i class="mdi mdi-download me-2"></i>Download Slips</h6>
        <div class="row g-2 text-center justify-content-center">
            @if (!empty($nin) && $nin !== 'N/A')
                @php
                    $slipTypes = [
                        ['name' => 'Basic', 'price' => $basicSlipPrice ?? ($freeSlipPrice ?? 0), 'route' => $downloadRoutes['basic'] ?? ($downloadRoutes['free'] ?? '#'), 'color' => 'btn-light border', 'icon' => 'mdi-file-document-outline'],
                        ['name' => 'Regular', 'price' => $regularSlipPrice ?? 0, 'route' => $downloadRoutes['regular'] ?? '#', 'color' => 'btn-info', 'icon' => 'mdi-file-document-outline'],
                        ['name' => 'Standard', 'price' => $standardSlipPrice ?? 0, 'route' => $downloadRoutes['standard'] ?? '#', 'color' => 'btn-outline-primary', 'icon' => 'mdi-file-document-outline'],
                        ['name' => 'Premium', 'price' => $premiumSlipPrice ?? 0, 'route' => $downloadRoutes['premium'] ?? '#', 'color' => 'btn-primary shadow-sm bg-gradient', 'icon' => 'mdi-file-star-outline'],
                        ['name' => 'VNIN', 'price' => $vninSlipPrice ?? 0, 'route' => $downloadRoutes['vnin'] ?? '#', 'color' => 'btn-warning', 'icon' => 'mdi-qrcode'],
                    ];
                @endphp

                @foreach($slipTypes as $slip)
                    @if($slip['route'] !== '#')
                        <div class="col-6 col-md-4 col-lg">
                            <button onclick="confirmDownload('{{ $slip['route'] }}', '{{ $slip['name'] }} Slip', {{ $slip['price'] }})" 
                                class="btn {{ $slip['color'] }} w-100 py-2 rounded-3 {{ $slip['name'] === 'Regular' ? 'text-white' : '' }}">
                                <i class="mdi {{ $slip['icon'] }} me-1"></i> {{ $slip['name'] }} <br>
                                <small class="fw-bold">₦{{ number_format($slip['price'], 2) }}</small>
                            </button>
                        </div>
                    @endif
                @endforeach
            @else
                <div class="col-12">
                    <div class="alert alert-warning border-0 small py-2 mb-0 text-center">
                        <i class="mdi mdi-alert-circle me-1"></i> NIN data not available for slip download.
                    </div>
                </div>
            @endif
        </div>
    </div>
@else
    <div class="text-center py-5">
        <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 100px; height: 100px;">
            <i class="mdi mdi-file-search-outline fs-1 text-muted"></i>
        </div>
        <h6 class="text-muted fw-bold">No results to display</h6>
        <p class="small text-muted mb-0">Complete the form and click verify to see details.</p>
    </div>
@endif

@once
@push('scripts')
<script>
    function confirmDownload(url, type, price) {
        if (!url || url === '#') {
            Swal.fire('Error', 'Download link is not available.', 'error');
            return;
        }
        Swal.fire({
            title: 'Download Confirmation',
            text: `You will be charged ₦${price.toLocaleString()} for the ${type}.`,
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: '#0db4bd',
            cancelButtonColor: '#ff4d6d',
            confirmButtonText: '<i class="mdi mdi-download me-1"></i> Yes, Download',
            cancelButtonText: 'Cancel',
            customClass: {
                confirmButton: 'btn btn-primary px-4 py-2 rounded-3',
                cancelButton: 'btn btn-danger px-4 py-2 rounded-3 ms-2'
            },
            buttonsStyling: false
        }).then((result) => {
            if (result.isConfirmed) window.location.href = url;
        });
    }
</script>
@endpush
@endonce

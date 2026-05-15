<!-- Wallet Modal -->
<div class="modal fade" id="walletModal" tabindex="-1" aria-labelledby="walletModalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="walletModalLabel">Fund Wallet</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info border-0 rounded-3 small mb-4">
                    <i class="mdi mdi-information-outline me-1"></i>
                    Fund your wallet instantly by depositing into any of the virtual account numbers below.
                </div>
                
                <div class="virtual-account-list">
                    @if (auth()->user()->virtualAccount->isNotEmpty())
                        @foreach (auth()->user()->virtualAccount as $data)
                            <div class="account-item p-3 mb-3 border rounded-3 bg-light">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="bank-logo me-3" style="width: 40px;">
                                        @php
                                            $logoPath = 'assets/images/' . strtolower(str_replace(' ', '', $data->bankName)) . '.png';
                                        @endphp
                                        <img src="{{ asset($logoPath) }}" class="img-fluid rounded" alt="{{ $data->bankName }}">
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0 fw-bold">{{ $data->bankName }}</h6>
                                        <small class="text-muted">{{ $data->accountName }}</small>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center bg-white p-2 rounded border">
                                    <span class="fw-bold text-primary font-monospace fs-5">{{ $data->accountNo }}</span>
                                    <button class="btn btn-sm btn-primary copy-account-number" data-account="{{ $data->accountNo }}">
                                        Copy
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="text-center py-3">
                            <p class="text-muted mb-0">No virtual accounts generated yet.</p>
                        </div>
                    @endif
                </div>

                <div class="text-center mt-4">
                    <p class="text-muted small mb-1">Need help? Funds not reflecting?</p>
                    <a href="{{ route('user.support') }}" class="btn btn-link text-decoration-none fw-bold">Contact Support</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- KYC Modal -->
<div class="modal fade" id="kycModal" tabindex="-1" aria-labelledby="kycModal" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Verify Your Identity</h5>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-4">To activate your account and access all features, please complete your profile details.</p>
                
                <form id="verify" method="POST" action="{{ route('user.kyc.submit') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">First Name</label>
                            <input type="text" name="first_name" class="form-control rounded-3" value="{{ old('first_name', auth()->user()->first_name ?? '') }}" required />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Last Name</label>
                            <input type="text" name="last_name" class="form-control rounded-3" value="{{ old('last_name', auth()->user()->last_name ?? '') }}" required />
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Phone Number</label>
                            <input type="tel" name="phone" class="form-control rounded-3" value="{{ old('phone', auth()->user()->phone_number ?? '') }}" required />
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-bold">BVN (11 Digits)</label>
                            <input type="text" name="bvn" class="form-control rounded-3 font-monospace" maxlength="11" value="{{ old('bvn', auth()->user()->bvn ?? '') }}" required />
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Date of Birth</label>
                            <input type="date" name="dob" class="form-control rounded-3" value="{{ old('dob', auth()->user()->dob ?? '') }}" required />
                        </div>
                    </div>

                    <div class="mt-4 d-grid gap-2">
                        <button type="submit" id="submit" class="btn btn-primary rounded-pill py-2 fw-bold">Verify & Activate</button>
                        <form method="POST" action="{{ route('logout') }}" class="m-0 p-0">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger border-0 w-100 rounded-pill py-2 small">Logout</button>
                        </form>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('.copy-account-number').forEach(button => {
        button.addEventListener('click', function() {
            const acctNo = this.getAttribute('data-account');
            navigator.clipboard.writeText(acctNo);
            const originalText = this.innerText;
            this.innerText = 'Copied!';
            this.classList.replace('btn-primary', 'btn-success');
            setTimeout(() => {
                this.innerText = originalText;
                this.classList.replace('btn-success', 'btn-primary');
            }, 2000);
        });
    });
</script>

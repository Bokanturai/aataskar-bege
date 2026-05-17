@extends('layouts.dashboard')

@section('title', 'Support')

@section('content')
<div class="row">
    <div class="mb-3 mt-1">
        <h4 class="mb-1">Help & Support 🎧</h4>
        <p class="mb-0">Need assistance? We're here to help.</p>
    </div>

    <div class="col-lg-12 grid-margin stretch-card">
        <div class="card p-4 text-center">
            <div class="card-body">
                <i class="mdi mdi-lifebuoy text-primary mb-3" style="font-size: 4rem;"></i>
                <h3 class="mb-4">Contact Our Support Team</h3>
                
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="list-group list-group-flush mb-4 text-start">
                            <div class="list-group-item d-flex align-items-center p-3">
                                <i class="mdi mdi-map-marker text-primary me-3" style="font-size: 1.5rem;"></i>
                                <div>
                                    <h6 class="mb-1">Headquarters</h6>
                                    <p class="mb-0 text-muted">Tudun wada street opposite primary school mafara</p>
                                </div>
                            </div>
                            <div class="list-group-item d-flex align-items-center p-3">
                                <i class="mdi mdi-phone text-primary me-3" style="font-size: 1.5rem;"></i>
                                <div>
                                    <h6 class="mb-1">Phone Support</h6>
                                    <p class="mb-0 text-muted">+234 8030564012</p>
                                </div>
                            </div>
                            <div class="list-group-item d-flex align-items-center p-3">
                                <i class="mdi mdi-email text-primary me-3" style="font-size: 1.5rem;"></i>
                                <div>
                                    <h6 class="mb-1">Email Address</h6>
                                    <p class="mb-0 text-muted">abdulazizabubakartma2030@gmail.com</p>
                                </div>
                            </div>
                        </div>

                        @php
                            $phoneNumber = env('phoneNumber', '2348030564012');
                            $message = urlencode(env('message', 'Hello, I need help.'));
                            $apiUrl = env('API_URL');
                            // Fallback if API_URL is missing or weird
                            $whatsappUrl = $apiUrl ? $apiUrl . "{$phoneNumber}&text={$message}" : "https://wa.me/{$phoneNumber}?text={$message}";
                        @endphp
                        
                        <a href="{{ $whatsappUrl }}" target="_blank" class="btn btn-success btn-lg">
                            <i class="mdi mdi-whatsapp me-2"></i> Chat on WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

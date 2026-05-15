@extends('layouts.auth')

@section('title', 'Forgot Password')

@section('content')
    <div class="content-wrapper d-flex align-items-center auth">
        <div class="row flex-grow">
            <div class="col-lg-4 col-md-6 col-sm-10 mx-auto">
                <div class="auth-form-light text-start p-5 shadow-lg rounded-4 bg-white">
                    <div class="brand-logo text-center mb-4">
                        <img src="{{ asset('assets/images/logo.png') }}" alt="logo" style="max-width: 60px; height: auto;">
                    </div>
                    <div class="text-center mb-4">
                        <h4 class="fw-bold">Forgot Password?</h4>
                        <h6 class="fw-light text-muted">No worries, we'll send you reset instructions.</h6>
                    </div>

                    @include('common.message')

                    <form method="POST" action="{{ route('auth.password.email') }}" class="pt-3">
                        @csrf

                        <!-- Email -->
                        <div class="form-group mb-4">
                            <label for="email" class="form-label">E-Mail Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-transparent border-end-0">
                                    <i class="mdi mdi-email-outline text-primary"></i>
                                </span>
                                <input id="email" type="email"
                                    class="form-control form-control-lg border-start-0 @error('email') is-invalid @enderror" 
                                    name="email" value="{{ old('email') }}" placeholder="Enter your email" 
                                    required autocomplete="email" autofocus>
                            </div>
                            @error('email')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Submit Button -->
                        <div class="my-4 d-grid">
                            <button type="submit" class="btn btn-primary btn-lg fw-bold auth-form-btn">
                                SEND RESET LINK
                            </button>
                        </div>

                        <div class="text-center mt-4 fw-light">
                            Remember your password? <a href="{{ route('auth.login') }}" class="text-primary fw-bold text-decoration-none">Back to Login</a>
                        </div>
                    </form>
                    <p class="text-muted text-center mt-5 small">&copy; {{ date('Y') }} All rights reserved.</p>
                </div>
            </div>
        </div>
    </div>
@endsection

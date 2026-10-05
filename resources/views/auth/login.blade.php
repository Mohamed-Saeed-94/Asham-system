@extends('layouts.app')

@section('title', __('Login'))
@section('auth_subtitle', __('Enter your email & password to login'))

@section('form')
  <form method="POST"
        action="{{ route('login') }}"
        class="row g-3 needs-validation"
        novalidate>
    @csrf

    {{-- Email / Username --}}
    <div class="col-12">
      <label for="email" class="form-label">{{ __('Email address or username') }}</label>
      <div class="input-group has-validation">
        <span class="input-group-text" id="emailPrepend">@</span>
        <input  id="email"
                type="text"
                name="email"
                class="form-control @error('email') is-invalid @enderror"
                value="{{ old('email') }}"
                required
                dir="ltr"
                autocomplete="username"
                autocapitalize="none"
                spellcheck="false"
                autofocus
                aria-describedby="emailPrepend emailHelp">
        @error('email')
          <div class="invalid-feedback d-block" id="emailHelp" aria-live="polite">
            <strong>{{ $message }}</strong>
          </div>
        @else
          <div class="invalid-feedback" id="emailHelp">{{ __('Please enter your email or username.') }}</div>
        @enderror
      </div>
    </div>

    {{-- Password --}}
    <div class="col-12">
      <label for="password" class="form-label">{{ __('Password') }}</label>
      <div class="input-group has-validation">
        <input  id="password"
                type="password"
                name="password"
                class="form-control @error('password') is-invalid @enderror"
                required
                autocomplete="current-password"
                aria-describedby="togglePassword pwdHelp">
        <x-button.action type="button"
                  variant="secondary"
                  :outline="true"
                  id="togglePassword"
                  aria-controls="password"
                  aria-pressed="false"
                  aria-label="{{ __('Show password') }}"
                  title="{{ __('Show password') }}"
                  data-show-label="{{ __('Show password') }}"
                  data-hide-label="{{ __('Hide password') }}"
                  data-password-toggle-target="password">
          <i class="bi bi-eye" aria-hidden="true"></i>
        </x-button.action>
        @error('password')
          <div class="invalid-feedback d-block" id="pwdHelp" aria-live="polite">
            <strong>{{ $message }}</strong>
          </div>
        @else
          <div class="invalid-feedback" id="pwdHelp">{{ __('Password is required.') }}</div>
        @enderror
      </div>
    </div>

    {{-- Remember Me --}}
    <div class="col-12">
      <div class="form-check">
        <input  id="remember"
                type="checkbox"
                name="remember"
                value="1"
                class="form-check-input"
                {{ old('remember') ? 'checked' : '' }}>
        <label class="form-check-label" for="remember">{{ __('Remember Me') }}</label>
      </div>
    </div>

    {{-- Actions --}}
    <div class="col-12 d-flex flex-column gap-2">
      <x-button.action type="submit" variant="primary" :outline="true" :block="true">{{ __('Login') }}</x-button.action>

      <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
        @if (Route::has('password.request'))
          <a class="link-secondary small" href="{{ route('password.request') }}">
            {{ __('Forgot Your Password?') }}
          </a>
        @endif

        {{-- التسجيل الذاتي مغلق؛ إنشاء المستخدمين يتم بواسطة المدير --}}
      </div>
    </div>
  </form>
@endsection

@push('scripts')
  @once('password-toggle-script')
    @include('components.password-toggle-script')
  @endonce
@endpush

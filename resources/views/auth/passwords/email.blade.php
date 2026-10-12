@php
    $title = 'Reset Password';
@endphp

@extends('cdash', [
    'vue' => true,
    'daisyui' => true,
])

@section('main_content')
    <div class="tw-flex tw-flex-row tw-w-full tw-justify-center">
        <div class="tw-flex tw-flex-col tw-w-96 tw-gap-2">
            <form method="POST" action="{{ route('password.email') }}">
                @csrf
                <div class="tw-w-full tw-flex tw-flex-row tw-justify-center">
                    <img src="{{ asset('img/cdash_logo_full.svg?rev=2023-05-31') }}" height="60" alt="CDash logo" style="height: 60px;">
                </div>
                @if (session('status'))
                    <div role="alert" class="tw-alert tw-alert-success" data-test="status-message">
                        {{ session('status') }}
                    </div>
                @endif
                <p class="tw-my-2">
                    Enter the e-mail address of your CDash account and we will e-mail you a link to reset your password.
                </p>
                <label class="tw-form-control tw-w-full">
                    <span class="tw-label tw-label-text">
                        E-Mail Address
                    </span>
                    <input type="email"
                           name="email"
                           value="{{ old('email') }}"
                           class="tw-input tw-input-bordered tw-w-full"
                           data-test="email-input"
                           required
                           autofocus
                    />
                    @if ($errors->has('email'))
                        <span class="tw-label-alt tw-text-error">
                            {{ $errors->first('email') }}
                        </span>
                    @endif
                </label>
                <button class="tw-btn tw-btn-block tw-mt-2" type="submit" data-test="send-link-button">
                    Send Password Reset Link
                </button>
            </form>
        </div>
    </div>
@endsection

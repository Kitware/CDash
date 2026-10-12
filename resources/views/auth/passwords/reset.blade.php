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
            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                <div class="tw-w-full tw-flex tw-flex-row tw-justify-center">
                    <img src="{{ asset('img/cdash_logo_full.svg?rev=2023-05-31') }}" height="60" alt="CDash logo" style="height: 60px;">
                </div>

                <input type="hidden" name="token" value="{{ $token }}">

                <label class="tw-form-control tw-w-full">
                    <span class="tw-label tw-label-text">
                        E-Mail Address
                    </span>
                    <input type="email"
                           name="email"
                           value="{{ $email ?? old('email') }}"
                           class="tw-input tw-input-bordered tw-w-full"
                           data-test="email-input"
                           required
                    />
                    @if ($errors->has('email'))
                        <span class="tw-label-alt tw-text-error">
                            {{ $errors->first('email') }}
                        </span>
                    @endif
                </label>

                <label class="tw-form-control tw-w-full">
                    <span class="tw-label tw-label-text">
                        New Password
                    </span>
                    <input type="password"
                           name="password"
                           class="tw-input tw-input-bordered tw-w-full"
                           data-test="password-input"
                           required
                           autofocus
                    />
                    @if ($errors->has('password'))
                        <span class="tw-label-alt tw-text-error">
                            {{ $errors->first('password') }}
                        </span>
                    @endif
                </label>

                <label class="tw-form-control tw-w-full">
                    <span class="tw-label tw-label-text">
                        Confirm New Password
                    </span>
                    <input type="password"
                           name="password_confirmation"
                           class="tw-input tw-input-bordered tw-w-full"
                           data-test="password-confirmation-input"
                           required
                    />
                </label>

                <button class="tw-btn tw-btn-block tw-mt-2" type="submit" data-test="reset-password-button">
                    Reset Password
                </button>
            </form>
        </div>
    </div>
@endsection

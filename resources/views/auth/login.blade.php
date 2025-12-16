@php
    $oauthCollection = collect(config('services'))->where("oauth","true");
    $has_oauth_login = $oauthCollection->firstWhere('enable', true);

    $has_saml2_login = config('saml2.enabled');
    $saml2_login_text = config('saml2.login_text');

    $login_field = config('cdash.login_field');
    $show_login_form = config('cdash.username_password_authentication_enabled');
    $title = 'Login';
@endphp

@extends('cdash', [
    'vue' => true,
    'daisyui' => true,
])

@section('main_content')
    <div id="message" style="color: green;"></div>
    <div class="tw-flex tw-flex-row tw-w-full tw-justify-center tw-gap-8">
        @if(View::exists('local.login'))
            <div class="tw-max-w-xl">
                @include('local.login')
            </div>
        @endif
        <div class="tw-flex tw-flex-col tw-w-96 tw-gap-2">
            @if ($show_login_form)
                <form method="POST" action="{{ url('/login') }}" name="loginform" id="loginform">
                    @csrf
                    <div class="tw-w-full tw-flex tw-flex-row tw-justify-center">
                        <img src="{{ asset('img/cdash_logo_full.svg?rev=2023-05-31') }}" height="60" alt="CDash logo" style="height: 60px;">
                    </div>
                    <label class="tw-form-control tw-w-full">
                        <span class="tw-label tw-label-text">
                            {{ $login_field }}
                        </span>
                        <input type="text" name="email" value="{{ old('email') }}" class="tw-input tw-input-bordered tw-w-full" />
                        @if ($errors->has('email'))
                            <span class="tw-label-alt tw-text-error">
                                {{ $errors->first('email') }}
                            </span>
                        @endif
                    </label>
                    <label class="tw-form-control tw-w-full">
                        <span class="tw-label">
                            <span class="tw-label-text">
                                Password
                            </span>
                            <a href="{{ url('recoverPassword.php') }}" class="tw-label-text-alt tw-link tw-link-hover">
                                Forgot Password?
                            </a>
                        </span>
                        <input type="password" name="password" class="tw-input tw-input-bordered tw-w-full" />
                        @if ($errors->has('password'))
                            <span class="tw-label-alt tw-text-error">
                                    {{ $errors->first('password') }}
                                </span>
                        @endif
                    </label>
                    <button class="tw-btn tw-btn-block tw-mt-2" type="submit">Sign In</button>
                </form>
            @endif
            @if($show_login_form && ($has_saml2_login || $has_oauth_login))
                <div class="tw-divider">OR</div>
            @endif
            @if($has_saml2_login)
                <form method="POST" action="{{ url('/saml2/login') }}" name="saml2_login_form" id="saml2_login_form">
                    @csrf
                    <button type="submit" class="tw-btn tw-btn-block">
                        {{ $saml2_login_text }}
                    </button>
                </form>
            @endif
            @if($has_oauth_login)
                @foreach($oauthCollection as $provider => $config)
                    @if ($config['enable'])
                        <a class="tw-btn tw-btn-block" href="{{ url("/auth/$provider/redirect") }}">
                            <img src="img/{{ $provider }}_signin.png" alt="Log in with your {{ $provider }} account" style="height:40px"/>
                            {{ $config['display_name']}}
                        </a>
                    @endif
                @endforeach
            @endif
        </div>
    </div>
@endsection

@php
    $collection = collect(config('oauth2'));
    $has_oauth2_login = $collection->firstWhere('enable', true);

    $has_saml2_login = config('saml2.enabled');
    $saml2_login_text = config('saml2.login_text');

    $login_field = config('cdash.login_field');
    $show_login_form = config('auth.username_password_authentication_enabled');

    $title = 'Login';
@endphp

@extends('cdash')

@section('main_content')
    @includeIf('local.login')
    <div id="message" style="color: green;"></div>
    <div class="container-fluid">
    @if ($show_login_form)
        <form method="POST" action="login" name="loginform" id="loginform">
            <input type="hidden" name="_token" id="csrf-token" value="{{ csrf_token() }}" />
            <div class="row table-heading">
                <div class="col-1 ml-2 mt-1 mb-1 text-right">
                    {{ $login_field }}:
                </div>
                <div class="col-auto mt-1 mb-1">
                    <input class="textbox" name="email" size="40" value="{{ old('email') }}">
                    @if ($errors->has('email'))
                        <div>
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('email') }}</strong>
                        </span>
                        </div>
                    @endif
                </div>
            </div>
			<div class="row table-heading">
				<div class="col-1 ml-2 mt-1 mb-1 text-right">
					Password:
				</div>
				<div class="col-auto mt-1 mb-1">
					<input class="textbox" type="password" name="password" size="20" autocomplete="off">
					<input class="textbox" type="checkbox" name="remember" id="remember" {{old('remember') ? 'checked' : ''}}> Remember Me
					@if ($errors->has('password'))
						<div>
							<span class="invalid-feedback" role="alert">
								<strong>{{ $errors->first('password') }}</strong>
							</span>
						</div>
					@endif
				</div>
			</div>
			<div class="row table-heading pb-2">
				<div class="col-1 ml-2 mt-1 mb-1">
				</div>
				<div class="col-1 mt-1 mb-1 mr-auto">
					<input type="submit" value="Login >>" name="sent" class="textbox">
				</div>
				<div class="col-auto text-right">
					@if (Route::has('password.request'))
						<a href="recoverPassword.php">Forgot your password?</a>
					@endif
				</div>
			</div>
        </form>
    @endif  <!-- $show_login_form -->

    @if ($has_oauth2_login || $has_saml2_login)
    <div class="row table-heading border-top pt-2">
        <div class="col-auto offset-1">
            <p>
                Sign in with:
                @if ($has_saml2_login)
                    <form method="POST" action="/saml2/login" name="saml2_login_form" id="saml2_login_form">
                        <input type="hidden" name="_token" id="csrf-token" value="{{ csrf_token() }}" />
                        <button type="submit" class="btn btn-primary">
                            {{ $saml2_login_text }}
                        </button>
                    </form>
                @endif
                @foreach($collection as $key => $config)
                    @if ($config['enable'])
                        <a href="/oauth/{{ $key  }}"><img class="paddr" src="img/{{ $key }}_signin.png" title="Log in with your {{ $key }} account"/></a>
                    @endif
                @endforeach
            </p>
        </div>
    </div>
    @endif
@endsection

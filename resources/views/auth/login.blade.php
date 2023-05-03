@php
    $collection = collect(config('oauth2'));
    $hasEnabled = $collection->firstWhere('enable', true);
    $login_field = config('cdash.login_field');
    $show_login_form = config('auth.username_password_authentication_enabled');
    $title = 'Login';
@endphp

@extends('cdash')

@section('main_content')
    @includeIf('local.login')
    <div id="message" style="color: green;"></div>
    <form method="post" action="login" name="loginform" id="loginform">
        <input type="hidden" name="_token" id="csrf-token" value="{{ csrf_token() }}" />
        <table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb">
            <tbody>
            @if ($show_login_form)
                <tr class="table-heading">
                    <td width="10%" class="nob">
                        <div align="right"> {{ $login_field }}:</div>
                    </td>
                    <td width="70%" class="nob">
                        <input class="textbox" name="email" size="40" value="{{ old('email') }}">
                        @if ($errors->has('email'))
                            <div>
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $errors->first('email') }}</strong>
                            </span>
                            </div>
                        @endif
                    </td>
                    <td width="20%" align="right" class="nob"></td>
                </tr>
                <tr class="table-heading">
                    <td width="10%" class="nob">
                        <div align="right">Password:</div>
                    </td>
                    <td width="70%" class="nob">
                        <input class="textbox" type="password" name="password" size="20" autocomplete="off">
                        <input class="textbox" type="checkbox" name="remember" id="remember" {{old('remember') ? 'checked' : ''}}> Remember Me
                        @if ($errors->has('password'))
                            <div>
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $errors->first('password') }}</strong>
                                </span>
                            </div>
                        @endif
                    </td>
                    <td width="20%" align="right" class="nob"></td>
                </tr>
                <tr class="table-heading">
                    <td width="10%" class="nob"></td>
                    <td width="70%" class="nob">
                        <input type="submit" value="Login >>" name="sent" class="textbox">
                    </td>
                    <td width="20%" align="right" class="nob">
                        @if (Route::has('password.request'))
                            <a href="recoverPassword.php">Forgot your password?</a>
                        @endif
                    </td>
                </tr>
            @endif  <!-- $show_login_form -->
            @if ($hasEnabled)
                <tr class="table-heading">
                    <td width="10%" class="nob"></td>
                    <td width="70%" class="nob">
                        <hr>
                        <p>
                            Sign in with:
                            @foreach($collection as $key => $config)
                                @if ($config['enable'])
                                    <a href="/oauth/{{ $key  }}"><img class="paddr" src="img/{{ $key }}_signin.png"
                                                                      title="Log in with your {{ $key }} account"/></a>
                                @endif
                            @endforeach
                        </p>
                    </td>
                    <td width="20%" class="nob"></td>
                </tr>
            @endif
            </tbody>
        </table>
    </form>
@endsection

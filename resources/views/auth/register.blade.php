@extends('cdash')
@section('header_script')
    <script language="javascript" type="text/javascript">
        function doSubmit() {
            document.getElementById('url').value = 'catchbot';
        }
    </script>
@endsection

@section('main_content')
    <form method="post" action="{{ route('register') }}" name="regform" onsubmit="doSubmit();" id="regform">
        @csrf
        <input type="hidden" value="" name="url"/>
        @if ($errors->has('url'))
            <div>
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $errors->first('url') }}</strong>
                </span>
            </div>
        @endif
        <table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb">
            <tbody>
            <tr class="treven">
                <td width="20%" height="2" class="nob">
                    <div align="right">First Name:</div>
                </td>
                <td width="80%" height="2" class="nob">
                    <input class="form-control{{ $errors->has('fname')? ' is-invalid' : ''}}"
                           name="fname"
                           size="20"
                           value="{{ old('fname', $fname) }}"
                           required
                           autofocus
                    />
                    @if ($errors->has('fname'))
                        <div>
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('fname') }}</strong>
                        </span>
                        </div>
                    @endif
                </td>
            </tr>
            <tr class="trodd">
                <td width="20%" height="2" class="nob">
                    <div align="right"> Last Name:</div>
                </td>
                <td width="80%" height="2" class="nob">
                    <input class="form-control{{ $errors->has('lname') ? ' is-invalid' : ''}}"
                           name="lname"
                           size="20"
                           value="{{ old('lname', $lname) }}"
                           required
                    />
                    @if ($errors->has('lname'))
                        <div>
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('lname') }}</strong>
                        </span>
                        </div>
                    @endif
                </td>
            </tr>
            <tr class="treven">
                <td width="20%" height="2" class="nob">
                    <div align="right"> Email:</div>
                </td>
                <td width="80%" height="2" class="nob">
                    <input class="form-control{{ $errors->has('email') ? ' is-invalid' : ''}}"
                           name="email"
                           size="20"
                           value="{{ old('email', $email) }}"
                           required
                    />
                    @if ($errors->has('email'))
                        <div>
                            <span class="invalid-feedback" role="alert">
                            <strong>{{$errors->first('email')}}</strong>
                        </span>
                        </div>

                    @endif
                </td>
            </tr>
            <tr class="trodd">
                <td width="20%" height="2" class="nob">
                    <div align="right">Password:</div>
                </td>
                <td width="80%" height="2" class="nob">
                    <input class="form-control{{ $errors->has('password') ? ' is-invalid' : ''}}"
                           type="password"
                           name="password"
                           size="20"
                           autocomplete="off"
                           required
                    />
                    @if ($errors->has('password'))
                        <div>
                            <span class="invalid-feedback" role="alert">
                            <strong>{{$errors->first('password')}}</strong>
                        </span>
                        </div>

                    @endif
                </td>
            </tr>
            <tr class="treven">
                <td width="20%" height="2" class="nob">
                    <div align="right">Confirm Password:</div>
                </td>
                <td width="80%" height="2" class="nob">
                    <input class="form-control"
                           type="password"
                           name="password_confirmation"
                           size="20"
                           autocomplete="off"
                    />
                </td>
            </tr>
            <tr class="trodd">
                <td width="20%" height="2" class="nob">
                    <div align="right"> Institution:</div>
                </td>
                <td width="80%" height="2" class="nob">
                    <input class="form-control"
                           name="institution"
                           size="20"
                           value="{{ old('institution') }}"
                    />
                </td>
            </tr>
            <tr>
                <td width="20%" class="nob"></td>
                <td width="80%" class="nob">
                    <input type="submit"
                           value="Register"
                           name="sent"
                           class="textbox"
                    />
                    <input id="url"
                           class="textbox"
                           type="hidden"
                           name="url"
                           size="20"
                    />
                </td>
            </tr>
            </tbody>
        </table>
    </form>
@endsection

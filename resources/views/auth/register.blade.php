@php
    $title = 'Register';
@endphp

@extends('cdash', [
    'vue' => true,
    'daisyui' => true,
])

@section('header_script')
    <script language="javascript" type="text/javascript">
        function doSubmit() {
            document.getElementById('url').value = 'catchbot';
        }
    </script>
@endsection

@section('main_content')
    <div class="tw-flex tw-flex-row tw-w-full tw-justify-center tw-gap-8">
        <div class="tw-flex tw-flex-col tw-w-96 tw-gap-2">
            <form method="post" action="{{ route('register') }}" name="regform" onsubmit="doSubmit();" id="regform">
                @csrf
                <div class="tw-w-full tw-flex tw-flex-row tw-justify-center">
                    <img src="{{ asset('img/cdash_logo_full.svg?rev=2023-05-31') }}" height="60" alt="CDash logo" style="height: 60px;">
                </div>

                <input type="hidden" value="" name="url"/>
                @if ($errors->has('url'))
                    <div class="tw-text-error">
                        <strong>{{ $errors->first('url') }}</strong>
                    </div>
                @endif

                <label class="tw-form-control tw-w-full">
                    <span class="tw-label tw-label-text">
                        First Name
                    </span>
                    <input class="tw-input tw-input-bordered tw-w-full"
                           name="fname"
                           value="{{ old('fname', $fname) }}"
                           required
                           autofocus
                    />
                    @if ($errors->has('fname'))
                        <span class="tw-label-alt tw-text-error">
                            {{ $errors->first('fname') }}
                        </span>
                    @endif
                </label>

                <label class="tw-form-control tw-w-full">
                    <span class="tw-label tw-label-text">
                        Last Name
                    </span>
                    <input class="tw-input tw-input-bordered tw-w-full"
                           name="lname"
                           value="{{ old('lname', $lname) }}"
                           required
                    />
                    @if ($errors->has('lname'))
                        <span class="tw-label-alt tw-text-error">
                            {{ $errors->first('lname') }}
                        </span>
                    @endif
                </label>

                <label class="tw-form-control tw-w-full">
                    <span class="tw-label tw-label-text">
                        Email
                    </span>
                    <input class="tw-input tw-input-bordered tw-w-full"
                           name="email"
                           value="{{ old('email', $email) }}"
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
                        Password
                    </span>
                    <input type="password"
                           name="password"
                           class="tw-input tw-input-bordered tw-w-full"
                           autocomplete="off"
                           required
                    />
                    @if ($errors->has('password'))
                        <span class="tw-label-alt tw-text-error">
                            {{ $errors->first('password') }}
                        </span>
                    @endif
                </label>

                <label class="tw-form-control tw-w-full">
                    <span class="tw-label tw-label-text">
                        Confirm Password
                    </span>
                    <input type="password"
                           name="password_confirmation"
                           class="tw-input tw-input-bordered tw-w-full"
                           autocomplete="off"
                    />
                </label>

                <label class="tw-form-control tw-w-full">
                    <span class="tw-label tw-label-text">
                        Institution
                    </span>
                    <input class="tw-input tw-input-bordered tw-w-full"
                           name="institution"
                           value="{{ old('institution') }}"
                    />
                </label>

                <button class="tw-btn tw-btn-block tw-mt-4" type="submit">Register</button>

                <input id="url"
                       type="hidden"
                       name="url"
                />
            </form>
        </div>
    </div>
@endsection

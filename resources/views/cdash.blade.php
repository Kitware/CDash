@php
    use App\Http\Controllers\AbstractController;

    $js_version = AbstractController::getJsVersion();
    $cdash_version = AbstractController::getCDashVersion();
@endphp

<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    @if(isset($angular) && $angular === true)
        ng-app="CDash"
        ng-controller="HeadController"
        ng-strict-di
        ng-cloak
    @endif
>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <meta name="robots" content="noindex,nofollow"/>
    <meta name="csrf-token" content="{{ csrf_token() }}"/>
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}"/>

    {{-- Framework-specific details --}}
    @if(isset($angular) && $angular === true)
        <link rel="stylesheet" type="text/css" href="{{ mix('build/css/3rdparty.css') }}"/>
        <link rel="stylesheet" type="text/css" ng-href="{{ asset("build/css") }}/@{{cssfile}}_{{  $js_version }}.css"/>
        <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}"/>
        <script src="{{ mix("js/3rdparty.min.js") }}"></script>
        <script src="{{ mix("js/legacy_1stparty.min.js") }}"></script>
    @elseif(isset($vue) && $vue === true)
        <link rel="stylesheet" type="text/css" href="{{ asset(get_css_file()) }}"/>
        @if(isset($daisyui) && $daisyui === true)
            <link rel="stylesheet" type="text/css" href="{{ asset('laravel/css/app.css') }}"/>
        @else
            <link rel="stylesheet" type="text/css" href="{{ asset(mix('build/css/3rdparty.css')) }}"/>
            <link type="text/css" rel="stylesheet" href="{{ asset('css/jquery.dataTables.css') }}"/>
            <link rel="stylesheet" type="text/css" href="{{ asset('css/vue_common.css') }}"/>
            <link rel="stylesheet" type="text/css" href="{{ asset('css/bootstrap.min.css') }}"/>
        @endif
        <script src="{{ mix('js/3rdparty.min.js') }}" type="text/javascript" defer></script>
        <script src="{{ mix('laravel/js/app.js') }}" type="text/javascript" defer></script>
    @else
        <link rel="stylesheet" type="text/css" href="{{ asset(mix('build/css/3rdparty.css')) }}"/>
        <link rel="stylesheet" type="text/css" href="{{ asset('css/jquery.dataTables.css') }}"/>
        <link rel="stylesheet" type="text/css" href="{{ asset('css/cdash.css') }}"/>
        <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}"/>
        <script src="{{ mix("js/3rdparty.min.js") }}"></script>
        <script src="{{ mix("js/legacy_1stparty.min.js") }}"></script>
        <script src="{{ asset('js/jquery.tablesorter.js') }}" type="text/javascript" charset="utf-8"></script>
        <script src="{{ asset('js/jquery.dataTables.min.js') }}" type="text/javascript" charset="utf-8"></script>
        <script src="{{ asset('js/jquery.metadata.js') }}" type="text/javascript" charset="utf-8"></script>
    @endif

    @yield('header_script')

    <title
        @if(isset($angular) && $angular === true)
            ng-bind="::title"
        @endif
    >@if(isset($title)) {{ $title }} @else CDash @endif</title>
</head>
<body
    @if(isset($angular) && $angular === true)
        ng-controller="{{ $angular_controller }}"
    @endif
>
{{-- This is a horrible hack which allows AngularJS to show the login page when prompted by the API --}}
@if(isset($angular) && $angular === true)
    <div ng-if="cdash.requirelogin == 1" ng-include="'login'"></div>
    <div ng-if="cdash.requirelogin != 1" id="app">
@else
    <div
        id="app"
        data-app-url="{{ url('/') }}"
    >
@endif
        @section('header')
            @include('components.header')
        @show

        <div id="main_content"
            @if(isset($angular) && $angular === true)
                ng-if="!loading"
            @endif
        >

            @if(isset($angular) && $angular === true)
                <div ng-if="cdash.error">@{{cdash.error}}</div>
            @endif

            @hasSection('main_content')
                @yield('main_content')
            @elseif(isset($xsl) && $xsl === true)
                {!! $xsl_content !!}
            @endif
        </div>

        @section('footer')
            @include('components.footer')
        @show
    </div>
</body>
</html>

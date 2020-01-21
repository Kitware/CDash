<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <title>CDash - {{ $title }}</title>

    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="robots" content="noindex,nofollow" />
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" />

    <!--[if IE]>
    <script language="javascript" type="text/javascript" src="{{ asset('js/excanvas.js') }}">
    </script>
    <![endif]-->

    <link rel="stylesheet" type="text/css" href="{{ asset('css/jquery-ui-1.10.4.css') }}" />
    <link rel="stylesheet" type="text/css" href="{{ asset('css/jqModal.css') }}" />
    <link type="text/css" rel="stylesheet" href="{{ asset('css/jquery.qtip.min.css') }}" />
    <link type="text/css" rel="stylesheet" media="all" href="{{ asset('css/jquery.dataTables.css') }}" />
    <link rel="StyleSheet" type="text/css" href="{{ asset('css/cdash.css') }}" />
    <link rel="StyleSheet" type="text/css" href="{{ asset('css/vue_common.css') }}" />
    @yield('header_script')
</head>
<body>

<div id="app">
    @yield('page-header')
    @yield('content')
</div>

@yield('post_content_script')
</body>
<script src="{{ asset('js/3rdparty.js') }}" type="text/javascript"></script>
<script src="{{ asset('laravel/js/app.js') }}" type="text/javascript"></script>
</html>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <title>CDash - {{ $title }}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="robots" content="noindex,nofollow" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <link rel="stylesheet" type="text/css" href="/css/nv.d3.css"/>
    <link rel="stylesheet" href="/css/bootstrap.min.css"/>
@yield('header_script')
</head>
<body>
@yield('content')
@yield('post_content_script')
</body>
</html>

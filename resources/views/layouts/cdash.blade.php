<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" ng-app="CDash">
<head ng-controller="HeadController">
@if(isset($xsl) && $xsl === false)
    @include('cdash.html-head-angular')
@else
    @include('cdash.html-head-std')
@endif
@yield('header_script')
</head>
<body{!! empty($controller) ? '' : " ng-controller=\"{$controller}\"" !!}>
@yield('content')
@yield('post_content_script')
</body>

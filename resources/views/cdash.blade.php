@extends('layouts.cdash')

@section('html-head')
    @if($xsl)
        @include('cdash.html-head-std')
    @else
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <meta name="robots" content="noindex,nofollow" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <link rel="shortcut icon" href="favicon.ico" />
        <title ng-bind="::title">CDash</title>
        <link rel="stylesheet" type="text/css" ng-href="build/css/cdash_{{ $js_version }}.css" />
        <link rel="stylesheet" type="text/css" href="css/nv.d3.css"/>
        <link rel="stylesheet" href="css/bootstrap.min.css"/>
        <script src="js/CDash_{{ $js_version  }}.min.js"></script>
        <link rel="stylesheet" type="text/css" href="css/jqModal.css" />
    @endif
@endsection

@section('content')
{!! $content !!}
@endsection

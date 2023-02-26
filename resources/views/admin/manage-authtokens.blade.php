@php
    use CDash\Config;

    $version = Config::getVersion();
@endphp

@extends('master')

@section('page-header')
    @include('build.page-header')
@endsection

@section('content')
    <manage-auth-tokens></manage-auth-tokens>
    @include('build.page-footer')
@endsection

@section('post_content_script')
<script></script>
@endsection

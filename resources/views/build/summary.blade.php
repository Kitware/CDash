@extends('master')

@section('page-header')
@include('cdash.build-page-header')
@endsection

@section('content')
<build-summary></build-summary>
@include('build.page-footer')
@endsection

@section('post_content_script')
<script></script>
@endsection

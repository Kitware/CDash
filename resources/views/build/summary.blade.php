
@extends('master')

@section('page-header')
@include('cdash.build-page-header')
@endsection

@section('content')
<build-summary></build-summary>
@include('cdash.footer')
@endsection

@section('post_content_script')
<script></script>
@endsection

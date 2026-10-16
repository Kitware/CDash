@extends('cdash', [
    'vue' => true,
])

@section('main_content')
    <div
        data-vue-component="{{ $componentName }}"
        data-vue-props="{{ json_encode((object) $props, JSON_THROW_ON_ERROR) }}"
    ></div>
@endsection

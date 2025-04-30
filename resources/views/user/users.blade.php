@extends('cdash', [
    'vue' => true,
    'daisyui' => true,
])

@section('main_content')
    <users-page :can-invite-users="@js(auth()->user()?->can('createInvitation', \App\Models\GlobalInvitation::class) ?? false)"></users-page>
@endsection

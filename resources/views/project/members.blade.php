@extends('cdash', [
    'vue' => true,
    'daisyui' => true,
])

@section('main_content')
    <project-members-page
        :project-id="@js($project->Id)"
        :user-id="@js(auth()->user()?->id)"
        :can-edit-users="@js(auth()->user()?->can('inviteUser', \App\Models\Project::findOrFail((int) $project->Id)) ?? false)"
    ></project-members-page>
@endsection

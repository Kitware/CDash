@extends('cdash', [
    'title' => 'Remove Builds'
])

@section('main_content')
    @if(strlen($alert) > 0)
        <b>{{ $alert }}</b>
        <br>
        <br>
    @endif

    Project:
    <select onchange="location = '{{ url('removeBuilds.php') }}?projectid='+this.options[this.selectedIndex].value;" name="projectSelection">
        <option value="0">
            Choose...
        </option>

        @foreach($available_projects as $available_project)
            <option
                value="{{ $available_project->Id }}"
                @if($available_project->Id === $selected_projectid) selected @endif
            >
                {{ $available_project->Name }}
            </option>
        @endforeach
    </select>
    <br>
    <br>

    Remove builds in this date range.
    <br>
    <br>

    <form name="form1" enctype="multipart/form-data" method="post" action="">
        From:
        <input
            name="monthFrom"
            type="text"
            size="2"
            value="{{ $monthFrom }}"
        >
        <input
            name="dayFrom"
            type="text"
            size="2"
            value="{{ $dayFrom }}"
        >
        <input
            name="yearFrom"
            type="text"
            size="4"
            value="{{ $yearFrom }}"
        >
        To:
        <input
            name="monthTo"
            type="text"
            size="2"
            value="{{ $monthTo }}"
        >
        <input
            name="dayTo"
            type="text"
            size="2"
            value="{{ $dayTo }}"
        >
        <input
            name="yearTo"
            type="text"
            size="4"
            value="{{ $yearTo }}"
        >
        <br>
        <br>
        <input type="submit" name="Submit" value="Remove Builds >>">
    </form>
@endsection

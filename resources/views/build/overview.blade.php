@extends('cdash', [
    'title' => 'Build Overview'
])

@section('main_content')
    <h3>
        Build summary for <u>{{ $project->Name }}</u> starting at {{ $startdate }}.
    </h3>

    {{-- Group Selection --}}
    <form name="form1" method="post" action="">
        <b>Group: </b>
        <select onchange="document.form1.submit()" name="groupSelection">
            <option value="0">All</option>
            @foreach($project->GetBuildGroups() as $group)
                <option
                    value="{{ $group->GetId() }}"
                    @if($group->GetId() === $selected_group) selected @endif
                >
                    {{ $group->GetName() }}
                </option>
            @endforeach
        </select>
    </form>
    <br>

    {{-- Warnings and Errors --}}
    @forelse($sourcefiles as $sourcefile)
        <div class="title-divider">{{ $sourcefile['name'] }}</div>

       {{-- Errors --}}
        @if(count($sourcefile['errors']) > 0)
            <h3>Errors:</h3>
            @foreach($sourcefile['errors'] as $error)
                <b>{{ $error['buildname'] }}:</b>
                <pre style="white-space: pre-wrap;">{{ $error['text'] }}</pre>
            @endforeach
        @endif

        {{-- Warnings --}}
        @if(count($sourcefile['warnings']) > 0)
            <h3>Warnings:</h3>
            @foreach($sourcefile['warnings'] as $warning)
                <b>{{ $warning['buildname'] }}:</b>
                <pre style="white-space: pre-wrap;">{{ $warning['text'] }}</pre>
            @endforeach
        @endif
        <br>
    @empty
        No warnings or errors today!
    @endforelse
@endsection

@extends('cdash', [
    'title' => 'Site Statistics'
])

@section('main_content')
    <table id="siteStatisticsTable" border="0" cellspacing="0" cellpadding="3" class="tabb striped">
        <thead>
            <tr class="table-heading1">
                <th id="sort_0">Site Name</th>
                <th id="sort_1" class="nob">Busy time</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sites as $site)
                <tr>
                    <td>
                        <b>
                            <a href="{{ url('/sites/' . $site->siteid) }}">
                                {{ $site->sitename }}
                            </a>
                        </b>
                    </td>
                    <td>
                        {{ $site->busytime }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection

@extends('cdash', [
    'title' => 'Coverage for ' . $coverage_file->FullPath,
])

@section('main_content')
    <table border="0">
        <tr>
            <td align="left">
                <b>Site:</b> {{ $build->GetSite()->name }}
            </td>
        </tr>
        <tr>
            <td align="left">
                <b>Build Name:</b> {{ $build->Name }}
            </td>
        </tr>
        <tr>
            <td align="left">
                <b>Coverage File:</b> <tt>{{ $coverage_file->FullPath }}</tt>
            </td>
        </tr>
    </table>
    <hr/>

    <pre>{!! $log !!}</pre>
@endsection

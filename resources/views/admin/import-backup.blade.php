@extends('cdash', [
    'title' => 'Import Backup'
])

@section('main_content')
    @if($alert !== '')
        <b>{{ $alert  }}</b>
        <br/>
        <br/>
    @endif

    <form name="form1" method="post" action="">
        This page allows you to import xml files in the backup directory for this installation of CDash.<br/>
        <br/>
        <p>
            <input type="submit" name="Submit" value="Import Backups"/>
            matching
            <input type="text" name="filemask" size="100" value="*.xml"/>
        </p>
    </form>
@endsection

@extends('cdash', [
    'title' => 'Manage Backup'
])

@section('main_content')
    <table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb">
        <tbody>
        <tr class="table-heading1"><td id="nob"><h3>Import</h3></td></tr>
        <tr class="treven"><td id="nob"><a href="import.php">[Import Dart1 Files]</a></td></tr>
        <tr class="trodd"><td id="nob"><a href="importBackup.php">[Import from current backup directory]</a></td></tr>
        <tr class="treven"><td id="nob"><a href="removeBuilds.php">[Remove builds]</a></td></tr>
        </tbody>
    </table>
@endsection

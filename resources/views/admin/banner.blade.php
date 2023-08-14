@extends('cdash', [
    'title' => 'Manage Banner'
])

@section('main_content')
    <table width="100%"  border="0">
        <tr>
            <td width="10%">
                <div align="right">
                    <strong>Project:</strong>
                </div>
            </td>
            <td width="90%" >
                <form
                    name="form1"
                    method="post"
                    action="{{ url('manageBanner.php') }}?projectid={{ $project->Id }}"
                >
                    <select
                        onchange="location = '{{ url('manageBanner.php') }}?projectid='+this.options[this.selectedIndex].value;"
                        name="projectSelection"
                    >
                        @foreach($available_projects as $available_project)
                            <option
                                value="{{ $available_project->Id }}"
                                @if($available_project->Id === (int) $banner->projectid) selected @endif
                            >
                                {{ $available_project->Name }}
                            </option>
                        @endforeach
                    </select>
                </form>
            </td>
        </tr>
    </table>

    <form
        name="formnewgroup"
        method="post"
        action="{{ url('manageBanner.php') }}?projectid={{ $project->Id }}"
    >
        <table width="100%"  border="0">
            <tr>
                <td>
                    <div align="right"></div>
                </td>
                <td bgcolor="#DDDDDD">
                    <strong>Banner Message</strong>
                </td>
            </tr>
            <tr>
                <td width="10%"></td>
                <td width="90%">
                    <textarea name="message" cols="100" rows="3">{{ $banner->text }}</textarea>
                </td>
            </tr>

            <tr>
                <td>
                    <div align="right"></div>
                </td>
                <td>
                    <input type="submit" name="updateMessage" value="Update Message"/>
                </td>
            </tr>
        </table>
    </form>
@endsection

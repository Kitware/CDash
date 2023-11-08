@extends('cdash', [
    'title' => 'View Files',
])

@section('main_content')
		<b>Site:</b> {{ $build->GetSite()->name }}<br />
		<b>Build name:</b> <a href="{{ $build->GetBuildSummaryUrl() }}">{{ $build->Name }}</a><br />
    <b>Build start time:</b> {{ $build->StartTime }}<br />

		<h3>URLs or Files submitted with this build</h3>

		@if(count($urls) > 0)
				<table id="filesTable" class="tabb">
						<thead class="table-heading1">
								<tr>
										<th id="sort_0">URL</th>
								</tr>
						</thead>
            @foreach($urls as $url)
                <tr>
                    <td>
                        <a href="{{ $url['filename'] }}">{{ $url['filename'] }}</a>
                    </td>
                </tr>
            @endforeach
				</table>
				<br/>
    @endif

		@if(count($files) > 0)
        <table id="filesTable" class="tabb">
            <thead class="table-heading1">
                <tr>
                    <th id="sort_0">File</th>
                    <th id="sort_1">Size</th>
                    <th id="sort_2">SHA-1</th>
                </tr>
            </thead>
            @foreach($files as $file)
                <tr>
                    <td>
                        <a href="{{ $file['href'] }}">
                            <img src="{{ url('/img/package.png') }}" alt="Files" border="0"/> {{ $file['filename'] }}
                        </a>
                    </td>
                    <td>
                        <span style="display:none">{{ $file['filesize'] }}</span>
                        {{ $file['filesizedisplay'] }}
                    </td>
                    <td>
                        {{ $file['sha1sum'] }}
                    </td>
                </tr>
            @endforeach
        </table>
    @endif
@endsection

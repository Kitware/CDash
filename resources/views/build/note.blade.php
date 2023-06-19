@foreach ($notes as $note)
    @switch (intval($note->status))
        @case(0)
            <b>[note] </b>
            @break
        @case(1)
            <b>[fix in progress] </b>
            @break
        @case(2)
            <b>[fixed] </b>
            @break
    @endswitch
    by <b> {{ htmlspecialchars($note->user->getFullNameAttribute()) }}</b>
    ({{ date('H:i:s T', strtotime($note->timestamp . ' UTC')) }})
    <pre> {{ substr($note->note, 0, 100) }}</pre>
@endforeach

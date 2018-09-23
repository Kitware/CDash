<?php use CDash\Model\BuildError; use CDash\Model\BuildFailure;?>
@foreach($items as $warnings)
@if (is_a($warnings, BuildFailure::class))
{{ $warnings->SourceFile }} ({!! $warnings->GetUrlForSelf() !!})
{!! mb_substr(trim($warnings->StdError), 0, $maxChars) !!}
@else
    @if(strlen($warnings->SourceFile) > 0)
{{ $warnings->SourceFile }} line {{ $warnings->SourceLine }} ({{ $warnings->GetUrlForSelf() }}
{!! mb_substr($warnings->Text, 0, $maxChars) !!}
{!! mb_substr($warnings->PostContext, 0, $maxChars) !!}
    @else
{!! mb_substr($warnings->Text, 0, $maxChars) !!}
{!! mb_substr($warnings->PostContext, 0, $maxChars) !!}
    @endif
@endif
@endforeach

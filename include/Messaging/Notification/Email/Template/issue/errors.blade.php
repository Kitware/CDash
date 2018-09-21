<?php use CDash\Model\BuildError; use CDash\Model\BuildFailure;?>
@if (is_a($warnings, BuildFailure::class))
{{ $warnings->SourceFile }} ({{ $warnings->GetUrlForSelf() }}
{!! $warnings->StdOutput !!}
{!! $warnings->StdErr !!}
@else
    @if(strlen($warnings->SourceFile) > 0)
{{ $warnings->SourceFile }} line {{ $warnings->SourceLine }} ({{ $warnings->GetUrlForSelf() }}
{!! $warnings->Text !!}
{!! $warnings->PostContext !!}
    @else
{!! $warnings->Text !!}
{!! $warnings->PostContext !!}
    @endif
@endif

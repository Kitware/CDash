<?php use CDash\Model\BuildError; use CDash\Model\BuildFailure;?>
@foreach($items as $errors)
@if (is_a($errors, BuildFailure::class))
{{ $errors->SourceFile }} ({!! $errors->GetUrlForSelf() !!})
{!! mb_substr(trim($errors->StdError), 0, $maxChars) !!}
@else
    @if(strlen($errors->SourceFile) > 0)
{{ $errors->SourceFile }} line {{ $errors->SourceLine }} ({{ $errors->GetUrlForSelf() }}
{!! mb_substr($errors->Text, 0, $maxChars) !!}
{!! mb_substr($errors->PostContext, 0, $maxChars) !!}
    @else
{!! mb_substr($errors->Text, 0, $maxChars) !!}
{!! mb_substr($errors->PostContext, 0, $maxChars) !!}
    @endif
@endif
@endforeach

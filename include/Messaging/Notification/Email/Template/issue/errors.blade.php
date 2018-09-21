<?php use CDash\Model\BuildError; use CDash\Model\BuildFailure;?>
@if (is_a($errors, BuildFailure::class))
{{ $errors->SourceFile }} ({!! $errors->GetUrlForSelf() !!})
{!! $errors->StdOutput !!}
{!! $errors->StdErr !!}
@else
    @if(strlen($errors->SourceFile) > 0)
{{ $errors->SourceFile }} line {{ $errors->SourceLine }} ({!! $errors->GetUrlForSelf() !!})
{!! $errors->Text !!}
{!! $errors->PostContext !!}
    @else
{!! $errors->Text !!}
{!! $errors->PostContext !!}
    @endif
@endif

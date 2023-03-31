@php
    use App\Http\Controllers\AbstractController;

    $js_version = AbstractController::getJsVersion();
    $xsl = false;
    $controller = 'ViewUpdateController';
    $content = file_get_contents(public_path() . '/build/views/viewUpdate.html');
@endphp

@extends('cdash')

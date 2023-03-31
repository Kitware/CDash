@php
    use App\Http\Controllers\AbstractController;

    $js_version = AbstractController::getJsVersion();
    $xsl = false;
    $controller = 'UserController';
    $content = file_get_contents(public_path() . '/build/views/user.html');
@endphp

@extends('cdash')

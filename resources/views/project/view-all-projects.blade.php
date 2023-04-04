@php
    use App\Http\Controllers\AbstractController;

    $js_version = AbstractController::getJsVersion();
    $xsl = false;
    $controller = 'ViewProjectsController';
    $content = file_get_contents(public_path() . '/build/views/viewProjects.html');
@endphp

@extends('cdash')

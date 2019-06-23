<?php

namespace App\Http\Controllers;

use CDash\Model\Image;
use Illuminate\Http\Request;

class ImageController extends Controller
{
    public function image(Image $image) {
        return response()->stream(function () use ($image) {
            echo $image->Data;
        }, 200, ['Content-type' => $image->Extension]);
    }
}

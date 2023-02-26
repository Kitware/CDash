<?php

namespace App\Http\Controllers;

use CDash\Model\Image;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImageController extends AbstractController
{
    public function image(Image $image): StreamedResponse
    {
        return response()->stream(function () use ($image) {
            echo $image->Data;
        }, 200, ['Content-type' => $image->Extension]);
    }
}

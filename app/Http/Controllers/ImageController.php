<?php

namespace App\Http\Controllers;

use CDash\Model\Image;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ImageController extends AbstractController
{
    /**
     * @throws HttpException
     */
    public function image(Image $image): StreamedResponse
    {
        if (Gate::denies('view-image', $image)) {
            abort(404);
        }

        return response()->stream(function () use ($image) {
            echo $image->Data;
        }, 200, ['Content-type' => $image->Extension]);
    }
}

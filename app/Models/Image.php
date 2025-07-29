<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property resource $img
 * @property string $extension
 * @property string $checksum
 *
 * @mixin Builder<Image>
 */
class Image extends Model
{
    protected $table = 'image';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'img',
        'extension',
        'checksum',
    ];

    protected $casts = [
        'id' => 'integer',
    ];
}

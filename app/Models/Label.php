<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $text
 *
 * @mixin Builder<Label>
 */
class Label extends Model
{
    protected $table = 'label';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'text',
    ];

    protected $casts = [
        'id' => 'integer',
    ];
}

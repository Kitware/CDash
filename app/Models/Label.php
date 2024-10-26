<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Note: Use caution when creating relationships on this model.  Labels can be shared between
 * projects and relationships could allow malicious actors to access resources they should
 * not have access to via those relationships if not set up properly.  In the future, it would
 * be good to have labels be unique on a per-project basis instead of being shared between projects.
 *
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

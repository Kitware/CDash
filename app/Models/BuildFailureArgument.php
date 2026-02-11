<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $argument
 *
 * @mixin Builder<BuildError>
 */
class BuildFailureArgument extends Model
{
    protected $table = 'buildfailureargument';

    public $timestamps = false;

    protected $fillable = [
        'argument',
    ];
}

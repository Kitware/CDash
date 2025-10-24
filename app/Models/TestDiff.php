<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $buildid
 * @property int $type
 * @property int $difference_positive
 * @property int $difference_negative
 *
 * @mixin Builder<TestDiff>
 */
class TestDiff extends Model
{
    protected $table = 'testdiff';

    public $timestamps = false;

    protected $fillable = [
        'buildid',
        'type',
        'difference_positive',
        'difference_negative',
    ];
}

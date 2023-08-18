<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $projectid
 * @property string $text
 *
 * @mixin Builder<Banner>
 */
class Banner extends Model
{
    public $timestamps = false;

    protected $table = 'banner';

    protected $primaryKey = 'projectid';
    public $incrementing = false;

    protected $fillable = [
        'projectid',
        'text',
    ];
}

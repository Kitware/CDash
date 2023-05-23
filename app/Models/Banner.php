<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    public $timestamps = false;

    protected $table = 'banner';

    protected $primaryKey = 'projectid';
    public $incrementing = false;

    protected $fillable = [
        'projectid',
        'text'
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestLabel extends Model
{
    protected $table = 'label2test';
    protected $fillable = [
        'labelid',
        'buildid',
        'outputid',
    ];

    public $timestamps = false;
}

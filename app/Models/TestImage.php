<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestImage extends Model
{
    protected $table = 'test2image';
    protected $fillable = ['imgid', 'outputid', 'role'];

    public $timestamps = false;
}

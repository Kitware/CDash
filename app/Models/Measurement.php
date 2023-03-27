<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Measurement extends Model
{
    protected $table = 'measurement';
    protected $fillable = ['projectid', 'name', 'position'];

    public $timestamps = false;
}

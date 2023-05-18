<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuccessfulJob extends Model
{
    public $timestamps = false;
    protected $fillable = ['filename'];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Password extends Model
{
    public $incrementing = false;

    protected $table = 'password';
    protected $fillable = ['password', 'date'];
    protected $hidden = ['password'];
    protected $primaryKey = null;
}

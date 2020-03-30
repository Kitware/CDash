<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestMeasurement extends Model
{
    protected $table = 'testmeasurement';
    protected $fillable = ['outputid', 'name', 'type', 'value'];

    public $timestamps = false;
}

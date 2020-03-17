<?php

namespace App\Models;

use CDash\Models\Build;

use Illuminate\Database\Eloquent\Model;

class TestOutput extends Model
{
    protected $table = 'testoutput';
    protected $fillable = ['testid', 'path', 'command', 'output', 'crc32'];

    public $timestamps = false;
}

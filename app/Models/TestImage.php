<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestImage extends Model
{
    protected $table = 'test2image';
    protected $fillable = ['imgid', 'outputid', 'role'];

    public $timestamps = false;

    /**
     * @return BelongsTo<TestOutput, self>
     */
    public function testOutput(): BelongsTo
    {
        return $this->belongsTo('App\Models\TestOutput', 'outputid');
    }
}

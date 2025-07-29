<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $imgid
 * @property int $outputid
 * @property string $role
 *
 * @mixin Builder<TestImage>
 */
class TestImage extends Model
{
    protected $table = 'test2image';

    public $timestamps = false;

    protected $fillable = [
        'imgid',
        'outputid',
        'role',
    ];

    protected $casts = [
        'id' => 'integer',
        'imgid' => 'integer',
        'outputid' => 'integer',
    ];

    /**
     * @return BelongsTo<TestOutput, $this>
     */
    public function testOutput(): BelongsTo
    {
        return $this->belongsTo(TestOutput::class, 'outputid');
    }

    /**
     * @return BelongsTo<Image, $this>
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'imgid');
    }
}

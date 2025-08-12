<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $imgid
 * @property int $testid
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
        'testid',
        'role',
    ];

    protected $casts = [
        'id' => 'integer',
        'imgid' => 'integer',
        'testid' => 'integer',
    ];

    /**
     * @return BelongsTo<Test, $this>
     */
    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class, 'testid');
    }

    /**
     * @return BelongsTo<Image, $this>
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'imgid');
    }
}

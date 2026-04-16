<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    /**
     * @return Attribute<?string,void>
     */
    protected function url(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes): ?string {
                if ($attributes['imgid'] === null || (int) $attributes['imgid'] === 0) {
                    return null;
                }

                return url('/image/' . $attributes['imgid']);
            },
        );
    }
}

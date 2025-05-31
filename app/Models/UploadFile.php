<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\File;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;

/**
 * @property int $id
 * @property string $filename
 * @property int $filesize File size in bytes
 * @property string $sha1sum
 * @property bool $isurl
 *
 * @mixin Builder<UploadFile>
 */
class UploadFile extends Model
{
    protected $table = 'uploadfile';

    public $timestamps = false;

    protected $fillable = [
        'filename',
        'filesize',
        'sha1sum',
        'isurl',
    ];

    protected $casts = [
        'id' => 'integer',
        'filesize' => 'integer',
        'isurl' => 'boolean',
    ];

    /**
     * @return BelongsToMany<Build, $this>
     */
    public function builds(): BelongsToMany
    {
        return $this->belongsToMany(Build::class, 'build2uploadfile', 'fileid', 'buildid');
    }

    /**
     * @throws FileNotFoundException
     */
    public function file(): File
    {
        return new File(storage_path("app/upload/{$this->sha1sum}"));
    }
}

<?php

namespace Tests\Traits;

use App\Models\Site;
use Illuminate\Support\Str;

trait CreatesSites
{
    /**
     * @param array<string,mixed> $attributes
     */
    public function makeSite(array $attributes = []): Site
    {
        $attributes['name'] = ($attributes['name'] ?? 'Site') . '_' . Str::uuid()->toString();
        return Site::create($attributes);
    }
}

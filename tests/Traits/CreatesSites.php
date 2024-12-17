<?php

namespace Tests\Traits;

use App\Models\Site;
use Illuminate\Support\Str;
use InvalidArgumentException;

trait CreatesSites
{
    /**
     * @param array<string,mixed> $attributes
     */
    public function makeSite(array $attributes = []): Site
    {
        $siteName = $attributes['name'] ?? 'Site';
        if (!is_string($siteName)) {
            throw new InvalidArgumentException('Site name must be a string.');
        }

        $attributes['name'] = $siteName . '_' . Str::uuid()->toString();
        return Site::create($attributes);
    }
}

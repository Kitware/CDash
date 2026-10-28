<?php

namespace Tests\Traits;

trait DisablesSchemaCache
{
    /**
     * Build the GraphQL schema from the current schema definition, rather than loading a potentially stale or
     * unrelated schema from the cache, which is enabled in the test environment.
     *
     * Validating the schema deletes the cache file, so this also uses a private cache path to avoid deleting the
     * shared cache file out from under other tests running in parallel.
     */
    protected function disableSchemaCache(): void
    {
        config([
            'lighthouse.schema_cache.enable' => false,
            'lighthouse.schema_cache.path' => sys_get_temp_dir() . '/' . uniqid('lighthouse-schema-', true) . '.php',
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Utils;

use Exception;
use Illuminate\Support\Facades\DB;

class SystemUtils
{
    public static function isDatabaseOnline(): bool
    {
        // We consider the database to be offline if migrations are running.
        if (app()->isDownForMaintenance()) {
            return false;
        }

        try {
            DB::connection()->getPdo();
            return true;
        } catch (Exception) {
            return false;
        }
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Nightly times without a timezone used to be interpreted in APP_TIMEZONE, but are now interpreted
     * as UTC.  Append APP_TIMEZONE to such nightly times so that existing testing days don't change.
     */
    public function up(): void
    {
        // install.sh runs migrations before refreshing the config cache, so app.schedule_timezone may not
        // exist yet.  In that case, app.timezone is still the old APP_TIMEZONE value.
        $configured_timezone = (string) (config('app.schedule_timezone') ?? config('app.timezone'));

        // PHP ignored APP_TIMEZONE unless it was a valid timezone identifier (e.g., not an offset like
        // "+02:00"), so nightly times without a timezone were never interpreted in it.
        try {
            $timezone = (new DateTimeZone($configured_timezone))->getName();
        } catch (DateInvalidTimeZoneException) {
            $timezone = null;
        }
        if ($timezone === null || !in_array($timezone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
            echo "APP_TIMEZONE '$configured_timezone' is not a valid timezone identifier.  Leaving nightly times unchanged.\n";
            return;
        }

        // When APP_TIMEZONE is UTC (the default), nightly times without a timezone were already
        // interpreted as UTC, so there's nothing to update.
        if ($timezone === 'UTC') {
            return;
        }

        $projects = DB::select('SELECT id, nightlytime FROM project');
        foreach ($projects as $project) {
            $old_nightly_time = date_parse((string) $project->nightlytime);

            // Skip nightly times which already have a timezone or can't be parsed at all.
            if ($old_nightly_time['error_count'] > 0 || $old_nightly_time['is_localtime'] === true || $old_nightly_time['hour'] === false) {
                continue;
            }

            $new_nightly_time_string = trim((string) $project->nightlytime) . ' ' . $timezone;
            $new_nightly_time = date_parse($new_nightly_time_string);

            // Make sure the new value fits in the column and refers to the same time of day in the new timezone.
            if (
                strlen($new_nightly_time_string) > 50
                || $new_nightly_time['error_count'] > 0
                || $new_nightly_time['is_localtime'] !== true
                || $new_nightly_time['hour'] !== $old_nightly_time['hour']
                || $new_nightly_time['minute'] !== $old_nightly_time['minute']
                || $new_nightly_time['second'] !== $old_nightly_time['second']
            ) {
                echo "Unable to add timezone $timezone to nightly time '{$project->nightlytime}' for project #{$project->id}\n";
                continue;
            }

            DB::update('UPDATE project SET nightlytime = ? WHERE id = ?', [$new_nightly_time_string, (int) $project->id]);
        }
    }

    public function down(): void
    {
    }
};

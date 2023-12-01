<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ClassicPalette;
use App\Enums\HighContrastPalette;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class MonitorController extends AbstractController
{
    public function monitor(): View
    {
        return view('admin.monitor');
    }

    /**
     * Get statistics about our recent submissions queue performance.
     */
    public function get(): JsonResponse
    {
        // Get the length of the current backlog and how long the oldest submission
        // has been waiting.
        $backlog_length = DB::table('jobs')->count();
        $backlog_time = null;
        if ($backlog_length > 0) {
            $backlog_time = time_difference(time() - DB::table('jobs')->min('created_at'), false, 'ago');
        }

        // Choose colors for the chart based on user preferences.
        if (array_key_exists('colorblind', $_COOKIE) && intval($_COOKIE['colorblind']) === 1) {
            $palette = HighContrastPalette::class;
        } else {
            $palette = ClassicPalette::class;
        }

        // Get pass/fail data.
        $pass_data = $this->resultsPerHour('successful_jobs', 'finished_at');
        $fail_data = $this->resultsPerHour('failed_jobs', 'failed_at');

        // Get beginning and end of our time range.
        if ($pass_data->isNotEmpty() && $fail_data->isNotEmpty()) {
            $begin = min($fail_data->first()->truncated_time, $pass_data->first()->truncated_time);
            $end = max($fail_data->last()->truncated_time, $pass_data->last()->truncated_time);
        } else {
            if ($pass_data->isNotEmpty()) {
                $begin = $pass_data->first()->truncated_time;
                $end = $pass_data->last()->truncated_time;
            } elseif ($fail_data->isNotEmpty()) {
                $begin = $fail_data->first()->truncated_time;
                $end = $fail_data->last()->truncated_time;
            } else {
                $begin = time();
                $end = time();
            }
        }

        // Initialize trendline data.
        $success_values = [];
        $fail_values = [];
        $num_hours = 0;
        for ($timestamp = $begin; $timestamp <= $end; $timestamp += 3600) {
            // Truncate this timestamp to the beginning of the hour.
            $success_values[$timestamp] = 0;
            $fail_values[$timestamp] = 0;
            $num_hours++;
        }

        foreach ($fail_data as $row) {
            $key = $row->truncated_time;
            if (array_key_exists($key, $fail_values)) {
                $fail_values[$key] += $row->n_jobs;
            } else {
                $fail_values[$key] = $row->n_jobs;
            }
        }

        foreach ($pass_data as $row) {
            $key = $row->truncated_time;
            if (array_key_exists($key, $success_values)) {
                $success_values[$key] += $row->n_jobs;
            } else {
                $success_values[$key] = $row->n_jobs;
            }
        }

        // Massage data into the expected format
        $success_trend = [
            'color' =>  $palette::Success,
            'name' => 'Success',
            'values' => [],
        ];
        foreach ($success_values as $key => $value) {
            $success_trend['values'][] = [
                $key,
                $value,
            ];
        }

        $fail_trend = [
            'color' =>  $palette::Failure,
            'name' => 'Fail',
            'values' => [],
        ];
        foreach ($fail_values as $key => $value) {
            $fail_trend['values'][] = [
                $key,
                $value,
            ];
        }

        return response()->json([
            'backlog_length' => $backlog_length,
            'backlog_time' => $backlog_time,
            'time_chart_data' => [
                "data" => [
                    $success_trend,
                    $fail_trend,
                ],
                "title" => "Submissions Parsed Over the Past {$num_hours} Hours",
                "xLabel" => "Date",
                "yLabel" => "# of Submissions",
            ],
            'log_directory' => config('logging.default') === 'stack' ? storage_path('logs') : '',
        ]);
    }

    /**
     * Group timestamp values by hour.
     * @return Collection<int,\stdClass>
     */
    private function resultsPerHour(string $table, string $field) : Collection
    {
        if (config('database.default') === 'mysql') {
            return $this->mySQLResultsPerHour($table, $field);
        } else {
            return $this->postgreSQLResultsPerHour($table, $field);
        }
    }

    /**
     * MySQL implementation of resultsPerHour
     * @return Collection<int,\stdClass>
     */
    private function mySQLResultsPerHour(string $table, string $field) : Collection
    {
        // Group jobs by hour.
        // We achieve this by:
        // 1) subtracting the seconds
        // 2) subtracting the minutes
        // 3) casting the result to a UNIX timestamp
        return DB::table($table)
            ->select(DB::raw("
              UNIX_TIMESTAMP(
                DATE_SUB(
                  DATE_SUB({$field}, INTERVAL MINUTE({$field}) MINUTE),
                  INTERVAL SECOND({$field}) SECOND
                )
              ) AS truncated_time,
              COUNT(1) AS n_jobs
            "))
            ->groupBy('truncated_time')
            ->get();
    }

    /**
     * Postgres implementation of resultsPerHour
     * @return Collection<int,\stdClass>
     */
    private function postgreSQLResultsPerHour(string $table, string $field) : Collection
    {
        // Group jobs by hour.
        // We achieve this by:
        // 1) using DATE_TRUNC() to truncate timestamps to the hour
        // 2) using EXTRACT(EPOCH ...) to convert this value to a UNIX timestamp
        $timezone = date_default_timezone_get();
        return DB::table($table)
            ->select(DB::raw("
              EXTRACT(EPOCH FROM
                  DATE_TRUNC('hour', {$field}) AT TIME ZONE '{$timezone}'
              )::INTEGER AS truncated_time,
              COUNT(1) AS n_jobs
            "))
            ->groupBy('truncated_time')
            ->get();
    }
}

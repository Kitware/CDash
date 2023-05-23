<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use DateTime;
use DateInterval;
use DatePeriod;
use App\Enums\ClassicPalette;
use App\Enums\HighContrastPalette;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonitorController extends AbstractController
{
    public function monitor(): View
    {
        $user = Auth::user();
        if ($user->admin === 1) {
            return view('admin.monitor');
        } else {
            return view('cdash', [
                'xsl' => true,
                'xsl_content' => 'Admin login required to display monitoring info.',
                'title' => 'System Monitor'
            ]);
        }
    }

    /**
     * Get statistics about our recent submissions queue performance.
     */
    public function get(): JsonResponse
    {
        $user = Auth::user();
        if ($user === null || !$user->IsAdmin()) {
            return response()->json(['error' => 'Permissions error'], status: Response::HTTP_FORBIDDEN);
        }

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

        // Initialize trendline data.
        $success_values = [];
        $fail_values = [];
        $ticks = [];
        $period = new \DatePeriod(
            new DateTime('24 hours ago'),
            new DateInterval('PT1H'),
            24);
        $i = 0;
        foreach ($period as $datetime) {
            // Truncate this timestamp to the beginning of the hour.
            $timestamp = $datetime->setTime(intval($datetime->format('H')), 0, 0)->getTimestamp();
            $success_values[$timestamp] = 0;
            $fail_values[$timestamp] = 0;
            $i++;
            if ($i % 3 === 0) {
                $ticks[] = $timestamp;
            }
        }

        // Populate pass/fail trendline data.
        foreach ($this->resultsPerHour('failed_jobs', 'failed_at') as $row) {
            $key = $row->truncated_time;
            if (array_key_exists($key, $fail_values)) {
                $fail_values[$key] += $row->n_jobs;
            } else {
                $fail_values[$key] = $row->n_jobs;
            }
        }

        foreach ($this->resultsPerHour('successful_jobs', 'finished_at') as $row) {
            $key = $row->truncated_time;
            if (array_key_exists($key, $success_values)) {
                $success_values[$key] += $row->n_jobs;
            } else {
                $success_values[$key] = $row->n_jobs;
            }
        }

        // Massage data into the format expected by nvd3.
        $success_trend = [];
        $success_trend['key'] = 'success';
        $success_trend['color'] =  $palette::Success;
        $success_trend['values'] = [];
        foreach ($success_values as $key => $value) {
            $data_point = [$key, $value];
            $success_trend['values'][] = $data_point;
        }

        $fail_trend = [];
        $fail_trend['key'] = 'fail';
        $fail_trend['color'] =  $palette::Failure;
        $fail_trend['values'] = [];
        foreach ($fail_values as $key => $value) {
            $data_point = [$key, $value];
            $fail_trend['values'][] = $data_point;
        }

        $time_chart_data = [$success_trend, $fail_trend];

        return response()->json([
            'backlog_length' => $backlog_length,
            'backlog_time' => $backlog_time,
            'time_chart_data' => $time_chart_data,
            'ticks' => $ticks
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
        // 2) subracting the minutes
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

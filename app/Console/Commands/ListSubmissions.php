<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SubmissionStatus;
use App\Enums\UnparsedSubmissionType;
use App\Enums\XmlSubmissionType;
use App\Models\Project;
use App\Models\Submission;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ListSubmissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'submission:list
                            {--project= : Only list submissions to the project with this name}
                            {--build= : Only list submissions for builds with this name}
                            {--build-id= : Only list submissions for the build with this id}
                            {--site= : Only list submissions for builds from the site with this name}
                            {--type=* : Only list submissions of this type of file, such as Build, Test or GcovTar}
                            {--status=* : Only list submissions with this status: queued, processing, done or failed}
                            {--since= : Only list submissions received since this time, such as "2026-11-20" or "3 hours ago", in UTC unless it gives an offset}
                            {--limit=50 : The maximum number of submissions to list}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List submitted files, newest first';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($limit === false) {
            $this->error('--limit must be a positive integer');
            return self::FAILURE;
        }

        $query = $this->filteredQuery();
        if ($query === null) {
            return self::FAILURE;
        }

        $total = $query->count();
        if ($total === 0) {
            $this->line('No submissions match.');
            return self::SUCCESS;
        }

        $submissions = $query->with(['project', 'build'])
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $this->table(
            ['ID', 'Received (UTC)', 'Project', 'Build', 'File type', 'Status', 'Attempts', 'Error'],
            $submissions->map(fn (Submission $submission): array => [
                $submission->id,
                $submission->received_at->copy()->utc()->format('Y-m-d H:i:s'),
                $submission->project?->name,
                $submission->build === null ? '—' : "{$submission->build->name} ({$submission->build->id})",
                $submission->type,
                strtolower($submission->status->value),
                $submission->attempts,
                Str::limit(rtrim(Str::before($submission->error ?? '', "\n")), 60, '…'),
            ])->all(),
        );

        if ($total > $submissions->count()) {
            $this->line("Showing {$submissions->count()} of {$total} submissions. Use --limit to see more.");
        }

        return self::SUCCESS;
    }

    /**
     * The submissions that match the filter options, or null after reporting an invalid option.
     *
     * @return ?Builder<Submission>
     */
    private function filteredQuery(): ?Builder
    {
        $query = Submission::query();

        $project_name = $this->option('project');
        if (is_string($project_name)) {
            $project = Project::where('name', $project_name)->first();
            if ($project === null) {
                $this->error("Project {$project_name} does not exist");
                return null;
            }
            $query->where('projectid', $project->id);
        }

        $build_name = $this->option('build');
        if (is_string($build_name)) {
            $query->whereHas('build', fn (Builder $build) => $build->where('name', $build_name));
        }

        $build_id = $this->option('build-id');
        if (is_string($build_id)) {
            $build_id = filter_var($build_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($build_id === false) {
                $this->error('--build-id must be a positive integer');
                return null;
            }
            $query->where('buildid', $build_id);
        }

        $site_name = $this->option('site');
        if (is_string($site_name)) {
            $query->whereHas('build.site', fn (Builder $site) => $site->where('name', $site_name));
        }

        $type_names = array_column([...XmlSubmissionType::cases(), ...UnparsedSubmissionType::cases()], 'value');
        sort($type_names, SORT_STRING | SORT_FLAG_CASE);
        $types_by_lowercase_name = array_combine(array_map(strtolower(...), $type_names), $type_names);
        $types = [];
        foreach ((array) $this->option('type') as $type_name) {
            $type = $types_by_lowercase_name[strtolower((string) $type_name)] ?? null;
            if ($type === null) {
                $this->error("Unknown type {$type_name}. Valid types are: " . implode(', ', $type_names));
                return null;
            }
            $types[] = $type;
        }
        if ($types !== []) {
            $query->whereIn('type', $types);
        }

        $statuses = [];
        foreach ((array) $this->option('status') as $status_name) {
            $status = SubmissionStatus::tryFrom(strtoupper((string) $status_name));
            if ($status === null) {
                $valid_names = array_map(fn (SubmissionStatus $case): string => strtolower($case->value), SubmissionStatus::cases());
                $this->error("Unknown status {$status_name}. Valid statuses are: " . implode(', ', $valid_names));
                return null;
            }
            $statuses[] = $status->value;
        }
        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        $since = $this->option('since');
        if (is_string($since)) {
            try {
                // Queries write a time without its offset, and the database reads it as UTC.
                $query->where('received_at', '>=', Carbon::parse($since, 'UTC')->utc());
            } catch (InvalidFormatException) {
                $this->error("Can't read --since={$since} as a time");
                return null;
            }
        }

        return $query;
    }
}

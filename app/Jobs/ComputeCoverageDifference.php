<?php

namespace App\Jobs;

use App\Models\Build;
use App\Models\CoverageDiff;
use App\Models\CoverageLine;
use App\Models\CoverageView;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;

class ComputeCoverageDifference implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        public Build $baseBuild,
        public Build $compareBuild,
    ) {
        if ($this->baseBuild->projectid !== $this->compareBuild->projectid) {
            throw new InvalidArgumentException('Builds must belong to the same project.');
        }
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $basePaths = $this->baseBuild->coverage()->pluck('fullpath');
        $comparePaths = $this->compareBuild->coverage()->pluck('fullpath');
        /** @var Collection<int, string> $allPaths */
        $allPaths = $basePaths->merge($comparePaths)->unique()->sort()->values();

        $coverageDiff = CoverageDiff::updateOrCreate(
            [
                'basebuildid' => $this->baseBuild->id,
                'comparebuildid' => $this->compareBuild->id,
            ],
            [
                'coveredlinesadded' => 0,
                'coveredlinesremoved' => 0,
                'coveredlinesuncovered' => 0,
                'uncoveredlinesadded' => 0,
                'uncoveredlinesremoved' => 0,
                'uncoveredlinescovered' => 0,
            ]
        );

        foreach ($allPaths->chunk(100) as $batch) {
            $baseViews = $this->baseBuild->coverage()
                ->whereIn('fullpath', $batch)
                ->get()
                ->keyBy('fullpath');
            $compareViews = $this->compareBuild->coverage()
                ->whereIn('fullpath', $batch)
                ->get()
                ->keyBy('fullpath');

            foreach ($batch as $path) {
                /** @var CoverageView|null $baseView */
                $baseView = $baseViews->get((string) $path);
                /** @var CoverageView|null $compareView */
                $compareView = $compareViews->get((string) $path);
                $this->computeFileDiff($baseView, $compareView, $coverageDiff);
            }
        }

        $coverageDiff->save();
    }

    /**
     * Compute the coverage diff between two coverage files.
     */
    private function computeFileDiff(?CoverageView $cv1, ?CoverageView $cv2, CoverageDiff $coverageDiff): void
    {
        if ($cv1 === null && $cv2 === null) {
            return;
        }

        if ($cv1 === null) {
            // File added in compare build
            if ($cv2 !== null) {
                foreach ($cv2->coveredLines as $line) {
                    if ($line->isCovered) {
                        $coverageDiff->coveredlinesadded++;
                    } else {
                        $coverageDiff->uncoveredlinesadded++;
                    }
                }
            }
            return;
        }

        if ($cv2 === null) {
            // File removed in compare build
            foreach ($cv1->coveredLines as $line) {
                if ($line->isCovered) {
                    $coverageDiff->coveredlinesremoved++;
                } else {
                    $coverageDiff->uncoveredlinesremoved++;
                }
            }
            return;
        }

        /** @var Collection<int, CoverageLine> $cl1 */
        $cl1 = collect($cv1->coveredLines)->keyBy('lineNumber');
        /** @var Collection<int, CoverageLine> $cl2 */
        $cl2 = collect($cv2->coveredLines)->keyBy('lineNumber');

        $differ = new Differ(new UnifiedDiffOutputBuilder());

        // Files are 1-indexed, with the first line being line 1.
        $file1Line = 1;
        $file2Line = 1;
        foreach ($differ->diffToArray($cv1->file ?? '', $cv2->file ?? '') as [$lineText, $status]) {
            $line1 = $cl1->get($file1Line);
            $line2 = $cl2->get($file2Line);

            switch ($status) {
                case Differ::OLD:  // Line is shared between both files
                    if ($line1 !== null && $line1->isCovered && $line2 !== null && !$line2->isCovered) {
                        $coverageDiff->coveredlinesuncovered++;
                    } elseif ($line1 !== null && !$line1->isCovered && $line2 !== null && $line2->isCovered) {
                        $coverageDiff->uncoveredlinescovered++;
                    } elseif ($line1 !== null && $line2 === null) {
                        // We consider a line to be added if it was previously not executable, and now is executable.
                        // The reverse is also true: a line is removed if it was previously executable, and now is not executable.
                        if ($line1->isCovered) {
                            $coverageDiff->coveredlinesremoved++;
                        } else {
                            $coverageDiff->uncoveredlinesremoved++;
                        }
                    } elseif ($line1 === null && $line2 !== null) {
                        if ($line2->isCovered) {
                            $coverageDiff->coveredlinesadded++;
                        } else {
                            $coverageDiff->uncoveredlinesadded++;
                        }
                    }

                    $file1Line++;
                    $file2Line++;
                    break;
                case Differ::ADDED:  // Line exists in file 2 but not file 1
                    if ($line2 !== null) {
                        if ($line2->isCovered) {
                            $coverageDiff->coveredlinesadded++;
                        } else {
                            $coverageDiff->uncoveredlinesadded++;
                        }
                    }

                    $file2Line++;
                    break;
                case Differ::REMOVED:  // Line exists in file 1 but not file 2
                    if ($line1 !== null) {
                        if ($line1->isCovered) {
                            $coverageDiff->coveredlinesremoved++;
                        } else {
                            $coverageDiff->uncoveredlinesremoved++;
                        }
                    }

                    $file1Line++;
                    break;
                default:
                    throw new Exception('Invalid Differ status: ' . $status);
            }
        }
    }
}

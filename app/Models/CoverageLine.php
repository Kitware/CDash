<?php

namespace App\Models;

readonly class CoverageLine
{
    public function __construct(
        public int $lineNumber,
        public ?int $timesHit = null,
        public ?int $totalBranches = null,
        public ?int $branchesHit = null,
    ) {
    }
}

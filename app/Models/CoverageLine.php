<?php

namespace App\Models;

readonly class CoverageLine
{
    public bool $isCovered;

    public function __construct(
        public int $lineNumber,
        public ?int $timesHit = null,
        public ?int $totalBranches = null,
        public ?int $branchesHit = null,
    ) {
        $this->isCovered = ($this->timesHit !== null && $this->timesHit > 0) || ($this->branchesHit !== null && $this->branchesHit > 0);
    }
}

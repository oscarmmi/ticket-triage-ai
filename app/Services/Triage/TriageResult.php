<?php

namespace App\Services\Triage;

final readonly class TriageResult
{
    public function __construct(
        public bool $succeeded,
        public ?string $error = null,
    ) {}
}

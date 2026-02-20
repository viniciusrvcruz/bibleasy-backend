<?php

namespace App\Services\Chapter\Parsers\ApiBible;

use Illuminate\Support\Facades\Log;

class WarningCollector
{
    /** @var array<array{message: string, context: array<string, mixed>}> */
    private array $warnings = [];

    public function add(string $message, array $context = []): void
    {
        $this->warnings[] = ['message' => $message, 'context' => $context];
    }

    public function flush(): void
    {
        foreach ($this->warnings as $warning) {
            Log::warning($warning['message'], $warning['context']);
        }

        $this->warnings = [];
    }

    public function hasWarnings(): bool
    {
        return count($this->warnings) > 0;
    }
}

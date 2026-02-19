<?php

namespace App\Services\Chapter\Parsers\ApiBible;

use Psr\Log\LoggerInterface;

class WarningCollector
{
    /** @var array<array{message: string, context: array<string, mixed>}> */
    private array $warnings = [];

    public function __construct(
        private readonly ?LoggerInterface $logger = null
    ) {}

    public function add(string $message, array $context = []): void
    {
        $this->warnings[] = ['message' => $message, 'context' => $context];
    }

    public function flush(): void
    {
        if ($this->logger === null) {
            return;
        }

        foreach ($this->warnings as $warning) {
            $this->logger->warning($warning['message'], $warning['context']);
        }

        $this->warnings = [];
    }

    public function hasWarnings(): bool
    {
        return count($this->warnings) > 0;
    }
}

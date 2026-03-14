<?php

namespace App\Services\Chapter\Parsers\ApiBible;

use Psr\Log\LoggerInterface;

class WarningCollector
{
    /** @var array<array{message: string, context: array<string, mixed>}> */
    private array $warnings = [];

    private ?string $versionAbbreviation = null;

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /**
     * Sets the version abbreviation so every warning log includes it in context until flush().
     */
    public function setVersionAbbreviation(string $abbreviation): void
    {
        $this->versionAbbreviation = $abbreviation;
    }

    public function add(string $message, array $context = []): void
    {
        $this->warnings[] = [
            'message' => $message,
            'context' => array_merge($context, ['version_abbreviation' => $this->versionAbbreviation]),
        ];
    }

    public function flush(): void
    {
        foreach ($this->warnings as $warning) {
            $this->logger->warning($warning['message'], $warning['context']);
        }

        $this->warnings = [];
        $this->versionAbbreviation = null;
    }

    public function hasWarnings(): bool
    {
        return count($this->warnings) > 0;
    }
}

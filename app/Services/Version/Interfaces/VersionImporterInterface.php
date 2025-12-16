<?php

namespace App\Services\Version\Interfaces;

interface VersionImporterInterface
{
    public function parse(string $content): array;
    public function validate(array $data): void;
    public function import(array $data, int $versionId): void;
}

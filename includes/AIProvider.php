<?php

declare(strict_types=1);

require_once __DIR__ . '/ProductResult.php';

interface AIProvider
{
    public function name(): string;

    /**
     * @param list<string> $knownBrands
     * @param list<array<string,string>> $knownProducts
     * @throws RuntimeException on failure
     */
    public function recognize(
        string $imageBinary,
        string $mime,
        int $timeoutSeconds,
        array $knownBrands = [],
        array $knownProducts = []
    ): ProductResult;

    /**
     * @return array{ok:bool,message:string}
     */
    public function testConnection(int $timeoutSeconds): array;
}

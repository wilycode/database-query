<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Binding;

/**
 * Driver-ready SQL and parameters produced from a canonical compiled query.
 */
final readonly class BoundQuery
{
    /**
     * @param array<int|string, mixed> $params
     * @param array<int|string, mixed> $types
     */
    public function __construct(
        public string $sql,
        public array $params = [],
        public array $types = [],
    ) {}
}

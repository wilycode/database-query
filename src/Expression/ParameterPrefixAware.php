<?php
declare(strict_types=1);

namespace WilyCode\Database\Query\Expression;

interface ParameterPrefixAware
{
    public function paramPrefix(?string $prefix): static;
    public function hasParamPrefix(): bool;
}

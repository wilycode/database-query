<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Statement;

use WilyCode\Database\Query\CompiledQuery;
use WilyCode\Database\Query\Dialect\DialectInterface;

interface QueryInterface
{
    public function compile(?DialectInterface $dialect = null): CompiledQuery;
}

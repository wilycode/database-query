<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Statement;

use WilyCode\Database\Query\CompiledQuery;

interface QueryInterface
{
    public function compile(): CompiledQuery;
}

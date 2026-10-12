<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Binding;

use WilyCode\Database\Query\CompiledQuery;

interface BinderInterface
{
    public function bind(CompiledQuery $query): BoundQuery;
}

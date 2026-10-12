<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Expression;

use WilyCode\Database\Query\ParameterBag;

interface Expression
{
    /**
     * Compile expression into SQL and bind parameters into provided bag.
     */
    public function compile(ParameterBag $bag): string;
}

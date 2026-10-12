<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Enum;

enum JoinType: string
{
    case INNER = 'INNER JOIN';
    case LEFT = 'LEFT JOIN';
    case RIGHT = 'RIGHT JOIN';
}

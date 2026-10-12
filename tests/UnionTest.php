<?php
declare(strict_types=1);

namespace WilyCode\Database\Query\Tests;

use PHPUnit\Framework\TestCase;
use WilyCode\Database\Query\Enum\ParameterType;
use WilyCode\Database\Query\Query;

final class UnionTest extends TestCase
{
    public function test_union_remaps_parameters_to_avoid_collisions(): void
    {
        $a = Query::select('users', 'u')
            ->column('u.id', 'id')
            ->where('u.status', 'active');

        $b = Query::select('admins', 'a')
            ->column('a.id', 'id')
            ->where('a.status', 'active');

        $u = Query::union($a, $b)->orderBy('id')->limit(10);

        $compiled = $u->compile();

        $this->assertSame(
            '(SELECT u.id AS `id` FROM users AS u WHERE u.status = :union_1_param_1) UNION (SELECT a.id AS `id` FROM admins AS a WHERE a.status = :union_2_param_1) ORDER BY id ASC LIMIT 10',
            $compiled->sql
        );

        $this->assertSame([
            ':union_1_param_1' => 'active',
            ':union_2_param_1' => 'active',
        ], $compiled->params);

        $this->assertSame([
            ':union_1_param_1' => ParameterType::STRING,
            ':union_2_param_1' => ParameterType::STRING,
        ], $compiled->types);
    }
}

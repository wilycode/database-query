<?php
declare(strict_types=1);

namespace WilyCode\Database\Query\Tests;

use PHPUnit\Framework\TestCase;
use WilyCode\Database\Query\Enum\ParameterType;
use WilyCode\Database\Query\Query;
use WilyCode\Database\Query\Expression\Subquery;

final class SubqueryTest extends TestCase
{
    public function test_subquery_parameters_are_collected_in_main_query(): void
    {
        $sub = Query::select('comments', 'c')
            ->columnRaw('cnt', 'COUNT(*)')
            ->where('c.status', 'approved');

        $q = Query::select('posts', 'p')
            ->column('id', 'id')
            ->columnExp('approved_count', new Subquery($sub))
            ->where('id', 10);

        $compiled = $q->compile();

        $this->assertStringContainsString('SELECT p.id AS `id`, (SELECT COUNT(*) AS `cnt` FROM comments AS c WHERE c.status = :param_2) AS `approved_count`', $compiled->sql);
        $this->assertStringContainsString('FROM posts AS p WHERE id = :param_1', $compiled->sql);

        $this->assertSame([
            ':param_2' => 'approved',
            ':param_1' => 10,
        ], $compiled->params);

        $this->assertSame([
            ':param_2' => ParameterType::STRING,
            ':param_1' => ParameterType::INTEGER,
        ], $compiled->types);
    }
}

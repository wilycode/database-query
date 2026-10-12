<?php
declare(strict_types=1);

namespace WilyCode\Database\Query\Tests;

use PHPUnit\Framework\TestCase;
use WilyCode\Database\Query\Enum\ParameterType;
use WilyCode\Database\Query\Expression\Subquery;
use WilyCode\Database\Query\Query;

final class SelectTest extends TestCase
{
    public function test_column_aliases_quote_reserved_words_and_escape_backticks(): void
    {
        $query = Query::select('entry_value', 'ev')->column('value_string', 'values')->columnRaw('odd`name', '1');
        self::assertSame('SELECT ev.value_string AS `values`, 1 AS `odd``name` FROM entry_value AS ev', $query->compile()->sql);
        self::assertSame($query->compile()->sql, $query->compile()->sql);
    }

    public function test_select_builds_columns_from_alias_expr_map_and_prefixes_simple_identifiers(): void
    {
        $q = Query::select('posts', 'p')
            ->columns([
                0 => 'p.*',
                'id' => 'id',
                'userName' => 'u.name',
            ])->leftJoin('user', 'u', 'u.id = p.user_id');

        $compiled = $q->compile();

        $this->assertSame(
            'SELECT p.*, p.id AS `id`, u.name AS `userName` FROM posts AS p LEFT JOIN user AS u ON u.id = p.user_id',
            $compiled->sql
        );
        $this->assertSame([], $compiled->params);
        $this->assertSame([], $compiled->types);
    }

    public function test_where_is_prefixed_with_main_alias_and_supports_grouping(): void
    {
        $q = Query::select('posts', 'p')
            ->column('id', 'id')
            ->whereGroup(function ($g) {
                $g->where('p.status', 'new')
                  ->orWhere('p.status', 'pending');
            })
            ->andWhere('p.type', 'A');

        $compiled = $q->compile();

        $this->assertSame(
            'SELECT p.id AS `id` FROM posts AS p WHERE (p.status = :param_1 OR p.status = :param_2) AND p.type = :param_3',
            $compiled->sql
        );

        $this->assertSame([
            ':param_1' => 'new',
            ':param_2' => 'pending',
            ':param_3' => 'A',
        ], $compiled->params);

        $this->assertSame([
            ':param_1' => ParameterType::STRING,
            ':param_2' => ParameterType::STRING,
            ':param_3' => ParameterType::STRING,
        ], $compiled->types);
    }

    public function test_having_and_having_group_work(): void
    {
        $q = Query::select('posts', 'p')
            ->column('user_id', 'userId')
            ->columnRaw('cnt', 'COUNT(*)')
            ->groupBy('user_id')
            ->having('COUNT(*)', 1, '>')
            ->orHavingGroup(function ($h) {
                $h->havingRaw('SUM(p.points) > ?', [10])
                  ->orHaving('MAX(p.score)', 50, '>');
            });

        $compiled = $q->compile();

        $this->assertSame(
            'SELECT p.user_id AS `userId`, COUNT(*) AS `cnt` FROM posts AS p GROUP BY user_id HAVING COUNT(*) > :param_1 OR (SUM(p.points) > :param_2 OR MAX(p.score) > :param_3)',
            $compiled->sql
        );

        $this->assertSame([
            ':param_1' => 1,
            ':param_2' => 10,
            ':param_3' => 50,
        ], $compiled->params);

        $this->assertSame([
            ':param_1' => ParameterType::INTEGER,
            ':param_2' => ParameterType::INTEGER,
            ':param_3' => ParameterType::INTEGER,
        ], $compiled->types);
    }

    public function test_columns_map_accepts_subquery_expression_and_collects_params(): void
    {
        $sub = Query::select('comments', 'c')
            ->columnRaw('cnt', 'COUNT(*)')
            ->where('c.status', 'approved');

        $q = Query::select('posts', 'p')
            ->columns([
                'id' => 'id',
                'approved_count' => new Subquery($sub),
            ])
            ->where('id', 10);

        $compiled = $q->compile();

        $this->assertSame(
            'SELECT p.id AS `id`, (SELECT COUNT(*) AS `cnt` FROM comments AS c WHERE c.status = :param_2) AS `approved_count` FROM posts AS p WHERE id = :param_1',
            $compiled->sql
        );

        $this->assertSame([
            ':param_2' => 'approved',
            ':param_1' => 10,
        ], $compiled->params);

        $this->assertSame([
            ':param_2' => ParameterType::STRING,
            ':param_1' => ParameterType::INTEGER,
        ], $compiled->types);
    }

    public function test_column_raw_supports_parameters_and_types(): void
    {
        $q = Query::select('posts', 'p')
            ->column('id', 'id')
            ->columnRaw(
                'newCount',
                'SUM(CASE WHEN p.status = ? THEN 1 ELSE 0 END)',
                ['new']
            )
            ->where('id', 123);

        $compiled = $q->compile();

        $this->assertSame(
            'SELECT p.id AS `id`, SUM(CASE WHEN p.status = :param_2 THEN 1 ELSE 0 END) AS `newCount` FROM posts AS p WHERE id = :param_1',
            $compiled->sql
        );

        $this->assertSame([
            ':param_2' => 'new',
            ':param_1' => 123,
        ], $compiled->params);

        $this->assertSame([
            ':param_2' => ParameterType::STRING,
            ':param_1' => ParameterType::INTEGER,
        ], $compiled->types);
    }

    public function test_for_pagination_count_wraps_query_removes_order_limit_and_does_not_mutate_original(): void
    {
        $q = Query::select('posts', 'p')
            ->column('id', 'id')
            ->where('id', 123)
            ->orderBy('p.id', 'DESC')
            ->limit(10)
            ->offset(20);

        $before = $q->compile();

        $count = $q->forPaginationCount(); // default alias: total

        $after = $q->compile();

        $this->assertSame($before->sql, $after->sql);
        $this->assertSame($before->params, $after->params);
        $this->assertSame($before->types, $after->types);

        $this->assertSame(
            'SELECT p.id AS `id` FROM posts AS p WHERE id = :param_1 ORDER BY p.id DESC LIMIT 10 OFFSET 20',
            $before->sql
        );

        $this->assertSame([
            ':param_1' => 123,
        ], $before->params);

        $this->assertSame([
            ':param_1' => ParameterType::INTEGER,
        ], $before->types);


        $this->assertSame(
            'SELECT COUNT(*) AS total FROM (SELECT p.id AS `id` FROM posts AS p WHERE id = :param_1) AS _count',
            $count->sql
        );

        $this->assertSame([
            ':param_1' => 123,
        ], $count->params);

        $this->assertSame([
            ':param_1' => ParameterType::INTEGER,
        ], $count->types);
    }


    public function test_for_pagination_count_rejects_invalid_alias(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid count alias');

        Query::select('posts')->forPaginationCount('total; DROP TABLE posts');
    }

    public function test_null_comparisons_use_is_null_without_binding(): void
    {
        $query = Query::select('users', 'u')
            ->where('deleted_at', null)
            ->orWhere('archived_at', null, '!=');

        $compiled = $query->compile();

        self::assertSame(
            'SELECT * FROM users AS u WHERE deleted_at IS NULL OR archived_at IS NOT NULL',
            $compiled->sql,
        );
        self::assertSame([], $compiled->params);
        self::assertSame([], $compiled->types);
    }

    public function test_null_rejects_incompatible_comparison(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('NULL is not compatible');

        Query::select('users')->where('created_at', null, '>');
    }

    public function test_compile_is_deterministic_and_does_not_mutate_parameters(): void
    {
        $query = Query::select('posts', 'p')
            ->columnRaw('score', 'CASE WHEN p.status = ? THEN ? ELSE 0 END', ['published', 10])
            ->leftJoin('users', 'u', 'u.id = p.user_id AND u.active = ?', [1])
            ->where('p.id', 7);

        $first = $query->compile();
        $second = $query->compile();

        self::assertSame($first->sql, $second->sql);
        self::assertSame($first->params, $second->params);
        self::assertSame($first->types, $second->types);
    }

}

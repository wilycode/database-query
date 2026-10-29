<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Tests;

use PHPUnit\Framework\TestCase;
use WilyCode\Database\Query\Dialect\{MySqlDialect, PostgreSqlDialect, SqliteDialect};
use WilyCode\Database\Query\Expression\{JsonArray, JsonArraySubquery, JsonObject, Subquery, ValueExpression};
use WilyCode\Database\Query\Query;
use WilyCode\Database\Query\ParameterBag;

final class DialectTest extends TestCase
{
    protected function tearDown(): void { Query::dialectDefault(null); }

    public function testDefaultExplicitChoiceAndRepeatedCompilation(): void
    {
        $query = Query::select('users')->column('id', 'odd"name')->offset(2);
        $mysql = $query->compile();
        self::assertSame('SELECT users.id AS `odd"name` FROM users LIMIT 18446744073709551615 OFFSET 2', $mysql->sql);
        self::assertSame('SELECT users.id AS "odd""name" FROM users OFFSET 2', $query->compile(new PostgreSqlDialect())->sql);
        self::assertSame('SELECT users.id AS "odd""name" FROM users LIMIT -1 OFFSET 2', $query->compile(new SqliteDialect())->sql);
        self::assertEquals($mysql, $query->compile());
        Query::dialectDefault(new SqliteDialect());
        self::assertSame($query->compile(new SqliteDialect())->sql, $query->compile()->sql);
        self::assertSame($mysql->sql, $query->compile(new MySqlDialect())->sql);
    }

    public function testZeroLimitAndOffsetArePreserved(): void
    {
        foreach ([new MySqlDialect(), new PostgreSqlDialect(), new SqliteDialect()] as $dialect) {
            self::assertSame('SELECT * FROM users LIMIT 0 OFFSET 0', Query::select('users')->limit(0)->offset(0)->compile($dialect)->sql);
        }
    }

    public function testDialectReachesWhereHavingGroupsAndScalarSubqueries(): void
    {
        $sub = Query::select('users')->column('id', 'value')->limit(1);
        $query = Query::select('users')->columnExp('sub', new Subquery($sub))
            ->whereGroup(fn ($g) => $g->whereExp(new Subquery($sub)))
            ->havingGroup(fn ($g) => $g->havingExp(new Subquery($sub)));
        $mysql = $query->compile();
        $postgres = $query->compile(new PostgreSqlDialect());
        self::assertSame(3, substr_count($postgres->sql, 'AS "value"'));
        self::assertStringNotContainsString('`', $postgres->sql);
        self::assertEquals($mysql, $query->compile());
        $count = $query->forPaginationCount('total', new PostgreSqlDialect());
        self::assertStringNotContainsString('`', $count->sql);
    }

    public function testPostgresJsonParametersHaveTypesAndNullGuardIsPortable(): void
    {
        $q = Query::select('users', 'u')->columnExp('json', JsonObject::of([
            'text' => ValueExpression::of('hello'), 'n' => 5, 'flag' => true,
            'items' => JsonArray::of([1.5, null]),
        ], 'u.id'));
        $sql = $q->compile(new PostgreSqlDialect())->sql;
        self::assertStringContainsString('CASE WHEN u.id IS NULL THEN NULL ELSE JSONB_BUILD_OBJECT(', $sql);
        self::assertStringContainsString('CAST(:param_1 AS text)', $sql);
        self::assertStringContainsString('CAST(:param_2 AS bigint)', $sql);
        self::assertStringContainsString('CAST(:param_3 AS boolean)', $sql);
        self::assertStringContainsString('CAST(:param_4 AS double precision)', $sql);
    }

    public function testUnsupportedMysqlDistinctFailsClearly(): void
    {
        $this->expectException(\LogicException::class);
        JsonArraySubquery::of(Query::select('users'), 'id', distinct: true)->compile(new ParameterBag(new MySqlDialect()));
    }

    public function testUnionPropagatesDialectAndPreservesIndependentParameters(): void
    {
        $q = Query::union(Query::select('a')->column('id', 'id')->where('id', 1)->limit(1), Query::select('b')->column('id', 'id')->where('id', 2))->orderBy('id')->limit(2);
        $sqlite = $q->compile(new SqliteDialect());
        self::assertStringStartsWith('SELECT * FROM (SELECT a.id AS "id"', $sqlite->sql);
        self::assertStringEndsWith('ORDER BY id ASC LIMIT 2', $sqlite->sql);
        self::assertSame([1, 2], array_values($sqlite->params));
        self::assertStringStartsWith('(SELECT a.id AS "id"', $q->compile(new PostgreSqlDialect())->sql);
    }

    public function testUpdateDeleteDeferredConditionsUseSameDialect(): void
    {
        $sub = new Subquery(Query::select('flags')->column('id', 'value')->where('id', 3));
        foreach ([Query::update('users')->set(['active' => true]), Query::delete('users')] as $q) {
            $q->whereExp($sub);
            $mysql = $q->compile();
            self::assertStringContainsString('AS "value"', $q->compile(new PostgreSqlDialect())->sql);
            self::assertEquals($mysql, $q->compile());
        }
    }
    public function testPostgresRawQuotedTextAndPathOperatorDoNotConsumeParameters(): void
    {
        $q = Query::raw('SELECT $$:ignored ?$$, $tag$:also_ignored$tag$, data #>> :path FROM users WHERE id = :id', ['path' => '{name}', 'id' => 2]);
        self::assertSame([':path' => '{name}', ':id' => 2], $q->compile(new PostgreSqlDialect())->params);
    }

    public function testJsonKeysWithQuotesAndBackslashesAreBound(): void
    {
        $key = "odd'\\key";
        $q = Query::select()->columnExp('data', JsonObject::of([$key => 1]));
        foreach ([new MySqlDialect(), new PostgreSqlDialect(), new SqliteDialect()] as $dialect) {
            $compiled = $q->compile($dialect);
            self::assertStringNotContainsString($key, $compiled->sql);
            self::assertSame([$key, 1], array_values($compiled->params));
        }
    }
}

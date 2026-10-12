<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WilyCode\Database\Query\Binding\BinderInterface;
use WilyCode\Database\Query\Binding\BoundQuery;
use WilyCode\Database\Query\CompiledQuery;
use WilyCode\Database\Query\Enum\ParameterType;
use WilyCode\Database\Query\Query;
use WilyCode\Database\Query\Statement\QueryInterface;

final class PublicApiTest extends TestCase
{
    public function testQueryFactoriesImplementQueryContract(): void
    {
        self::assertTrue(Query::select('users') instanceof QueryInterface);
        self::assertTrue(Query::insert('users') instanceof QueryInterface);
        self::assertTrue(Query::update('users') instanceof QueryInterface);
        self::assertTrue(Query::delete('users') instanceof QueryInterface);
        self::assertTrue(Query::raw('SELECT 1') instanceof QueryInterface);
    }

    public function testCompiledQueryRetainsSQLParameterOrderAndTypes(): void
    {
        $compiled = new CompiledQuery(
            'SELECT * FROM users WHERE first_id = :first AND second_id = :second',
            [':second' => 2, ':first' => 1],
            [':second' => ParameterType::INTEGER, ':first' => ParameterType::INTEGER],
        );

        self::assertSame([':first' => 1, ':second' => 2], $compiled->params);
        self::assertSame([
            ':first' => ParameterType::INTEGER,
            ':second' => ParameterType::INTEGER,
        ], $compiled->types);
        self::assertSame($compiled, $compiled->compile());
    }

    public function testBinderContractExchangesCanonicalQueryForDriverReadyRepresentation(): void
    {
        $binder = new class implements BinderInterface {
            public function bind(CompiledQuery $query): BoundQuery
            {
                return new BoundQuery($query->sql, $query->params, $query->types);
            }
        };

        $compiled = Query::raw('SELECT * FROM users WHERE id = ?', [15])->compile();
        $bound = $binder->bind($compiled);

        self::assertSame($compiled->sql, $bound->sql);
        self::assertSame($compiled->params, $bound->params);
        self::assertSame($compiled->types, $bound->types);
    }

    public function testUnmodifiedQueryCanBeCompiledRepeatedly(): void
    {
        $builder = Query::select('users', 'u')->where('u.id', 15);

        $first = $builder->compile();
        $second = $builder->compile();

        self::assertSame($first->sql, $second->sql);
        self::assertSame($first->params, $second->params);
        self::assertSame($first->types, $second->types);
    }

    public function testRawQueryRejectsMissingNamedParameter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Query::raw('SELECT * FROM users WHERE id = :id')->compile();
    }
}

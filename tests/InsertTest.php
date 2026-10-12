<?php
declare(strict_types=1);

namespace WilyCode\Database\Query\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WilyCode\Database\Query\Enum\ParameterType;
use WilyCode\Database\Query\Query;

final class InsertTest extends TestCase
{
    public function test_insert_single_row(): void
    {
        $q = Query::insert('users')->values([
            'email' => 'a@example.com',
            'age' => 20,
        ]);

        $compiled = $q->compile();

        $this->assertSame(
            'INSERT INTO users (email, age) VALUES (:param_1, :param_2)',
            $compiled->sql
        );

        $this->assertSame([
            ':param_1' => 'a@example.com',
            ':param_2' => 20,
        ], $compiled->params);

        $this->assertSame([
            ':param_1' => ParameterType::STRING,
            ':param_2' => ParameterType::INTEGER,
        ], $compiled->types);
    }

    public function test_insert_many_rows(): void
    {
        $q = Query::insert('users')->valuesMany([
            ['email' => 'a@example.com', 'age' => 20],
            ['email' => 'b@example.com', 'age' => 30],
        ]);

        $compiled = $q->compile();

        $this->assertSame(
            'INSERT INTO users (email, age) VALUES (:param_1, :param_2), (:param_3, :param_4)',
            $compiled->sql
        );

        $this->assertSame([
            ':param_1' => 'a@example.com',
            ':param_2' => 20,
            ':param_3' => 'b@example.com',
            ':param_4' => 30,
        ], $compiled->params);

        $this->assertSame([
            ':param_1' => ParameterType::STRING,
            ':param_2' => ParameterType::INTEGER,
            ':param_3' => ParameterType::STRING,
            ':param_4' => ParameterType::INTEGER,
        ], $compiled->types);
    }

    public function test_insert_many_requires_same_columns(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Query::insert('users')->valuesMany([
            ['email' => 'a@example.com', 'age' => 20],
            ['email' => 'b@example.com'],
        ])->compile();
    }
}

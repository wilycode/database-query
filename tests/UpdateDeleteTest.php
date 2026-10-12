<?php
declare(strict_types=1);

namespace WilyCode\Database\Query\Tests;

use PHPUnit\Framework\TestCase;
use WilyCode\Database\Query\Enum\ParameterType;
use WilyCode\Database\Query\Query;

final class UpdateDeleteTest extends TestCase
{
    public function test_update_with_where(): void
    {
        $q = Query::update('users')
            ->set(['name' => 'Simon', 'active' => true])
            ->where('id', 123);

        $compiled = $q->compile();

        $this->assertSame(
            'UPDATE users SET name = :param_1, active = :param_2 WHERE id = :param_3',
            $compiled->sql
        );

        $this->assertSame([
            ':param_1' => 'Simon',
            ':param_2' => true,
            ':param_3' => 123,
        ], $compiled->params);

        $this->assertSame([
            ':param_1' => ParameterType::STRING,
            ':param_2' => ParameterType::BOOLEAN,
            ':param_3' => ParameterType::INTEGER,
        ], $compiled->types);
    }

    public function test_delete_with_where_raw_in_array(): void
    {
        $q = Query::delete('users')
            ->whereRaw('id IN (?)', [[1, 2, 3]]);

        $compiled = $q->compile();

        $this->assertSame(
            'DELETE FROM users WHERE id IN (:param_1, :param_2, :param_3)',
            $compiled->sql
        );

        $this->assertSame([
            ':param_1' => 1,
            ':param_2' => 2,
            ':param_3' => 3,
        ], $compiled->params);

        $this->assertSame([
            ':param_1' => ParameterType::INTEGER,
            ':param_2' => ParameterType::INTEGER,
            ':param_3' => ParameterType::INTEGER,
        ], $compiled->types);
    }
    public function test_update_rejects_duplicate_column_assignment(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('assigned more than once');

        Query::update('users')
            ->set(['name' => 'First'])
            ->set(['name' => 'Second']);
    }

}

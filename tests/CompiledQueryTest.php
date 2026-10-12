<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WilyCode\Database\Query\CompiledQuery;
use WilyCode\Database\Query\Enum\ParameterType;

final class CompiledQueryTest extends TestCase
{
    public function testAcceptsCanonicalNamedParameters(): void
    {
        $query = new CompiledQuery(
            'SELECT * FROM users WHERE id = :id',
            [':id' => 10],
            [':id' => ParameterType::INTEGER],
        );

        self::assertSame([':id' => 10], $query->params);
    }

    public function testRejectsPositionalPlaceholders(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot contain positional');

        new CompiledQuery('SELECT * FROM users WHERE id = ?', [], []);
    }

    public function testRejectsRepeatedNamedPlaceholders(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be unique');

        new CompiledQuery(
            'SELECT * FROM users WHERE id = :id OR parent_id = :id',
            [':id' => 10],
            [],
        );
    }

    public function testRejectsMissingAndUnusedValues(): void
    {
        try {
            new CompiledQuery('SELECT * FROM users WHERE id = :id', [], []);
            self::fail('Expected missing placeholder value error.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('has no matching value', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not used');

        new CompiledQuery('SELECT 1', [':id' => 10], []);
    }

    public function testIgnoresPlaceholderLikeTextInsideSqlLiteralsAndComments(): void
    {
        $query = new CompiledQuery(
            "SELECT ':ignored' # :comment\n, :value",
            [':value' => 10],
            [],
        );

        self::assertSame([':value' => 10], $query->params);
    }
}

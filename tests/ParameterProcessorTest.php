<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WilyCode\Database\Query\ParameterBag;
use WilyCode\Database\Query\ParameterProcessor;

final class ParameterProcessorTest extends TestCase
{
    public function testNamedListKeepsParentheses(): void
    {
        $bag = new ParameterBag();

        $sql = ParameterProcessor::process(
            'SELECT * FROM users WHERE id IN (:ids)',
            ['ids' => [10, 20]],
            $bag,
        );

        self::assertSame(
            'SELECT * FROM users WHERE id IN (:ids_param_1, :ids_param_2)',
            $sql,
        );
        self::assertSame([':ids_param_1' => 10, ':ids_param_2' => 20], $bag->getParameters());
    }

    public function testEmptyNamedListCreatesAnEmptySubquery(): void
    {
        $bag = new ParameterBag();

        $sql = ParameterProcessor::process(
            'SELECT * FROM users WHERE id IN (:ids)',
            ['ids' => []],
            $bag,
        );

        self::assertSame(
            'SELECT * FROM users WHERE id IN (SELECT 1 WHERE 0=1)',
            $sql,
        );
    }

    public function testUnusedNamedParameterIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('has no matching placeholder');

        ParameterProcessor::process(
            'SELECT * FROM users',
            ['id' => 10],
            new ParameterBag(),
        );
    }

    public function testNamedParameterCannotBeReusedWithAnotherValue(): void
    {
        $bag = new ParameterBag();
        $bag->add(10, 'id');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already registered');

        $bag->add(20, 'id');
    }

    public function testExplicitParameterNameAdvancesAutomaticCounter(): void
    {
        $bag = new ParameterBag();
        $bag->add(10, 'param_10');

        self::assertSame(':param_11', $bag->addAuto(20));
    }

    public function testRepeatedNamedScalarGetsUniquePlaceholders(): void
    {
        $bag = new ParameterBag();

        $sql = ParameterProcessor::process(
            'SELECT * FROM users WHERE first_name = :name OR last_name = :name',
            ['name' => 'Smith'],
            $bag,
        );

        self::assertSame(
            'SELECT * FROM users WHERE first_name = :name OR last_name = :name_param_1',
            $sql,
        );
        self::assertSame(
            [':name' => 'Smith', ':name_param_1' => 'Smith'],
            $bag->getParameters(),
        );
    }

    public function testRepeatedNamedListGetsIndependentPlaceholders(): void
    {
        $bag = new ParameterBag();

        $sql = ParameterProcessor::process(
            'SELECT * FROM users WHERE id IN (:ids) OR parent_id IN (:ids)',
            ['ids' => [10, 20]],
            $bag,
        );

        self::assertSame(
            'SELECT * FROM users WHERE id IN (:ids_param_1, :ids_param_2) OR parent_id IN (:ids_param_3, :ids_param_4)',
            $sql,
        );
        self::assertSame(
            [':ids_param_1' => 10, ':ids_param_2' => 20, ':ids_param_3' => 10, ':ids_param_4' => 20],
            $bag->getParameters(),
        );
    }

    public function testParameterBagRecognizesNullValues(): void
    {
        $bag = new ParameterBag();
        $bag->add(null, 'deletedAt');

        self::assertTrue($bag->has('deletedAt'));
        self::assertTrue($bag->has(':deletedAt'));
    }

    public function testPlaceholdersInsideStringsAndCommentsAreIgnored(): void
    {
        $bag = new ParameterBag();
        $sql = ParameterProcessor::process(
            "SELECT ':ignored', value # :also_ignored ?\nFROM items WHERE id = :id AND note = '?'",
            ['id' => 10],
            $bag,
        );

        self::assertSame(
            "SELECT ':ignored', value # :also_ignored ?\nFROM items WHERE id = :id AND note = '?'",
            $sql,
        );
        self::assertSame([':id' => 10], $bag->getParameters());
    }

    public function testArrayParameterMustBeParenthesized(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be used inside parentheses');

        ParameterProcessor::process(
            'SELECT * FROM users WHERE id IN :ids',
            ['ids' => [10, 20]],
            new ParameterBag(),
        );
    }
}

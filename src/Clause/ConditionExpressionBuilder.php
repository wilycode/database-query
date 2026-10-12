<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Clause;

use InvalidArgumentException;
use WilyCode\Database\Query\Enum\ComparisonOperator;
use WilyCode\Database\Query\ParameterBag;

/** @internal */
final class ConditionExpressionBuilder
{
    private function __construct() {}

    public static function build(
        ParameterBag $parameters,
        string $column,
        mixed $value,
        ComparisonOperator $operator,
    ): string {
        if ($value === null) {
            return match ($operator) {
                ComparisonOperator::EQUALS, ComparisonOperator::IS => $column . ' IS NULL',
                ComparisonOperator::NOT_EQUALS, ComparisonOperator::IS_NOT => $column . ' IS NOT NULL',
                default => throw new InvalidArgumentException(sprintf(
                    'NULL is not compatible with the "%s" comparison operator.',
                    $operator->value,
                )),
            };
        }

        $operator->assertCompatibleValue($value);

        if (is_array($value)) {
            if ($value === []) {
                return $operator === ComparisonOperator::IN ? '0=1' : '1=1';
            }

            $placeholders = array_map(
                static fn (mixed $item): string => $parameters->add($item),
                array_values($value),
            );

            return sprintf(
                '%s %s (%s)',
                $column,
                $operator->value,
                implode(', ', $placeholders),
            );
        }

        return sprintf(
            '%s %s %s',
            $column,
            $operator->value,
            $parameters->add($value),
        );
    }
}

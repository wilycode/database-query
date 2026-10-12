<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Enum;

use InvalidArgumentException;

enum ComparisonOperator: string
{
    case EQUALS = '=';
    case NOT_EQUALS = '<>';
    case GREATER_THAN = '>';
    case LESS_THAN = '<';
    case GREATER_THAN_OR_EQUAL = '>=';
    case LESS_THAN_OR_EQUAL = '<=';
    case LIKE = 'LIKE';
    case NOT_LIKE = 'NOT LIKE';
    case IN = 'IN';
    case NOT_IN = 'NOT IN';
    case IS = 'IS';
    case IS_NOT = 'IS NOT';


    public static function fromString(string $operator): self
    {
        return match (strtoupper(trim($operator))) {
            '=' => self::EQUALS,
            '<>', '!=' => self::NOT_EQUALS,
            '>' => self::GREATER_THAN,
            '<' => self::LESS_THAN,
            '>=' => self::GREATER_THAN_OR_EQUAL,
            '<=' => self::LESS_THAN_OR_EQUAL,
            'LIKE' => self::LIKE,
            'NOT LIKE' => self::NOT_LIKE,
            'IN' => self::IN,
            'NOT IN' => self::NOT_IN,
            'IS' => self::IS,
            'IS NOT' => self::IS_NOT,
            default => throw new InvalidArgumentException("Invalid comparison operator: $operator")
        };
    }

    public function supportsArrayValue(): bool
    {
        return match ($this) {
            self::IN, self::NOT_IN => true,
            default => false,
        };
    }

    public function assertCompatibleValue(mixed $value): void
    {
        if (is_array($value) && !$this->supportsArrayValue()) {
            throw new InvalidArgumentException(
                "Array values can only be used with IN or NOT IN operators. Got: {$this->value}"
            );
        }
    }

}
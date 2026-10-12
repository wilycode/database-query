<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Enum;

enum ParameterType: string
{
    case STRING   = 'string';
    case INTEGER  = 'integer';
    case BOOLEAN  = 'boolean';
    case NULL     = 'null';
    case LOB      = 'lob';
    case FLOAT    = 'float';
    case DATE     = 'date';
    case DATETIME = 'datetime';

    public static function fromPhpType(mixed $value): self
    {
        return match (true) {
            is_int($value)      => self::INTEGER,
            is_float($value)    => self::FLOAT,
            is_bool($value)     => self::BOOLEAN,
            is_null($value)     => self::NULL,
            is_resource($value) => self::LOB,
            is_string($value)   => self::detectStringType($value),
            default             => self::STRING,
        };
    }

    private static function detectStringType(string $value): self
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return self::DATE;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
            return self::DATETIME;
        }

        return self::STRING;
    }

}

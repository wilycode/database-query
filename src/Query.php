<?php

declare(strict_types=1);

namespace WilyCode\Database\Query;

use WilyCode\Database\Query\Expression\RawExpression;
use WilyCode\Database\Query\Statement\Delete;
use WilyCode\Database\Query\Statement\Insert;
use WilyCode\Database\Query\Statement\RawQuery;
use WilyCode\Database\Query\Statement\Select;
use WilyCode\Database\Query\Statement\Union;
use WilyCode\Database\Query\Statement\Update;

/** Static factory for query statements and raw expressions. */
final class Query
{
    public static function select(string $table = '', ?string $alias = null): Select
    {
        return new Select($table, $alias);
    }

    public static function insert(string $table): Insert
    {
        return new Insert($table);
    }

    public static function update(string $table): Update
    {
        return new Update($table);
    }

    public static function delete(string $table): Delete
    {
        return new Delete($table);
    }

    /** @param array<int|string, mixed> $parameters */
    public static function raw(string $sql, array $parameters = []): RawQuery
    {
        return new RawQuery($sql, $parameters);
    }

    /**
     * Convenience helper for RawExpression (e.g. for columnRaw / whereExp / havingExp).
     */
    /** @param array<int|string, mixed> $parameters */
    public static function rawExp(string $sql, array $parameters = []): RawExpression
    {
        return RawExpression::raw($sql, $parameters);
    }

    /**
     * UNION helper.
     */
    public static function union(Select ...$selects): Union
    {
        $union = new Union();
        foreach ($selects as $select) {
            $union->add($select);
        }
        return $union;
    }
}

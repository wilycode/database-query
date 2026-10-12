<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Expression;

use WilyCode\Database\Query\CompiledQuery;
use WilyCode\Database\Query\ParameterBag;
use WilyCode\Database\Query\Support\ParameterImporter;
use WilyCode\Database\Query\Statement\Select;

/**
 * Correlated JSON array subquery.
 *
 * Compilation (requires MySQL 8+ / MariaDB 10.5+):
 *   (SELECT COALESCE(JSON_ARRAYAGG(<item>), JSON_ARRAY()) <FROM+JOIN+WHERE...>)
 *
 * The provided $query acts as the source (FROM/JOIN/WHERE...).
 * The $item can be a string SQL fragment or an Expression (e.g. JsonObject).
 *
 * Nested objects are supported by using JsonObject inside $item, and JsonObject values
 * can in turn contain JsonArraySubquery instances.
 */
final class JsonArraySubquery implements Expression, ParameterPrefixAware
{
    public function __construct(
        private readonly Select $query,
        private readonly string|Expression $item,
        private readonly bool $coalesceEmptyArray = true,
        private readonly bool $distinct = false,
        private readonly bool $includeGroupBy = false,
        private readonly bool $includeHaving = false,
    ) {}

    private ?string $paramPrefix = null;

    /**
     * Set an explicit prefix for parameters merged from this subquery.
     * Examples:
     *   ->paramPrefix('comments') => :comments_param_1
     * If not set, a unique prefix based on spl_object_id() will be used.
     */
    public function paramPrefix(?string $prefix): static
    {
        $prefix = $prefix !== null ? trim($prefix) : null;
        if ($prefix === '') {
            $prefix = null;
        }

        if ($prefix !== null && !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $prefix)) {
            throw new \InvalidArgumentException('Invalid param prefix: ' . $prefix);
        }

        $this->paramPrefix = $prefix;
        return $this;
    }

    public function hasParamPrefix(): bool
    {
        return $this->paramPrefix !== null && $this->paramPrefix !== '';
    }

    private function resolveParamPrefix(): string
    {
        return $this->hasParamPrefix() ? (string) $this->paramPrefix : ('sub_' . spl_object_id($this));
    }

    public static function of(
        Select $query,
        string|Expression $item,
        bool $coalesceEmptyArray = true,
        bool $distinct = false,
        bool $includeGroupBy = false,
        bool $includeHaving = false,
    ): self {
        return new self($query, $item, $coalesceEmptyArray, $distinct, $includeGroupBy, $includeHaving);
    }

    public function compile(ParameterBag $bag): string
    {
        $subBag = new ParameterBag();
        $itemSql = $this->item instanceof Expression
            ? $this->item->compile($subBag)
            : $this->item;

        $aggregate = 'JSON_ARRAYAGG('
            . ($this->distinct ? 'DISTINCT ' : '')
            . $itemSql
            . ')';

        if ($this->coalesceEmptyArray) {
            $aggregate = 'COALESCE(' . $aggregate . ', JSON_ARRAY())';
        }

        $body = $this->query->compileBody(
            $subBag,
            $this->includeGroupBy,
            $this->includeHaving,
        );

        $compiled = new CompiledQuery(
            '(SELECT ' . $aggregate . ' ' . $body . ')',
            $subBag->getParameters(),
            $subBag->getTypes(),
        );

        return ParameterImporter::import($compiled, $bag, $this->resolveParamPrefix());
    }
}

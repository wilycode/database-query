<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Support;

use InvalidArgumentException;
use WilyCode\Database\Query\CompiledQuery;
use WilyCode\Database\Query\ParameterBag;

/** Imports SQL parameters into a parent parameter bag. @internal */
final class ParameterImporter
{
    private function __construct() {}

    public static function import(
        CompiledQuery $query,
        ParameterBag $target,
        ?string $prefix = null,
    ): string {
        $source = new ParameterBag();
        foreach ($query->params as $placeholder => $value) {
            $source->add($value, $placeholder, $query->types[$placeholder] ?? null);
        }

        return self::importSql($query->sql, $source, $target, $prefix);
    }

    public static function importSql(
        string $sql,
        ParameterBag $source,
        ParameterBag $target,
        ?string $prefix = null,
    ): string {
        if ($source === $target) {
            return $sql;
        }

        $params = [];
        foreach ($source->getParameters() as $placeholder => $value) {
            $params[ltrim($placeholder, ':')] = $value;
        }

        $types = [];
        foreach ($source->getTypes() as $placeholder => $type) {
            $types[ltrim($placeholder, ':')] = $type;
        }

        $mapped = [];

        return SqlPlaceholderRewriter::rewrite(
            $sql,
            null,
            static function (string $name) use (
                $params,
                $types,
                $target,
                $prefix,
                &$mapped,
            ): string {
                if (!array_key_exists($name, $params)) {
                    throw new InvalidArgumentException(sprintf(
                        'Placeholder ":%s" is not available in the source parameter bag.',
                        $name,
                    ));
                }

                if (isset($mapped[$name])) {
                    return $mapped[$name];
                }

                $preferredName = $prefix !== null ? $prefix . '_' . $name : $name;

                return $mapped[$name] = !$target->has($preferredName)
                    ? $target->add($params[$name], $preferredName, $types[$name] ?? null)
                    : $target->addAuto($params[$name], $prefix ?? 'param', $types[$name] ?? null);
            },
        );
    }
}

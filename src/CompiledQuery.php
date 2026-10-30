<?php

declare(strict_types=1);

namespace WilyCode\Database\Query;

use InvalidArgumentException;
use WilyCode\Database\Query\Dialect\DialectInterface;
use WilyCode\Database\Query\Enum\ParameterType;
use WilyCode\Database\Query\Statement\QueryInterface;
use WilyCode\Database\Query\Support\SqlPlaceholderRewriter;

/** Canonical immutable SQL query with unique named placeholders. */
final readonly class CompiledQuery implements QueryInterface
{
    public string $sql;

    /** @var array<string, mixed> Keys include the leading `:` and follow SQL occurrence order. */
    public array $params;

    /** @var array<string, ParameterType|null> Keys include the leading `:` and follow SQL occurrence order. */
    public array $types;

    /**
     * @param array<string, mixed> $params Keys include the leading `:`.
     * @param array<string, mixed> $types Keys include the leading `:`; values must be ParameterType|null.
     */
    public function __construct(string $sql, array $params = [], array $types = [])
    {
        foreach ($params as $placeholder => $_) {
            self::assertPlaceholderKey($placeholder, 'parameter');
        }

        $validatedTypes = [];
        foreach ($types as $placeholder => $type) {
            self::assertPlaceholderKey($placeholder, 'parameter type');
            if (!array_key_exists($placeholder, $params)) {
                throw new InvalidArgumentException(sprintf(
                    'Parameter type "%s" has no matching value.',
                    $placeholder,
                ));
            }
            if ($type !== null && !$type instanceof ParameterType) {
                throw new InvalidArgumentException(sprintf(
                    'Parameter type "%s" must be a %s value or null.',
                    $placeholder,
                    ParameterType::class,
                ));
            }
            $validatedTypes[$placeholder] = $type;
        }

        $orderedParams = [];
        $orderedTypes = [];

        SqlPlaceholderRewriter::rewrite(
            $sql,
            static function (): never {
                throw new InvalidArgumentException(
                    'Canonical compiled queries cannot contain positional placeholders.',
                );
            },
            static function (string $name) use ($params, $validatedTypes, &$orderedParams, &$orderedTypes): string {
                $placeholder = ':' . $name;
                if (!array_key_exists($placeholder, $params)) {
                    throw new InvalidArgumentException(sprintf(
                        'Placeholder "%s" has no matching value.',
                        $placeholder,
                    ));
                }
                if (array_key_exists($placeholder, $orderedParams)) {
                    throw new InvalidArgumentException(sprintf(
                        'Canonical placeholder "%s" must be unique.',
                        $placeholder,
                    ));
                }

                $orderedParams[$placeholder] = $params[$placeholder];
                if (array_key_exists($placeholder, $validatedTypes)) {
                    $orderedTypes[$placeholder] = $validatedTypes[$placeholder];
                }

                return $placeholder;
            },
        );

        foreach ($params as $placeholder => $_) {
            if (!array_key_exists($placeholder, $orderedParams)) {
                throw new InvalidArgumentException(sprintf(
                    'Parameter "%s" is not used by the SQL query.',
                    $placeholder,
                ));
            }
        }

        $this->sql = $sql;
        $this->params = $orderedParams;
        $this->types = $orderedTypes;
    }

    public function compile(?DialectInterface $dialect = null): self
    {
        return $this;
    }

    private static function assertPlaceholderKey(int|string $placeholder, string $label): void
    {
        if (
            !is_string($placeholder)
            || preg_match('/^:[a-zA-Z_][a-zA-Z0-9_]*$/', $placeholder) !== 1
        ) {
            throw new InvalidArgumentException(sprintf(
                'Canonical %s keys must use the format ":name".',
                $label,
            ));
        }
    }
}

<?php

declare(strict_types=1);

namespace WilyCode\Database\Query;

use BackedEnum;
use DateTimeInterface;
use InvalidArgumentException;
use WilyCode\Database\Query\Dialect\DialectInterface;
use WilyCode\Database\Query\Enum\ParameterType;

final class ParameterBag
{
    /** @var array<string,mixed> keys WITHOUT ":" */
    private array $namedParameters = [];

    /** @var array<string,ParameterType> keys WITHOUT ":" */
    private array $namedTypes  = [];

    private int $counter = 0;

    public function __construct(private ?DialectInterface $dialect = null) {}

    public function dialect(): DialectInterface
    {
        return $this->dialect ?? Query::dialectDefault();
    }

    public function withDialect(DialectInterface $dialect): self
    {
        $copy = clone $this;
        $copy->dialect = $dialect;
        return $copy;
    }

    public function add(mixed $value, ?string $name = null, ?ParameterType $type = null): string
    {
        if (is_array($value)) {
            throw new InvalidArgumentException(
                'Arrays are not supported in ParameterBag::add(). Use ParameterProcessor expansion.'
            );
        }

        $value = $this->normalizeValue($value, $type);
        $type  = $this->determineType($value, $type);

        // NAMED: if a name is provided, re-use it (this allows using :x in multiple places)
        if ($name !== null) {
            $name = ltrim($name, ':');

            if (array_key_exists($name, $this->namedParameters)) {
                if ($this->namedParameters[$name] !== $value || $this->namedTypes[$name] !== $type) {
                    throw new InvalidArgumentException(sprintf(
                        'Parameter "%s" was already registered with a different value or type.',
                        $name,
                    ));
                }

                return ':' . $name;
            }

            $this->bumpCounterForName($name);

            $this->namedParameters[$name] = $value;
            $this->namedTypes[$name]      = $type;

            return ':' . $name;
        }

        return $this->addAuto($value, 'param', $type);
    }

    public function addAuto(mixed $value, string $prefix = 'param', ?ParameterType $type = null): string
    {
        if (is_array($value)) {
            throw new InvalidArgumentException(
                'Arrays are not supported in ParameterBag::addAuto(). Use addList().'
            );
        }

        $value = $this->normalizeValue($value, $type);
        $type  = $this->determineType($value, $type);

        $name = $this->nextName($prefix);
        $this->namedParameters[$name] = $value;
        $this->namedTypes[$name]      = $type;

        return ':' . $name;
    }

    /**
     * @param array<array-key, mixed> $values
     * @return list<string> placeholders (named), e.g. [':param_1', ':param_2']
     */
    public function addList(array $values, string $prefix = 'param', ?ParameterType $type = null): array
    {
        $placeholders = [];

        foreach ($values as $v) {
            if (is_array($v)) {
                throw new InvalidArgumentException('Nested arrays are not supported in parameters');
            }

            $v = $this->normalizeValue($v, $type);
            $t = $this->determineType($v, $type);

            $placeholders[] = $this->addAuto($v, $prefix, $t);
        }

        return $placeholders;
    }

    /**
     * Keep the internal counter in sync with explicitly provided parameter names.
     *
     * Without this, mixing named additions (e.g. ':param_10') with addAuto() could
     * accidentally reuse an existing name and overwrite a value.
     */
    private function bumpCounterForName(string $name): void
    {
        // Accept both "param_12" and "<prefix>_param_12" patterns.
        if (preg_match('/(?:^param_|_param_)(\d+)$/', $name, $m) === 1) {
            $n = (int) $m[1];
            if ($n > $this->counter) {
                $this->counter = $n;
            }
        }
    }

    private function nextName(string $prefix): string
    {
        $n = ++$this->counter;

        if ($prefix === '' || $prefix === 'param') {
            return 'param_' . $n;
        }

        return $prefix . '_param_' . $n;
    }

    private function normalizeValue(mixed $value, ?ParameterType $type): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            return match ($type) {
                ParameterType::DATE     => $value->format('Y-m-d'),
                ParameterType::DATETIME => $value->format('Y-m-d H:i:s'),
                default                 => $value->format('Y-m-d H:i:s'),
            };
        }

        return $value;
    }

    private function determineType(mixed $value, ?ParameterType $type = null): ParameterType
    {
        return $type ?? ParameterType::fromPhpType($value);
    }

    /**
     * @return array<string, ParameterType> keys WITH ":"
     */
    public function getTypes(): array
    {
        $out = [];
        foreach ($this->namedTypes as $name => $type) {
            $out[':' . $name] = $type;
        }
        return $out;
    }

    /**
     * @return array<string, mixed> keys WITH ":"
     */
    public function getParameters(): array
    {
        $out = [];
        foreach ($this->namedParameters as $name => $value) {
            $out[':' . $name] = $value;
        }
        return $out;
    }

    public function has(string $name): bool
    {
        return array_key_exists(ltrim($name, ':'), $this->namedParameters);
    }

}

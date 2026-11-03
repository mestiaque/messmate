<?php

namespace App\Support;

/**
 * Decimal-safe arithmetic on numeric strings (bcmath). Never use floats for
 * meal units or money.
 */
final class Decimal
{
    public static function of(string|int|float|null $value, int $scale = 4): string
    {
        $value = trim((string) ($value ?? '0'));

        if ($value === '' || ! is_numeric($value)) {
            $value = '0';
        }

        return bcadd($value, '0', $scale);
    }

    public static function add(string $a, string $b, int $scale = 4): string
    {
        return bcadd($a, $b, $scale);
    }

    /**
     * Multiply with half-up rounding to $scale (bcmath alone truncates).
     */
    public static function mul(string $a, string $b, int $scale = 4): string
    {
        return self::round(bcmul($a, $b, $scale + 6), $scale);
    }

    /**
     * @param  iterable<string>  $values
     */
    public static function sum(iterable $values, int $scale = 4): string
    {
        $total = '0';

        foreach ($values as $value) {
            $total = bcadd($total, self::of($value, $scale + 2), $scale + 2);
        }

        return self::round($total, $scale);
    }

    public static function round(string $value, int $scale = 4): string
    {
        $offset = '0.'.str_repeat('0', $scale).'5';

        return str_starts_with($value, '-')
            ? bcsub($value, $offset, $scale)
            : bcadd($value, $offset, $scale);
    }

    public static function compare(string $a, string $b, int $scale = 6): int
    {
        return bccomp($a, $b, $scale);
    }

    public static function isZero(string $value): bool
    {
        return bccomp($value, '0', 6) === 0;
    }

    /**
     * Split $total (2 dp) across keys in proportion to non-negative $weights so the
     * parts add up exactly; leftover paisa go to the largest remainders, ties by key order.
     *
     * @param  array<int|string, string>  $weights
     * @return array<int|string, string>
     */
    public static function allocate(string $total, array $weights): array
    {
        $sumWeights = self::sum($weights, 6);

        if (! $weights || self::isZero($sumWeights)) {
            return array_map(fn () => '0.00', $weights);
        }

        $negative = bccomp($total, '0', 2) < 0;
        $cents = (int) bcmul(ltrim($total, '-'), '100', 0);
        $parts = [];
        $assigned = 0;

        foreach ($weights as $key => $weight) {
            $exact = bcdiv(bcmul((string) $cents, self::of($weight, 6), 10), $sumWeights, 10);
            $floor = (int) explode('.', $exact)[0];
            $parts[$key] = ['cents' => $floor, 'fraction' => bcsub($exact, (string) $floor, 10)];
            $assigned += $floor;
        }

        // usort is stable in PHP 8, so equal remainders keep key order.
        $order = array_keys($parts);
        usort($order, fn ($a, $b) => bccomp($parts[$b]['fraction'], $parts[$a]['fraction'], 10));

        foreach ($order as $key) {
            if ($assigned >= $cents) {
                break;
            }
            $parts[$key]['cents']++;
            $assigned++;
        }

        return array_map(fn ($p) => ($negative ? '-' : '').bcdiv((string) $p['cents'], '100', 2), $parts);
    }
}

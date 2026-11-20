<?php

namespace Modules\Store\Domain\Shared;

use InvalidArgumentException;
use Modules\Store\Domain\Shared\Exceptions\CurrencyMismatch;

final readonly class Money
{
    private const EXPONENTS = [
        'JOD' => 3,
        'USD' => 2,
        'EUR' => 2,
        'GBP' => 2,
    ];

    public int $amountMinor;

    public string $currency;

    public function __construct(mixed $amountMinor, string $currency)
    {
        if (! is_int($amountMinor)) {
            throw new InvalidArgumentException('Money amount must be an integer minor-unit value.');
        }

        if ($amountMinor < 0) {
            throw new InvalidArgumentException('Money amount cannot be negative.');
        }

        if ($currency !== strtoupper($currency) || ! isset(self::EXPONENTS[$currency])) {
            throw new InvalidArgumentException('Unsupported or non-canonical currency code.');
        }

        $this->amountMinor = $amountMinor;
        $this->currency = $currency;
    }

    public static function fromDecimal(string $amount, string $currency): self
    {
        $currency = strtoupper(trim($currency));
        $exponent = self::exponent($currency);
        $amount = trim($amount);

        if (! preg_match('/^(0|[1-9][0-9]*)(?:\.([0-9]+))?$/', $amount, $matches)) {
            throw new InvalidArgumentException('Money must be provided as a non-negative decimal string.');
        }

        $fraction = $matches[2] ?? '';
        if (strlen($fraction) > $exponent) {
            throw new InvalidArgumentException("{$currency} accepts at most {$exponent} fractional digits.");
        }

        $minorString = ltrim(strtok($amount, '.').str_pad($fraction, $exponent, '0'), '0');
        $minorString = $minorString === '' ? '0' : $minorString;

        if (strlen($minorString) > strlen((string) PHP_INT_MAX)
            || (strlen($minorString) === strlen((string) PHP_INT_MAX) && strcmp($minorString, (string) PHP_INT_MAX) > 0)) {
            throw new InvalidArgumentException('Money amount exceeds the supported integer range.');
        }

        return new self((int) $minorString, $currency);
    }

    public static function exponent(string $currency): int
    {
        $currency = strtoupper(trim($currency));

        return self::EXPONENTS[$currency]
            ?? throw new InvalidArgumentException("Unsupported currency [{$currency}].");
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountMinor + $other->amountMinor, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        if ($other->amountMinor > $this->amountMinor) {
            throw new InvalidArgumentException('Money subtraction cannot produce a negative amount.');
        }

        return new self($this->amountMinor - $other->amountMinor, $this->currency);
    }

    public function percentage(int $basisPoints): self
    {
        if ($basisPoints < 0 || $basisPoints > 10_000) {
            throw new InvalidArgumentException('Percentage basis points must be between 0 and 10000.');
        }

        return new self(intdiv($this->amountMinor * $basisPoints, 10_000), $this->currency);
    }

    public function decimal(): string
    {
        $exponent = self::exponent($this->currency);
        $digits = str_pad((string) $this->amountMinor, $exponent + 1, '0', STR_PAD_LEFT);

        if ($exponent === 0) {
            return $digits;
        }

        return substr($digits, 0, -$exponent).'.'.substr($digits, -$exponent);
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new CurrencyMismatch($this->currency, $other->currency);
        }
    }
}

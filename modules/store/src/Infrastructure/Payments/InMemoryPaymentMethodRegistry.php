<?php

namespace Modules\Store\Infrastructure\Payments;

use LogicException;
use Modules\Store\Contracts\Payments\PaymentMethod;
use Modules\Store\Contracts\Payments\PaymentMethodRegistry;

final class InMemoryPaymentMethodRegistry implements PaymentMethodRegistry
{
    /** @var array<string, PaymentMethod> */
    private array $methods = [];

    public function __construct(private readonly string $defaultCode) {}

    public function register(PaymentMethod $method): void
    {
        $code = trim($method->code());

        if ($code === '') {
            throw new LogicException('A Store payment method must define a non-empty code.');
        }

        $this->methods[$code] = $method;
    }

    public function has(string $code): bool
    {
        return isset($this->methods[$code]);
    }

    public function get(string $code): ?PaymentMethod
    {
        return $this->methods[$code] ?? null;
    }

    public function default(): PaymentMethod
    {
        return $this->methods[$this->defaultCode]
            ?? throw new LogicException("The default Store payment method [{$this->defaultCode}] is not registered.");
    }

    public function all(): array
    {
        return array_filter(
            $this->methods,
            static fn (PaymentMethod $method): bool => $method->isAvailable(),
        );
    }
}

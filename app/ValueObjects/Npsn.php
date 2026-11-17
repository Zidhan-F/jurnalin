<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;

final readonly class Npsn
{
    public string $value;

    public function __construct(string $raw)
    {
        $cleaned = trim($raw);

        if (! preg_match('/^\d{8}$/', $cleaned)) {
            throw new InvalidArgumentException('NPSN wajib terdiri dari tepat 8 digit angka.');
        }

        $this->value = $cleaned;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

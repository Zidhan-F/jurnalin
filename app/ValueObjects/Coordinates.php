<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;

final readonly class Coordinates
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {
        if ($latitude < -90.0 || $latitude > 90.0) {
            throw new InvalidArgumentException('Latitude harus berada di antara -90 dan 90.');
        }

        if ($longitude < -180.0 || $longitude > 180.0) {
            throw new InvalidArgumentException('Longitude harus berada di antara -180 dan 180.');
        }
    }

    public function distanceMetersTo(self $target): int
    {
        $earthRadiusMeters = 6_371_000;
        $latFrom = deg2rad($this->latitude);
        $lonFrom = deg2rad($this->longitude);
        $latTo = deg2rad($target->latitude);
        $lonTo = deg2rad($target->longitude);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(
            (sin($latDelta / 2) ** 2) +
            cos($latFrom) * cos($latTo) * (sin($lonDelta / 2) ** 2)
        ));

        return (int) round($angle * $earthRadiusMeters);
    }
}

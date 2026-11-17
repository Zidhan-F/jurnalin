<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\ValueObjects\Coordinates;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CoordinatesTest extends TestCase
{
    public function test_it_rejects_invalid_latitude_or_longitude(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Coordinates(95.0, 107.6);
    }

    public function test_it_calculates_haversine_distance_accurately(): void
    {
        $hotel = new Coordinates(-6.9255000, 107.6365000);
        $samePoint = new Coordinates(-6.9255000, 107.6365000);
        $nearbyPoint = new Coordinates(-6.9260000, 107.6365000); // ~55m north/south

        $this->assertSame(0, $hotel->distanceMetersTo($samePoint));
        $distance = $hotel->distanceMetersTo($nearbyPoint);
        $this->assertGreaterThan(45, $distance);
        $this->assertLessThan(65, $distance);
    }
}

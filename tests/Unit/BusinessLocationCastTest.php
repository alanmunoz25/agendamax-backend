<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Business;
use PHPUnit\Framework\TestCase;

class BusinessLocationCastTest extends TestCase
{
    public function test_latitude_is_cast_to_float(): void
    {
        $business = new Business(['latitude' => '18.4861']);

        $this->assertIsFloat($business->latitude);
        $this->assertSame(18.4861, $business->latitude);
    }

    public function test_longitude_is_cast_to_float(): void
    {
        $business = new Business(['longitude' => '-69.9312']);

        $this->assertIsFloat($business->longitude);
        $this->assertSame(-69.9312, $business->longitude);
    }

    public function test_location_point_returns_null_when_latitude_is_null(): void
    {
        $business = new Business(['latitude' => null, 'longitude' => -69.9312]);

        $this->assertNull($business->locationPoint());
    }

    public function test_location_point_returns_null_when_longitude_is_null(): void
    {
        $business = new Business(['latitude' => 18.4861, 'longitude' => null]);

        $this->assertNull($business->locationPoint());
    }

    public function test_location_point_returns_array_when_both_coordinates_set(): void
    {
        $business = new Business(['latitude' => 18.4861, 'longitude' => -69.9312]);

        $point = $business->locationPoint();

        $this->assertIsArray($point);
        $this->assertSame(18.4861, $point['lat']);
        $this->assertSame(-69.9312, $point['lng']);
    }
}

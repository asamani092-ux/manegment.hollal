<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Geofenced punch site (path-2ب): coordinates + radius in meters.
 */
class AttendanceLocation extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'name', 'latitude', 'longitude', 'radius_meters', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_meters' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<AttendanceRecord, $this> */
    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'attendance_location_id');
    }

    /** Haversine distance in meters. Time: O(1) */
    public function distanceMeters(float $lat, float $lng): float
    {
        $earth = 6371000.0;
        $φ1 = deg2rad((float) $this->latitude);
        $φ2 = deg2rad($lat);
        $Δφ = deg2rad($lat - (float) $this->latitude);
        $Δλ = deg2rad($lng - (float) $this->longitude);
        $a = sin($Δφ / 2) ** 2 + cos($φ1) * cos($φ2) * sin($Δλ / 2) ** 2;

        return 2 * $earth * asin(min(1.0, sqrt($a)));
    }

    /** Whether the point falls inside this location's radius. */
    public function contains(float $lat, float $lng): bool
    {
        return $this->distanceMeters($lat, $lng) <= (float) $this->radius_meters;
    }

    /**
     * First active location containing the coordinates, or null.
     * Time: O(n) locations
     */
    public static function findContaining(float $lat, float $lng): ?self
    {
        $candidates = static::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        foreach ($candidates as $location) {
            if ($location->contains($lat, $lng)) {
                return $location;
            }
        }

        return null;
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\ValueObjects\Coordinates;
use Carbon\CarbonImmutable;
use DomainException;

final class RecordCheckOutAction
{
    public function handle(
        Attendance $attendance,
        ?Coordinates $studentCoordinates,
        ?string $photoPath = null,
    ): Attendance {
        if (! $attendance->status->countsTowardAttendanceScore()) {
            throw new DomainException('Absensi dengan status Sakit/Izin tidak memerlukan absen pulang.');
        }

        if ($attendance->check_out_at !== null) {
            throw new DomainException('Anda sudah melakukan absen pulang hari ini.');
        }

        $placement = $attendance->placement;
        // Koordinat pulang hanya disimpan bila kebijakan lokasi berlaku dan persetujuan wali tercatat (S13).
        if (! $placement->pklPeriod->require_location || ! $placement->student->trackingAllowed()) {
            $studentCoordinates = null;
        }
        $distanceMeters = $studentCoordinates?->distanceMetersTo($placement->attendancePoint());

        $attendance->update([
            'check_out_at' => CarbonImmutable::now('UTC'),
            'check_out_lat' => $studentCoordinates?->latitude,
            'check_out_lng' => $studentCoordinates?->longitude,
            'check_out_distance_m' => $distanceMeters,
            'check_out_photo_path' => $photoPath,
        ]);

        return $attendance->refresh();
    }
}

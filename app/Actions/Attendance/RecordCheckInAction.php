<?php

declare(strict_types=1);

namespace App\Actions\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\PlacementStatus;
use App\Models\Attendance;
use App\Models\Placement;
use App\ValueObjects\Coordinates;
use Carbon\CarbonImmutable;
use DomainException;

/**
 * Presensi masuk (P37). Waktu selalu waktu server. Lokasi dan swafoto hanya dipakai bila kebijakan
 * periode mewajibkannya DAN peserta dewasa atau persetujuan wali tercatat (S13). Server memberi tanda
 * (di luar radius, akurasi rendah, lompatan jarak, perangkat berganti) untuk ditinjau guru; tanda bukan sanksi.
 */
final class RecordCheckInAction
{
    public const LOW_ACCURACY_METERS = 150;

    public const JUMP_METERS = 30000;

    public function handle(
        Placement $placement,
        ?Coordinates $studentCoordinates = null,
        ?int $gpsAccuracyMeters = null,
        ?string $photoPath = null,
        bool $isOutsideDuty = false,
        ?string $exceptionReason = null,
        ?AttendanceStatus $leaveStatus = null,
        ?string $deviceHash = null,
    ): Attendance {
        if ($placement->status !== PlacementStatus::Active) {
            throw new DomainException('Penempatan PKL tidak berstatus aktif.');
        }

        $school = $placement->school;
        $period = $placement->pklPeriod;
        $nowLocal = CarbonImmutable::now($school->timezone);
        $todayDate = $nowLocal->toDateString();

        $existing = Attendance::query()
            ->where('placement_id', $placement->id)
            ->whereDate('attendance_date', $todayDate)
            ->exists();

        if ($existing) {
            throw new DomainException('Anda sudah mencatat kehadiran untuk hari ini.');
        }

        if ($leaveStatus !== null) {
            if (! in_array($leaveStatus, [AttendanceStatus::Sick, AttendanceStatus::Permission], true)) {
                throw new DomainException('Status izin hanya mendukung Sakit atau Izin.');
            }

            $reason = trim((string) $exceptionReason);
            if ($reason === '') {
                throw new DomainException('Keterangan izin/sakit wajib diisi.');
            }

            return Attendance::query()->create([
                'school_id' => $placement->school_id,
                'placement_id' => $placement->id,
                'student_id' => $placement->student_id,
                'attendance_date' => $todayDate,
                'status' => $leaveStatus,
                'check_in_at' => $nowLocal->utc(),
                'check_in_photo_path' => $photoPath,
                'is_within_radius' => true,
                'exception_reason' => $reason,
            ]);
        }

        $tracking = $period->require_location && $placement->student->trackingAllowed();
        $selfieAllowed = $placement->student->trackingAllowed();

        if ($tracking && $studentCoordinates === null) {
            throw new DomainException('Koordinat GPS perangkat wajib tersedia untuk absen hadir.');
        }
        if (! $tracking) {
            // Tanpa kebijakan lokasi atau tanpa persetujuan wali: koordinat tidak disimpan sama sekali.
            $studentCoordinates = null;
            $gpsAccuracyMeters = null;
        }
        if (! $selfieAllowed) {
            $photoPath = null;
        }

        if ($selfieAllowed && $period->selfieRequired() && empty($photoPath)) {
            throw new DomainException('Foto selfie kehadiran wajib dilampirkan sesuai kebijakan sekolah.');
        }

        $flags = [];
        $distanceMeters = null;
        $withinRadius = true;
        $allowedRadius = $placement->attendanceRadius();

        if ($studentCoordinates !== null) {
            $distanceMeters = $studentCoordinates->distanceMetersTo($placement->attendancePoint());
            $withinRadius = $distanceMeters <= $allowedRadius;

            if (! $withinRadius) {
                if (! $isOutsideDuty) {
                    throw new DomainException("Jarak Anda {$distanceMeters} m dari {$placement->company->name} (batas {$allowedRadius} m). Aktifkan opsi Tugas Luar Area dan tulis alasan jika sedang bertugas di luar lokasi tempat PKL.");
                }

                if (trim((string) $exceptionReason) === '') {
                    throw new DomainException('Alasan tugas luar area wajib diisi untuk persetujuan guru pembimbing.');
                }
                $flags[] = 'outside_radius';
            }

            if ($gpsAccuracyMeters !== null && $gpsAccuracyMeters > self::LOW_ACCURACY_METERS) {
                $flags[] = 'low_accuracy';
            }

            $previous = Attendance::query()
                ->where('placement_id', $placement->id)
                ->whereNotNull('check_in_lat')
                ->latest('attendance_date')
                ->first();
            if ($previous !== null) {
                $jump = (new Coordinates((float) $previous->check_in_lat, (float) $previous->check_in_lng))->distanceMetersTo($studentCoordinates);
                if ($jump > self::JUMP_METERS && $isOutsideDuty === false) {
                    $flags[] = 'distance_jump';
                }
            }
        }

        if ($deviceHash !== null) {
            $lastDevice = Attendance::query()->where('placement_id', $placement->id)->whereNotNull('device_hash')->latest('attendance_date')->value('device_hash');
            if ($lastDevice !== null && $lastDevice !== $deviceHash) {
                $flags[] = 'device_changed';
            }
        }

        $scheduledStart = CarbonImmutable::parse("{$todayDate} {$placement->work_start_time}", $school->timezone);
        $lateThreshold = $scheduledStart->addMinutes($period->lateToleranceMinutes());
        $status = $nowLocal->greaterThan($lateThreshold) ? AttendanceStatus::Late : AttendanceStatus::Present;

        return Attendance::query()->create([
            'school_id' => $placement->school_id,
            'placement_id' => $placement->id,
            'student_id' => $placement->student_id,
            'attendance_date' => $todayDate,
            'status' => $status,
            'check_in_at' => $nowLocal->utc(),
            'check_in_lat' => $studentCoordinates?->latitude,
            'check_in_lng' => $studentCoordinates?->longitude,
            'check_in_distance_m' => $distanceMeters,
            'check_in_gps_accuracy_m' => $gpsAccuracyMeters,
            'check_in_photo_path' => $photoPath,
            'is_within_radius' => $withinRadius,
            'exception_reason' => trim((string) $exceptionReason) !== '' ? trim((string) $exceptionReason) : null,
            'flags' => $flags ?: null,
            'review_status' => $flags ? 'pending' : null,
            'device_hash' => $deviceHash,
        ]);
    }
}

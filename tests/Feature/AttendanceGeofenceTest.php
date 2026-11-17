<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Attendance\RecordCheckInAction;
use App\Actions\Attendance\RecordCheckOutAction;
use App\Enums\AttendanceStatus;
use App\Models\Placement;
use App\Models\School;
use App\Models\User;
use App\Support\TenantContext;
use App\ValueObjects\Coordinates;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceGeofenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_within_radius_can_check_in_and_check_out(): void
    {
        $this->seed();

        $schoolA = School::query()->where('npsn', '20219101')->firstOrFail();
        $nadiaUser = $this->userByEmail('siswa.nadia@smkn1par-bdg.sch.id');

        TenantContext::runInSchoolScope($schoolA->id, function () use ($nadiaUser): void {
            $placement = Placement::query()->where('student_id', $nadiaUser->student->id)->firstOrFail();
            $hotelCoords = $placement->company->coordinates();

            $attendance = (new RecordCheckInAction())->handle(
                placement: $placement,
                studentCoordinates: new Coordinates($hotelCoords->latitude + 0.0002, $hotelCoords->longitude),
                gpsAccuracyMeters: 10,
            );

            $this->assertTrue($attendance->is_within_radius);
            $this->assertLessThanOrEqual(250, $attendance->check_in_distance_m);
            $this->assertContains($attendance->status, [AttendanceStatus::Present, AttendanceStatus::Late]);

            $checkedOut = (new RecordCheckOutAction())->handle($attendance, $hotelCoords);
            $this->assertNotNull($checkedOut->check_out_at);
        });
    }

    public function test_student_outside_radius_without_reason_is_rejected_but_allowed_with_outside_duty_reason(): void
    {
        $this->seed();

        $schoolA = School::query()->where('npsn', '20219101')->firstOrFail();
        $nadiaUser = $this->userByEmail('siswa.nadia@smkn1par-bdg.sch.id');

        TenantContext::runInSchoolScope($schoolA->id, function () use ($nadiaUser): void {
            $placement = Placement::query()->where('student_id', $nadiaUser->student->id)->firstOrFail();
            $hotelCoords = $placement->company->coordinates();
            $farCoords = new Coordinates($hotelCoords->latitude + 0.01, $hotelCoords->longitude); // ~1.1 km

            $thrown = false;
            try {
                (new RecordCheckInAction())->handle(
                    placement: $placement,
                    studentCoordinates: $farCoords,
                    isOutsideDuty: false,
                );
            } catch (DomainException) {
                $thrown = true;
            }
            $this->assertTrue($thrown);

            $attendance = (new RecordCheckInAction())->handle(
                placement: $placement,
                studentCoordinates: $farCoords,
                isOutsideDuty: true,
                exceptionReason: 'Tugas event catering hotel di luar lokasi.',
            );

            $this->assertFalse($attendance->is_within_radius);
            $this->assertGreaterThan(500, $attendance->check_in_distance_m);
            $this->assertNull($attendance->approved_by_teacher_id);
        });
    }
}

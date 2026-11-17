<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Journal\SubmitJournalAction;
use App\Actions\Journal\ValidateJournalAction;
use App\Enums\JournalStatus;
use App\Models\Placement;
use App\Models\School;
use App\Models\User;
use App\Support\TenantContext;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalStateMachineTest extends TestCase
{
    use RefreshDatabase;

    public function test_journal_lifecycle_and_lock_after_approval(): void
    {
        $this->seed();

        $schoolA = School::query()->where('npsn', '20219101')->firstOrFail();
        $nadiaUser = $this->userByEmail('siswa.nadia@smkn1par-bdg.sch.id');
        $teacherUser = $this->userByEmail('guru.perhotelan@smkn1par-bdg.sch.id');

        TenantContext::runInSchoolScope($schoolA->id, function () use ($nadiaUser, $teacherUser): void {
            $placement = Placement::query()->where('student_id', $nadiaUser->student->id)->firstOrFail();
            $teacher = $teacherUser->teacher;

            $submit = new SubmitJournalAction();
            $validate = new ValidateJournalAction();

            $journal = $submit->handle($placement, [
                'journal_date' => now()->toDateString(),
                'work_unit' => 'Front Office',
                'activity_title' => 'Menyambut tamu VIP',
                'activity_description' => 'Melakukan greeting dan verifikasi dokumen kedatangan tamu VIP lantai 12.',
                'duration_minutes' => 480,
            ], JournalStatus::Submitted);

            $this->assertSame(JournalStatus::Submitted, $journal->status);

            // Guru minta revisi
            $revised = $validate->handle($journal, $teacher, JournalStatus::RevisionNeeded, 'Lampirkan nama sistem PMS.');
            $this->assertSame(JournalStatus::RevisionNeeded, $revised->status);

            // Siswa perbaiki dan kirim ulang
            $resubmitted = $submit->handle($placement, [
                'journal_date' => now()->toDateString(),
                'work_unit' => 'Front Office',
                'activity_title' => 'Menyambut tamu VIP via Opera PMS',
                'activity_description' => 'Melakukan greeting dan verifikasi dokumen kedatangan tamu VIP melalui Opera PMS.',
                'duration_minutes' => 480,
            ], JournalStatus::Submitted, $revised);
            $this->assertSame(JournalStatus::Submitted, $resubmitted->status);

            // Guru setujui
            $approved = $validate->handle($resubmitted, $teacher, JournalStatus::Approved);
            $this->assertSame(JournalStatus::Approved, $approved->status);

            // Setelah disetujui, siswa tidak dapat mengubahnya lagi
            $this->expectException(DomainException::class);
            $submit->handle($placement, [
                'journal_date' => now()->toDateString(),
                'work_unit' => 'Front Office',
                'activity_title' => 'Coba Ubah',
                'activity_description' => 'Percobaan mengubah jurnal yang sudah disetujui oleh guru.',
            ], JournalStatus::Submitted, $approved);
        });
    }
}

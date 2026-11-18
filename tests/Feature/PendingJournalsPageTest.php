<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\JournalStatus;
use App\Models\Journal;
use App\Models\School;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Halaman admin: jurnal menunggu validasi dikelompokkan per guru pembimbing. */
class PendingJournalsPageTest extends TestCase
{
    use RefreshDatabase;

    private const JOURNAL_TITLE = 'Setup Meja Prasmanan dan Coffee Break 150 Pax';

    private function schoolA(): School
    {
        return School::query()->where('npsn', '20219101')->firstOrFail();
    }

    private function pendingJournal(): Journal
    {
        return TenantContext::runInSchoolScope($this->schoolA()->id, fn () => Journal::query()
            ->where('status', JournalStatus::Submitted)
            ->where('activity_title', self::JOURNAL_TITLE)
            ->firstOrFail());
    }

    public function test_admin_sees_pending_journals_grouped_by_teacher(): void
    {
        $this->seed();
        $admin = $this->userByEmail('admin@smkn1par-bdg.sch.id');

        $this->actingAs($admin)->get('/admin/jurnal-tertunda')
            ->assertOk()
            ->assertSee('Jurnal tertunda per guru')
            ->assertSee('Budi Prasetyo')
            ->assertSee(self::JOURNAL_TITLE)
            ->assertSee('Bagas Pratama');
    }

    public function test_stale_filter_only_lists_journals_older_than_three_days(): void
    {
        $this->seed();
        $admin = $this->userByEmail('admin@smkn1par-bdg.sch.id');
        $journal = $this->pendingJournal();
        $today = now($this->schoolA()->timezone)->startOfDay();

        TenantContext::runInSchoolScope($this->schoolA()->id, fn () => $journal->forceFill(['journal_date' => $today->copy()->subDays(2)])->save());
        $this->actingAs($admin)->get('/admin/jurnal-tertunda?filter=lama')
            ->assertOk()
            ->assertDontSee(self::JOURNAL_TITLE)
            ->assertSee('Tidak ada jurnal yang menunggu lebih dari 3 hari');

        TenantContext::runInSchoolScope($this->schoolA()->id, fn () => $journal->forceFill(['journal_date' => $today->copy()->subDays(6)])->save());
        $this->actingAs($admin)->get('/admin/jurnal-tertunda?filter=lama')
            ->assertOk()
            ->assertSee(self::JOURNAL_TITLE)
            ->assertSee('1 lebih dari 3 hari');

        // Angka di dashboard memakai ambang yang sama dan menautkan ke halaman ini.
        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('Periksa jurnal peserta')
            ->assertSee('/admin/jurnal-tertunda?filter=lama', false);
    }

    public function test_teacher_cannot_open_admin_page(): void
    {
        $this->seed();

        $this->actingAs($this->userByEmail('guru.perhotelan@smkn1par-bdg.sch.id'))
            ->get('/admin/jurnal-tertunda')
            ->assertForbidden();
    }

    public function test_other_school_admin_never_sees_school_a_journals(): void
    {
        $this->seed();

        $this->actingAs($this->userByEmail('admin@smkharapan-bali.sch.id'))
            ->get('/admin/jurnal-tertunda')
            ->assertOk()
            ->assertDontSee(self::JOURNAL_TITLE)
            ->assertDontSee('Budi Prasetyo');
    }
}

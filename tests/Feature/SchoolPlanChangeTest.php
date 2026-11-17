<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SchoolPlan;
use App\Models\BillingGroup;
use App\Models\PklPeriod;
use App\Models\School;
use App\Models\SchoolPayment;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Admin sekolah mengganti paket sekolah sendiri: naik langsung / lewat selisih, turun terjadwal, Free terkunci. */
class SchoolPlanChangeTest extends TestCase
{
    use RefreshDatabase;

    private function school(): School
    {
        return School::query()->where('npsn', '20219101')->firstOrFail();
    }

    /** Membuat grup dan memverifikasi pembayarannya dengan paket sekolah saat ini. Mengembalikan jumlah anggota. */
    private function paidGroup($admin, $super): int
    {
        $period = TenantContext::runInSchoolScope($admin->school_id, function () {
            $period = PklPeriod::firstOrFail();
            $period->update(['start_date' => '2027-01-01', 'end_date' => '2027-06-30']);

            return $period;
        });
        $this->actingAs($admin)->post("/admin/payments/periods/{$period->id}/groups", [
            'name' => 'Bimbingan Bu Sari', 'class_name' => 'XII Perhotelan 1', 'confirm_members' => 1,
        ])->assertSessionHasNoErrors();
        $group = TenantContext::runInSchoolScope($admin->school_id, fn () => BillingGroup::withCount('members')->firstOrFail());
        $quote = $group->members_count * $this->school()->plan()->price() * count($period->billingCycles());
        $this->post("/admin/payments/groups/{$group->id}/proof", ['quote' => $quote, 'proof' => UploadedFile::fake()->image('b.png')])->assertSessionHasNoErrors();
        $payment = TenantContext::runInSchoolScope($admin->school_id, fn () => SchoolPayment::where('status', 'pending')->firstOrFail());
        $this->actingAs($super)->post("/super-admin/payments/{$payment->id}/review", ['decision' => 'verified', 'version' => hash('sha256', $payment->proof_path)])->assertSessionHasNoErrors();

        return $group->members_count;
    }

    public function test_upgrade_and_downgrade_are_immediate_without_running_groups(): void
    {
        $this->seed();
        $admin = $this->userByEmail('admin@smkn1par-bdg.sch.id');
        $this->assertSame(SchoolPlan::Regular, $this->school()->plan());

        $this->actingAs($admin)->get('/admin/payments')->assertOk()->assertSee('Naik ke Pro sekarang');
        $this->post('/admin/plan/upgrade')->assertSessionHasNoErrors();
        $this->assertSame(SchoolPlan::Pro, $this->school()->plan());

        $this->post('/admin/plan/downgrade')->assertSessionHasErrors('confirm_downgrade');
        $this->post('/admin/plan/downgrade', ['confirm_downgrade' => 1])->assertSessionHasNoErrors();
        $this->assertSame(SchoolPlan::Regular, $this->school()->plan());
    }

    public function test_upgrade_with_running_regular_group_needs_verified_difference(): void
    {
        $this->travelTo(Carbon::parse('2027-01-01 09:00', 'Asia/Jakarta'));
        $this->seed();
        Storage::fake('local');
        $admin = $this->userByEmail('admin@smkn1par-bdg.sch.id');
        $super = $this->userByEmail('superadmin@jurnalin.id');
        $members = $this->paidGroup($admin, $super);

        // 1 Jan s.d. 30 Jun = 6 bulan sisa; selisih Rp2.000 per siswa per bulan.
        $expected = $members * 6 * (SchoolPlan::Pro->price() - SchoolPlan::Regular->price());
        $this->actingAs($admin)->get('/admin/payments')->assertSee('Total selisih')->assertSee('Rp'.number_format($expected, 0, ',', '.'));

        $this->post('/admin/plan/upgrade')->assertSessionHasErrors('quote');
        $this->post('/admin/plan/upgrade', ['quote' => 1000, 'proof' => UploadedFile::fake()->image('s.png')])->assertSessionHasErrors('quote');
        $this->post('/admin/plan/upgrade', ['quote' => $expected, 'proof' => UploadedFile::fake()->image('s.png')])->assertSessionHasNoErrors();
        $this->assertSame(SchoolPlan::Regular, $this->school()->plan(), 'Paket belum berubah sebelum diverifikasi');
        $this->post('/admin/plan/upgrade', ['quote' => $expected, 'proof' => UploadedFile::fake()->image('s.png')])->assertSessionHasErrors('plan');

        $upgrade = TenantContext::runAsSuperAdmin(fn () => SchoolPayment::where('kind', 'upgrade')->firstOrFail());
        $this->assertSame($expected, $upgrade->amount());
        $this->actingAs($super)->get('/super-admin/payments')->assertSee('Selisih naik ke Sekolah Pro');
        $this->post("/super-admin/payments/{$upgrade->id}/review", ['decision' => 'verified', 'version' => hash('sha256', $upgrade->proof_path)])->assertSessionHasNoErrors();
        $this->assertSame(SchoolPlan::Pro, $this->school()->plan());
    }

    public function test_downgrade_with_running_pro_group_is_scheduled_after_it_ends_and_can_be_cancelled(): void
    {
        $this->travelTo(Carbon::parse('2027-01-01 09:00', 'Asia/Jakarta'));
        $this->seed();
        Storage::fake('local');
        $this->school()->update(['subscription_tier' => SchoolPlan::Pro->value]);
        $admin = $this->userByEmail('admin@smkn1par-bdg.sch.id');
        $this->paidGroup($admin, $this->userByEmail('superadmin@jurnalin.id'));

        $this->actingAs($admin)->post('/admin/plan/downgrade', ['confirm_downgrade' => 1])->assertSessionHasNoErrors();
        $this->assertSame('2027-07-01', $this->school()->scheduled_plan_on->toDateString());
        $this->assertSame(SchoolPlan::Pro, $this->school()->plan(), 'Fitur Pro tetap aktif sampai grup Pro selesai');

        $this->post('/admin/plan/upgrade')->assertSessionHasNoErrors();
        $this->assertNull($this->school()->scheduled_plan, 'Membatalkan jadwal tanpa biaya');

        $this->post('/admin/plan/downgrade', ['confirm_downgrade' => 1]);
        $this->travelTo(Carbon::parse('2027-07-01 00:01', 'Asia/Jakarta'));
        $this->assertSame(SchoolPlan::Regular, $this->school()->plan());
    }

    public function test_free_school_cannot_change_plan_itself(): void
    {
        $this->seed();
        $this->school()->update(['subscription_tier' => SchoolPlan::Free->value]);
        $admin = $this->userByEmail('admin@smkn1par-bdg.sch.id');

        $this->actingAs($admin)->get('/admin/payments')->assertSee('uji coba gratis');
        $this->post('/admin/plan/upgrade')->assertSessionHasErrors('plan');
        $this->assertSame(SchoolPlan::Free, $this->school()->plan());
    }

    public function test_group_can_take_all_students_of_one_supervising_teacher_across_classes(): void
    {
        $this->seed();
        $admin = $this->userByEmail('admin@smkn1par-bdg.sch.id');
        $teacher = $this->userByEmail('guru.perhotelan@smkn1par-bdg.sch.id');
        $otherSchoolTeacher = $this->userByEmail('guru@smkharapan-bali.sch.id');
        [$period, $teacherId, $expected] = TenantContext::runInSchoolScope($admin->school_id, function () use ($teacher) {
            $period = PklPeriod::firstOrFail();
            $teacherId = $teacher->teacher->id;
            $ids = $period->placements()->whereIn('status', ['scheduled', 'active'])->where('teacher_id', $teacherId)->pluck('student_id')->unique()->sort()->values()->all();

            return [$period, $teacherId, $ids];
        });
        $otherTeacherId = TenantContext::runInSchoolScope($otherSchoolTeacher->school_id, fn () => $otherSchoolTeacher->teacher->id);

        $this->actingAs($admin)->get('/admin/payments')->assertSee('Siswa bimbingan satu guru');
        $this->post("/admin/payments/periods/{$period->id}/groups", ['name' => 'Guru lain', 'source' => 'teacher:'.$otherTeacherId, 'confirm_members' => 1])->assertNotFound();
        $this->post("/admin/payments/periods/{$period->id}/groups", ['name' => 'Rusak', 'source' => 'teacher:bukan-uuid', 'confirm_members' => 1])->assertNotFound();
        $this->post("/admin/payments/periods/{$period->id}/groups", [
            'name' => 'Bimbingan Pak Budi', 'source' => 'teacher:'.$teacherId, 'confirm_members' => 1,
        ])->assertSessionHasNoErrors();

        $members = TenantContext::runInSchoolScope($admin->school_id, fn () => BillingGroup::where('name', 'Bimbingan Pak Budi')->firstOrFail()->members()->pluck('students.id')->sort()->values()->all());
        $this->assertNotEmpty($expected);
        $this->assertSame($expected, $members);
    }
}

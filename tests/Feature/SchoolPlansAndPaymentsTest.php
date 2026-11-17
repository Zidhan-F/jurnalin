<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SchoolPlan;
use App\Models\PklPeriod;
use App\Models\Placement;
use App\Models\SchoolPayment;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class SchoolPlansAndPaymentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_and_partial_cycles_start_on_the_period_anniversary(): void
    {
        $period = new PklPeriod(['start_date' => '2026-01-31', 'end_date' => '2026-07-30']);
        $this->assertCount(6, $period->billingCycles());
        $this->assertSame(['from' => '2026-02-28', 'until' => '2026-03-30'], $period->billingCycles()[2]);
        $this->assertEquals(1800000, 100 * SchoolPlan::Regular->price() * count($period->billingCycles()));
        $this->assertEquals(3000000, 100 * SchoolPlan::Pro->price() * count($period->billingCycles()));
        $period->end_date = '2026-03-09';
        $this->assertCount(2, $period->billingCycles());
        $this->assertSame('2026-03-09', $period->accessEndsFrom($period->start_date->toImmutable())->toDateString());
        $this->assertEquals(600000, 100 * SchoolPlan::Regular->price() * count($period->billingCycles()));
    }

    public function test_verified_payment_unlocks_six_months_then_one_calendar_month_for_journals(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2027-01-01 09:00', 'Asia/Jakarta'));
        $this->seed();
        Storage::fake('local');
        $admin = $this->userByEmail('admin@smkn1par-bdg.sch.id');
        $other = $this->userByEmail('admin@smkharapan-bali.sch.id');
        $student = $this->userByEmail('siswa.bagas@smkn1par-bdg.sch.id');
        $teacher = $this->userByEmail('guru.perhotelan@smkn1par-bdg.sch.id');
        $super = $this->userByEmail('superadmin@jurnalin.id');
        $period = TenantContext::runInSchoolScope($admin->school_id, function () {
            $period = PklPeriod::firstOrFail();
            $period->update(['start_date' => '2027-01-01', 'end_date' => '2027-06-30']);

            return $period;
        });
        $this->actingAs($admin)->post("/admin/payments/periods/{$period->id}/groups", [
            'name' => 'Hotel A', 'class_name' => 'XII Perhotelan 1', 'confirm_members' => 1,
        ])->assertSessionHasNoErrors();
        $group = TenantContext::runInSchoolScope($admin->school_id, fn () => \App\Models\BillingGroup::firstOrFail());
        $url = "/admin/payments/groups/{$group->id}/proof";
        $data = fn () => ['quote' => 36000, 'proof' => UploadedFile::fake()->image('bukti.png')];
        $this->actingAs($student)->get('/student')->assertRedirect('/login');
        $this->actingAs($other)->post($url, $data())->assertNotFound();
        $this->actingAs($admin)->post($url, ['quote' => 1000] + $data())->assertSessionHasErrors('quote');
        $this->post($url, $data())->assertSessionHasNoErrors();
        $this->post($url, $data())->assertSessionHasErrors('proof');
        $payment = TenantContext::runInSchoolScope($admin->school_id, fn () => SchoolPayment::firstOrFail());
        $this->assertSame(36000, $payment->amount());
        $review = "/super-admin/payments/{$payment->id}/review";
        $decision = ['decision' => 'verified', 'version' => hash('sha256', $payment->proof_path)];
        $this->get('/admin/payments')->assertOk()->assertSee('Menunggu verifikasi')->assertSee('Rp36.000');
        $this->post($review, $decision)->assertForbidden();
        $this->actingAs($other)->get("/admin/payments/{$payment->id}/proof")->assertNotFound();
        $this->actingAs($super)->get('/super-admin/payments')->assertOk()->assertSee('Rp36.000');
        $this->post($review, ['decision' => 'returned'] + $decision)->assertSessionHasErrors('note');
        $this->post($review, ['decision' => 'returned', 'note' => 'Referensi transfer belum terbaca.'] + $decision)->assertSessionHasNoErrors();
        $this->actingAs($admin)->get('/admin/payments')->assertSee('Referensi transfer belum terbaca.');
        $this->post($url, $data())->assertSessionHasNoErrors();
        $payment = TenantContext::runInSchoolScope($admin->school_id, fn () => SchoolPayment::where('status', 'pending')->firstOrFail());
        $review = "/super-admin/payments/{$payment->id}/review";
        $decision['version'] = hash('sha256', $payment->proof_path);
        $this->actingAs($super);
        $this->post($review, $decision)->assertSessionHasNoErrors();
        $this->post($review, $decision)->assertStatus(409);
        $this->actingAs($student)->get('/student')->assertOk();
        $this->travelTo(\Carbon\Carbon::parse('2027-06-30 23:59', 'Asia/Jakarta'));
        $this->get('/student')->assertOk();
        $this->travelTo(\Carbon\Carbon::parse('2027-07-01 00:00', 'Asia/Jakarta'));
        $this->get('/student')->assertRedirect('/student/journals');
        $this->post('/student/check-in')->assertForbidden();
        $this->get('/student/journals')->assertOk();
        TenantContext::runInSchoolScope($admin->school_id, fn () => Placement::where('student_id', $student->student->id)->update(['status' => 'completed']));
        $this->post('/student/journals', ['journal_date' => '2027-06-30', 'work_unit' => 'Resepsionis',
            'activity_title' => 'Menyelesaikan laporan PKL', 'activity_description' => 'Menyusun laporan kegiatan bersama pembimbing.',
            'duration_minutes' => 60, 'submit_action' => 'submitted', 'confirmed_done' => '1'])->assertSessionHasNoErrors()->assertRedirect('/student/journals');
        $entry = TenantContext::runInSchoolScope($admin->school_id, fn () => \App\Models\Journal::where('activity_title', 'Menyelesaikan laporan PKL')->firstOrFail());
        $this->actingAs($teacher)->get('/teacher')->assertOk()->assertSee('Menyelesaikan laporan PKL');
        $this->post("/teacher/journals/{$entry->id}/revision", ['teacher_feedback' => 'Lengkapi nama kegiatan.'])->assertSessionHasNoErrors();
        $this->actingAs($student)->get('/student/journals?edit='.$entry->id)->assertOk()->assertSee('Perbaiki Jurnal');
        $this->post('/student/journals', ['journal_id' => $entry->id, 'journal_date' => '2027-06-30', 'work_unit' => 'Resepsionis',
            'activity_title' => 'Laporan kegiatan resepsionis', 'activity_description' => 'Menyusun laporan kegiatan bersama pembimbing.',
            'duration_minutes' => 60, 'submit_action' => 'submitted', 'confirmed_done' => '1'])->assertSessionHasNoErrors();
        $this->actingAs($teacher)->post("/teacher/journals/{$entry->id}/approve")->assertSessionHasNoErrors();
        TenantContext::runInSchoolScope($admin->school_id, fn () => $this->assertSame('approved', $entry->fresh()->status->value));
        $this->travelTo(\Carbon\Carbon::parse('2027-07-31 23:59', 'Asia/Jakarta'));
        $this->actingAs($student)->get('/student/journals')->assertOk();
        $this->travelTo(\Carbon\Carbon::parse('2027-08-01 00:00', 'Asia/Jakarta'));
        $this->get('/student/journals')->assertRedirect('/login');
    }

    public function test_stopping_a_class_revokes_its_access_and_reactivation_requires_new_verification(): void
    {
        $this->seed();
        Storage::fake('local');
        $admin = $this->userByEmail('admin@smkn1par-bdg.sch.id');
        $teacher = $this->userByEmail('guru.perhotelan@smkn1par-bdg.sch.id');
        $student = $this->userByEmail('siswa.bagas@smkn1par-bdg.sch.id');
        $otherStudent = $this->userByEmail('siswa.citra@smkn1par-bdg.sch.id');
        $super = $this->userByEmail('superadmin@jurnalin.id');
        $period = TenantContext::runInSchoolScope($admin->school_id, fn () => PklPeriod::firstOrFail());
        $this->actingAs($admin)->post("/admin/payments/periods/{$period->id}/groups", [
            'name' => 'Hotel A', 'class_name' => 'XII Perhotelan 1', 'confirm_members' => 1,
        ])->assertSessionHasNoErrors();
        $group = TenantContext::runInSchoolScope($admin->school_id, fn () => \App\Models\BillingGroup::firstOrFail());
        $pay = "/admin/payments/groups/{$group->id}/proof";
        $stop = "/admin/payments/groups/{$group->id}/stop";
        $data = fn () => ['quote' => 6000 * count($period->billingCycles()), 'proof' => UploadedFile::fake()->image('baru.png')];
        $payment = fn () => TenantContext::runInSchoolScope($admin->school_id, fn () => SchoolPayment::where('status', 'pending')->firstOrFail());
        $verify = fn ($p) => $this->actingAs($super)->post("/super-admin/payments/{$p->id}/review",
            ['decision' => 'verified', 'version' => hash('sha256', $p->proof_path)]);
        $this->post($pay, $data())->assertSessionHasNoErrors();
        $verify($payment())->assertSessionHasNoErrors();
        $this->actingAs($admin)->post($stop)->assertSessionHasErrors('confirm_stop');
        $this->actingAs($student)->get('/student')->assertOk();
        $this->actingAs($admin)->post($stop, ['confirm_stop' => 1])->assertSessionHasNoErrors();
        $this->actingAs($student)->get('/student/journals')->assertRedirect('/login');
        $this->actingAs($otherStudent)->get('/student')->assertOk();
        $this->actingAs($teacher)->get('/teacher/assessments')->assertOk()->assertSee('Citra')->assertDontSee('Bagas');
        $this->actingAs($admin)->post($pay, $data())->assertSessionHasNoErrors();
        $cancelled = $payment();
        $this->post($stop, ['confirm_stop' => 1])->assertSessionHasNoErrors();
        $verify($cancelled)->assertStatus(409);
        $this->actingAs($admin)->post($pay, $data())->assertSessionHasNoErrors();
        $verify($payment())->assertSessionHasNoErrors();
        $this->actingAs($this->userByEmail($student->email))->withSession(['access_version' => 0])->get('/student')->assertRedirect('/login');
        $this->post('/login', ['email' => $student->email, 'password' => 'password123'])->assertRedirect('/student');
        $this->get('/student')->assertOk();
        $this->actingAs($admin)->post('/admin/payments/stop-school', ['confirm_stop' => 1])->assertSessionHasNoErrors();
        $this->actingAs($otherStudent)->get('/student')->assertRedirect('/login');
        $this->actingAs($admin)->get('/admin/payments')->assertOk();
    }

    public function test_free_trial_caps_quota_and_allows_placements_in_only_one_period(): void
    {
        $this->seed();
        $admin = $this->userByEmail('admin@smkharapan-bali.sch.id');
        $super = $this->userByEmail('superadmin@jurnalin.id');
        $this->actingAs($super)->post("/super-admin/schools/{$admin->school_id}/quota", [
            'subscription_tier' => 'free_pilot', 'max_students_quota' => 36,
        ])->assertSessionHasErrors('max_students_quota');

        $payload = TenantContext::runInSchoolScope($admin->school_id, function () {
            $first = Placement::firstOrFail();
            $period = $first->pklPeriod->replicate();
            $period->name = 'Periode berikutnya';
            $period->save();

            return ['pkl_period_id' => $period->id, 'student_id' => $first->student_id,
                'company_id' => $first->company_id, 'teacher_id' => $first->teacher_id,
                'work_start_time' => '08:00', 'work_end_time' => '16:00'];
        });
        $this->actingAs($admin)->post('/admin/placements', $payload)->assertSessionHasErrors('pkl_period_id');
        $this->get('/admin/payments')->assertOk()->assertSee('Periode uji coba gratis');
    }

    public function test_pro_reports_and_filtered_downloads_are_tenant_scoped(): void
    {
        $this->seed();
        $admin = $this->userByEmail('admin@smkn1par-bdg.sch.id');
        $other = $this->userByEmail('admin@smkharapan-bali.sch.id');
        $this->actingAs($admin)->get('/admin/hubin?download=xlsx')->assertForbidden();
        $admin->school->update(['subscription_tier' => SchoolPlan::Pro->value]);
        $placement = TenantContext::runInSchoolScope($admin->school_id, fn () => Placement::with('student')->firstOrFail());
        $query = ['period' => $placement->pkl_period_id, 'class_name' => $placement->student->class_name];
        $this->get('/admin/hubin?'.http_build_query($query))->assertOk()->assertSee('Unduh rekap');
        foreach (['zip', 'xlsx'] as $format) {
            $response = $this->get('/admin/hubin?'.http_build_query($query + ['download' => $format]))->assertOk();
            $path = $response->baseResponse->getFile()->getPathname();
            $archive = new ZipArchive;
            $this->assertTrue($archive->open($path));
            try {
                $this->assertGreaterThan(0, $archive->numFiles);
                if ($format === 'zip') {
                    $this->assertStringStartsWith('%PDF', $archive->getFromIndex(0));
                } else {
                    $this->assertNotFalse($archive->locateName('xl/worksheets/sheet1.xml'));
                }
            } finally {
                $archive->close();
                unlink($path);
            }
        }
        $foreign = TenantContext::runInSchoolScope($other->school_id, fn () => PklPeriod::firstOrFail());
        $this->get('/admin/hubin?period='.$foreign->id)->assertNotFound();
        $this->actingAs($other)->get('/admin/hubin')->assertForbidden();
    }
}

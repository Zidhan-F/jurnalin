<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PlacementStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\PeriodClass;
use App\Models\Placement;
use App\Models\PlacementGroup;
use App\Models\PklPeriod;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PeriodRosterTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private PklPeriod $period;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->school = School::query()->where('npsn', '20219101')->firstOrFail();
        $this->admin = $this->userByEmail('admin@smkn1par-bdg.sch.id');
        $this->period = $this->scoped(fn () => PklPeriod::query()->where('name', 'PKL Kelas XII Gelombang 1')->firstOrFail());
        // Mulai dari periode tanpa kelas dan grup demo.
        $this->scoped(function (): void {
            PlacementGroup::query()->where('pkl_period_id', $this->period->id)->delete();
            PeriodClass::query()->where('pkl_period_id', $this->period->id)->delete();
        });
    }

    private function scoped(\Closure $fn): mixed
    {
        return TenantContext::runInSchoolScope($this->school->id, $fn);
    }

    private function url(string $suffix = ''): string
    {
        return "/admin/periods/{$this->period->id}{$suffix}";
    }

    private function newStudent(string $name, string $class): Student
    {
        return $this->scoped(function () use ($name, $class): Student {
            $user = User::query()->create(['school_id' => $this->school->id, 'name' => $name, 'email' => str()->slug($name).'@contoh.test', 'password' => 'rahasia-123', 'role' => UserRole::Student]);

            return Student::query()->create(['school_id' => $this->school->id, 'user_id' => $user->id, 'nisn' => (string) random_int(1000000000, 9999999999), 'nis' => (string) random_int(10000000, 99999999), 'class_name' => $class, 'major' => 'Perhotelan', 'gender' => 'P', 'parent_phone' => '628120000000']);
        });
    }

    public function test_adding_a_class_copies_its_students_and_keeps_them_when_students_move_up(): void
    {
        $this->actingAs($this->admin)->post($this->url('/kelas'), ['name' => 'XII Perhotelan 1'])->assertRedirect()->assertSessionHasNoErrors();
        $class = $this->scoped(fn () => PeriodClass::query()->with('members')->where('pkl_period_id', $this->period->id)->firstOrFail());
        $this->assertSame('XII Perhotelan 1', $class->name);
        $this->assertSame('Perhotelan', $class->major);
        $this->assertCount(2, $class->members);

        $this->actingAs($this->admin)->post($this->url('/kelas'), ['name' => 'XII Perhotelan 1'])->assertSessionHasErrors('name');

        // Siswa naik kelas: daftar kelas di periode tetap sama.
        $this->scoped(fn () => Student::query()->where('class_name', 'XII Perhotelan 1')->update(['class_name' => 'Alumni 2027']));
        $this->assertCount(2, $this->scoped(fn () => $class->fresh()->members));

        $student = $class->members->first();
        $this->actingAs($this->admin)->post($this->url("/kelas/{$class->id}/siswa/{$student->id}/keluar"))->assertRedirect();
        $this->assertCount(1, $this->scoped(fn () => $class->fresh()->members));

        $this->actingAs($this->admin)->get($this->url('?tab=kelas'))->assertOk()->assertSee('XII Perhotelan 1')->assertSee('Tambah kelas ke periode');
    }

    public function test_group_places_new_students_joins_same_company_and_reports_others(): void
    {
        $nadiaUser = $this->userByEmail('siswa.nadia@smkn1par-bdg.sch.id');
        $bagasUser = $this->userByEmail('siswa.bagas@smkn1par-bdg.sch.id');
        $nadia = $this->scoped(fn () => Student::query()->where('user_id', $nadiaUser->id)->firstOrFail());
        $bagas = $this->scoped(fn () => Student::query()->where('user_id', $bagasUser->id)->firstOrFail());
        $nadiaPlacement = $this->scoped(fn () => Placement::query()->where('student_id', $nadia->id)->firstOrFail());
        $rina = $this->newStudent('Rina Lestari', 'XII Perhotelan 1');
        $teacher2 = $this->scoped(fn () => Teacher::query()->whereHas('user', fn ($q) => $q->where('email', 'guru.kuliner@smkn1par-bdg.sch.id'))->firstOrFail());

        $this->actingAs($this->admin)->post($this->url('/grup'), [
            'name' => 'Trans Luxury - Front Office', 'company_id' => $nadiaPlacement->company_id, 'teacher_id' => $teacher2->id,
            'department_divisi' => 'Front Office', 'work_start_time' => '07:00', 'work_end_time' => '15:00',
            'student_ids' => [$nadia->id, $bagas->id, $rina->id],
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('import_errors', fn ($e) => count($e) === 1 && str_contains($e[0], 'Bagas'));

        $group = $this->scoped(fn () => PlacementGroup::query()->with('placements')->where('pkl_period_id', $this->period->id)->firstOrFail());
        $this->assertCount(2, $group->placements);
        $this->scoped(function () use ($group, $nadiaPlacement, $rina, $teacher2): void {
            $this->assertSame($group->id, $nadiaPlacement->fresh()->placement_group_id, 'penempatan lama di tempat yang sama digabungkan, bukan dibuat ulang');
            $this->assertSame($teacher2->id, $nadiaPlacement->fresh()->teacher_id);
            $new = Placement::query()->where('student_id', $rina->id)->firstOrFail();
            $this->assertSame(PlacementStatus::Active, $new->status);
            $this->assertSame('07:00:00', substr((string) $new->work_start_time, 0, 8));
        });

        $this->actingAs($this->admin)->post($this->url('/grup'), [
            'name' => 'Trans Luxury - Front Office', 'company_id' => $nadiaPlacement->company_id, 'teacher_id' => $teacher2->id,
            'work_start_time' => '07:00', 'work_end_time' => '15:00',
        ])->assertSessionHasErrors('name');

        $this->actingAs($this->admin)->get($this->url('?tab=grup'))->assertOk()->assertSee('Trans Luxury - Front Office')->assertSee('belum punya grup');
    }

    public function test_changing_group_teacher_updates_every_member_and_respects_teacher_limit(): void
    {
        $placements = $this->scoped(fn () => Placement::query()->where('pkl_period_id', $this->period->id)->whereHas('teacher.user', fn ($q) => $q->where('email', 'guru.perhotelan@smkn1par-bdg.sch.id'))->get());
        $first = $placements->first();
        $group = $this->scoped(fn () => PlacementGroup::query()->create(['school_id' => $this->school->id, 'pkl_period_id' => $this->period->id, 'name' => 'Grup uji', 'company_id' => $first->company_id, 'teacher_id' => $first->teacher_id]));
        $this->scoped(fn () => Placement::query()->whereIn('id', $placements->pluck('id'))->update(['placement_group_id' => $group->id]));
        $teacher2 = $this->scoped(fn () => Teacher::query()->whereHas('user', fn ($q) => $q->where('email', 'guru.kuliner@smkn1par-bdg.sch.id'))->firstOrFail());

        $this->scoped(fn () => $teacher2->update(['max_mentees' => 2]));
        $payload = ['name' => 'Grup uji', 'teacher_id' => $teacher2->id, 'department_divisi' => 'Banquet', 'work_start_time' => '09:00', 'work_end_time' => '17:00'];
        $this->actingAs($this->admin)->post($this->url("/grup/{$group->id}"), $payload)->assertSessionHasErrors('teacher_id');

        $this->scoped(fn () => $teacher2->update(['max_mentees' => 20]));
        $this->actingAs($this->admin)->post($this->url("/grup/{$group->id}"), $payload)->assertSessionHasNoErrors();
        $this->scoped(function () use ($placements, $teacher2): void {
            foreach (Placement::query()->whereIn('id', $placements->pluck('id'))->get() as $p) {
                $this->assertSame($teacher2->id, $p->teacher_id);
                $this->assertSame('Banquet', $p->department_divisi);
            }
        });

        $this->actingAs($this->admin)->post($this->url("/grup/{$group->id}"), $payload + ['company_id' => $first->company_id])->assertSessionHasErrors('company_id');

        $this->actingAs($this->admin)->post($this->url("/grup/{$group->id}/keluar/{$first->id}"))->assertRedirect();
        $this->scoped(function () use ($first): void {
            $this->assertNull($first->fresh()->placement_group_id);
            $this->assertSame(PlacementStatus::Active, $first->fresh()->status);
        });

        $this->actingAs($this->admin)->post($this->url("/grup/{$group->id}/hapus"))->assertRedirect();
        $this->assertSame(0, $this->scoped(fn () => Placement::query()->whereNotNull('placement_group_id')->count()));
        $this->assertSame($placements->count(), $this->scoped(fn () => Placement::query()->whereIn('id', $placements->pluck('id'))->where('status', PlacementStatus::Active)->count()));
    }

    public function test_other_school_cannot_read_or_change_classes_and_groups(): void
    {
        $company = $this->scoped(fn () => Company::query()->firstOrFail());
        $teacher = $this->scoped(fn () => Teacher::query()->firstOrFail());
        $group = $this->scoped(fn () => PlacementGroup::query()->create(['school_id' => $this->school->id, 'pkl_period_id' => $this->period->id, 'name' => 'Rahasia A', 'company_id' => $company->id, 'teacher_id' => $teacher->id]));
        $class = $this->scoped(fn () => PeriodClass::query()->create(['school_id' => $this->school->id, 'pkl_period_id' => $this->period->id, 'name' => 'Kelas A']));

        $adminB = $this->userByEmail('admin@smkharapan-bali.sch.id');
        $this->actingAs($adminB)->get($this->url('?tab=grup'))->assertNotFound();
        $this->actingAs($adminB)->post($this->url("/grup/{$group->id}/hapus"))->assertNotFound();
        $this->actingAs($adminB)->post($this->url("/kelas/{$class->id}/hapus"))->assertNotFound();

        $schoolB = TenantContext::runAsSuperAdmin(fn () => School::query()->where('npsn', '50103822')->firstOrFail());
        TenantContext::runInSchoolScope($schoolB->id, function (): void {
            $this->assertSame(0, PlacementGroup::query()->count());
            $this->assertSame(0, PeriodClass::query()->count());
            $this->assertSame(0, DB::table('period_class_members')->count());
            $this->assertSame(0, DB::table('placement_groups')->count(), 'RLS menyaring query mentah juga');
        });
        $this->assertTrue($this->scoped(fn () => PlacementGroup::query()->whereKey($group->id)->exists()));
    }

    public function test_hubin_bulk_placement_can_save_a_group(): void
    {
        $this->scoped(fn () => \App\Models\TenantFeature::query()->updateOrCreate(['school_id' => $this->school->id, 'feature' => 'hubin'], ['enabled' => true]));
        \App\Support\Features::flush();
        $rina = $this->newStudent('Rina Lestari', 'XII Perhotelan 2');
        $company = $this->scoped(fn () => Company::query()->firstOrFail());
        $teacher = $this->scoped(fn () => Teacher::query()->firstOrFail());

        $this->actingAs($this->admin)->post('/admin/hubin/penempatan', [
            'pkl_period_id' => $this->period->id, 'company_id' => $company->id, 'teacher_id' => $teacher->id,
            'work_start_time' => '08:00', 'work_end_time' => '16:00', 'student_ids' => [$rina->id], 'group_name' => 'Gelombang Rina',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $group = $this->scoped(fn () => PlacementGroup::query()->with('placements')->where('name', 'Gelombang Rina')->firstOrFail());
        $this->assertSame([$rina->id], $group->placements->pluck('student_id')->all());
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\SchoolPlan;
use App\Models\AuditLog;
use App\Models\BillingGroup;
use App\Models\School;
use App\Models\SchoolPayment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Admin sekolah mengganti paket sekolah sendiri.
 * Naik ke Pro: langsung bila tidak ada grup berjalan yang dibayar Reguler; bila ada, sekolah membayar selisihnya dulu.
 * Turun ke Reguler: langsung bila tidak ada grup berjalan yang dibayar Pro; bila ada, dijadwalkan setelah grup itu selesai.
 */
final class ChangeSchoolPlanAction
{
    /**
     * Grup yang sedang berjalan dan dibayar dengan paket tertentu, beserta sisa bulannya.
     *
     * @return Collection<int, array{group: BillingGroup, students: int, months: int}>
     */
    public function activeGroupsPaidWith(School $school, SchoolPlan $plan): Collection
    {
        $today = CarbonImmutable::now($school->timezone)->startOfDay();

        return BillingGroup::query()
            ->where('stopped', false)->whereNotNull('paid_until')->where('paid_until', '>=', $today->toDateString())
            ->get()
            ->filter(fn (BillingGroup $g) => $g->payments()->where('status', 'verified')->latest()->first()?->plan === $plan)
            ->map(function (BillingGroup $g) use ($today) {
                $from = $today->max(CarbonImmutable::parse($g->starts_on));
                $until = CarbonImmutable::parse($g->paid_until);
                // Sisa bulan kalender, dibulatkan ke atas, sama seperti aturan tagihan grup (1 Jan s.d. 30 Jun = 6).
                $months = max(1, (int) ceil(round($from->floatDiffInMonths($until->addDay()), 4)));

                return ['group' => $g, 'students' => $g->members()->count(), 'months' => $months];
            })
            ->values();
    }

    public function upgradeDifference(School $school): int
    {
        $diff = SchoolPlan::Pro->price() - SchoolPlan::Regular->price();

        return $this->activeGroupsPaidWith($school, SchoolPlan::Regular)->sum(fn ($r) => $r['students'] * $r['months']) * $diff;
    }

    public function upgrade(User $admin, ?int $quote = null, ?UploadedFile $proof = null): string
    {
        $path = null;
        try {
            return DB::transaction(function () use ($admin, $quote, $proof, &$path): string {
                $school = School::lockForUpdate()->findOrFail($admin->school_id);
                $this->ensureSelfService($school);
                if ($school->plan() === SchoolPlan::Pro && ! $school->scheduled_plan) {
                    throw ValidationException::withMessages(['plan' => 'Sekolah sudah memakai paket Pro.']);
                }
                if (SchoolPayment::where('kind', 'upgrade')->where('status', 'pending')->exists()) {
                    throw ValidationException::withMessages(['plan' => 'Pembayaran selisih naik ke Pro sedang diperiksa Admin Jurnalin.']);
                }

                // Membatalkan jadwal turun paket berarti tetap Pro, tanpa biaya tambahan.
                if ($school->plan() === SchoolPlan::Pro) {
                    $school->update(['scheduled_plan' => null, 'scheduled_plan_on' => null]);
                    AuditLog::record('plan.downgrade_cancelled', $school, [], $school->id);

                    return 'Jadwal turun paket dibatalkan. Sekolah tetap memakai paket Pro.';
                }

                $rows = $this->activeGroupsPaidWith($school, SchoolPlan::Regular);
                if ($rows->isEmpty()) {
                    $school->update(['subscription_tier' => SchoolPlan::Pro->value, 'scheduled_plan' => null, 'scheduled_plan_on' => null]);
                    AuditLog::record('plan.upgraded', $school, ['plan' => 'Sekolah Pro'], $school->id);

                    return 'Paket sekolah sekarang Sekolah Pro. Grup berikutnya dibayar dengan tarif Pro.';
                }

                $diff = SchoolPlan::Pro->price() - SchoolPlan::Regular->price();
                $studentMonths = $rows->sum(fn ($r) => $r['students'] * $r['months']);
                if ($proof === null || $quote !== $studentMonths * $diff) {
                    throw ValidationException::withMessages(['quote' => 'Periksa kembali rincian selisih sebelum mengirim bukti transfer.']);
                }
                $path = $proof->store("billing/{$school->id}", 'local');
                if (! $path) {
                    throw new RuntimeException('Bukti pembayaran gagal disimpan.');
                }
                // student_count menyimpan total siswa-bulan, jadi amount() = siswa-bulan x selisih tarif.
                $payment = SchoolPayment::query()->create([
                    'kind' => 'upgrade', 'pkl_period_id' => $rows->sortByDesc(fn ($r) => $r['group']->paid_until)->first()['group']->pkl_period_id,
                    'cycle' => 1, 'plan' => SchoolPlan::Pro, 'months' => 1, 'student_count' => $studentMonths, 'unit_price' => $diff,
                    'proof_path' => $path, 'submitted_by' => $admin->id, 'status' => 'pending',
                ]);
                AuditLog::record('payment.submitted', $payment, ['amount' => $payment->amount(), 'jenis' => 'selisih naik ke Pro'], $school->id);

                return 'Bukti transfer selisih terkirim. Paket berubah menjadi Pro setelah Admin Jurnalin memverifikasinya.';
            });
        } catch (Throwable $error) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }
    }

    public function downgrade(User $admin): string
    {
        return DB::transaction(function () use ($admin): string {
            $school = School::lockForUpdate()->findOrFail($admin->school_id);
            $this->ensureSelfService($school);
            if ($school->plan() !== SchoolPlan::Pro || $school->scheduled_plan) {
                throw ValidationException::withMessages(['plan' => 'Sekolah tidak sedang memakai paket Pro, atau turun paket sudah dijadwalkan.']);
            }

            $proGroups = $this->activeGroupsPaidWith($school, SchoolPlan::Pro);
            if ($proGroups->isEmpty()) {
                $school->update(['subscription_tier' => SchoolPlan::Regular->value]);
                AuditLog::record('plan.downgraded', $school, ['plan' => 'Sekolah Reguler'], $school->id);

                return 'Paket sekolah sekarang Sekolah Reguler.';
            }

            // Fitur Pro sudah dibayar sampai grup Pro terakhir selesai; turun paket berlaku sehari sesudahnya.
            $on = CarbonImmutable::parse($proGroups->max(fn ($r) => $r['group']->paid_until))->addDay();
            $school->update(['scheduled_plan' => SchoolPlan::Regular->value, 'scheduled_plan_on' => $on->toDateString()]);
            AuditLog::record('plan.downgrade_scheduled', $school, ['mulai' => $on->format('d/m/Y')], $school->id);

            return 'Turun ke Reguler dijadwalkan mulai '.$on->translatedFormat('j F Y').'. Fitur Pro tetap aktif sampai grup yang sudah dibayar Pro selesai.';
        });
    }

    private function ensureSelfService(School $school): void
    {
        if ($school->plan() === SchoolPlan::Free) {
            throw ValidationException::withMessages(['plan' => 'Sekolah masih dalam uji coba gratis. Hubungi Admin Jurnalin untuk memilih paket berbayar.']);
        }
    }
}

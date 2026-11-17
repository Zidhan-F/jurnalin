<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Models\AuditLog;
use App\Models\BillingGroup;
use App\Models\School;
use App\Models\SchoolPayment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class SubmitPaymentAction
{
    public function handle(User $admin, BillingGroup $group, int $quote, UploadedFile $proof): void
    {
        $path = null;
        try {
            DB::transaction(function () use ($admin, $group, $quote, $proof, &$path) {
                $school = School::lockForUpdate()->findOrFail($admin->school_id);
                $group = BillingGroup::lockForUpdate()->findOrFail($group->id);
                $today = now($school->timezone)->toDateString();
                if ((! $group->stopped && $group->paid_until >= $today) || $group->payments()->where('status', 'pending')->exists()) {
                    throw ValidationException::withMessages(['proof' => 'Grup ini sudah memiliki akses penuh atau pembayaran sedang diperiksa.']);
                }
                $months = count($group->period->billingCycles());
                $students = $group->members()->count();
                $rate = $school->plan()->price();
                if (! $rate || ! $students || $quote !== $months * $students * $rate) {
                    throw ValidationException::withMessages(['quote' => 'Periksa kembali paket dan rincian tagihan sebelum membayar.']);
                }
                $path = $proof->store("billing/{$school->id}", 'local');
                if (! $path) {
                    throw new RuntimeException('Bukti pembayaran gagal disimpan.');
                }
                $payment = $group->payments()->create(['pkl_period_id' => $group->pkl_period_id, 'cycle' => 1,
                    'plan' => $school->plan(), 'months' => $months, 'student_count' => $students, 'unit_price' => $rate,
                    'proof_path' => $path, 'submitted_by' => $admin->id, 'status' => 'pending']);
                AuditLog::record('payment.submitted', $payment, ['amount' => $payment->amount()]);
            });
        } catch (Throwable $error) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Billing\ChangeSchoolPlanAction;
use App\Actions\Billing\ManageBillingGroupsAction;
use App\Actions\Billing\SubmitPaymentAction;
use App\Enums\SchoolPlan;
use App\Models\AuditLog;
use App\Models\BillingGroup;
use App\Models\PklPeriod;
use App\Models\School;
use App\Models\SchoolPayment;
use App\Models\Teacher;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SchoolPaymentController extends Controller
{
    public function index(Request $request, ManageBillingGroupsAction $action, ChangeSchoolPlanAction $planAction): View
    {
        $selected = $request->validate(['period' => ['nullable', 'uuid']]);
        $school = $request->user()->school;
        $periods = PklPeriod::orderByDesc('is_active')->orderByDesc('start_date')->get();
        $period = isset($selected['period']) ? $periods->firstWhere('id', $selected['period']) : $periods->first();
        abort_if(isset($selected['period']) && ! $period, 404);
        if ($period) {
            $action->syncPeriodGroup($school, $period);
        }
        $months = $period ? count($period->billingCycles()) : 0;
        $groups = BillingGroup::where('pkl_period_id', $period?->id)->withCount('members')->orderBy('name')->get();
        $payments = SchoolPayment::where('pkl_period_id', $period?->id)->with('group')->latest()->get();
        $today = now($school->timezone)->toDateString();

        $upgradeRows = $school->plan() === SchoolPlan::Regular ? $planAction->activeGroupsPaidWith($school, SchoolPlan::Regular) : collect();
        $pendingUpgrade = SchoolPayment::where('kind', 'upgrade')->where('status', 'pending')->first();

        return view('admin.payments', compact('school', 'periods', 'period', 'months', 'groups', 'payments', 'today', 'upgradeRows', 'pendingUpgrade'));
    }

    public function createGroup(Request $request, PklPeriod $period, ManageBillingGroupsAction $action): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'class_name' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:300'], 'confirm_members' => ['accepted']]);
        // Satu pilihan di form: "teacher:<id>" (siswa bimbingan satu guru) atau "class:<nama>" (satu kelas); kosong = semua.
        [$kind, $value] = array_pad(explode(':', (string) ($data['source'] ?? ''), 2), 2, null);
        $class = $kind === 'class' ? $value : ($data['class_name'] ?? null);
        abort_if($kind === 'teacher' && ! \Illuminate\Support\Str::isUuid((string) $value), 404);
        $teacherId = $kind === 'teacher' ? Teacher::query()->findOrFail($value)->id : null;
        $action->create($request->user(), $period, $data['name'], $class, $teacherId);

        return back()->with('status', 'Grup siswa sudah dibuat.');
    }

    public function upgradePlan(Request $request, ChangeSchoolPlanAction $action): RedirectResponse
    {
        $data = $request->validate([
            'quote' => ['nullable', 'integer', 'min:0'],
            'proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
        ]);

        return back()->with('status', $action->upgrade($request->user(), isset($data['quote']) ? (int) $data['quote'] : null, $request->file('proof')));
    }

    public function downgradePlan(Request $request, ChangeSchoolPlanAction $action): RedirectResponse
    {
        $request->validate(['confirm_downgrade' => ['accepted']], ['confirm_downgrade.accepted' => 'Centang konfirmasi turun paket.']);

        return back()->with('status', $action->downgrade($request->user()));
    }

    public function stop(Request $request, ManageBillingGroupsAction $action, ?BillingGroup $group = null): RedirectResponse
    {
        $request->validate(['confirm_stop' => ['accepted']]);
        $action->stop($request->user(), $group);

        return back()->with('status', $group ? "Akses grup {$group->name} telah dinonaktifkan." : 'Akses semua grup di sekolah telah dinonaktifkan.')->with('status_popup', true);
    }

    public function submit(Request $request, BillingGroup $group, SubmitPaymentAction $action): RedirectResponse
    {
        $data = $request->validate(['quote' => ['required', 'integer', 'min:1'], 'proof' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096']]);
        $action->handle($request->user(), $group, (int) $data['quote'], $request->file('proof'));

        return redirect()->route('admin.payments', ['period' => $group->pkl_period_id])->with('status', 'Bukti pembayaran telah dikirim ke admin Jurnalin.');
    }

    public function queue(): View
    {
        $payments = SchoolPayment::with(['school', 'period', 'group'])->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('updated_at')->orderBy('id')->paginate(30);

        return view('super-admin.payments', compact('payments'));
    }

    public function proof(SchoolPayment $payment): StreamedResponse
    {
        $extension = pathinfo($payment->proof_path, PATHINFO_EXTENSION);
        abort_unless(Storage::disk('local')->exists($payment->proof_path), 404);

        return Storage::disk('local')->download($payment->proof_path, "bukti-{$payment->id}.{$extension}", [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function review(Request $request, SchoolPayment $payment): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:verified,returned'],
            'note' => ['required_if:decision,returned', 'nullable', 'string', 'max:500'],
            'version' => ['required', 'string', 'size:64']]);
        DB::transaction(function () use ($request, $payment, $data): void {
            $school = School::lockForUpdate()->findOrFail($payment->school_id);
            $group = $payment->billing_group_id ? BillingGroup::lockForUpdate()->findOrFail($payment->billing_group_id) : null;
            $payment = SchoolPayment::lockForUpdate()->findOrFail($payment->id);
            abort_unless($payment->status === 'pending' && hash_equals(hash('sha256', $payment->proof_path), $data['version']), 409, 'Bukti sudah berubah atau sudah diperiksa. Muat ulang halaman.');
            $payment->update(['status' => $data['decision'], 'review_note' => $data['note'] ?? null,
                'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            if ($payment->kind === 'upgrade' && $payment->status === 'verified') {
                $school->update(['subscription_tier' => SchoolPlan::Pro->value, 'scheduled_plan' => null, 'scheduled_plan_on' => null]);
            }
            if ($group && $payment->status === 'verified') {
                $today = CarbonImmutable::today($school->timezone);
                $start = $today->max(CarbonImmutable::parse($group->period->start_date->toDateString(), $school->timezone));
                $group->grant($start, $group->period->accessEndsFrom($start));
            }
            AuditLog::record('payment.'.$payment->status, $payment, ['amount' => $payment->amount(), 'note' => $payment->review_note]);
        });

        return back()->with('status', 'Hasil verifikasi sudah tersedia di panel pembayaran sekolah.');
    }
}

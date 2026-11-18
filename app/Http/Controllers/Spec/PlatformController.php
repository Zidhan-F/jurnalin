<?php

declare(strict_types=1);

namespace App\Http\Controllers\Spec;

use App\Http\Controllers\Controller;
use App\Models\AiRequest;
use App\Models\AuditLog;
use App\Models\MessageLog;
use App\Models\School;
use App\Models\TenantFeature;
use App\Support\Features;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Aktivasi modul per tenant (S14), pengaturan alur sekolah (S05), dan monitoring operasional (S09).
 * Staf platform hanya melihat metrik agregat: tidak ada akses ke isi jurnal siswa.
 */
final class PlatformController extends Controller
{
    public function schoolModules(Request $request): View
    {
        $school = $request->user()->school;

        return view('spec.admin.modules', [
            'school' => $school,
            'addons' => collect(Features::ADDONS)->map(fn ($label, $key) => ['label' => $label, 'enabled' => $school->hasFeature($key)]),
            'pro' => collect(Features::PRO)->map(fn ($label, $key) => ['label' => $label, 'enabled' => $school->hasFeature($key)]),
        ]);
    }

    public function schoolWhatsapp(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'journal_approver' => ['required', 'in:teacher,mentor,either'],
            'max_journal_photos' => ['required', 'integer', 'min:1', 'max:10'],
        ]);
        $school = $request->user()->school;
        $school->forceFill([...$data, 'wa_notifications' => $request->boolean('wa_notifications')])->save();
        AuditLog::record('school.workflow_updated', $school, $data, $school->id);

        return back()->with('status', 'Pengaturan alur sekolah diperbarui.');
    }

    public function modules(): View
    {
        return view('spec.super.modules', [
            'schools' => School::query()->orderBy('name')->get(),
            'features' => TenantFeature::query()->get()->groupBy('school_id'),
            'catalog' => [...Features::ADDONS, ...Features::PRO],
        ]);
    }

    public function toggleModule(Request $request, School $school): RedirectResponse
    {
        $data = $request->validate(['feature' => ['required', 'in:'.implode(',', array_keys([...Features::ADDONS, ...Features::PRO]))], 'enabled' => ['required', 'boolean'], 'source' => ['required', 'in:addon,plan,pilot']]);
        Features::set($school, $data['feature'], (bool) $data['enabled'], $data['source']);
        AuditLog::record('school.module_toggled', $school, $data, $school->id);

        return back()
            ->with('open_school_id', $school->id)
            ->with('status', Features::label($data['feature']).' '.($data['enabled'] ? 'diaktifkan' : 'dinonaktifkan').' untuk '.$school->name.'. Data modul tidak dihapus.');
    }

    public function operations(): View
    {
        $since = now()->subDays(7);
        $storageBytes = 0;
        foreach (Storage::disk('local')->allFiles('schools') as $file) {
            $storageBytes += Storage::disk('local')->size($file);
        }

        return view('spec.super.operations', [
            'queue' => ['pending' => DB::table('jobs')->count(), 'failed' => DB::table('failed_jobs')->count()],
            'storageMb' => round($storageBytes / 1048576, 1),
            'messages' => MessageLog::query()->where('created_at', '>=', $since)->selectRaw('status, count(*) as total, sum(cost) as cost')->groupBy('status')->get(),
            'ai' => AiRequest::query()->where('created_at', '>=', $since)->selectRaw('feature, count(*) as total, sum(input_chars + output_chars) as chars')->groupBy('feature')->get(),
            'schools' => School::query()->withCount(['students', 'placements'])->orderByDesc('students_count')->limit(20)->get(),
            'errors' => AuditLog::query()->where('action', 'guest.otp_failed')->where('created_at', '>=', $since)->count(),
            'dbOk' => (bool) TenantContext::runAsSuperAdmin(fn () => DB::select('select 1 as ok')[0]->ok ?? false),
        ]);
    }
}

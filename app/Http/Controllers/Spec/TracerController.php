<?php

declare(strict_types=1);

namespace App\Http\Controllers\Spec;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Student;
use App\Models\TracerCampaign;
use App\Models\TracerResponse;
use App\Support\Notify;
use App\Support\PublicToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tracer Study Internal (T01-T12), opsional. Bukan BKK, lowongan, atau pelaporan pemerintah otomatis.
 * Status lulusan boleh tumpang tindih; dashboard selalu menampilkan jumlah dan tingkat respons, dan
 * nonresponden tidak pernah dihitung sebagai penganggur. Kelompok kecil disembunyikan.
 */
final class TracerController extends Controller
{
    public const MIN_GROUP = 3;

    public function index(): View
    {
        return view('spec.tracer.index', [
            'campaigns' => TracerCampaign::query()->withCount(['responses', 'responses as submitted_count' => fn ($q) => $q->whereIn('status', ['submitted', 'corrected'])])->latest()->get(),
            'graduates' => Student::query()->whereNotNull('graduated_at')->count(),
            'cohorts' => Student::query()->whereNotNull('graduated_at')->selectRaw("extract(year from graduated_at)::int as y, count(*) as c")->groupBy('y')->pluck('c', 'y'),
            'classes' => Student::query()->whereNull('graduated_at')->distinct()->orderBy('class_name')->pluck('class_name'),
        ]);
    }

    /** T01: sekolah mengonfirmasi kelulusan; status alumni tidak membuka akses data karier. */
    public function markGraduates(Request $request): RedirectResponse
    {
        $data = $request->validate(['class_name' => ['required', 'string'], 'graduated_at' => ['required', 'date']]);
        $count = Student::query()->where('class_name', $data['class_name'])->whereNull('graduated_at')->update(['graduated_at' => $data['graduated_at']]);
        AuditLog::record('tracer.graduated', null, ['class' => $data['class_name'], 'count' => $count]);

        return back()->with('status', "{$count} siswa kelas {$data['class_name']} ditandai lulus.");
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:200'], 'cohort_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'opens_on' => ['required', 'date'], 'closes_on' => ['required', 'date', 'after_or_equal:opens_on'], 'intro' => ['nullable', 'string', 'max:2000']]);
        $campaign = TracerCampaign::query()->create([...$data, 'school_id' => $request->user()->school_id, 'status' => 'open']);
        $made = 0;
        foreach (Student::query()->whereYear('graduated_at', $data['cohort_year'])->get() as $s) {
            [$token, $hash] = PublicToken::make(40);
            TracerResponse::query()->create(['school_id' => $s->school_id, 'tracer_campaign_id' => $campaign->id, 'student_id' => $s->id, 'token_hash' => $hash, 'token' => $token]);
            $made++;
        }
        AuditLog::record('tracer.campaign_created', $campaign, ['targets' => $made]);

        return redirect()->route('admin.tracer.show', $campaign)->with('status', "Kampanye dibuat untuk {$made} lulusan angkatan {$data['cohort_year']}. Kampanye internal ini bukan laporan resmi.");
    }

    public function show(TracerCampaign $campaign): View
    {
        $responses = TracerResponse::query()->with('student.user')->where('tracer_campaign_id', $campaign->id)->get();
        $submitted = $responses->whereIn('status', ['submitted', 'corrected']);
        $statusCounts = collect(config('pkl.tracer_statuses'))->map(fn ($label, $key) => $submitted->filter(fn ($r) => in_array($key, $r->statuses ?? [], true))->count());
        $byMajor = $submitted->groupBy(fn ($r) => $r->student->major)->filter(fn ($g) => $g->count() >= self::MIN_GROUP)
            ->map(fn ($g) => collect(config('pkl.tracer_statuses'))->map(fn ($l, $k) => $g->filter(fn ($r) => in_array($k, $r->statuses ?? [], true))->count()));

        return view('spec.tracer.show', [
            'campaign' => $campaign,
            'responses' => $responses,
            'submitted' => $submitted->count(),
            'rate' => $responses->count() ? round($submitted->count() / $responses->count() * 100) : 0,
            'statusCounts' => $statusCounts,
            'byMajor' => $byMajor,
            'relevant' => $submitted->filter(fn ($r) => ($r->answers['relevance'] ?? null) === 'sesuai')->count(),
        ]);
    }

    public function exportCsv(TracerCampaign $campaign): StreamedResponse
    {
        AuditLog::record('tracer.exported', $campaign);
        $statuses = config('pkl.tracer_statuses');
        $rows = TracerResponse::query()->with('student')->where('tracer_campaign_id', $campaign->id)->whereIn('status', ['submitted', 'corrected'])->get()->groupBy(fn ($r) => $r->student->major);

        return response()->streamDownload(function () use ($rows, $statuses, $campaign): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Rekap internal tracer (bukan data resmi pemerintah)', $campaign->name, 'cut-off '.now()->format('Y-m-d')]);
            fputcsv($out, ['Program keahlian', 'Responden', ...array_values($statuses)]);
            foreach ($rows as $major => $g) {
                if ($g->count() < self::MIN_GROUP) {
                    continue;
                }
                fputcsv($out, [$major, $g->count(), ...array_map(fn ($k) => $g->filter(fn ($r) => in_array($k, $r->statuses ?? [], true))->count(), array_keys($statuses))]);
            }
            fclose($out);
        }, 'rekap-tracer-'.Str::slug($campaign->name).'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function form(string $token): Response
    {
        $result = PublicToken::within(TracerResponse::class, $token, function (TracerResponse $r) use ($token) {
            $r->load(['campaign', 'student.user', 'school']);

            return response(view('spec.public.tracer', ['r' => $r, 'token' => $token, 'statuses' => config('pkl.tracer_statuses')])->render());
        });

        return $result ?? response()->view('spec.public.invalid', ['message' => 'Tautan survei tidak dikenal.'], 404);
    }

    public function submit(Request $request, string $token): Response
    {
        $data = $request->validate([
            'statuses' => ['required', 'array', 'min:1'], 'statuses.*' => ['in:'.implode(',', array_keys(config('pkl.tracer_statuses')))],
            'workplace' => ['nullable', 'string', 'max:200'], 'study_place' => ['nullable', 'string', 'max:200'], 'business' => ['nullable', 'string', 'max:200'],
            'relevance' => ['nullable', 'in:sesuai,sebagian,tidak'], 'started_on' => ['nullable', 'date'], 'pkl_feedback' => ['nullable', 'string', 'max:2000'],
            'contact_ok' => ['nullable', 'boolean'], 'action' => ['required', 'in:draft,submit'], 'correction_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $result = PublicToken::within(TracerResponse::class, $token, function (TracerResponse $r) use ($data, $token) {
            $r->load('campaign');
            if ($r->campaign->status !== 'open' || now()->toDateString() > $r->campaign->closes_on->toDateString()) {
                return redirect()->route('tracer.form', $token)->withErrors(['statuses' => 'Periode survei sudah ditutup.']);
            }
            $answers = collect($data)->only(['workplace', 'study_place', 'business', 'relevance', 'started_on', 'pkl_feedback'])->filter()->all();
            $wasSubmitted = in_array($r->status, ['submitted', 'corrected'], true);
            if ($wasSubmitted && $data['action'] === 'submit' && trim((string) ($data['correction_reason'] ?? '')) === '') {
                return redirect()->route('tracer.form', $token)->withErrors(['correction_reason' => 'Tuliskan alasan koreksi jawaban.']);
            }
            $corrections = $r->corrections ?? [];
            if ($wasSubmitted && $data['action'] === 'submit') {
                $corrections[] = ['at' => now()->toIso8601String(), 'reason' => $data['correction_reason'], 'previous' => ['statuses' => $r->statuses, 'answers' => $r->answers]];
            }
            $r->update([
                'statuses' => $data['statuses'], 'answers' => $answers, 'corrections' => $corrections ?: null,
                'status' => $data['action'] === 'draft' ? ($wasSubmitted ? $r->status : 'draft') : ($wasSubmitted ? 'corrected' : 'submitted'),
                'submitted_at' => $data['action'] === 'submit' ? now() : $r->submitted_at,
            ]);
            $r->student->forceFill(['alumni_contact_ok' => (bool) ($data['contact_ok'] ?? false)])->save();

            return redirect()->route('tracer.form', $token)->with('status', $data['action'] === 'draft' ? 'Jawaban disimpan sementara.' : 'Terima kasih, jawaban terkirim.');
        });

        return $result ?? response()->view('spec.public.invalid', ['message' => 'Tautan survei tidak dikenal.'], 404);
    }

    /** Tautan undangan untuk satu responden (dibagikan operator, misalnya lewat WhatsApp). */
    public static function linkFor(TracerResponse $r): ?string
    {
        return $r->token ? route('tracer.form', $r->token) : null;
    }

    public static function waFor(TracerResponse $r, TracerCampaign $c): ?string
    {
        $url = self::linkFor($r);

        return $url ? Notify::waShare($r->student->user->phone, "Halo {$r->student->user->name}, sekolah mengundang Anda mengisi survei alumni \"{$c->name}\" (sekitar 3 menit): {$url}") : null;
    }
}

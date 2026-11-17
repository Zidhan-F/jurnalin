<?php

declare(strict_types=1);

namespace App\Http\Controllers\Spec;

use App\Enums\JournalStatus;
use App\Enums\PlacementStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Journal;
use App\Models\LearningTarget;
use App\Models\MentoringNote;
use App\Models\Placement;
use App\Models\WorkProof;
use App\Support\AiAssistant;
use App\Support\FinalReportBuilder;
use App\Support\LearningObjectives;
use App\Support\PklMonitor;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Asisten Jurnal (P26), Laporan Akhir Otomatis (P27), Ringkasan Pembimbing (P28), Latihan Wawancara (P36).
 * Semua keluaran berupa draf berbasis sumber yang wajib diperiksa manusia. Tanpa kunci API, fitur memakai
 * penyusun berbasis aturan sehingga tetap berguna dan tidak pernah mengarang isi.
 */
final class AiController extends Controller
{
    public function journalDraft(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->school->hasFeature('ai'), 403, 'Asisten Jurnal termasuk add-on AI.');
        $data = $request->validate(['points' => ['required', 'string', 'min:5', 'max:1500']]);
        $objectives = LearningObjectives::forSchool($user->school);
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r?\n|;/', $data['points']))));

        $prompt = "Susun uraian jurnal PKL dari poin berikut menjadi 3-6 kalimat runtut (langkah kerja lalu hasil). "
            ."Lalu di baris terakhir tulis 'TP:' diikuti kode TP yang paling relevan dari daftar ini, atau kosong bila tidak ada:\n"
            .$objectives->map(fn ($o) => "{$o->code} {$o->label}")->implode("\n")
            ."\n\nPoin dari siswa:\n- ".implode("\n- ", $lines);

        $ai = AiAssistant::complete($user, 'journal_draft', $prompt, 1500);
        if ($ai !== null) {
            $suggested = preg_match('/TP:\s*([A-Z0-9, ]+)/', $ai, $m) ? array_values(array_filter(array_map('trim', explode(',', $m[1])))) : [];
            $text = trim(preg_replace('/\n?TP:.*$/s', '', $ai) ?? $ai);
        } else {
            // Cadangan tanpa AI: merapikan poin menjadi kalimat tanpa menambah isi.
            $text = implode(' ', array_map(fn ($l) => ucfirst(rtrim($l, '.')).'.', $lines));
            $suggested = [];
        }

        return response()->json([
            'draft' => $text,
            'suggested_objectives' => $objectives->whereIn('code', $suggested)->pluck('id')->values(),
            'source' => $ai !== null ? 'ai' : 'rules',
            'notice' => 'Periksa dan sesuaikan draf ini. Jangan kirim kegiatan yang tidak Anda lakukan.',
        ]);
    }

    public function finalReport(Request $request): View
    {
        $student = $request->user()->student;
        abort_if($student === null, 403);
        $placement = Placement::query()->with(['company', 'pklPeriod', 'school', 'student.user'])->where('student_id', $student->id)->latest()->first();

        return view('spec.student.final-report', [
            'enabled' => $request->user()->school->hasFeature('ai'),
            'placement' => $placement,
            'report' => $placement ? FinalReportBuilder::build($placement) : null,
        ]);
    }

    public function mentorSummary(Request $request, Placement $placement): View
    {
        $user = $request->user();
        abort_unless($user->role === UserRole::SchoolAdmin || $placement->teacher_id === $user->teacher?->id, 403);
        $placement->load(['student.user', 'company', 'pklPeriod', 'school']);
        $today = CarbonImmutable::now($placement->school->timezone)->toDateString();
        $recent = Journal::query()->where('placement_id', $placement->id)->whereIn('status', [JournalStatus::Submitted, JournalStatus::Approved, JournalStatus::RevisionNeeded])->latest('journal_date')->limit(10)->get();
        $attendance = Attendance::query()->where('placement_id', $placement->id)->where('attendance_date', '>=', CarbonImmutable::parse($today)->subDays(14)->toDateString())->get();

        $facts = [
            'Jurnal 14 hari terakhir: '.$recent->where('journal_date', '>=', CarbonImmutable::parse($today)->subDays(14))->count(),
            'Presensi 14 hari terakhir: '.$attendance->groupBy(fn ($a) => $a->status->label())->map->count()->map(fn ($c, $k) => "{$k} {$c}")->implode(', '),
            'Jurnal perlu revisi: '.$recent->where('status', JournalStatus::RevisionNeeded)->count(),
            'Target terbuka: '.LearningTarget::query()->where('placement_id', $placement->id)->where('status', 'open')->count(),
            'Bukti Kerja menunggu tinjauan: '.WorkProof::query()->where('placement_id', $placement->id)->where('status', 'submitted')->count(),
        ];
        $kendala = $recent->pluck('obstacles')->filter()->take(3)->values();
        $questions = collect([
            $kendala->isNotEmpty() ? 'Bagaimana kendala "'.$kendala->first().'" diatasi sejauh ini?' : null,
            $recent->first() ? 'Apa yang paling dipelajari dari "'.$recent->first()->activity_title.'"?' : null,
            'Kompetensi apa yang ingin dicoba dua minggu ke depan dan apakah tersedia di tempat PKL?',
            'Adakah dukungan dari sekolah atau pembimbing lapangan yang dibutuhkan?',
        ])->filter()->values();

        return view('spec.mentor-summary', [
            'placement' => $placement,
            'facts' => $facts,
            'recent' => $recent,
            'notes' => MentoringNote::query()->where('placement_id', $placement->id)->latest()->limit(3)->get(),
            'questions' => $questions,
            'alerts' => PklMonitor::alerts(collect([$placement]), $today),
        ]);
    }

    public function interview(Request $request): View
    {
        $student = $request->user()->student;
        abort_if($student === null, 403);
        $placement = Placement::query()->with('company')->where('student_id', $student->id)->latest()->first();
        $proofs = $placement ? WorkProof::query()->where('placement_id', $placement->id)->latest()->limit(3)->get() : collect();
        $journals = $placement ? Journal::query()->where('placement_id', $placement->id)->where('status', JournalStatus::Approved)->latest('journal_date')->limit(3)->get() : collect();

        $questions = collect([
            $placement ? "Ceritakan peran Anda selama PKL di {$placement->company->name}." : 'Ceritakan pengalaman kerja atau praktik yang paling berkesan.',
            ...$proofs->map(fn ($w) => "Jelaskan proyek \"{$w->title}\": apa tugas Anda dan bagaimana hasilnya?"),
            ...$journals->map(fn ($j) => "Saat {$j->activity_title}, langkah apa yang Anda lakukan dan apa yang dipelajari?"),
            'Ceritakan satu kendala selama PKL dan cara Anda mengatasinya.',
        ])->take(5)->values();

        return view('spec.student.interview', ['questions' => $questions, 'aiEnabled' => AiAssistant::available($request->user())]);
    }

    public function interviewFeedback(Request $request): JsonResponse
    {
        $data = $request->validate(['question' => ['required', 'string', 'max:300'], 'answer' => ['required', 'string', 'min:10', 'max:2000']]);
        $prompt = "Pertanyaan wawancara: {$data['question']}\nJawaban siswa: {$data['answer']}\n\n"
            .'Beri umpan balik keterbacaan jawaban dalam 3 poin singkat: apakah ada situasi, tindakan, dan hasil yang konkret; satu saran perbaikan. '
            .'Jangan menilai kepribadian, jangan memberi skor, jangan memprediksi peluang diterima kerja.';
        $ai = AiAssistant::complete($request->user(), 'interview', $prompt, 800);

        if ($ai === null) {
            $words = str_word_count($data['answer']);
            $hasNumber = (bool) preg_match('/\d/', $data['answer']);
            $tips = [
                $words < 40 ? 'Jawaban masih singkat. Tambahkan situasi dan langkah yang Anda lakukan.' : 'Panjang jawaban sudah cukup untuk wawancara.',
                $hasNumber ? 'Bagus, ada angka atau ukuran hasil yang konkret.' : 'Tambahkan hasil yang terukur, misalnya jumlah tamu, waktu, atau target.',
                'Gunakan pola situasi, tindakan, hasil agar cerita mudah diikuti.',
            ];
            $ai = '- '.implode("\n- ", $tips);
        }

        return response()->json(['feedback' => $ai, 'notice' => 'Umpan balik ini latihan, bukan penilaian atau prediksi kelulusan kerja.']);
    }
}

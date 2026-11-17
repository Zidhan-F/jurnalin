<?php

declare(strict_types=1);

namespace App\Http\Controllers\Spec;

use App\Http\Controllers\Controller;
use App\Models\ReadinessModule;
use App\Models\ReadinessResult;
use App\Models\School;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Pembekalan pra-PKL (P43). Status "siap PKL" bukan syarat kelulusan kecuali ditetapkan sekolah. */
final class ReadinessController extends Controller
{
    public function studentIndex(Request $request): View
    {
        $student = $request->user()->student;
        abort_if($student === null, 403);
        $modules = $this->modules($request->user()->school)->where('is_active', true)->values();

        return view('spec.student.readiness', [
            'modules' => $modules,
            'results' => ReadinessResult::query()->where('student_id', $student->id)->get()->keyBy('readiness_module_id'),
        ]);
    }

    public function submitQuiz(Request $request, ReadinessModule $module): RedirectResponse
    {
        $student = $request->user()->student;
        abort_if($student === null || ! $module->is_active, 404);
        $answers = $request->validate(['answers' => ['required', 'array'], 'answers.*' => ['integer']])['answers'];
        $quiz = $module->quiz ?? [];
        $correct = collect($quiz)->filter(fn ($q, $i) => isset($answers[$i]) && (int) $answers[$i] === (int) $q['answer'])->count();
        $score = $quiz ? (int) round($correct / count($quiz) * 100) : 100;
        $passed = $score >= $module->pass_score;

        ReadinessResult::query()->updateOrCreate(
            ['readiness_module_id' => $module->id, 'student_id' => $student->id],
            ['school_id' => $student->school_id, 'score' => $score, 'passed' => $passed, 'completed_at' => now()],
        );

        return back()->with('status', $passed ? "Lulus modul {$module->title} dengan skor {$score}." : "Skor {$score}. Baca ulang materi lalu coba lagi (batas lulus {$module->pass_score}).");
    }

    public function admin(Request $request): View
    {
        $modules = $this->modules($request->user()->school);
        $total = Student::query()->count();

        return view('spec.admin.readiness', [
            'modules' => $modules->load('results'),
            'total' => $total,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'summary' => ['nullable', 'string', 'max:300'],
            'content' => ['required', 'string', 'max:10000'],
            'video_url' => ['nullable', 'url', 'max:300'],
            'quiz_text' => ['nullable', 'string', 'max:5000'],
            'pass_score' => ['required', 'integer', 'min:0', 'max:100'],
        ]);
        // Format kuis per baris: Pertanyaan | opsi A | opsi B | opsi C | nomor jawaban benar (1-3)
        $quiz = collect(preg_split('/\r?\n/', (string) ($data['quiz_text'] ?? '')))->map(fn ($l) => array_map('trim', explode('|', $l)))
            ->filter(fn ($p) => count($p) >= 4)->map(fn ($p) => ['q' => $p[0], 'options' => array_slice($p, 1, -1), 'answer' => max(0, (int) end($p) - 1)])->values()->all();

        ReadinessModule::query()->create([...collect($data)->except('quiz_text')->all(), 'quiz' => $quiz, 'school_id' => $request->user()->school_id, 'sort' => 99]);

        return back()->with('status', 'Modul pembekalan ditambahkan. Pastikan konten pihak ketiga berlisensi.');
    }

    public function toggle(ReadinessModule $module): RedirectResponse
    {
        $module->update(['is_active' => ! $module->is_active]);

        return back()->with('status', 'Status modul diperbarui.');
    }

    private function modules(School $school)
    {
        $query = ReadinessModule::query()->where('school_id', $school->id);
        if (! (clone $query)->exists()) {
            foreach (config('pkl.readiness_modules') as $i => $m) {
                ReadinessModule::query()->create([...$m, 'school_id' => $school->id, 'sort' => $i]);
            }
        }

        return $query->orderBy('sort')->get();
    }
}

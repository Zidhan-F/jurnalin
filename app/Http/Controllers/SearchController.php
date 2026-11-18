<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Journal;
use App\Models\Student;
use App\Models\Teacher;
use App\Support\GlobalSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SearchController extends Controller
{
    public function index(Request $request): View
    {
        $query = (string) $request->query('q', '');

        return view('search.index', [
            'query' => $query,
            'results' => $this->links($request, GlobalSearch::run($request->user(), $query, 10)),
            'tooShort' => mb_strlen(trim($query)) < GlobalSearch::MIN_LENGTH,
        ]);
    }

    public function suggest(Request $request): JsonResponse
    {
        $groups = $this->links($request, GlobalSearch::run($request->user(), (string) $request->query('q', ''), 3));

        return response()->json(['results' => collect($groups)->flatten(1)->take(8)->values()]);
    }

    /** @return array<string, list<array{type: string, label: string, hint: string, url: string}>> */
    private function links(Request $request, array $found): array
    {
        $isAdmin = $request->user()->role === UserRole::SchoolAdmin;

        return [
            'Siswa' => $found['students']->map(function (Student $s) use ($isAdmin) {
                $placement = $s->placements->first();

                return ['type' => 'student', 'label' => $s->user->name, 'hint' => trim($s->class_name.' · NISN '.$s->nisn),
                    'url' => $placement ? route('monitor.participant', $placement) : ($isAdmin ? route('admin.students') : route('teacher.index'))];
            })->all(),
            'Guru' => $found['teachers']->map(fn (Teacher $t) => ['type' => 'teacher', 'label' => $t->user->name,
                'hint' => (string) $t->specialization, 'url' => route('admin.users.edit', $t->user)])->all(),
            'Tempat PKL' => $found['companies']->map(fn (Company $c) => ['type' => 'company', 'label' => $c->name,
                'hint' => (string) ($c->industry_type ?: $c->city), 'url' => $isAdmin ? route('admin.companies') : route('monitor.participant', $c->placements->first())])->all(),
            'Jurnal' => $found['journals']->map(fn (Journal $j) => ['type' => 'journal', 'label' => $j->activity_title,
                'hint' => $j->student->user->name.' · '.$j->journal_date->translatedFormat('d M Y'), 'url' => route('monitor.participant', $j->placement)])->all(),
        ];
    }
}

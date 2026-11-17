@extends('layouts.student-pwa', ['title' => 'Pembekalan pra-PKL - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Pembekalan pra-PKL</h1>
        <p>Modul singkat sebelum berangkat: K3, etika kerja, dan komunikasi. Baca materi lalu kerjakan kuis.</p>
    </div>
</div>
@forelse($modules as $m)
    @php $r = $results[$m->id] ?? null; @endphp
    <details class="card stack-sm" @if(! $r?->passed) open @endif>
        <summary class="flex justify-between items-center gap-2 min-h-11 font-semibold">
            <span>{{ $m->title }}</span>
            <span class="badge {{ $r?->passed ? 'badge-ok' : ($r ? 'badge-warn' : '') }}">{{ $r?->passed ? 'Siap · skor '.$r->score : ($r ? 'Belum lulus · '.$r->score : 'Belum dikerjakan') }}</span>
        </summary>
        @if($m->summary)<p class="hint">{{ $m->summary }}</p>@endif
        <div class="whitespace-pre-line">{{ $m->content }}</div>
        @if($m->video_url)<a href="{{ $m->video_url }}" target="_blank" rel="noopener" class="link">Tonton video materi</a>@endif
        @if($m->quiz)
            <form method="POST" action="{{ route('student.readiness.submit', $m) }}" class="stack-sm border-t border-border pt-3">
                @csrf
                @foreach($m->quiz as $i => $q)
                    <fieldset class="stack-sm">
                        <legend class="font-semibold">{{ $i + 1 }}. {{ $q['q'] }}</legend>
                        @foreach($q['options'] as $oi => $opt)
                            <label class="flex items-center gap-2"><input type="radio" name="answers[{{ $i }}]" value="{{ $oi }}" required> {{ $opt }}</label>
                        @endforeach
                    </fieldset>
                @endforeach
                <button class="btn btn-primary">Kirim jawaban</button>
            </form>
        @endif
    </details>
@empty
    <div class="empty">Sekolah belum menyiapkan modul pembekalan.</div>
@endforelse
<p class="hint">Status siap PKL membantu persiapan dan bukan syarat kelulusan kecuali ditetapkan sekolah.</p>
@endsection

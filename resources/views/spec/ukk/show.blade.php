@extends('layouts.app', ['title' => $session->name.' - UKK - Jurnalin'])

@section('content')
@php $final = $session->status === 'final'; @endphp
<div class="page-head">
    <div>
        <h1>{{ $session->name }}</h1>
        <p>{{ $session->concentration }} · {{ $session->venue }} · {{ $session->scheduled_at->format('d/m/Y H:i') }} · instrumen versi {{ $session->instrument_version }} · {{ ['prepared' => 'Disiapkan', 'ongoing' => 'Berlangsung', 'final' => 'Final'][$session->status] }}</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a class="btn btn-outline btn-sm" href="{{ route('reports.ukk-minutes', $session) }}">Berita acara PDF</a>
        <a class="btn btn-outline btn-sm" href="{{ route('admin.ukk.csv', $session) }}">Rekap CSV</a>
    </div>
</div>

<div class="split">
    <div class="stack">
        <section class="card stack-sm">
            <h2 class="section-title">Instrumen penilaian</h2>
            <form method="POST" action="{{ route('admin.ukk.instrument', $session) }}" class="stack-sm">
                @csrf
                <label class="label" for="aspects_text">Satu aspek per baris: kode | nama aspek | bobot</label>
                <textarea id="aspects_text" name="aspects_text" class="field tabular-nums" rows="5" @disabled($final)>{{ collect($session->instrument['aspects'])->map(fn ($a) => $a['code'].' | '.$a['label'].' | '.$a['weight'])->implode("\n") }}</textarea>
                <p class="hint">Mengubah instrumen setelah penilaian dimulai membuat versi baru.</p>
                <button class="btn btn-outline btn-sm" @disabled($final)>Simpan instrumen</button>
            </form>
        </section>

        <section class="card stack-sm">
            <h2 class="section-title">Penguji eksternal</h2>
            @foreach($grants as $g)<p class="hint">{{ $g->contact->name }} · {{ $g->otp_verified_at ? 'identitas terverifikasi' : 'belum verifikasi' }} · s.d. {{ $g->expires_at->format('d/m/Y') }}</p>@endforeach
            <form method="POST" action="{{ route('admin.ukk.examiner', $session) }}" class="grid grid-cols-2 gap-2">
                @csrf
                <select name="partner_contact_id" class="field col-span-2" aria-label="Penguji terdaftar"><option value="">Penguji baru (isi di bawah)</option>@foreach($examiners as $e)<option value="{{ $e->id }}">{{ $e->name }} · {{ $e->company?->name }}</option>@endforeach</select>
                <input name="name" class="field" placeholder="Nama penguji" aria-label="Nama penguji">
                <input name="email" type="email" class="field" placeholder="Email (untuk kode verifikasi)" aria-label="Email penguji">
                <input name="position" class="field" placeholder="Jabatan / kualifikasi" aria-label="Kualifikasi">
                <input name="phone" type="tel" class="field" placeholder="WhatsApp" aria-label="WhatsApp">
                <button class="btn btn-navy btn-sm col-span-2" @disabled($final)>Buat tautan penguji</button>
            </form>
        </section>

        @unless($final)
            <form method="POST" action="{{ route('admin.ukk.participants', $session) }}" class="card stack-sm">
                @csrf
                <h2 class="section-title">Tambah peserta dari data siswa</h2>
                <div class="grid gap-1 max-h-64 overflow-y-auto">
                    @foreach($students as $s)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="student_ids[]" value="{{ $s->id }}"> {{ $s->user->name }} <span class="hint">· {{ $s->class_name }} · {{ $s->major }}</span></label>@endforeach
                </div>
                <button class="btn btn-navy btn-sm">Tambahkan</button>
            </form>
        @endunless
    </div>

    <section class="stack-sm">
        <h2 class="section-title">Peserta ({{ $session->participants->count() }}/{{ $session->quota }})</h2>
        @forelse($session->participants as $p)
            @php $mine = $p->scores->firstWhere('examiner_user_id', auth()->id()); @endphp
            <details class="card">
                <summary class="flex flex-wrap justify-between gap-2 min-h-11 items-center">
                    <span><strong class="tabular-nums">{{ $p->participant_number }}</strong> {{ $p->student->user->name }}</span>
                    <span class="flex gap-2">
                        <span class="badge">{{ $p->scores->count() }} penguji</span>
                        @if($p->final_score !== null)<span class="badge badge-ok tabular-nums">{{ number_format($p->final_score, 2) }} · {{ $p->predicate }}</span>@endif
                        @if(isset($certificates[$p->id]))<span class="badge badge-navy">Surat {{ $certificates[$p->id]->verification_code }}</span>@endif
                    </span>
                </summary>
                @if(isset($certificates[$p->id]) && $certificates[$p->id]->status === 'valid')
                    <form method="POST" action="{{ route('admin.certificates.revoke', $certificates[$p->id]) }}" class="flex flex-wrap gap-2 mt-2 items-end">
                        @csrf
                        <a class="link text-sm" href="{{ route('reports.certificate', $certificates[$p->id]) }}">Unduh surat PDF</a>
                        <input name="reason" class="field" required minlength="10" placeholder="Alasan pencabutan surat" aria-label="Alasan pencabutan surat {{ $p->participant_number }}">
                        <button class="btn btn-danger btn-sm">Cabut surat</button>
                    </form>
                @endif
                @foreach($p->scores as $sc)<p class="hint mt-2">{{ $sc->examinerContact?->name ?? $sc->examinerUser?->name }} (v{{ $sc->instrument_version }}): {{ collect($sc->scores)->map(fn ($v, $k) => "{$k} {$v}")->implode(', ') }}</p>@endforeach
                @unless($final)
                    <form method="POST" action="{{ route('admin.ukk.score', [$session, $p]) }}" class="grid sm:grid-cols-2 gap-2 mt-3">
                        @csrf
                        @foreach($session->instrument['aspects'] as $a)
                            <div><label class="label" for="s-{{ $p->id }}-{{ $a['code'] }}">{{ $a['label'] }} ({{ $a['weight'] }}%)</label><input id="s-{{ $p->id }}-{{ $a['code'] }}" name="scores[{{ $a['code'] }}]" type="number" min="0" max="100" required class="field tabular-nums" value="{{ $mine->scores[$a['code']] ?? '' }}"></div>
                        @endforeach
                        <button class="btn btn-outline btn-sm sm:col-span-2">Simpan nilai saya (penguji internal)</button>
                    </form>
                @endunless
            </details>
        @empty
            <div class="empty">Belum ada peserta.</div>
        @endforelse

        @if(! $final)
            <form method="POST" action="{{ route('admin.ukk.finalize', $session) }}" class="card-muted stack-sm">
                @csrf
                <label class="label" for="minutes">Isi berita acara (opsional)</label>
                <textarea id="minutes" name="minutes" class="field">{{ $session->minutes }}</textarea>
                <button class="btn btn-primary">Finalkan rekap & kunci snapshot</button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.ukk.certificates', $session) }}">@csrf<button class="btn btn-primary btn-block">Terbitkan surat keterangan hasil ber-QR</button></form>
        @endif
    </section>
</div>
@endsection

@extends('layouts.app', ['title' => 'Jurnal yang perlu diperiksa - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Jurnal yang perlu diperiksa</h1>
        <p>Antrean jurnal siswa bimbingan, dikelompokkan sesuai jadwal periksa Anda. Persetujuan mengunci isi jurnal; pembukaan ulang hanya dengan alasan dan tercatat.</p>
    </div>
</div>

<section class="card stack-sm mb-6 {{ $isDue && $journals->isNotEmpty() ? 'card-attention' : '' }}" aria-labelledby="judul-jadwal">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 id="judul-jadwal" class="section-title">Jadwal periksa jurnal</h2>
            <p class="hint">
                @if($frequency === 'daily')
                    Anda memeriksa setiap hari dan menerima notifikasi untuk setiap jurnal baru.
                @else
                    Pemeriksaan berikutnya: <strong class="text-ink">{{ $isDue ? 'hari ini' : $nextCheck->translatedFormat('l, d M Y') }}</strong>{{ $lastReviewed ? ' ('.\App\Support\ReviewSchedule::intervalDays($frequency).' hari sejak pemeriksaan terakhir '.$lastReviewed->translatedFormat('d M').')' : '' }}.
                    Jadwal dihitung bergulir, tidak terikat hari tertentu, jadi cocok untuk tempat PKL yang buka Sabtu-Minggu. Notifikasi per jurnal dimatikan; ringkasan dikirim saat jadwal tiba.
                @endif
            </p>
        </div>
        <form method="POST" action="{{ route('teacher.review.frequency') }}" class="flex flex-wrap items-end gap-2">
            @csrf
            <div>
                <label class="label" for="review_frequency">Saya memeriksa jurnal</label>
                <select id="review_frequency" name="review_frequency" class="field">
                    @foreach(\App\Support\ReviewSchedule::FREQUENCIES as $key => $label)
                        <option value="{{ $key }}" @selected($frequency === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-navy">Simpan jadwal</button>
        </form>
    </div>
</section>

<form method="GET" class="card-muted grid sm:grid-cols-6 gap-3 items-end mb-6">
    <div class="sm:col-span-2"><label class="label" for="placement">Siswa</label>
        <select id="placement" name="placement" class="field"><option value="">Semua siswa</option>@foreach($placements as $p)<option value="{{ $p->id }}" @selected(($filters['placement'] ?? '') === $p->id)>{{ $p->student->user->name }}</option>@endforeach</select></div>
    <div><label class="label" for="status">Status</label>
        <select id="status" name="status" class="field">@foreach(['submitted' => 'Menunggu', 'revision_needed' => 'Perlu revisi', 'approved' => 'Disetujui', 'draft' => 'Draf'] as $v => $l)<option value="{{ $v }}" @selected(($filters['status'] ?? 'submitted') === $v)>{{ $l }}</option>@endforeach</select></div>
    <div><label class="label" for="from">Dari</label><input id="from" name="from" type="date" class="field" value="{{ $filters['from'] ?? '' }}"></div>
    <div><label class="label" for="q">Cari</label><input id="q" name="q" class="field" value="{{ $filters['q'] ?? '' }}"></div>
    <button class="btn btn-navy">Terapkan</button>
</form>

<form id="bulk-approve" method="POST" action="/teacher/journals/bulk-approve" class="flex flex-wrap items-center gap-2 mb-4">
    @csrf
    <span class="hint">Batch hanya untuk jurnal yang sudah Anda baca dan centang. Gunakan "Sudah saya baca semua" per kelompok setelah membaca seluruh isinya.</span>
    <button class="btn btn-primary btn-sm">Setujui yang dicentang</button>
</form>

@forelse($groups as $groupKey => $group)
<section class="stack-sm mb-6" x-data aria-labelledby="grup-{{ $loop->index }}">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border pb-2">
        <h2 id="grup-{{ $loop->index }}" class="section-title">{{ $group['label'] }} <span class="hint font-normal">· {{ $group['items']->count() }} jurnal · {{ $group['items']->pluck('student.user.name')->unique()->count() }} siswa</span></h2>
        @if($group['items']->contains(fn ($j) => $j->status === \App\Enums\JournalStatus::Submitted))
            <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" @change="$root.querySelectorAll('input[form=bulk-approve]').forEach(c => c.checked = $event.target.checked)"> Sudah saya baca semua di kelompok ini</label>
        @endif
    </div>
@foreach($group['items'] as $journal)
    <article class="card mb-3 stack-sm {{ $journal->status === \App\Enums\JournalStatus::Submitted ? 'card-attention' : '' }}" x-data="{ rev: false, reopen: false }">
        <div class="flex flex-wrap justify-between gap-2">
            <div>
                <p class="hint tabular-nums">{{ $journal->journal_date->format('d M Y') }} · {{ $journal->work_unit }} · {{ $journal->duration_minutes }} menit · versi {{ $journal->version }}</p>
                <h2 class="font-bold">{{ $journal->activity_title }}</h2>
                <p class="text-sm font-medium text-navy">{{ $journal->student->user->name }} · {{ $journal->placement->company->name }}</p>
            </div>
            <span class="badge {{ $journal->status === \App\Enums\JournalStatus::Approved ? 'badge-ok' : 'badge-warn' }}">{{ $journal->status->label() }}</span>
        </div>
        <p class="whitespace-pre-line">{{ $journal->activity_description }}</p>
        <dl class="kv">
            @if($journal->learning_outcome)<dt>Pelajaran</dt><dd class="font-normal">{{ $journal->learning_outcome }}</dd>@endif
            @if($journal->obstacles)<dt>Kendala</dt><dd class="font-normal">{{ $journal->obstacles }}</dd>@endif
            @if($journal->tools_materials)<dt>Alat</dt><dd class="font-normal">{{ $journal->tools_materials }}</dd>@endif
            <dt>Konfirmasi siswa</dt><dd>{{ $journal->confirmed_done ? 'Kegiatan dinyatakan benar dilakukan' : 'Belum dikonfirmasi' }}</dd>
        </dl>
        @if($journal->objectives->isNotEmpty())
            <div class="flex flex-wrap gap-1">@foreach($journal->objectives as $o)<span class="badge {{ $o->pivot->status === 'verified' ? 'badge-ok' : '' }}">{{ $o->code }} {{ $o->pivot->status === 'verified' ? 'terverifikasi' : 'diklaim' }}</span>@endforeach</div>
        @endif
        @if($journal->photos())
            <div class="flex flex-wrap gap-2">@foreach($journal->photos() as $photo)<a href="/media/{{ $photo }}" target="_blank"><img src="/media/{{ $photo }}" alt="Foto kegiatan {{ $loop->iteration }}" class="h-24 rounded-md border border-border object-cover"></a>@endforeach</div>
        @endif

        @if($journal->status === \App\Enums\JournalStatus::Submitted)
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="journal_ids[]" value="{{ $journal->id }}" form="bulk-approve"> Sudah saya baca</label>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="/teacher/journals/{{ $journal->id }}/approve">@csrf<button class="btn btn-primary btn-sm">Setujui</button></form>
                <button type="button" class="btn btn-warn btn-sm" @click="rev = ! rev" :aria-expanded="rev">Minta revisi</button>
                @if($journal->objectives->where('pivot.status', 'claimed')->isNotEmpty())
                    <form method="POST" action="{{ route('learning.objectives.verify', $journal) }}">@csrf<button class="btn btn-outline btn-sm">Verifikasi klaim TP</button></form>
                @endif
            </div>
            <form method="POST" action="/teacher/journals/{{ $journal->id }}/revision" x-show="rev" x-cloak class="stack-sm" @keydown.escape="rev = false">
                @csrf
                <label class="label" for="fb-{{ $journal->id }}">Catatan perbaikan</label>
                <textarea id="fb-{{ $journal->id }}" name="teacher_feedback" class="field" required minlength="5" x-ref="fb"></textarea>
                <div class="flex flex-wrap gap-1">
                    @foreach($commentTemplates as $t)<button type="button" class="badge" @click="$refs.fb.value = ($refs.fb.value ? $refs.fb.value + ' ' : '') + @js($t)">{{ $t }}</button>@endforeach
                </div>
                <button class="btn btn-warn btn-sm">Kirim revisi</button>
            </form>
        @elseif($journal->status === \App\Enums\JournalStatus::Approved)
            <button type="button" class="link text-sm" @click="reopen = ! reopen" :aria-expanded="reopen">Buka ulang dengan alasan</button>
            <form method="POST" action="{{ route('journals.reopen', $journal) }}" x-show="reopen" x-cloak class="stack-sm">
                @csrf
                <label class="label" for="ro-{{ $journal->id }}">Alasan membuka ulang (tercatat di audit)</label>
                <input id="ro-{{ $journal->id }}" name="reason" class="field" required minlength="10">
                <button class="btn btn-danger btn-sm">Buka ulang jurnal</button>
            </form>
        @endif
    </article>
@endforeach
</section>
@empty
    <div class="empty">Tidak ada jurnal pada filter ini. Antrean kosong berarti semua jurnal sudah Anda tinjau.</div>
@endforelse
@endsection

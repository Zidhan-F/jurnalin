@extends('layouts.student-pwa', ['title' => 'Belajar - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Belajar selama PKL</h1>
        <p>Capaian Tujuan Pembelajaran, target dari pembimbing, catatan bimbingan, refleksi mingguan, dan Bukti Kerja.</p>
    </div>
</div>

@if($placement === null)
    <div class="empty">Belum ada penempatan PKL aktif.</div>
@else
<nav class="tab-nav" aria-label="Bagian halaman Belajar">
    <a href="#tp">TP</a><a href="#target">Target</a><a href="#bimbingan">Bimbingan</a><a href="#refleksi">Refleksi</a><a href="#bukti">Bukti Kerja</a>
</nav>

<section id="tp" class="card stack-sm">
    <h2 class="section-title">Peta Tujuan Pembelajaran</h2>
    <p class="hint">Tautkan jurnal ke TP saat menulis jurnal. Guru memverifikasi bukti. Jumlah jurnal bukan ukuran keterampilan.</p>
    <ul class="check-list">
        @foreach($objectives as $row)
            <li class="{{ $row['verified'] > 0 ? 'is-done' : '' }}">
                <span class="check-mark">{{ $row['objective']->code }}</span>
                <span>{{ $row['objective']->label }}
                    @if($row['verified'] === 0 && ($suggestions[$row['objective']->code] ?? collect())->isNotEmpty())
                        <span class="block hint">Ide kegiatan untuk didiskusikan: {{ $suggestions[$row['objective']->code]->take(2)->pluck('title')->implode('; ') }}</span>
                    @endif
                </span>
                <span class="hint tabular-nums">{{ $row['verified'] }} terverifikasi</span>
            </li>
        @endforeach
    </ul>
    <p class="hint">"Belum ada bukti" bukan berarti "tidak mampu". Saran hanya bahan diskusi bila pekerjaannya tersedia di tempat PKL.</p>
</section>

<section id="target" class="card stack-sm">
    <h2 class="section-title">Target belajar</h2>
    @forelse($targets as $t)
        <article class="border-t border-border pt-2 stack-sm">
            <div class="flex justify-between gap-2"><strong>{{ $t->title }}</strong><span class="badge {{ $t->status === 'done' ? 'badge-ok' : ($t->status === 'submitted' ? 'badge-warn' : '') }}">{{ ['open' => 'Berjalan', 'submitted' => 'Bukti dikirim', 'done' => 'Selesai'][$t->status] }}</span></div>
            @if($t->indicator)<p class="hint">Indikator: {{ $t->indicator }} @if($t->due_date)· s.d. <span class="tabular-nums">{{ $t->due_date->format('d/m') }}</span>@endif</p>@endif
            @if($t->status === 'open')
                <form method="POST" action="{{ route('student.targets.submit', $t) }}" class="stack-sm">
                    @csrf
                    <label class="label" for="tn-{{ $t->id }}">Apa yang sudah Anda lakukan?</label>
                    <textarea id="tn-{{ $t->id }}" name="student_note" class="field" required></textarea>
                    <label class="label" for="te-{{ $t->id }}">Jurnal pendukung</label>
                    <select id="te-{{ $t->id }}" name="evidence_journal_ids[]" multiple class="field" size="4">
                        @foreach($journals as $j)<option value="{{ $j->id }}">{{ $j->journal_date->format('d/m') }} {{ $j->activity_title }}</option>@endforeach
                    </select>
                    <button class="btn btn-navy btn-sm">Kirim bukti target</button>
                </form>
            @endif
            @if($t->reviewer_note)<p class="callout">Catatan pembimbing: {{ $t->reviewer_note }}</p>@endif
        </article>
    @empty
        <p class="hint">Belum ada target dari pembimbing.</p>
    @endforelse
</section>

<section id="bimbingan" class="card stack-sm">
    <h2 class="section-title">Bimbingan terstruktur</h2>
    @forelse($notes as $n)
        <article class="border-t border-border pt-2 stack-sm">
            <strong>{{ $n->topic }}</strong>
            <p class="text-sm">{{ $n->note }}</p>
            @if($n->action_item)<p class="hint">Tindakan: {{ $n->action_item }} @if($n->due_date)(s.d. {{ $n->due_date->format('d/m') }})@endif</p>@endif
            @if($n->student_response)<p class="callout">Tanggapan Anda: {{ $n->student_response }}</p>
            @elseif($n->status === 'open')
                <form method="POST" action="{{ route('student.mentoring.respond', $n) }}" class="flex gap-2">@csrf<input name="student_response" class="field" required placeholder="Tanggapan atau bukti penyelesaian" aria-label="Tanggapan untuk {{ $n->topic }}"><button class="btn btn-navy btn-sm">Kirim</button></form>
            @endif
        </article>
    @empty
        <p class="hint">Belum ada catatan bimbingan.</p>
    @endforelse
</section>

<section id="refleksi" class="card stack-sm">
    <h2 class="section-title">Refleksi mingguan</h2>
    @php $current = $reflections->firstWhere(fn ($r) => $r->week_start->toDateString() === $weekStart); @endphp
    <form method="POST" action="{{ route('student.reflections.store') }}" class="stack-sm">
        @csrf
        <input type="hidden" name="week_start" value="{{ $weekStart }}">
        <p class="hint">Minggu mulai <span class="tabular-nums">{{ \Carbon\Carbon::parse($weekStart)->format('d/m/Y') }}</span>. Refleksi bersifat pribadi.</p>
        <div><label class="label" for="achievements">Apa yang saya capai?</label><textarea id="achievements" name="achievements" class="field" required>{{ $current?->achievements }}</textarea></div>
        <div><label class="label" for="difficulties">Apa yang sulit?</label><textarea id="difficulties" name="difficulties" class="field">{{ $current?->difficulties }}</textarea></div>
        <div><label class="label" for="lessons">Pelajaran terpenting</label><input id="lessons" name="lessons" class="field" value="{{ $current?->lessons }}"></div>
        <div><label class="label" for="support_needed">Dukungan yang saya butuhkan</label><input id="support_needed" name="support_needed" class="field" value="{{ $current?->support_needed }}"></div>
        <div><label class="label" for="next_target">Target minggu depan</label><input id="next_target" name="next_target" class="field" value="{{ $current?->next_target }}"></div>
        <button class="btn btn-primary">Simpan refleksi</button>
    </form>
    @foreach($reflections->where('teacher_comment', '!=', null) as $r)
        <p class="callout">Komentar guru (minggu {{ $r->week_start->format('d/m') }}): {{ $r->teacher_comment }}</p>
    @endforeach
</section>

<section id="bukti" class="card stack-sm">
    <h2 class="section-title">Bukti Kerja & proyek</h2>
    @foreach($proofs as $w)
        <div class="border-t border-border pt-2">
            <div class="flex justify-between gap-2"><strong>{{ $w->title }}</strong><span class="badge {{ $w->status === 'verified' ? 'badge-ok' : 'badge-warn' }}">{{ ['submitted' => 'Menunggu tinjauan', 'verified' => 'Ditinjau pembimbing', 'revision' => 'Perlu revisi', 'draft' => 'Draf'][$w->status] }}</span></div>
            @if($w->review_note)<p class="hint">Catatan: {{ $w->review_note }}</p>@endif
        </div>
    @endforeach
    <form method="POST" action="{{ route('student.proofs.store') }}" enctype="multipart/form-data" class="stack-sm border-t border-border pt-3">
        @csrf
        <div class="grid grid-cols-2 gap-2">
            <div><label class="label" for="pkind">Jenis</label><select id="pkind" name="kind" class="field"><option value="proof">Hasil kerja</option><option value="project">Proyek / kerja kelompok</option></select></div>
            <div><label class="label" for="ptitle">Judul</label><input id="ptitle" name="title" class="field" required></div>
        </div>
        <div><label class="label" for="presult">Hasil yang dicapai</label><textarea id="presult" name="result" class="field" required></textarea></div>
        <div><label class="label" for="pcontrib">Kontribusi saya</label><input id="pcontrib" name="contribution" class="field"></div>
        <div><label class="label" for="pteam">Anggota tim (bila proyek)</label><input id="pteam" name="team_members" class="field"></div>
        <div><label class="label" for="pjournals">Jurnal sumber</label>
            <select id="pjournals" name="journal_ids[]" multiple required class="field" size="5">@foreach($journals as $j)<option value="{{ $j->id }}">{{ $j->journal_date->format('d/m') }} {{ $j->activity_title }}</option>@endforeach</select></div>
        <div><label class="label" for="pphoto">Foto hasil (opsional)</label><input id="pphoto" name="photo" type="file" accept="image/*" class="block w-full text-sm"></div>
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="publish_allowed" value="1" class="mt-1"> Boleh ditampilkan di Paspor Kompetensi bila saya memilihnya (tanpa data rahasia perusahaan)</label>
        <button class="btn btn-navy">Kirim Bukti Kerja</button>
    </form>
</section>
@endif
@endsection

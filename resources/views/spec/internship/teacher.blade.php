@extends('layouts.app', ['title' => 'Magang Guru - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Magang guru</h1>
        <p>Konteks peserta magang di akun guru Anda. Jurnal Anda ditinjau guru lain; Anda tidak dapat menyetujui jurnal sendiri.</p>
    </div>
</div>

@if($toReview->isNotEmpty())
    <section class="card card-attention stack-sm mb-6">
        <h2 class="section-title">Jurnal magang guru lain yang perlu Anda tinjau ({{ $toReview->count() }})</h2>
        @foreach($toReview as $j)
            <div class="border-t border-border pt-2 stack-sm">
                <p><strong>{{ $j->internship->teacher->user->name }}</strong> · <span class="tabular-nums">{{ $j->journal_date->format('d/m/Y') }}</span></p>
                <p>{{ $j->work_done }}</p>
                <form method="POST" action="{{ route('teacher.internship.review', $j) }}" class="flex flex-wrap gap-2">@csrf<input name="feedback" class="field" placeholder="Catatan (wajib bila revisi)" aria-label="Catatan tinjauan"><button name="status" value="approved" class="btn btn-primary btn-sm">Setujui</button><button name="status" value="revision_needed" class="btn btn-warn btn-sm">Minta revisi</button></form>
            </div>
        @endforeach
    </section>
@endif

@forelse($internships as $i)
    <section class="stack mb-8">
        <div class="flex flex-wrap justify-between gap-2"><h2 class="section-title">{{ $i->title }} · {{ $i->company->name }}</h2><a class="btn btn-outline btn-sm" href="{{ route('reports.internship', $i) }}">Unduh laporan PDF</a></div>
        <div class="split">
            <div class="stack">
                <form method="POST" action="{{ route('teacher.internship.journal', $i) }}" enctype="multipart/form-data" class="card-muted stack-sm">
                    @csrf
                    <h3 class="font-bold">Jurnal magang</h3>
                    <div><label class="label" for="jd-{{ $i->id }}">Tanggal</label><input id="jd-{{ $i->id }}" name="journal_date" type="date" class="field" value="{{ now()->toDateString() }}" required></div>
                    <div><label class="label" for="jw-{{ $i->id }}">Pekerjaan yang dilakukan</label><textarea id="jw-{{ $i->id }}" name="work_done" class="field" required></textarea></div>
                    <div><label class="label" for="jp-{{ $i->id }}">Proses industri yang diamati</label><textarea id="jp-{{ $i->id }}" name="industry_process" class="field"></textarea></div>
                    <div><label class="label" for="jt-{{ $i->id }}">Teknologi / peralatan</label><input id="jt-{{ $i->id }}" name="technology" class="field"></div>
                    <div><label class="label" for="jl-{{ $i->id }}">Pembelajaran profesional</label><textarea id="jl-{{ $i->id }}" name="learning" class="field"></textarea></div>
                    <div><label class="label" for="ja-{{ $i->id }}">Potensi penerapan di kelas</label><textarea id="ja-{{ $i->id }}" name="potential_application" class="field"></textarea></div>
                    <div><label class="label" for="jo-{{ $i->id }}">Kendala</label><input id="jo-{{ $i->id }}" name="obstacles" class="field"></div>
                    <div><label class="label" for="jf-{{ $i->id }}">Foto (hormati NDA perusahaan)</label><input id="jf-{{ $i->id }}" name="photo" type="file" accept="image/*" class="text-sm"></div>
                    <button class="btn btn-primary">Ajukan jurnal</button>
                </form>
                <section class="card stack-sm">
                    <h3 class="font-bold">Riwayat jurnal</h3>
                    @forelse($i->journals as $j)
                        <div class="border-t border-border pt-2"><span class="hint tabular-nums">{{ $j->journal_date->format('d/m') }}</span> {{ \Illuminate\Support\Str::limit($j->work_done, 90) }}
                            <span class="badge {{ $j->status === 'approved' ? 'badge-ok' : 'badge-warn' }}">{{ ['submitted' => 'Menunggu', 'approved' => 'Disetujui', 'revision_needed' => 'Perlu revisi'][$j->status] ?? $j->status }}</span>
                            @if($j->feedback)<p class="hint">{{ $j->feedback }}</p>@endif</div>
                    @empty
                        <p class="hint">Belum ada jurnal.</p>
                    @endforelse
                </section>
            </div>
            <div class="stack">
                <section class="card stack-sm">
                    <h3 class="font-bold">Target kompetensi guru</h3>
                    <ul class="legal-list">@foreach($objectives as $o)<li><strong>{{ $o->code }}</strong> {{ $o->label }}</li>@endforeach</ul>
                    @foreach($i->evaluations->where('status', 'final') as $ev)<p class="callout callout-ok">Evaluasi {{ $ev->contact->name }}: @foreach($ev->scores as $k => $v){{ $k }} {{ $v === 'NA' ? 'tidak dinilai' : $v }}@if(! $loop->last), @endif @endforeach</p>@endforeach
                    <p class="hint">Belum ada bukti tidak berarti tidak kompeten, dan tidak setara sertifikasi resmi.</p>
                </section>
                <section class="card stack-sm">
                    <h3 class="font-bold">Rencana Penerapan Hasil Magang</h3>
                    @foreach($i->transferPlans as $p)
                        <div class="border-t border-border pt-2">
                            <p><strong>{{ $p->practice_learned }}</strong></p><p class="hint">{{ $p->plan }} @if($p->schedule_date)· {{ $p->schedule_date->format('d/m/Y') }}@endif</p>
                            <form method="POST" action="{{ route('teacher.internship.plan.update', $p) }}" class="flex flex-wrap gap-2 mt-1">@csrf
                                <select name="status" class="field" aria-label="Status rencana">@foreach(['planned' => 'Rencana', 'in_progress' => 'Berjalan', 'implemented' => 'Terlaksana'] as $k => $l)<option value="{{ $k }}" @selected($p->status === $k)>{{ $l }}</option>@endforeach</select>
                                <input name="evidence" class="field" value="{{ $p->evidence }}" placeholder="Bukti pelaksanaan" aria-label="Bukti pelaksanaan"><button class="btn btn-outline btn-sm">Simpan</button></form>
                        </div>
                    @endforeach
                    <form method="POST" action="{{ route('teacher.internship.plan', $i) }}" class="stack-sm border-t border-border pt-3">
                        @csrf
                        <input name="practice_learned" class="field" required placeholder="Praktik industri yang dipelajari" aria-label="Praktik industri">
                        <textarea name="plan" class="field" required placeholder="Rencana pembelajaran di kelas" aria-label="Rencana pembelajaran"></textarea>
                        <input name="materials" class="field" placeholder="Materi atau alat yang diperlukan" aria-label="Materi">
                        <div class="grid grid-cols-2 gap-2"><input name="schedule_date" type="date" class="field" aria-label="Jadwal"><input name="owner_name" class="field" placeholder="Penanggung jawab" aria-label="Penanggung jawab"></div>
                        <input name="indicators" class="field" placeholder="Indikator hasil" aria-label="Indikator">
                        <button class="btn btn-navy btn-sm">Tambah rencana</button>
                    </form>
                </section>
                <form method="POST" action="{{ route('teacher.internship.report', $i) }}" class="card stack-sm">
                    @csrf
                    <h3 class="font-bold">Isi laporan magang</h3>
                    <textarea name="report_summary" class="field" rows="4" placeholder="Ringkasan kegiatan" aria-label="Ringkasan kegiatan">{{ $i->report_summary }}</textarea>
                    <textarea name="report_reflection" class="field" rows="4" placeholder="Refleksi profesional" aria-label="Refleksi">{{ $i->report_reflection }}</textarea>
                    <button class="btn btn-outline btn-sm">Simpan laporan</button>
                </form>
                <section class="card stack-sm">
                    <h3 class="font-bold">Diseminasi</h3>
                    @foreach($i->disseminations as $d)<p class="hint">{{ $d->held_on->format('d/m/Y') }} · {{ $d->title }} · {{ $d->participants_count }} peserta</p>@endforeach
                    <form method="POST" action="{{ route('teacher.internship.dissemination', $i) }}" class="grid grid-cols-2 gap-2">
                        @csrf
                        <input name="title" class="field col-span-2" required placeholder="Judul kegiatan berbagi" aria-label="Judul diseminasi">
                        <input name="held_on" type="date" class="field" required aria-label="Tanggal">
                        <input name="participants_count" type="number" min="0" class="field" placeholder="Peserta" aria-label="Jumlah peserta" required>
                        <input name="feedback" class="field col-span-2" placeholder="Umpan balik peserta" aria-label="Umpan balik">
                        <button class="btn btn-outline btn-sm col-span-2">Catat diseminasi</button>
                    </form>
                </section>
            </div>
        </div>
    </section>
@empty
    <div class="empty">Anda belum terdaftar di program magang guru. Admin sekolah mendaftarkan program dari menu Magang Guru.</div>
@endforelse
@endsection

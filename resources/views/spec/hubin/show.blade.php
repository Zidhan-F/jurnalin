@extends('layouts.app', ['title' => $company->name.' - Profil Mitra - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $company->name }}</h1>
        <p>{{ $company->industry_type }} · {{ $company->address }}, {{ $company->city }} · Profil 360 hanya memuat data milik sekolah ini.</p>
    </div>
    <form method="POST" action="{{ route('admin.partners.status', $company) }}" class="flex flex-wrap gap-2 items-end">
        @csrf
        <div><label class="label" for="ps">Status kemitraan</label><select id="ps" name="partnership_status" class="field">@foreach(\App\Models\Company::PARTNERSHIP_LABELS as $k => $l)<option value="{{ $k }}" @selected($company->partnership_status === $k)>{{ $l }}</option>@endforeach</select></div>
        <button class="btn btn-outline btn-sm">Simpan</button>
    </form>
</div>

<nav class="tab-nav" aria-label="Bagian profil mitra">
    <a href="#linimasa">Linimasa</a><a href="#kontak">Kontak</a><a href="#dokumen">MoU/PKS</a><a href="#kuota">Kuota</a><a href="#peserta">Peserta</a><a href="#undangan">Undangan mitra</a><a href="#evaluasi">Evaluasi</a>
</nav>

<div class="split">
    <div class="stack">
        <section id="linimasa" class="card stack-sm">
            <h2 class="section-title">Linimasa kemitraan</h2>
            @if($timeline->isEmpty())<p class="hint">Belum ada aktivitas tercatat.</p>@endif
            <ol class="timeline">
                @foreach($timeline as $e)<li><time>{{ \Carbon\Carbon::parse($e['date'])->format('d/m/Y') }}</time> <span class="badge">{{ $e['kind'] }}</span> {{ $e['title'] }} <span class="hint">· sumber: {{ $e['source'] }}</span></li>@endforeach
            </ol>
        </section>

        <section id="peserta" class="card stack-sm">
            <h2 class="section-title">Peserta PKL lintas periode ({{ $placements->count() }})</h2>
            @foreach($placements->groupBy(fn ($p) => $p->pklPeriod->name) as $periodName => $items)
                <details><summary class="font-semibold min-h-11 flex items-center">{{ $periodName }} · {{ $items->count() }} siswa</summary>
                    <ul class="hint">@foreach($items as $p)<li>{{ $p->student->user->name }} · {{ $p->department_divisi ?: '-' }} · {{ $p->status->label() }} · guru {{ $p->teacher->user->name }}</li>@endforeach</ul>
                </details>
            @endforeach
            @if($internships->isNotEmpty())<p><strong>Magang guru:</strong> {{ $internships->map(fn ($i) => $i->teacher->user->name.' ('.$i->start_date->format('Y').')')->implode(', ') }}</p>@endif
            @if($visits->isNotEmpty())<p><strong>Kunjungan:</strong> {{ $visits->count() }} tercatat, terakhir {{ $visits->first()->visit_date->format('d/m/Y') }}</p>@endif
        </section>

        <section id="evaluasi" class="card stack-sm">
            <h2 class="section-title">Evaluasi kemitraan (internal)</h2>
            @foreach($evaluations as $e)
                <div class="border-t border-border pt-2"><span class="badge">{{ $e->evaluator_role }}</span> {{ $e->pklPeriod?->name }} · rata-rata {{ number_format(collect($e->scores)->filter()->avg() ?? 0, 1) }}/5 <p class="hint">{{ $e->comment }}</p></div>
            @endforeach
            <form method="POST" action="{{ route('admin.partners.evaluation', $company) }}" class="stack-sm border-t border-border pt-3">
                @csrf
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="label" for="ev-role">Penilai</label><select id="ev-role" name="evaluator_role" class="field"><option value="hubin">Hubin</option><option value="teacher">Guru</option><option value="student">Rangkuman siswa</option><option value="mentor">Pembimbing lapangan</option></select></div>
                    <div><label class="label" for="ev-period">Periode</label><select id="ev-period" name="pkl_period_id" class="field"><option value="">-</option>@foreach($periods as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
                </div>
                @foreach(['bimbingan' => 'Kualitas bimbingan', 'relevansi' => 'Relevansi pekerjaan dengan kompetensi', 'k3' => 'Keselamatan & lingkungan kerja', 'komunikasi' => 'Komunikasi dengan sekolah'] as $k => $l)
                    <div class="flex items-center justify-between gap-2"><label for="ev-{{ $k }}">{{ $l }}</label><select id="ev-{{ $k }}" name="scores[{{ $k }}]" class="field max-w-28"><option value="">-</option>@for($i = 1; $i <= 5; $i++)<option value="{{ $i }}">{{ $i }}</option>@endfor</select></div>
                @endforeach
                <textarea name="comment" class="field" placeholder="Catatan" aria-label="Catatan evaluasi"></textarea>
                <input name="recommendation" class="field" placeholder="Rekomendasi periode berikutnya" aria-label="Rekomendasi">
                <button class="btn btn-navy btn-sm">Simpan evaluasi</button>
                <p class="hint">Evaluasi internal, tidak dipublikasikan sebagai rating perusahaan.</p>
            </form>
        </section>
    </div>

    <div class="stack">
        <section id="kontak" class="card stack-sm">
            <h2 class="section-title">Kontak & pembimbing lapangan</h2>
            @foreach($company->contacts as $ct)
                <div class="border-t border-border pt-2"><strong>{{ $ct->name }}</strong> <span class="badge">{{ ['pic' => 'PIC', 'mentor' => 'Pembimbing', 'examiner' => 'Penguji'][$ct->kind] ?? $ct->kind }}</span>
                    <p class="hint">{{ $ct->position }} {{ $ct->division ? '· '.$ct->division : '' }} · {{ $ct->phone ?: '-' }} · {{ $ct->email ?: '-' }} @if($ct->valid_until)· s.d. {{ $ct->valid_until->format('d/m/Y') }}@endif</p></div>
            @endforeach
            <form method="POST" action="{{ route('admin.partners.contact', $company) }}" class="grid grid-cols-2 gap-2 border-t border-border pt-3">
                @csrf
                <input name="name" class="field" required placeholder="Nama" aria-label="Nama kontak">
                <input name="position" class="field" placeholder="Jabatan" aria-label="Jabatan">
                <input name="division" class="field" placeholder="Divisi" aria-label="Divisi">
                <select name="kind" class="field" aria-label="Peran"><option value="pic">PIC</option><option value="mentor">Pembimbing</option><option value="examiner">Penguji</option></select>
                <input name="phone" type="tel" autocomplete="off" class="field" placeholder="WhatsApp" aria-label="WhatsApp">
                <input name="email" type="email" autocomplete="off" class="field" placeholder="Email" aria-label="Email">
                <input name="consent_note" class="field col-span-2" placeholder="Dasar penggunaan kontak (misal: dikonfirmasi PIC)" aria-label="Dasar penggunaan kontak">
                <button class="btn btn-navy btn-sm col-span-2">Tambah kontak</button>
            </form>
        </section>

        <section id="dokumen" class="card stack-sm">
            <h2 class="section-title">Arsip MoU / PKS</h2>
            @foreach($company->agreements as $a)
                @php $expiring = $a->expires_on && $a->expires_on->lte(now()->addDays($a->reminder_days)); @endphp
                <div class="border-t border-border pt-2 {{ $expiring ? 'card-attention pl-2' : '' }}">
                    <strong>{{ strtoupper($a->kind) }} {{ $a->title }}</strong> @if($a->reference_no)<span class="hint">No. {{ $a->reference_no }}</span>@endif
                    <p class="hint">{{ $a->signed_on?->format('d/m/Y') ?? '-' }} s.d. {{ $a->expires_on?->format('d/m/Y') ?? 'tanpa batas' }} · PIC {{ $a->pic_name ?: '-' }} @if($a->file_path)· <a class="link" href="/media/{{ $a->file_path }}" target="_blank">Lihat berkas</a>@endif</p>
                </div>
            @endforeach
            <form method="POST" action="{{ route('admin.partners.agreement', $company) }}" enctype="multipart/form-data" class="grid grid-cols-2 gap-2 border-t border-border pt-3">
                @csrf
                <select name="kind" class="field" aria-label="Jenis dokumen"><option value="mou">MoU</option><option value="pks">PKS</option><option value="acceptance">Surat penerimaan</option></select>
                <input name="reference_no" class="field" placeholder="Nomor" aria-label="Nomor dokumen">
                <input name="title" class="field col-span-2" required placeholder="Judul dokumen" aria-label="Judul dokumen">
                <div><label class="label" for="sg">Ditandatangani</label><input id="sg" name="signed_on" type="date" class="field"></div>
                <div><label class="label" for="ex">Berakhir</label><input id="ex" name="expires_on" type="date" class="field"></div>
                <input name="pic_name" class="field" placeholder="PIC" aria-label="PIC dokumen">
                <div><label class="label" for="rd">Ingatkan (hari sebelum)</label><input id="rd" name="reminder_days" type="number" value="60" min="7" max="365" class="field"></div>
                <input name="file" type="file" accept=".pdf,image/*" class="col-span-2 text-sm" aria-label="Berkas dokumen">
                <button class="btn btn-navy btn-sm col-span-2">Simpan dokumen</button>
            </form>
        </section>

        <section id="kuota" class="card stack-sm">
            <h2 class="section-title">Kuota</h2>
            @foreach($company->quotas as $q)
                <div class="flex justify-between gap-2 border-t border-border pt-2"><span>{{ $q->pklPeriod?->name ?? 'Umum' }} · {{ $q->division ?: 'semua divisi' }} · <span class="tabular-nums">{{ $q->capacity }}</span></span>
                    <span class="badge {{ $q->confirmed_at ? 'badge-ok' : 'badge-warn' }}">{{ $q->confirmed_at ? 'Dikonfirmasi '.$q->confirmed_at->format('d/m/Y').' ('.$q->source.')' : 'Belum dikonfirmasi' }}</span></div>
            @endforeach
            <form method="POST" action="{{ route('admin.partners.quota', $company) }}" class="grid grid-cols-2 gap-2 border-t border-border pt-3">
                @csrf
                <select name="pkl_period_id" class="field" aria-label="Periode"><option value="">Umum</option>@foreach($periods as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select>
                <input name="division" class="field" placeholder="Divisi" aria-label="Divisi kuota">
                <input name="capacity" type="number" min="0" required class="field tabular-nums" placeholder="Jumlah" aria-label="Jumlah kuota">
                <select name="source" class="field" aria-label="Sumber konfirmasi"><option value="phone">Telepon</option><option value="letter">Surat</option><option value="email">Email</option><option value="meeting">Pertemuan</option></select>
                <label class="flex items-center gap-2 col-span-2"><input type="checkbox" name="confirmed" value="1"> Sudah dikonfirmasi mitra</label>
                <button class="btn btn-navy btn-sm col-span-2">Simpan kuota</button>
            </form>
        </section>

        <section id="undangan" class="card stack-sm">
            <h2 class="section-title">Undangan formulir untuk PIC mitra</h2>
            <p class="hint">PIC mengonfirmasi kontak dan kuota, atau memberi masukan keterampilan, tanpa akun. Sekolah meninjau sebelum data disimpan.</p>
            @foreach($requests as $r)
                <div class="border-t border-border pt-2">
                    <span class="badge {{ $r->status === 'responded' ? 'badge-warn' : ($r->status === 'accepted' ? 'badge-ok' : '') }}">{{ ['sent' => 'Terkirim', 'responded' => 'Perlu ditinjau', 'accepted' => 'Diterima', 'rejected' => 'Ditolak'][$r->status] }}</span>
                    {{ $r->kind === 'feedback' ? 'Masukan keterampilan' : 'Konfirmasi kontak & kuota' }} · s.d. {{ $r->expires_at->format('d/m/Y') }}
                    @if($r->response)<p class="hint">Dari {{ $r->responder_name }} ({{ $r->responder_position }}): {{ collect($r->response)->map(fn ($v, $k) => "{$k}: {$v}")->implode('; ') }}</p>@endif
                    @if($r->status === 'responded')
                        <form method="POST" action="{{ route('admin.partners.request.review', $r) }}" class="flex gap-2 mt-1">@csrf<button name="decision" value="accepted" class="btn btn-primary btn-sm">Terima & simpan</button><button name="decision" value="rejected" class="btn btn-danger btn-sm">Tolak</button></form>
                    @endif
                </div>
            @endforeach
            <form method="POST" action="{{ route('admin.partners.request', $company) }}" class="grid grid-cols-3 gap-2 border-t border-border pt-3 items-end">
                @csrf
                <select name="kind" class="field col-span-2" aria-label="Jenis formulir"><option value="contact_quota">Konfirmasi kontak & kuota</option><option value="feedback">Masukan keterampilan</option></select>
                <input name="days" type="number" value="14" min="3" max="60" class="field" aria-label="Masa berlaku (hari)">
                <button class="btn btn-outline btn-sm col-span-3">Buat tautan formulir</button>
            </form>
        </section>
    </div>
</div>
@endsection

@extends('layouts.app', ['title' => 'Akses Pembimbing Industri - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Akses pembimbing industri</h1>
        <p>Pembimbing lapangan memantau, meninjau, dan menilai dari HP tanpa membuat akun. Satu tautan dapat mencakup beberapa peserta, berbatas waktu, dan bisa dicabut kapan saja. Formulir kertas tetap tersedia di halaman Penilaian.</p>
    </div>
</div>

@unless($enabled)
    <p class="callout callout-warn mb-6">Akses pembimbing industri termasuk paket Sekolah Pro. Selama belum aktif, gunakan formulir nilai industri kertas dan tautan tanda tangan per penempatan.</p>
@endunless

<p class="callout mb-6">Penyetuju jurnal di sekolah ini: <strong>{{ ['teacher' => 'guru pembimbing saja', 'mentor' => 'pembimbing lapangan', 'either' => 'guru atau pembimbing lapangan'][$approver] ?? $approver }}</strong>. Ubah di Pengaturan sekolah.</p>

<div class="split">
    <div class="stack">
        <form method="POST" action="{{ route('mentor-access.contact') }}" class="card-muted stack-sm">
            @csrf
            <h2 class="section-title">1. Catat pembimbing lapangan</h2>
            <div><label class="label" for="c-company">Tempat PKL</label><select id="c-company" name="company_id" class="field" required>@foreach($companies as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
            <div class="grid grid-cols-2 gap-2">
                <div><label class="label" for="c-name">Nama</label><input id="c-name" name="name" class="field" required></div>
                <div><label class="label" for="c-pos">Jabatan</label><input id="c-pos" name="position" class="field"></div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div><label class="label" for="c-phone">WhatsApp</label><input id="c-phone" name="phone" type="tel" autocomplete="off" class="field tabular-nums"></div>
                <div><label class="label" for="c-email">Email (untuk kode verifikasi)</label><input id="c-email" name="email" type="email" autocomplete="off" class="field"></div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div><label class="label" for="c-kind">Peran</label><select id="c-kind" name="kind" class="field"><option value="mentor">Pembimbing lapangan</option><option value="pic">PIC perusahaan</option><option value="examiner">Penguji eksternal</option></select></div>
                <div><label class="label" for="c-div">Divisi</label><input id="c-div" name="division" class="field"></div>
            </div>
            <button class="btn btn-navy">Simpan kontak</button>
        </form>

        <form method="POST" action="{{ route('mentor-access.issue') }}" class="card-muted stack-sm">
            @csrf
            <h2 class="section-title">2. Buat tautan aman</h2>
            <div><label class="label" for="g-contact">Pembimbing</label>
                <select id="g-contact" name="partner_contact_id" class="field" required>@foreach($contacts as $c)<option value="{{ $c->id }}">{{ $c->name }} · {{ $c->company?->name }}</option>@endforeach</select></div>
            <fieldset><legend class="label">Peserta dalam tautan</legend>
                <div class="grid gap-1 max-h-56 overflow-y-auto">
                    @foreach($placements as $p)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="placement_ids[]" value="{{ $p->id }}"> {{ $p->student->user->name }} <span class="hint">· {{ $p->company->name }}</span></label>@endforeach
                    @foreach($internships as $i)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="internship_ids[]" value="{{ $i->id }}"> Magang guru: {{ $i->teacher->user->name }} <span class="hint">· {{ $i->company->name }}</span></label>@endforeach
                </div>
            </fieldset>
            <div class="grid grid-cols-2 gap-2">
                <div><label class="label" for="g-days">Berlaku (hari)</label><input id="g-days" name="days" type="number" min="1" max="180" value="60" class="field tabular-nums"></div>
                <div class="grid gap-1 content-end">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="can_review" value="1"> Boleh meninjau jurnal</label>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="can_evaluate" value="1" checked> Boleh mengisi evaluasi</label>
                </div>
            </div>
            <p class="hint">Tautan tidak memuat NIS, nomor HP, atau data peserta. Membuat tautan baru untuk pembimbing yang sama mencabut tautan lamanya.</p>
            <button class="btn btn-primary" @disabled(! $enabled)>Buat tautan</button>
        </form>
    </div>

    <section class="stack-sm">
        <h2 class="section-title">Tautan aktif & riwayat</h2>
        @forelse($grants as $g)
            @php $active = ! $g->revoked_at && $g->expires_at->isFuture(); @endphp
            <article class="card {{ $active ? '' : 'opacity-80' }}">
                <div class="flex flex-wrap justify-between gap-2">
                    <div>
                        <strong>{{ $g->contact->name }}</strong> <span class="hint">· {{ $g->contact->company?->name }} · {{ $g->purpose === 'examiner' ? 'Penguji UKK' : 'Pembimbing' }}</span>
                        <p class="hint">{{ collect($g->scope['placement_ids'] ?? [])->map(fn ($id) => $studentNames[$id] ?? null)->filter()->implode(', ') ?: (count($g->scope['internship_ids'] ?? []) ? count($g->scope['internship_ids']).' magang guru' : 'Sesi UKK') }}</p>
                        <p class="hint tabular-nums">Berlaku s.d. {{ $g->expires_at->format('d/m/Y') }} · terakhir dibuka {{ $g->last_used_at?->format('d/m H:i') ?? 'belum pernah' }}</p>
                    </div>
                    <span class="badge {{ $active ? 'badge-ok' : 'badge-danger' }}">{{ $active ? 'Aktif' : ($g->revoked_at ? 'Dicabut' : 'Kedaluwarsa') }}</span>
                </div>
                @if($active)
                    <div class="flex flex-wrap gap-2 mt-2">
                        <form method="POST" action="{{ route('mentor-access.remind', $g) }}">@csrf<button class="btn btn-outline btn-sm">Pengingat evaluasi</button></form>
                        <form method="POST" action="{{ route('mentor-access.revoke', $g) }}" class="flex gap-2">@csrf<input name="reason" class="field" placeholder="Alasan (opsional)" aria-label="Alasan pencabutan"><button class="btn btn-danger btn-sm">Cabut</button></form>
                    </div>
                @elseif($g->revoked_reason)
                    <p class="hint mt-1">Alasan: {{ $g->revoked_reason }}</p>
                @endif
            </article>
        @empty
            <div class="empty">Belum ada tautan untuk pembimbing industri.</div>
        @endforelse
    </section>
</div>
@endsection

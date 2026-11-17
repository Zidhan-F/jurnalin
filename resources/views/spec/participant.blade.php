@extends('layouts.app', ['title' => $student->user->name.' - Pantau Siswa - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $student->user->name }}</h1>
        <p>{{ $student->class_name }} · {{ $student->major }} · {{ $placement->company->name }} @if($placement->department_divisi)({{ $placement->department_divisi }})@endif · Guru: {{ $placement->teacher->user->name }}</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('ai.mentor-summary', $placement) }}" class="btn btn-outline btn-sm">Ringkasan pembimbing</a>
        <a href="/reports/placements/{{ $placement->id }}/logbook.pdf" class="btn btn-outline btn-sm">PDF jurnal & presensi</a>
        @if($canAct)
            <form method="POST" action="{{ route('magic.issue', $student->user) }}">@csrf<button class="btn btn-outline btn-sm">Tautan masuk siswa</button></form>
        @endif
    </div>
</div>

<div class="split">
    <div class="stack">
        <section class="card">@include('spec.partials.calendar')</section>
        @include('spec.partials.readiness')

        <section class="card stack-sm">
            <h2 class="section-title">Peta TP & bukti praktik</h2>
            <p class="hint">Jumlah jurnal bukan ukuran keterampilan. Status terverifikasi hanya setelah Anda memeriksa bukti.</p>
            <ul class="check-list">
                @foreach($objectives as $row)
                    <li class="{{ $row['verified'] > 0 ? 'is-done' : '' }}">
                        <span class="check-mark">{{ $row['objective']->code }}</span>
                        <span>{{ $row['objective']->label }}</span>
                        <span class="hint tabular-nums">{{ $row['verified'] }} terverifikasi · {{ $row['claimed'] }} diklaim</span>
                    </li>
                @endforeach
            </ul>
            @php $gaps = $objectives->filter(fn ($r) => $r['verified'] === 0); @endphp
            @if($gaps->isNotEmpty())
                <p class="callout callout-warn">Belum ada bukti terverifikasi untuk {{ $gaps->map(fn ($r) => $r['objective']->code)->implode(', ') }}. Belum ada bukti tidak berarti tidak mampu; diskusikan aktivitas yang tersedia di tempat PKL.</p>
            @endif
            @foreach($claims as $j)
                <div class="flex flex-wrap justify-between gap-2 border-t border-border pt-2">
                    <span><span class="hint tabular-nums">{{ $j->journal_date->format('d/m') }}</span> {{ $j->activity_title }} · {{ $j->objectives->where('pivot.status', 'claimed')->pluck('code')->implode(', ') }}</span>
                    @if($canAct)<form method="POST" action="{{ route('learning.objectives.verify', $j) }}">@csrf<button class="btn btn-outline btn-sm">Verifikasi</button></form>@endif
                </div>
            @endforeach
        </section>

        <section class="card stack-sm">
            <h2 class="section-title">Persetujuan wali</h2>
            @if(! $student->isMinor())
                <p class="callout callout-ok">Siswa berusia 18 tahun atau lebih.</p>
            @elseif($consent)
                <p class="callout callout-ok">Tercatat dari {{ $consent->guardian_name }} ({{ $consent->method }}) pada {{ $consent->given_at->format('d/m/Y') }}.</p>
                @if($canAct)<form method="POST" action="{{ route('consents.withdraw', $consent) }}">@csrf<button class="btn btn-danger btn-sm">Catat pencabutan persetujuan</button></form>@endif
            @elseif($canAct)
                <p class="callout callout-warn">Belum ada persetujuan wali: lokasi dan swafoto tidak diambil.</p>
                <form method="POST" action="{{ route('consents.record', $student) }}" enctype="multipart/form-data" class="grid sm:grid-cols-2 gap-2">
                    @csrf
                    <input name="guardian_name" class="field" placeholder="Nama wali" required aria-label="Nama wali" value="{{ $student->guardian_name }}">
                    <input name="relation" class="field" placeholder="Hubungan" required aria-label="Hubungan">
                    <select name="method" class="field" aria-label="Metode"><option value="paper">Formulir kertas</option><option value="whatsapp">WhatsApp</option></select>
                    <input type="file" name="evidence" accept="image/*" class="text-sm" aria-label="Foto bukti">
                    <button class="btn btn-navy btn-sm sm:col-span-2">Catat persetujuan</button>
                </form>
                <form method="POST" action="{{ route('consents.link', $student) }}">@csrf<button class="btn btn-outline btn-sm">Kirim tautan persetujuan digital ke wali</button></form>
            @endif
        </section>
    </div>

    <div class="stack">
        <section class="card stack-sm">
            <h2 class="section-title">Target belajar</h2>
            @foreach($targets as $t)
                <div class="border-t border-border pt-2">
                    <div class="flex justify-between gap-2"><strong>{{ $t->title }}</strong><span class="badge {{ $t->status === 'done' ? 'badge-ok' : ($t->status === 'submitted' ? 'badge-warn' : '') }}">{{ ['open' => 'Berjalan', 'submitted' => 'Bukti dikirim', 'done' => 'Selesai'][$t->status] }}</span></div>
                    @if($t->indicator)<p class="hint">Indikator: {{ $t->indicator }}</p>@endif
                    @if($t->student_note)<p class="hint">Siswa: {{ $t->student_note }}</p>@endif
                    @if($canAct && $t->status !== 'done')
                        <form method="POST" action="{{ route('learning.target.close', $t) }}" class="flex gap-2 mt-1">@csrf<input name="reviewer_note" class="field" placeholder="Catatan" aria-label="Catatan target"><button class="btn btn-primary btn-sm">Selesaikan</button></form>
                    @endif
                </div>
            @endforeach
            @if($canAct)
                <form method="POST" action="{{ route('learning.target.store', $placement) }}" class="stack-sm border-t border-border pt-3">
                    @csrf
                    <input name="title" class="field" required placeholder="Target, misal: Melayani 5 check-in tamu secara mandiri" aria-label="Judul target">
                    <input name="indicator" class="field" placeholder="Indikator hasil" aria-label="Indikator">
                    <input name="due_date" type="date" class="field" aria-label="Tenggat">
                    <button class="btn btn-navy btn-sm">Tambah target</button>
                </form>
            @endif
        </section>

        <section class="card stack-sm">
            <h2 class="section-title">Bimbingan terstruktur</h2>
            @foreach($notes as $n)
                <div class="border-t border-border pt-2">
                    <div class="flex justify-between gap-2"><strong>{{ $n->topic }}</strong><span class="badge">{{ $n->visibility === 'teacher_only' ? 'Catatan guru' : ['open' => 'Menunggu siswa', 'responded' => 'Dijawab', 'closed' => 'Selesai'][$n->status] }}</span></div>
                    <p class="text-sm">{{ $n->note }}</p>
                    @if($n->action_item)<p class="hint">Tindakan: {{ $n->action_item }} @if($n->due_date)(s.d. {{ $n->due_date->format('d/m') }})@endif</p>@endif
                    @if($n->student_response)<p class="callout mt-1">Siswa: {{ $n->student_response }}</p>@endif
                    @if($canAct && $n->status !== 'closed')<form method="POST" action="{{ route('learning.mentoring.close', $n) }}">@csrf<button class="link text-sm">Konfirmasi selesai</button></form>@endif
                </div>
            @endforeach
            @if($canAct)
                <form method="POST" action="{{ route('learning.mentoring.store', $placement) }}" class="stack-sm border-t border-border pt-3">
                    @csrf
                    <input name="topic" class="field" required placeholder="Topik bimbingan" aria-label="Topik">
                    <textarea name="note" class="field" required placeholder="Catatan" aria-label="Catatan bimbingan"></textarea>
                    <input name="action_item" class="field" placeholder="Tindak lanjut untuk siswa" aria-label="Tindak lanjut">
                    <div class="grid grid-cols-2 gap-2">
                        <input name="due_date" type="date" class="field" aria-label="Tenggat">
                        <select name="visibility" class="field" aria-label="Siapa yang bisa membaca"><option value="shared">Dibaca siswa</option><option value="teacher_only">Hanya guru</option></select>
                    </div>
                    <button class="btn btn-navy btn-sm">Simpan catatan</button>
                </form>
            @endif
        </section>

        <section class="card stack-sm">
            <h2 class="section-title">Refleksi mingguan</h2>
            @forelse($reflections as $r)
                <div class="border-t border-border pt-2">
                    <p class="hint tabular-nums">Minggu {{ $r->week_start->format('d/m/Y') }}</p>
                    <p><strong>Capaian:</strong> {{ $r->achievements }}</p>
                    @if($r->difficulties)<p><strong>Kesulitan:</strong> {{ $r->difficulties }}</p>@endif
                    @if($r->support_needed)<p><strong>Butuh dukungan:</strong> {{ $r->support_needed }}</p>@endif
                    @if($r->teacher_comment)<p class="callout mt-1">Komentar: {{ $r->teacher_comment }}</p>
                    @elseif($canAct)
                        <form method="POST" action="{{ route('learning.reflection.comment', $r) }}" class="flex gap-2 mt-1">@csrf<input name="teacher_comment" class="field" required placeholder="Komentar untuk siswa" aria-label="Komentar refleksi"><button class="btn btn-outline btn-sm">Kirim</button></form>
                    @endif
                </div>
            @empty
                <p class="hint">Belum ada refleksi.</p>
            @endforelse
        </section>

        <section class="card stack-sm">
            <h2 class="section-title">Bukti Kerja & proyek</h2>
            @forelse($proofs as $w)
                <div class="border-t border-border pt-2">
                    <div class="flex justify-between gap-2"><strong>{{ $w->title }}</strong><span class="badge {{ $w->status === 'verified' ? 'badge-ok' : 'badge-warn' }}">{{ ['submitted' => 'Menunggu tinjauan', 'verified' => 'Ditinjau pembimbing', 'revision' => 'Perlu revisi', 'draft' => 'Draf'][$w->status] }}</span></div>
                    <p class="text-sm">{{ $w->result }}</p>
                    @if($w->contribution)<p class="hint">Kontribusi: {{ $w->contribution }}</p>@endif
                    @if($canAct && $w->status === 'submitted')
                        <form method="POST" action="{{ route('learning.proof.review', $w) }}" class="grid sm:grid-cols-3 gap-2 mt-1">
                            @csrf
                            <input name="review_note" class="field sm:col-span-3" placeholder="Catatan tinjauan" aria-label="Catatan tinjauan">
                            <button name="status" value="verified" class="btn btn-primary btn-sm">Tandai ditinjau</button>
                            <button name="status" value="revision" class="btn btn-warn btn-sm">Minta revisi</button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="hint">Belum ada Bukti Kerja. Ditinjau bukan berarti sertifikasi profesi.</p>
            @endforelse
        </section>

        @if($canAct)
            <section class="card stack-sm">
                <h2 class="section-title">Lokasi presensi penempatan</h2>
                <p class="hint">Kosongkan untuk memakai titik tempat PKL ({{ $placement->company->name }}).</p>
                @if(auth()->user()->role === \App\Enums\UserRole::SchoolAdmin)
                    <form method="POST" action="{{ route('admin.placements.location', $placement) }}" class="grid grid-cols-3 gap-2 items-end">
                        @csrf
                        <div><label class="label" for="plat">Titik lintang</label><input id="plat" name="latitude" class="field tabular-nums" value="{{ $placement->latitude }}"></div>
                        <div><label class="label" for="plng">Titik bujur</label><input id="plng" name="longitude" class="field tabular-nums" value="{{ $placement->longitude }}"></div>
                        <div><label class="label" for="prad">Jarak absen (meter)</label><input id="prad" name="geofence_radius_m" type="number" class="field tabular-nums" value="{{ $placement->geofence_radius_m }}"></div>
                        <button class="btn btn-outline btn-sm col-span-3">Simpan lokasi</button>
                    </form>
                @else
                    <p class="kv"><span>Titik aktif</span><span class="tabular-nums">{{ number_format($placement->attendancePoint()->latitude, 5) }}, {{ number_format($placement->attendancePoint()->longitude, 5) }} · {{ $placement->attendanceRadius() }} m</span></p>
                @endif
            </section>
        @endif
    </div>
</div>
@endsection

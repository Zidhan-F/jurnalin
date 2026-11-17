@extends('mentor.layout')
@section('title', 'Jurnal '.$placement->student->user->name)

@php
    $freq = \App\Support\MentorSignoffPeriods::FREQUENCY_LABELS[$placement->school->mentor_signoff_frequency] ?? 'per minggu';
    $range = fn ($p) => $p['start']->equalTo($p['end'])
        ? $p['start']->translatedFormat('l, j F Y')
        : $p['start']->translatedFormat('j M').' sampai '.$p['end']->translatedFormat('j M Y');
    $statusLabels = ['present' => 'Hadir', 'late' => 'Terlambat', 'sick' => 'Sakit', 'permission' => 'Izin', 'absent' => 'Alpa'];
@endphp

@section('content')
    <section class="mentor-intro">
        <p class="text-sm text-muted">{{ $placement->school->name }}</p>
        <h1>{{ $placement->student->user->name }}</h1>
        <p class="text-muted">{{ $placement->student->class_name }} · {{ $placement->student->major }}</p>
        <p class="text-muted">{{ $placement->company->name }}@if($placement->department_divisi) · {{ $placement->department_divisi }}@endif</p>
        <p class="mt-3 text-sm">Sekolah meminta tanda tangan Anda <strong>{{ $freq }}</strong> untuk jurnal yang ditulis siswa. Guru pembimbing: {{ $placement->teacher->user->name }}.</p>
    </section>

    @if(session('status'))
        <div role="status" class="mentor-flash">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div role="alert" class="mentor-error">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    @if($periods->isEmpty())
        <section class="mentor-card">
            <h2 class="font-bold text-navy">Belum ada jurnal untuk ditandatangani</h2>
            <p class="text-muted mt-1">Siswa belum mengirim jurnal. Buka tautan ini lagi setelah siswa mengisi jurnal hariannya.</p>
        </section>
    @else
        <nav aria-label="Pilih rentang" class="mentor-periods">
            @foreach($periods as $p)
                @php $isSelected = $selected && $p['start']->equalTo($selected['start']); @endphp
                <a href="{{ route('mentor.show', ['token' => $token, 'mulai' => $p['start']->toDateString()]) }}" @if($isSelected) aria-current="true" @endif>
                    <span>{{ $range($p) }}</span>
                    <small>{{ $p['journals']->count() }} jurnal · {{ $p['signoff'] ? 'Sudah ditandatangani' : ($p['end'] > $today ? 'Belum bisa' : 'Perlu tanda tangan') }}</small>
                </a>
            @endforeach
        </nav>

        @if($selected)
            <section class="mentor-card" aria-labelledby="judul-rentang">
                <h2 id="judul-rentang" class="text-lg font-bold text-navy">{{ $range($selected) }}</h2>
                <dl class="mentor-summary">
                    @foreach($summary as $status => $count)
                        @if($count > 0)<div><dt>{{ $statusLabels[$status] ?? $status }}</dt><dd class="tabular-nums">{{ $count }} hari</dd></div>@endif
                    @endforeach
                    @if(array_sum($summary) === 0)<div><dt>Kehadiran</dt><dd>Tidak ada catatan absen</dd></div>@endif
                </dl>

                <ol class="mentor-journals">
                    @foreach($selected['journals'] as $journal)
                        <li>
                            <div class="flex flex-wrap justify-between gap-2 text-sm text-muted">
                                <span class="tabular-nums">{{ $journal->journal_date->translatedFormat('l, j M Y') }}</span>
                                <span>{{ $journal->work_unit }} · <span class="tabular-nums">{{ intdiv($journal->duration_minutes, 60) }} jam{{ $journal->duration_minutes % 60 ? ' '.($journal->duration_minutes % 60).' menit' : '' }}</span></span>
                            </div>
                            <h3>{{ $journal->activity_title }}</h3>
                            <p>{{ $journal->activity_description }}</p>
                            @if($journal->tools_materials)<p class="text-sm text-muted">Alat/bahan: {{ $journal->tools_materials }}</p>@endif

                            <details class="mentor-note" @if($journal->mentor_note) open @endif>
                                <summary>{{ $journal->mentor_note ? 'Catatan Anda' : 'Beri catatan untuk jurnal ini' }}</summary>
                                <form method="POST" action="{{ route('mentor.note', ['token' => $token, 'journal' => $journal->id]) }}">
                                    @csrf
                                    <label for="note-{{ $journal->id }}" class="sr-only">Catatan untuk jurnal {{ $journal->journal_date->format('d/m/Y') }}</label>
                                    <textarea id="note-{{ $journal->id }}" name="mentor_note" rows="2" maxlength="500" placeholder="Misalnya: jam kerja hari itu sampai pukul 14.00">{{ $journal->mentor_note }}</textarea>
                                    <button type="submit" class="document-link text-sm">Simpan catatan</button>
                                </form>
                            </details>
                        </li>
                    @endforeach
                </ol>
            </section>

            <section class="mentor-card" aria-labelledby="judul-ttd">
                <h2 id="judul-ttd" class="text-lg font-bold text-navy">Tanda tangan</h2>
                @if($selected['signoff'])
                    @php $s = $selected['signoff']; @endphp
                    <p class="mt-1">Ditandatangani oleh <strong>{{ $s->signer_name }}</strong>@if($s->signer_title), {{ $s->signer_title }}@endif pada <span class="tabular-nums">{{ $s->signed_at->timezone($placement->school->timezone)->format('d/m/Y H:i') }}</span>.</p>
                    @if($signatureDataUri)
                        <img src="{{ $signatureDataUri }}" alt="Tanda tangan {{ $s->signer_name }}" width="320" height="140" class="mentor-sig-img">
                    @else
                        <p class="text-sm text-muted mt-2">Ditandatangani dengan nama yang diketik, tanpa gambar.</p>
                    @endif
                    @if($changed)
                        <p class="mentor-error mt-3">Isi jurnal pada rentang ini berubah setelah ditandatangani. Guru pembimbing dapat melihat perubahan ini.</p>
                    @endif
                @elseif($selected['end'] > $today)
                    <p class="text-muted mt-1">Rentang ini masih berjalan. Tanda tangan bisa diberikan mulai {{ $selected['end']->translatedFormat('l, j F Y') }}, setelah semua jurnal di rentang ini masuk.</p>
                @else
                    <form method="POST" action="{{ route('mentor.sign', ['token' => $token]) }}" class="mentor-sign" id="form-ttd">
                        @csrf
                        <input type="hidden" name="period_start" value="{{ $selected['start']->toDateString() }}">
                        <div>
                            <label for="signer_name" class="block font-semibold mb-1">Nama lengkap</label>
                            <input id="signer_name" name="signer_name" required maxlength="120" autocomplete="name" value="{{ old('signer_name', $placement->industry_mentor_name) }}">
                        </div>
                        <div>
                            <label for="signer_title" class="block font-semibold mb-1">Jabatan <span class="font-normal text-muted">(opsional)</span></label>
                            <input id="signer_title" name="signer_title" maxlength="120" autocomplete="organization-title" value="{{ old('signer_title') }}" placeholder="Misalnya: Supervisor IT">
                        </div>

                        <div>
                            <p id="sig-label" class="font-semibold mb-1">Gambar tanda tangan</p>
                            <canvas id="sig-pad" tabindex="-1" class="mentor-sig-pad" width="640" height="280" role="img" aria-labelledby="sig-label sig-help"></canvas>
                            <div class="flex justify-between items-center mt-1">
                                <p id="sig-help" class="text-sm text-muted">Coret dengan jari di kotak di atas.</p>
                                <button type="button" id="sig-clear" class="document-link text-sm">Hapus coretan</button>
                            </div>
                            <input type="hidden" name="signature_data" id="sig-data">
                        </div>
                        <label class="mentor-check">
                            <input type="checkbox" name="no_drawing" value="1" id="no-drawing" @checked(old('no_drawing'))>
                            <span>Saya tidak bisa menggambar tanda tangan. Gunakan nama yang saya ketik.</span>
                        </label>
                        <label class="mentor-check">
                            <input type="checkbox" name="statement" value="1" required>
                            <span>Saya menyatakan kegiatan dan kehadiran di atas benar dilakukan siswa di tempat kerja kami.</span>
                        </label>
                        <p class="text-sm text-muted">Tanda tangan elektronik ini tidak tersertifikasi. Kami menyimpan nama, waktu, alamat IP, dan sidik isi jurnal sebagai bukti.</p>
                        <button type="submit" class="mentor-submit">Tanda tangani {{ $selected['journals']->count() }} jurnal</button>
                    </form>
                    <script>
                        (() => {
                            const canvas = document.getElementById('sig-pad');
                            const ctx = canvas.getContext('2d');
                            const data = document.getElementById('sig-data');
                            const noDrawing = document.getElementById('no-drawing');
                            let drawing = false, drawn = false;
                            ctx.lineWidth = 3; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#18181B';
                            const pos = (e) => { const r = canvas.getBoundingClientRect(); return [(e.clientX - r.left) * canvas.width / r.width, (e.clientY - r.top) * canvas.height / r.height]; };
                            canvas.addEventListener('pointerdown', (e) => { drawing = true; canvas.setPointerCapture(e.pointerId); ctx.beginPath(); ctx.moveTo(...pos(e)); });
                            canvas.addEventListener('pointermove', (e) => { if (!drawing) return; ctx.lineTo(...pos(e)); ctx.stroke(); drawn = true; });
                            const stop = () => { drawing = false; if (drawn) data.value = canvas.toDataURL('image/png'); };
                            canvas.addEventListener('pointerup', stop);
                            canvas.addEventListener('pointercancel', stop);
                            document.getElementById('sig-clear').addEventListener('click', () => { ctx.clearRect(0, 0, canvas.width, canvas.height); drawn = false; data.value = ''; });
                            document.getElementById('form-ttd').addEventListener('submit', (e) => {
                                if (!drawn && !noDrawing.checked) { e.preventDefault(); document.getElementById('sig-help').textContent = 'Gambar tanda tangan dulu, atau centang opsi tanpa gambar di bawah.'; document.getElementById('sig-help').classList.add('mentor-help-error'); canvas.focus(); }
                            });
                        })();
                    </script>
                @endif
            </section>
        @endif
    @endif
@endsection

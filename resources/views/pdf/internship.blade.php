<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><title>Laporan Magang Guru</title>@include('pdf.partials.style')</head>
<body>
    @include('pdf.partials.letterhead', ['school' => $school])
    <h1>LAPORAN MAGANG GURU DI INDUSTRI</h1>
    <table class="meta">
        <tr><td style="width:22%">Guru</td><td>: <strong>{{ $internship->teacher->user->name }}</strong> (NIP/NIK {{ $internship->teacher->nip_nik ?: '-' }})</td></tr>
        <tr><td>Instansi</td><td>: {{ $internship->company->name }} @if($internship->division)· {{ $internship->division }}@endif</td></tr>
        <tr><td>Program</td><td>: {{ $internship->title }}</td></tr>
        <tr><td>Periode</td><td>: {{ $internship->start_date->format('d/m/Y') }} s.d. {{ $internship->end_date->format('d/m/Y') }}</td></tr>
    </table>
    <h2>A. Tujuan</h2><p>{{ $internship->objectives }}</p>
    <h2>B. Ringkasan kegiatan</h2><p>{{ $internship->report_summary ?: '[Lengkapi: ringkasan kegiatan magang]' }}</p>
    <table class="grid"><thead><tr><th style="width:12%">Tanggal</th><th>Pekerjaan &amp; proses industri</th><th style="width:22%">Teknologi</th><th style="width:22%">Penerapan potensial</th></tr></thead><tbody>
        @foreach($internship->journals->where('status', 'approved') as $j)
            <tr><td>{{ $j->journal_date->format('d/m/Y') }}</td><td>{{ $j->work_done }}@if($j->industry_process)<br><span class="muted">{{ $j->industry_process }}</span>@endif</td><td>{{ $j->technology }}</td><td>{{ $j->potential_application }}</td></tr>
        @endforeach
    </tbody></table>
    <p class="small muted">Hanya jurnal yang disetujui peninjau yang dicantumkan.</p>
    <h2>C. Refleksi</h2><p>{{ $internship->report_reflection ?: '[Lengkapi: refleksi profesional]' }}</p>
    <h2>D. Rencana Penerapan Hasil Magang</h2>
    <table class="grid"><thead><tr><th>Praktik industri</th><th>Rencana pembelajaran</th><th style="width:14%">Jadwal</th><th style="width:16%">Status</th></tr></thead><tbody>
        @forelse($internship->transferPlans as $tp)
            <tr><td>{{ $tp->practice_learned }}</td><td>{{ $tp->plan }}@if($tp->indicators)<br><span class="muted">Indikator: {{ $tp->indicators }}</span>@endif</td><td>{{ $tp->schedule_date?->format('d/m/Y') }}</td><td>{{ ['planned' => 'Rencana', 'in_progress' => 'Berjalan', 'implemented' => 'Terlaksana'][$tp->status] ?? $tp->status }}</td></tr>
        @empty
            <tr><td colspan="4" class="muted">Belum ada rencana penerapan.</td></tr>
        @endforelse
    </tbody></table>
    <p class="small muted">Rencana tidak dianggap dampak nyata sebelum terlaksana dan dibuktikan.</p>
    @if($internship->disseminations->isNotEmpty())
        <h2>E. Diseminasi</h2>
        @foreach($internship->disseminations as $d)<p>{{ $d->held_on->format('d/m/Y') }} · {{ $d->title }} ({{ $d->participants_count }} peserta). {{ $d->feedback }}</p>@endforeach
    @endif
    @if($internship->evaluations->where('status', 'final')->isNotEmpty())
        <h2>F. Evaluasi pembimbing industri</h2>
        @foreach($internship->evaluations->where('status', 'final') as $ev)
            <p>{{ $ev->contact->name }} ({{ $ev->finalized_at->format('d/m/Y') }}): @foreach($ev->scores as $k => $v){{ $k }} = {{ $v === 'NA' ? 'tidak dapat dinilai' : $v }}@if(! $loop->last), @endif @endforeach. {{ $ev->notes }}</p>
        @endforeach
    @endif
    <p class="small muted" style="margin-top:14px">Penerbitan dokumen resmi tetap memerlukan pengesahan pihak berwenang sekolah.</p>
</body></html>

<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><title>Laporan Kunjungan</title>@include('pdf.partials.style')</head>
<body>
    @include('pdf.partials.letterhead', ['school' => $school])
    <h1>LAPORAN KUNJUNGAN {{ $visit->kind === 'monitoring' ? 'MONITORING PKL' : 'KEMITRAAN' }}</h1>
    <table class="meta">
        <tr><td style="width:24%">Tempat</td><td>: {{ $visit->company?->name ?? '-' }}</td></tr>
        <tr><td>Tanggal</td><td>: {{ $visit->visit_date->format('d/m/Y') }}</td></tr>
        <tr><td>Petugas</td><td>: {{ $visit->author?->name }}</td></tr>
        @if($names->isNotEmpty())<tr><td>Siswa yang ditemui</td><td>: {{ $names->implode(', ') }}</td></tr>@endif
    </table>
    @foreach(['student_condition' => 'Kondisi siswa', 'mentor_discussion' => 'Diskusi dengan pembimbing industri', 'issues' => 'Kendala', 'follow_up' => 'Tindak lanjut'] as $f => $l)
        @if($visit->$f)<h2>{{ $l }}</h2><p>{{ $visit->$f }}</p>@endif
    @endforeach
    @if($visit->follow_up_due)<p>Penanggung jawab: {{ $visit->follow_up_owner ?: '-' }}, tenggat {{ $visit->follow_up_due->format('d/m/Y') }}.</p>@endif
    @if($photo)<p><img src="{{ $photo }}" style="max-width: 320px; max-height: 220px;" alt=""></p>@endif
    <p class="small muted">Laporan kunjungan bukan bukti kehadiran seluruh siswa kecuali dinyatakan eksplisit.</p>
</body></html>

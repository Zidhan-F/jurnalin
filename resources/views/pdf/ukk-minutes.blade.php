<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><title>Berita Acara UKK</title>@include('pdf.partials.style')</head>
<body>
    @include('pdf.partials.letterhead', ['school' => $school])
    <h1>REKAP NILAI &amp; BERITA ACARA UJI KOMPETENSI KEAHLIAN</h1>
    <table class="meta">
        <tr><td style="width:22%">Sesi</td><td>: {{ $session->name }} (TA {{ $session->academic_year }})</td></tr>
        <tr><td>Konsentrasi</td><td>: {{ $session->concentration }}</td></tr>
        <tr><td>Tempat / waktu</td><td>: {{ $session->venue }} / {{ $session->scheduled_at->timezone($school->timezone)->format('d/m/Y H:i') }}</td></tr>
        <tr><td>Status</td><td>: {{ ['prepared' => 'Disiapkan', 'ongoing' => 'Berlangsung', 'final' => 'Final'][$session->status] ?? $session->status }} · instrumen versi {{ $session->instrument_version }}</td></tr>
    </table>
    <table class="grid"><thead><tr><th>No. peserta</th><th>Nama</th>@foreach($session->instrument['aspects'] ?? [] as $a)<th>{{ $a['label'] }} ({{ $a['weight'] }}%)</th>@endforeach<th>Penguji</th><th>Nilai akhir</th><th>Predikat</th></tr></thead><tbody>
        @foreach($session->participants as $p)
            <tr><td>{{ $p->participant_number }}</td><td>{{ $p->student->user->name }}</td>
                @foreach($session->instrument['aspects'] ?? [] as $a)<td>{{ $p->scores->isNotEmpty() ? number_format($p->scores->avg(fn ($s) => (float) ($s->scores[$a['code']] ?? 0)), 1) : '-' }}</td>@endforeach
                <td>{{ $p->scores->map(fn ($s) => $s->examinerContact?->name ?? $s->examinerUser?->name)->filter()->implode(', ') }}</td>
                <td>{{ $p->final_score !== null ? number_format($p->final_score, 2) : '-' }}</td><td>{{ $p->predicate ?? '-' }}</td></tr>
        @endforeach
    </tbody></table>
    <h2>Berita acara</h2>
    <p>{{ $session->minutes ?: 'Pada waktu dan tempat tersebut telah dilaksanakan Uji Kompetensi Keahlian dengan peserta sebagaimana tercantum di atas. Nilai dari setiap penguji disimpan terpisah dan direkap sesuai bobot instrumen.' }}</p>
    @if($session->finalized_at)<p class="small muted">Difinalkan {{ $session->finalized_at->timezone($school->timezone)->format('d/m/Y H:i') }}. Snapshot nilai terkunci; revisi menjadi versi baru dengan alasan.</p>@endif
    <table class="sign-table"><tr><td style="width:50%">Koordinator UKK,<br><br><br><br>________________________</td><td>Penguji Eksternal,<br><br><br><br>________________________</td></tr></table>
</body></html>

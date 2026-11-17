<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><title>Draf Laporan Akhir PKL</title>@include('pdf.partials.style')</head>
<body>
    @include('pdf.partials.letterhead', ['school' => $school])
    <h1>DRAF LAPORAN AKHIR PRAKTIK KERJA LAPANGAN</h1>
    <p class="center">{{ $placement->student->user->name }} · {{ $placement->company->name }}</p>
    <p class="center small muted">Disusun otomatis dari {{ $report['sources'] }} sumber tercatat (jurnal disetujui dan Bukti Kerja). {{ $report['placeholders'] }} bagian bertanda [Lengkapi] wajib dilengkapi dan seluruh isi wajib ditinjau sebelum dipakai.</p>
    @foreach($report['chapters'] as $ch)
        <h2>{{ $ch['title'] }}</h2>
        @foreach($ch['paragraphs'] as $para)
            <p class="{{ str_contains($para, '[Lengkapi:') && strlen($para) < 120 ? 'placeholder' : '' }}">{{ $para }}</p>
        @endforeach
    @endforeach
</body></html>

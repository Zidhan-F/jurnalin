<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><title>Formulir Nilai Industri</title>@include('pdf.partials.style')</head>
<body>
    @include('pdf.partials.letterhead', ['school' => $school])
    <h1>FORMULIR PENILAIAN PEMBIMBING DUNIA KERJA</h1>
    <p class="center muted">{{ $placement->pklPeriod->name }} (TA {{ $placement->pklPeriod->academic_year }})</p>
    <table class="meta">
        <tr><td style="width:22%">Nama siswa</td><td>: <strong>{{ $placement->student->user->name }}</strong> ({{ $placement->student->class_name }})</td></tr>
        <tr><td>Program keahlian</td><td>: {{ $placement->student->major }}</td></tr>
        <tr><td>Tempat PKL / divisi</td><td>: {{ $placement->company->name }} / {{ $placement->department_divisi ?: '-' }}</td></tr>
        <tr><td>Periode</td><td>: {{ $placement->pklPeriod->start_date->format('d/m/Y') }} s.d. {{ $placement->pklPeriod->end_date->format('d/m/Y') }}</td></tr>
    </table>
    <p>Isi nilai 0 sampai 100 untuk setiap aspek. Tulis <strong>TDN</strong> (tidak dapat dinilai) bila kemampuan tersebut tidak Anda amati.</p>
    <table class="grid">
        <thead><tr><th style="width:6%">No</th><th>Aspek penilaian</th><th style="width:10%">Bobot</th><th style="width:16%">Nilai</th></tr></thead>
        <tbody>
            @foreach($aspects as $a)
                <tr><td>{{ $loop->iteration }}</td><td>{{ $a['label'] }}</td><td>{{ $a['weight'] }}%</td><td style="height:26px"></td></tr>
            @endforeach
        </tbody>
    </table>
    <h2>Catatan pembimbing</h2>
    <div style="border:1px solid #A1A1AA; height: 90px;"></div>
    <table class="sign-table"><tr>
        <td style="width:50%"></td>
        <td>{{ $placement->company->city ?? $school->city }}, ____________________<br>Pembimbing Dunia Kerja,<br><br><br><br><br>
            <strong>{{ $placement->industry_mentor_name ?: '________________________' }}</strong><br>Jabatan: ____________________</td>
    </tr></table>
    <p class="small muted" style="margin-top:20px">Setelah ditandatangani, guru pembimbing memotret formulir ini dan menginput nilainya ke Jurnalin. Dokumen akan berlabel "dokumen manual diunggah sekolah", bukan tanda tangan elektronik.</p>
</body></html>

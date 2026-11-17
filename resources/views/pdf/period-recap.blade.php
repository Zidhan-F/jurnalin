<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap Nilai PKL - {{ $period->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #18181B; }
        table.grid { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.grid th, table.grid td { border: 1px solid #A1A1AA; padding: 5px; text-align: left; }
        table.grid th { background-color: #F3EFEA; font-weight: bold; }
    </style>
</head>
<body>
    @include('pdf.partials.letterhead', ['school' => $school])

    <div style="text-align: center; margin-bottom: 10px;">
        <div style="font-size: 13px; font-weight: bold;">REKAPITULASI NILAI PRAKTIK KERJA LAPANGAN (PKL)</div>
        <div style="font-size: 10px;">{{ $period->name }} | Tahun Ajaran {{ $period->academic_year }}</div>
    </div>

    <table class="grid">
        <thead>
            <tr>
                <th>No</th>
                <th>NISN</th>
                <th>Nama Siswa</th>
                <th>Kelas</th>
                <th>Tempat PKL</th>
                <th>Guru Pembimbing</th>
                <th>Industri</th>
                <th>Sekolah</th>
                <th>Absensi</th>
                <th>Nilai Akhir</th>
                <th>Predikat</th>
            </tr>
        </thead>
        <tbody>
            @forelse($placements as $idx => $p)
                <tr>
                    <td>{{ $idx + 1 }}</td>
                    <td>{{ $p->student->nisn }}</td>
                    <td>{{ $p->student->user->name }}</td>
                    <td>{{ $p->student->class_name }}</td>
                    <td>{{ $p->company->name }}</td>
                    <td>{{ $p->teacher->user->name }}</td>
                    <td>{{ $p->assessment ? number_format($p->assessment->industry_scores['weighted_average'] ?? 0, 1) : '-' }}</td>
                    <td>{{ $p->assessment ? number_format($p->assessment->school_scores['weighted_average'] ?? 0, 1) : '-' }}</td>
                    <td>{{ $p->assessment ? number_format($p->assessment->attendance_score, 1) : '-' }}</td>
                    <td><strong>{{ $p->assessment ? number_format($p->assessment->final_score, 2) : '-' }}</strong></td>
                    <td>{{ $p->assessment?->predicate ?? 'Belum Dinilai' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" style="text-align: center;">Belum ada data penempatan siswa pada periode ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>

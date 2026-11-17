<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Buku Jurnal PKL - {{ $student->user->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #18181B; line-height: 1.4; }
        h2 { font-size: 12px; margin: 14px 0 6px 0; border-bottom: 1px solid #D4D4D8; padding-bottom: 3px; }
        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.meta td { padding: 2px 4px; vertical-align: top; }
        table.grid { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.grid th, table.grid td { border: 1px solid #A1A1AA; padding: 5px 6px; text-align: left; vertical-align: top; }
        table.grid th { background-color: #F3EFEA; font-weight: bold; }
        .sign-table { width: 100%; margin-top: 24px; text-align: center; }
        .sign-table td { width: 33.3%; vertical-align: top; }
    </style>
</head>
<body>
    @include('pdf.partials.letterhead', ['school' => $school])

    <div style="text-align: center; margin-bottom: 10px;">
        <div style="font-size: 13px; font-weight: bold;">LAPORAN JURNAL &amp; NILAI PRAKTIK KERJA LAPANGAN (PKL)</div>
        <div style="font-size: 10px;">{{ $placement->pklPeriod->name }} (TA {{ $placement->pklPeriod->academic_year }})</div>
    </div>

    <table class="meta">
        <tr>
            <td style="width: 18%;">Nama Siswa</td>
            <td style="width: 32%;">: <strong>{{ $student->user->name }}</strong></td>
            <td style="width: 18%;">Tempat PKL</td>
            <td style="width: 32%;">: <strong>{{ $placement->company->name }}</strong></td>
        </tr>
        <tr>
            <td>NISN / Kelas</td>
            <td>: {{ $student->nisn }} / {{ $student->class_name }}</td>
            <td>Divisi / Unit</td>
            <td>: {{ $placement->department_divisi ?: 'Operasional' }}</td>
        </tr>
        <tr>
            <td>Kompetensi</td>
            <td>: {{ $student->major }}</td>
            <td>Pembimbing Industri</td>
            <td>: {{ $placement->industry_mentor_name ?: '-' }}</td>
        </tr>
        <tr>
            <td>Guru Pembimbing</td>
            <td>: {{ $placement->teacher->user->name }}</td>
            <td>Periode</td>
            <td>: {{ $placement->pklPeriod->start_date->format('d/m/Y') }} s/d {{ $placement->pklPeriod->end_date->format('d/m/Y') }}</td>
        </tr>
    </table>

    <h2>A. Ringkasan Kehadiran</h2>
    @php
        $presentCount = $attendances->where('status', \App\Enums\AttendanceStatus::Present)->count();
        $lateCount = $attendances->where('status', \App\Enums\AttendanceStatus::Late)->count();
        $sickCount = $attendances->where('status', \App\Enums\AttendanceStatus::Sick)->count();
        $permCount = $attendances->where('status', \App\Enums\AttendanceStatus::Permission)->count();
    @endphp
    <table class="grid">
        <thead>
            <tr>
                <th>Hadir Tepat Waktu</th>
                <th>Terlambat</th>
                <th>Sakit</th>
                <th>Izin</th>
                <th>Total Hari Efektif</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $presentCount }} hari</td>
                <td>{{ $lateCount }} hari</td>
                <td>{{ $sickCount }} hari</td>
                <td>{{ $permCount }} hari</td>
                <td>{{ $presentCount + $lateCount }} / {{ $placement->pklPeriod->min_required_days }} hari target</td>
            </tr>
        </tbody>
    </table>

    <h2>B. Catatan Jurnal Kegiatan Disetujui ({{ $journals->count() }} Entri)</h2>
    @if($journals->isEmpty())
        <p style="color: #52525B;">Belum ada jurnal yang berstatus Disetujui pada penempatan ini.</p>
    @else
        <table class="grid">
            <thead>
                <tr>
                    <th style="width: 13%;">Tanggal</th>
                    <th style="width: 17%;">Unit Kerja</th>
                    <th style="width: 55%;">Uraian Kegiatan &amp; Capaian</th>
                    <th style="width: 15%;">Validasi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($journals as $journal)
                    <tr>
                        <td>{{ $journal->journal_date->format('d/m/Y') }}<br>({{ $journal->duration_minutes }} mnt)</td>
                        <td>{{ $journal->work_unit }}</td>
                        <td>
                            <strong>{{ $journal->activity_title }}</strong><br>
                            {{ $journal->activity_description }}
                            @if($journal->learning_outcome)
                                <br><em>Capaian: {{ $journal->learning_outcome }}</em>
                            @endif
                        </td>
                        <td>{{ $journal->validatedByTeacher?->user->name ?? 'Disetujui' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Tanda Tangan Pembimbing DUDI</h2>
    @if($signoffs->isEmpty())
        <p>Belum ada tanda tangan pembimbing DUDI.</p>
    @else
        <table class="grid">
            <thead>
                <tr><th>Rentang</th><th>Jurnal</th><th>Penanda tangan</th><th>Waktu</th><th>Tanda tangan</th></tr>
            </thead>
            <tbody>
                @foreach($signoffs as $row)
                    <tr>
                        <td>{{ $row['signoff']->period_start->format('d/m/Y') }}@if(! $row['signoff']->period_start->equalTo($row['signoff']->period_end)) s.d. {{ $row['signoff']->period_end->format('d/m/Y') }}@endif</td>
                        <td>{{ count($row['signoff']->journal_ids) }}</td>
                        <td>{{ $row['signoff']->signer_name }}@if($row['signoff']->signer_title)<br>{{ $row['signoff']->signer_title }}@endif</td>
                        <td>{{ $row['signoff']->signed_at->timezone($school->timezone)->format('d/m/Y H:i') }}</td>
                        <td>
                            @if($row['image'])<img src="{{ $row['image'] }}" alt="" style="height: 36px;">@else Nama diketik @endif
                            @if($row['changed'])<br><strong>Isi berubah setelah ditandatangani</strong>@endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p style="font-size: 9px;">Tanda tangan elektronik tidak tersertifikasi (UU ITE, PP 71/2019). Bukti tersimpan: nama, waktu, alamat IP, dan sidik isi jurnal.</p>
    @endif

    <h2>C. Rekap Penilaian Akhir PKL</h2>
    @if($assessment)
        <table class="grid">
            <thead>
                <tr>
                    <th>Komponen Nilai</th>
                    <th style="width: 22%;">Skor Komponen</th>
                    <th style="width: 28%;">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Nilai Dunia Kerja / Industri ({{ $placement->pklPeriod->rubric()->weights['industry_percentage'] }}%)</td>
                    <td>{{ number_format($assessment->industry_scores['weighted_average'] ?? 0, 2) }}</td>
                    <td>Penandatangan DUDI: {{ $assessment->industry_scores['signed_by_name'] ?? '-' }}</td>
                </tr>
                <tr>
                    <td>Nilai Pembimbing Sekolah ({{ $placement->pklPeriod->rubric()->weights['school_percentage'] }}%)</td>
                    <td>{{ number_format($assessment->school_scores['weighted_average'] ?? 0, 2) }}</td>
                    <td>Jurnal &amp; Laporan Akhir</td>
                </tr>
                <tr>
                    <td>Nilai Kehadiran ({{ $placement->pklPeriod->rubric()->weights['attendance_percentage'] }}%)</td>
                    <td>{{ number_format($assessment->attendance_score, 2) }}</td>
                    <td>Berdasarkan hari efektif</td>
                </tr>
                <tr>
                    <td><strong>Nilai Akhir &amp; Predikat</strong></td>
                    <td><strong>{{ number_format($assessment->final_score, 2) }}</strong></td>
                    <td><strong>{{ $assessment->predicate }}</strong></td>
                </tr>
            </tbody>
        </table>
        @if($assessment->achievement_description)
            <p style="margin-top: 6px;"><strong>Deskripsi capaian:</strong> {{ $assessment->achievement_description }}</p>
        @endif
        <p style="font-size: 8.5px; color: #52525B;">Sumber nilai industri: {{ $assessment->industrySourceLabel() }}. Status: {{ $assessment->finalized_at ? 'final versi '.$assessment->version : 'draf, belum final' }}.</p>
    @else
        <p style="color: #52525B;">Penilaian akhir belum diinput oleh guru pembimbing.</p>
    @endif
    @php
        $unapproved = $placement->journals()->where('status', '!=', \App\Enums\JournalStatus::Approved->value)->count();
        $docCode = strtoupper(substr(hash('sha256', $placement->id.'|'.$journals->pluck('id')->implode(',').'|'.($assessment?->snapshot_hash ?? '')), 0, 12));
    @endphp
    <p style="font-size: 8.5px; color: #52525B; margin-top: 6px;">Hanya jurnal berstatus disetujui yang dicantumkan.@if($unapproved) {{ $unapproved }} jurnal lain belum disetujui dan tidak termasuk dokumen ini.@endif Kode dokumen: {{ $docCode }}.</p>

    @php $cfg = $school->letterhead_config ?? []; @endphp
    <table class="sign-table">
        <tr>
            <td>
                Mengetahui,<br>Kepala {{ $school->name }}<br><br><br><br>
                <strong>{{ $cfg['principal_name'] ?: '________________________' }}</strong><br>
                NIP. {{ $cfg['principal_nip'] ?: '________________' }}
            </td>
            <td>
                Pembimbing Dunia Kerja,<br>{{ $placement->company->name }}<br><br><br><br>
                <strong>{{ $placement->industry_mentor_name ?: '________________________' }}</strong>
            </td>
            <td>
                {{ $school->city }}, {{ now()->format('d M Y') }}<br>Guru Pembimbing Sekolah,<br><br><br><br>
                <strong>{{ $placement->teacher->user->name }}</strong><br>
                NIP/NIK. {{ $placement->teacher->nip_nik ?: '-' }}
            </td>
        </tr>
    </table>
</body>
</html>

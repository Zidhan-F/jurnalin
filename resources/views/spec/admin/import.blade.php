@extends('layouts.app', ['title' => 'Impor Data - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Impor data siswa, guru, dan penempatan</h1>
        <p>Unggah template Excel Jurnalin atau ekspor Dapodik. Sistem memvalidasi kolom, menandai duplikat dan baris bermasalah, lalu menampilkan pratinjau. Data yang sudah ada tidak pernah ditimpa. Berkas tidak disimpan setelah diproses.</p>
    </div>
</div>

<div class="grid-auto mb-6">
    @foreach(['students' => ['Siswa', 'NISN, nama, email, kelas, jurusan; tanggal lahir dan nama wali untuk persetujuan data anak.'], 'teachers' => ['Guru', 'Nama, email, NIP/NIK, bidang keahlian.'], 'placements' => ['Penempatan', 'NISN siswa, email guru, nama tempat PKL, periode, divisi.']] as $kind => [$label, $hint])
        <section class="card stack-sm">
            <h2 class="section-title">{{ $label }}</h2>
            <p class="hint">{{ $hint }}</p>
            <a href="{{ route('admin.import.template', $kind) }}" class="link text-sm">Unduh template {{ strtolower($label) }}</a>
            <form method="POST" action="{{ route('admin.import.preview') }}" enctype="multipart/form-data" class="stack-sm">
                @csrf
                <input type="hidden" name="kind" value="{{ $kind }}">
                <label class="label" for="file-{{ $kind }}">Berkas .xlsx atau .csv</label>
                <input id="file-{{ $kind }}" name="file" type="file" accept=".xlsx,.csv" required class="block w-full text-sm">
                <button class="btn btn-navy btn-sm">Periksa & pratinjau</button>
            </form>
        </section>
    @endforeach
</div>

@if($preview)
    @php $rows = collect($preview['rows']); $okCount = $rows->where('status', 'ok')->count(); @endphp
    <section class="card stack-sm mb-6">
        <div class="flex flex-wrap justify-between gap-2 items-center">
            <h2 class="section-title">Pratinjau {{ ['students' => 'siswa', 'teachers' => 'guru', 'placements' => 'penempatan'][$preview['kind']] }} · {{ $preview['file'] }}</h2>
            <div class="flex flex-wrap gap-2">
                <span class="badge badge-ok">{{ $okCount }} siap</span>
                <span class="badge badge-warn">{{ $rows->whereIn('status', ['exists', 'duplicate'])->count() }} duplikat/sudah ada</span>
                <span class="badge badge-danger">{{ $rows->where('status', 'error')->count() }} bermasalah</span>
            </div>
        </div>
        <div class="overflow-x-auto max-h-96" role="region" aria-label="Pratinjau baris impor" tabindex="0">
            <table class="data-table">
                <thead><tr><th>Baris</th><th>Status</th><th>Data</th></tr></thead>
                <tbody>
                    @foreach($rows as $r)
                        <tr>
                            <td class="tabular-nums">{{ $r['line'] }}</td>
                            <td><span class="badge {{ $r['status'] === 'ok' ? 'badge-ok' : ($r['status'] === 'error' ? 'badge-danger' : 'badge-warn') }}">{{ $r['message'] }}</span></td>
                            <td class="hint">{{ collect($r['row'])->only(['nisn', 'nama', 'email', 'kelas', 'jurusan', 'email_guru', 'tempat_pkl', 'periode'])->filter()->implode(' · ') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <form method="POST" action="{{ route('admin.import.commit') }}" class="flex flex-wrap items-center gap-3">
            @csrf
            <button class="btn btn-primary" @disabled($okCount === 0)>Proses {{ $okCount }} baris yang siap</button>
            <span class="hint">Baris bermasalah dilewati tanpa menggagalkan seluruh impor.</span>
        </form>
    </section>
@endif

<section class="stack-sm">
    <h2 class="section-title">Log impor</h2>
    @forelse($logs as $log)
        <article class="card">
            <div class="flex flex-wrap justify-between gap-2"><strong>{{ ['students' => 'Siswa', 'teachers' => 'Guru', 'placements' => 'Penempatan'][$log->kind] ?? $log->kind }} · {{ $log->file_name }}</strong><span class="hint tabular-nums">{{ $log->created_at->format('d/m/Y H:i') }} · {{ $log->user?->name }}</span></div>
            <p class="hint tabular-nums">{{ $log->success }} berhasil · {{ $log->failed }} dilewati dari {{ $log->total }} baris</p>
            @if($log->errors)<details><summary class="link text-sm">Lihat baris dilewati</summary><ul class="hint mt-1">@foreach($log->errors as $e)<li>{{ $e }}</li>@endforeach</ul></details>@endif
        </article>
    @empty
        <div class="empty">Belum ada impor. Mulai dari template siswa.</div>
    @endforelse
</section>
@endsection

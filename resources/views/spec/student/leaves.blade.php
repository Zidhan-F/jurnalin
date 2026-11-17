@extends('layouts.student-pwa', ['title' => 'Izin & Sakit - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Izin & sakit</h1>
        <p>Ajukan izin atau sakit untuk satu hari atau beberapa hari. Guru pembimbing menyetujui atau menolak dengan alasan.</p>
    </div>
</div>

@if($placement === null)
    <div class="empty">Belum ada penempatan PKL aktif. Hubungi admin sekolah.</div>
@else
    <form method="POST" action="{{ route('student.leaves.store') }}" enctype="multipart/form-data" class="card-muted stack-sm">
        @csrf
        <fieldset>
            <legend class="label">Jenis pengajuan</legend>
            <div class="grid grid-cols-2 gap-2" role="group">
                <label class="card flex items-center gap-2"><input type="radio" name="type" value="sick" @checked(old('type', 'sick') === 'sick')> Sakit</label>
                <label class="card flex items-center gap-2"><input type="radio" name="type" value="permission" @checked(old('type') === 'permission')> Izin</label>
            </div>
        </fieldset>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="start_date" class="label">Mulai</label>
                <input id="start_date" name="start_date" type="date" required class="field tabular-nums" value="{{ old('start_date', $today) }}">
            </div>
            <div>
                <label for="end_date" class="label">Sampai</label>
                <input id="end_date" name="end_date" type="date" required class="field tabular-nums" value="{{ old('end_date', $today) }}">
            </div>
        </div>
        <div>
            <label for="reason" class="label">Alasan</label>
            <textarea id="reason" name="reason" required minlength="5" class="field" placeholder="Contoh: demam sejak semalam, sudah ke puskesmas">{{ old('reason') }}</textarea>
        </div>
        <div>
            <label for="attachment" class="label">Foto surat dokter atau surat izin (opsional)</label>
            <input id="attachment" name="attachment" type="file" accept="image/*" class="block w-full text-sm">
        </div>
        <p class="hint">Hari libur dan izin yang disetujui tidak dihitung sebagai alpa atau jurnal terlambat.</p>
        <button class="btn btn-warn btn-block">Kirim pengajuan</button>
    </form>
@endif

<section class="stack-sm">
    <h2 class="section-title">Riwayat pengajuan</h2>
    @forelse($leaves as $leave)
        @php $badge = ['submitted' => ['Menunggu guru', 'badge-warn'], 'approved' => ['Disetujui', 'badge-ok'], 'rejected' => ['Ditolak', 'badge-danger']][$leave->status]; @endphp
        <article class="card {{ $leave->status === 'submitted' ? 'card-attention' : '' }}">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <div class="font-semibold">{{ $leave->type === 'sick' ? 'Sakit' : 'Izin' }}</div>
                    <div class="hint tabular-nums">{{ $leave->start_date->format('d/m/Y') }} @if(! $leave->start_date->equalTo($leave->end_date)) s.d. {{ $leave->end_date->format('d/m/Y') }} @endif</div>
                </div>
                <span class="badge {{ $badge[1] }}">{{ $badge[0] }}</span>
            </div>
            <p class="mt-2 text-sm">{{ $leave->reason }}</p>
            @if($leave->decision_note)<p class="callout {{ $leave->status === 'rejected' ? 'callout-danger' : 'callout-ok' }} mt-2">Catatan guru: {{ $leave->decision_note }}</p>@endif
        </article>
    @empty
        <div class="empty">Belum ada pengajuan izin atau sakit.</div>
    @endforelse
</section>
@endsection

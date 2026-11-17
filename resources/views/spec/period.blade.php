@extends('layouts.app', ['title' => $period->name.' | Jurnalin'])
@section('content')
<div class="space-y-6">
    <header>
        <a class="document-link" href="{{ route('admin.periods') }}">Kembali ke daftar periode</a>
        <h1 class="text-2xl font-bold">{{ $period->name }}</h1>
        <p>{{ $period->is_active ? 'Aktif' : 'Arsip' }} · TA {{ $period->academic_year }} · {{ $period->start_date->format('d/m/Y') }} sampai {{ $period->end_date->format('d/m/Y') }} · Target {{ $period->min_required_days }} hari</p>
    </header>
    <section class="card">
        <h2 class="section-title">Grup siswa</h2>
        @forelse($groups as $group)
            <details>
                <summary class="cursor-pointer py-3">{{ $group->name }} · {{ $group->members->count() }} siswa</summary>
                <ul class="pl-5 list-disc">
                    @foreach($group->members->sortBy('user.name') as $student)
                        <li>{{ $student->user->name }} · {{ $student->class_name }}</li>
                    @endforeach
                </ul>
            </details>
        @empty
            <p>Belum ada grup pada periode ini.</p>
        @endforelse
        <a class="document-link" href="{{ route('admin.payments', ['period' => $period->id]) }}">Kelola grup dan pembayaran</a>
    </section>
    <section class="space-y-3">
        <h2 class="section-title">Penempatan siswa ({{ $placements->total() }})</h2>
        @forelse($placements as $placement)
            <article class="card">
                <h3><a class="document-link" href="{{ route('monitor.participant', $placement) }}">{{ $placement->student->user->name }}</a></h3>
                <p>{{ $placement->student->class_name }} · {{ $placement->company->name }} · {{ $placement->status->label() }}</p>
                <p>Guru pembimbing: {{ $placement->teacher->user->name }}</p>
                <p>{{ $placement->attendances_count }} catatan kehadiran · {{ $placement->journals_count }} jurnal · Nilai akhir: {{ $placement->assessment?->final_score ?? 'Belum tersedia' }}</p>
            </article>
        @empty
            <p>Belum ada penempatan siswa untuk periode ini. <a class="document-link" href="{{ route('admin.placements') }}">Atur penempatan</a></p>
        @endforelse
        {{ $placements->links() }}
    </section>
</div>
@endsection

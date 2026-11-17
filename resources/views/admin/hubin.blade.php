@extends('layouts.app', ['title' => 'Rekap Hubin | Jurnalin'])
@section('content')
<div class="space-y-6">
    <h1 class="text-2xl font-bold">Rekap Hubin</h1>
    @if($periods->isEmpty())
        <p>Belum ada periode PKL. <a class="document-link" href="{{ route('admin.periods') }}">Buat periode</a></p>
    @else
        <form method="GET" class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <label class="grid gap-1">Periode<select name="period" class="border rounded-md bg-paper px-2">
                @foreach($periods as $item)<option value="{{ $item->id }}" @selected($period->is($item))>{{ $item->name }}</option>@endforeach
            </select></label>
            <label class="grid gap-1">Jurusan<select name="major" class="border rounded-md bg-paper px-2"><option value="">Semua jurusan</option>
                @foreach($majors as $major)<option @selected(request('major') === $major)>{{ $major }}</option>@endforeach
            </select></label>
            <label class="grid gap-1">Kelas<select name="class_name" class="border rounded-md bg-paper px-2"><option value="">Semua kelas</option>
                @foreach($classes as $class)<option @selected(request('class_name') === $class)>{{ $class }}</option>@endforeach
            </select></label>
            <label class="grid gap-1">Guru pembimbing<select name="teacher" class="border rounded-md bg-paper px-2"><option value="">Semua pembimbing</option>
                @foreach($teachers as $teacher)<option value="{{ $teacher->id }}" @selected(request('teacher') === $teacher->id)>{{ $teacher->user->name }}</option>@endforeach
            </select></label>
            <button class="primary-link">Tampilkan rekap</button>
        </form>
        @if($placements->isEmpty())
            <p>Belum ada penempatan aktif atau selesai untuk pilihan ini. Pilih periode atau kelas lain.</p>
        @else
            <h2 class="text-lg font-bold">Siswa yang perlu ditindaklanjuti</h2>
            @forelse($followUp as $p)
                <div class="border-b border-border pb-3">
                    <strong>{{ $p->student->user->name }}</strong> · {{ $p->student->class_name }} · Pembimbing {{ $p->teacher->user->name }}
                    <ul class="list-disc pl-6">
                        @if($p->missing_journals)<li>{{ $p->missing_journals }} hari hadir sebelum hari ini belum memiliki jurnal terkirim.</li>@endif
                        @if($p->revisions)<li>{{ $p->revisions }} jurnal perlu revisi.</li>@endif
                        @if(! $p->assessment?->finalized_at && ($p->status === \App\Enums\PlacementStatus::Completed || $period->end_date->toDateString() < now(auth()->user()->school->timezone)->toDateString()))<li>PKL telah selesai dan nilai belum final.</li>@endif
                    </ul>
                </div>
            @empty
                <p>Semua jurnal hari hadir sebelumnya sudah terkirim. Tidak ada revisi atau nilai akhir yang tertunda untuk pilihan ini.</p>
            @endforelse
            <h2 class="text-lg font-bold">Dokumen untuk {{ $period->name }}</h2>
            @php $filters = ['period' => $period->id, ...request()->only('major', 'class_name', 'teacher')]; @endphp
            <a class="document-link" href="{{ route('admin.hubin', [...$filters, 'download' => 'xlsx']) }}">Unduh rekap {{ $placements->count() }} penempatan (Excel)</a>
            @if(request()->filled('class_name'))
                <a class="document-link" href="{{ route('admin.hubin', [...$filters, 'download' => 'zip']) }}">Unduh PDF jurnal sesuai filter (ZIP)</a>
            @else
                <p>Pilih satu kelas untuk mengunduh kumpulan PDF jurnalnya.</p>
            @endif
        @endif
    @endif
</div>
@endsection

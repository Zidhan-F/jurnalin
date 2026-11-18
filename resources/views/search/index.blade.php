@extends('layouts.app', ['title' => 'Cari - Jurnalin'])

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-ink">Hasil pencarian</h1>
        <form action="{{ route('search.index') }}" method="GET" class="mt-3 flex flex-wrap gap-2 max-w-xl" role="search">
            <label for="cari-halaman" class="sr-only">Kata kunci</label>
            <input id="cari-halaman" name="q" value="{{ $query }}" class="field flex-1 min-w-0" placeholder="Nama siswa, NISN, guru, tempat PKL, atau judul jurnal" autocomplete="off">
            <button class="btn btn-navy">Cari</button>
        </form>
    </div>

    @if($tooShort)
        <p class="callout">Ketik minimal {{ \App\Support\GlobalSearch::MIN_LENGTH }} huruf untuk mencari.</p>
    @elseif(collect($results)->flatten(1)->isEmpty())
        <p class="callout" role="status">Tidak ada hasil untuk "<strong>{{ $query }}</strong>". Periksa ejaan, atau cari dengan sebagian nama saja.</p>
    @else
        @foreach($results as $group => $items)
            @continue(empty($items))
            <section class="card" aria-labelledby="hasil-{{ \Illuminate\Support\Str::slug($group) }}">
                <h2 id="hasil-{{ \Illuminate\Support\Str::slug($group) }}" class="section-title">{{ $group }} <span class="badge">{{ count($items) }}</span></h2>
                <div class="task-list mt-2">
                    @foreach($items as $item)
                        <a href="{{ $item['url'] }}"><span>{{ $item['label'] }}<small>{{ $item['hint'] }}</small></span></a>
                    @endforeach
                </div>
            </section>
        @endforeach
    @endif
</div>
@endsection

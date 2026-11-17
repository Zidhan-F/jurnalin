@extends('layouts.student-pwa', ['title' => 'Kilas Balik PKL - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Kilas balik PKL</h1>
        <p>Perjalanan PKL Anda dari data yang tercatat. Tanpa peringkat dan tanpa membandingkan dengan siswa lain. Tidak dibagikan otomatis.</p>
    </div>
</div>
@if($placement === null)
    <div class="empty">Belum ada perjalanan PKL untuk ditampilkan.</div>
@else
    <dl class="stat-row">@foreach($stats as $label => $value)<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach</dl>
    <section class="card">
        <ol class="timeline">
            @foreach($events as $e)
                <li><time>{{ \Carbon\Carbon::parse($e['date'])->format('d/m/Y') }}</time><p class="font-semibold">{{ $e['title'] }}</p></li>
            @endforeach
        </ol>
    </section>
@endif
@endsection

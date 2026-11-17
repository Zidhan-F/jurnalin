@extends('layouts.app', ['title' => 'Ringkasan Pembimbing - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Ringkasan pembimbing: {{ $placement->student->user->name }}</h1>
        <p>Agenda bimbingan dari data tercatat. Ringkasan ini tidak menetapkan nilai, status kelulusan, atau tuduhan perilaku.</p>
    </div>
    <a href="{{ route('monitor.participant', $placement) }}" class="btn btn-outline btn-sm">Kembali ke halaman siswa</a>
</div>
<div class="split">
    <section class="card stack-sm">
        <h2 class="section-title">Fakta 14 hari terakhir</h2>
        <ul class="legal-list">@foreach($facts as $f)<li>{{ $f }}</li>@endforeach</ul>
        @foreach($alerts as $a)<p class="callout callout-warn">{{ $a['message'] }}</p>@endforeach
        <h2 class="section-title">Usulan pertanyaan bimbingan</h2>
        <ol class="legal-list list-decimal pl-5">@foreach($questions as $q)<li>{{ $q }}</li>@endforeach</ol>
    </section>
    <section class="card stack-sm">
        <h2 class="section-title">Sumber: jurnal terbaru</h2>
        @foreach($recent as $j)
            <div class="border-t border-border pt-2"><span class="hint tabular-nums">{{ $j->journal_date->format('d/m') }}</span> {{ $j->activity_title }} <span class="badge">{{ $j->status->label() }}</span></div>
        @endforeach
        @if($notes->isNotEmpty())
            <h2 class="section-title">Catatan bimbingan terakhir</h2>
            @foreach($notes as $n)<p class="hint">{{ $n->created_at->format('d/m') }} · {{ $n->topic }}</p>@endforeach
        @endif
    </section>
</div>
@endsection

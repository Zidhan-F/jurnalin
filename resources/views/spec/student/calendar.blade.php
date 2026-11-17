@extends('layouts.student-pwa', ['title' => 'Kalender PKL - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Kalender PKL</h1>
        <p>Lihat hari yang sudah tercatat, menunggu review, izin, dan libur. Ketuk Jurnal untuk melengkapi hari yang belum tercatat.</p>
    </div>
</div>
@if($placement === null)
    <div class="empty">Belum ada penempatan PKL.</div>
@else
    <section class="card">@include('spec.partials.calendar')</section>
    @include('spec.partials.readiness')
    <a href="{{ route('student.journals') }}" class="btn btn-primary btn-block">Tulis jurnal</a>
@endif
@endsection

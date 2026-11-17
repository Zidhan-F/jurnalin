@extends('spec.public.layout')
@section('title', 'Paspor Kompetensi '.$student->user->name)
@section('context', 'Paspor Kompetensi')
@section('footer', 'Ditampilkan atas izin pemiliknya. Data final diverifikasi sekolah penerbit; klaim pribadi ditandai terpisah.')

@section('content')
<div class="mentor-intro">
    <p class="hint">{{ $student->school->name }}</p>
    <h1>{{ $student->user->name }}</h1>
    <p class="hint">{{ $student->major }} · berlaku sampai {{ $passport->expires_at?->format('d/m/Y') }}</p>
</div>
<div class="stack">
    @include('spec.partials.passport-body', ['data' => $data])
</div>
<p class="hint">Paspor ini bukan portal lowongan atau rekrutmen. Keaslian sertifikat dapat diperiksa di <a class="link" href="{{ route('verify.form') }}">halaman verifikasi</a>.</p>
@endsection

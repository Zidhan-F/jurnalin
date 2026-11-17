@extends('errors.layout')
@section('code', '419')
@section('title', 'Sesi Anda sudah habis')
@section('message', 'Halaman terlalu lama dibiarkan terbuka, jadi formulir tidak dikirim demi keamanan. Muat ulang halaman, lalu kirim lagi.')
@section('actions')
<a class="primary-link" href="{{ url()->previous() !== url()->current() ? url()->previous() : "/" }}">Kembali</a>
    <a class="document-link" href="/login">Masuk lagi</a>
@endsection

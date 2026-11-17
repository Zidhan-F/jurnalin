@extends('errors.layout')
@section('code', '404')
@section('title', 'Halaman tidak ditemukan')
@section('message', 'Alamat ini tidak ada atau sudah dipindahkan. Periksa lagi tautannya, atau mulai dari beranda.')
@section('actions')
<a class="primary-link" href="/">Ke beranda</a>
    <a class="document-link" href="/login">Masuk ke akun</a>
@endsection

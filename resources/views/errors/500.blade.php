@extends('errors.layout')
@section('code', '500')
@section('title', 'Terjadi kesalahan di sistem kami')
@section('message', 'Bukan kesalahan Anda. Kami sudah mencatatnya. Coba lagi beberapa saat lagi; bila terus terjadi, hubungi admin sekolah atau tim Jurnalin.')
@section('actions')
<a class="primary-link" href="/">Ke beranda</a>
    <a class="document-link" href="/kontak">Kontak</a>
@endsection

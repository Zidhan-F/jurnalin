@extends('errors.layout')
@section('code', '403')
@section('title', 'Anda tidak punya akses ke halaman ini')
@section('message', 'Halaman ini milik peran atau sekolah lain. Bila menurut Anda ini keliru, hubungi admin sekolah Anda.')
@section('actions')
<a class="primary-link" href="{{ url()->previous() !== url()->current() ? url()->previous() : "/" }}">Kembali</a>
    <a class="document-link" href="/">Ke beranda</a>
@endsection

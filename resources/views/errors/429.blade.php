@extends('errors.layout')
@section('code', '429')
@section('title', 'Terlalu banyak percobaan')
@section('message', 'Demi keamanan akun, percobaan dari perangkat ini dibatasi sementara. Tunggu satu menit, lalu coba lagi.')
@section('actions')
<a class="primary-link" href="{{ url()->previous() !== url()->current() ? url()->previous() : "/" }}">Kembali</a>
@endsection

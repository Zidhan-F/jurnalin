@extends('spec.public.layout')
@section('title', 'Tautan tidak berlaku')

@section('content')
    <h1 class="text-2xl font-bold text-navy">Tautan tidak berlaku</h1>
    <p class="text-muted mt-3 leading-relaxed">{{ $message ?? 'Tautan ini sudah dicabut, diganti, atau masa berlakunya habis. Minta tautan terbaru kepada sekolah.' }}</p>
@endsection

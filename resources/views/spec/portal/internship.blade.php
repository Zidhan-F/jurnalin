@extends('spec.public.layout')
@section('title', 'Magang guru')
@section('context', 'Pembimbing magang guru')

@section('content')
@php $t = request()->attributes->get('portal_token'); @endphp
<a href="{{ route('portal.hub', $t) }}" class="link text-sm">Kembali</a>
<div class="mentor-intro">
    <h1>{{ $internship->teacher->user->name }}</h1>
    <p class="hint">{{ $internship->title }} · {{ $internship->company->name }} · {{ $internship->start_date->format('d/m/Y') }} s.d. {{ $internship->end_date->format('d/m/Y') }}</p>
</div>
<section class="mentor-card stack-sm">
    <h2 class="section-title">Ringkasan jurnal magang</h2>
    <ul class="mentor-journals">
        @forelse($internship->journals as $j)
            <li><span class="hint tabular-nums">{{ $j->journal_date->format('d/m') }}</span><h3>{{ \Illuminate\Support\Str::limit($j->work_done, 90) }}</h3>@if($j->technology)<p class="hint">Teknologi: {{ $j->technology }}</p>@endif</li>
        @empty
            <li class="hint">Belum ada jurnal magang.</li>
        @endforelse
    </ul>
</section>
@if($grant->can_evaluate)
    <section class="mentor-card stack-sm">
        <h2 class="section-title">Evaluasi magang guru</h2>
        @if($evaluation?->status === 'final')
            <p class="callout callout-ok">Evaluasi sudah disahkan.</p>
        @else
            <form method="POST" action="{{ route('portal.internship.evaluate', [$t, $internship->id]) }}" class="mentor-sign">
                @csrf
                @foreach($objectives as $o)
                    <div><label class="label" for="m-{{ $o->code }}">{{ $o->label }}</label>
                        <input id="m-{{ $o->code }}" name="scores[{{ $o->code }}]" class="field tabular-nums" placeholder="0-100 atau NA" value="{{ $evaluation->scores[$o->code] ?? '' }}"></div>
                @endforeach
                <div><label class="label" for="mi-notes">Catatan</label><textarea id="mi-notes" name="notes">{{ $evaluation?->notes }}</textarea></div>
                <button class="btn btn-outline">Simpan draf</button>
            </form>
            @if($evaluation)
                @include('spec.portal.otp')
                <form method="POST" action="{{ route('portal.confirm', [$t, $evaluation->id]) }}" class="mentor-sign">
                    @csrf
                    <label class="mentor-check"><input type="checkbox" name="statement" value="1"> Saya menyatakan evaluasi ini sesuai pengamatan saya.</label>
                    <button class="mentor-submit" @disabled(! $otpFresh)>Sahkan evaluasi</button>
                </form>
            @endif
        @endif
    </section>
@endif
@endsection

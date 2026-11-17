@extends('layouts.app', ['title' => $campaign->name.' - Tracer - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $campaign->name }}</h1>
        <p>Angkatan {{ $campaign->cohort_year }}. Data per {{ now()->format('d/m/Y') }}. Status dapat tumpang tindih (misalnya bekerja sambil kuliah). Nonresponden tidak dianggap penganggur.</p>
    </div>
    <a class="btn btn-outline btn-sm" href="{{ route('admin.tracer.csv', $campaign) }}">Ekspor rekap internal CSV</a>
</div>

<dl class="stat-row mb-6">
    <div><dt>Sasaran</dt><dd>{{ $responses->count() }}</dd></div>
    <div><dt>Responden</dt><dd>{{ $submitted }}</dd></div>
    <div><dt>Tingkat respons</dt><dd>{{ $rate }}%</dd></div>
    @foreach($statusCounts as $key => $count)<div><dt>{{ config('pkl.tracer_statuses')[$key] }}</dt><dd>{{ $count }}</dd></div>@endforeach
    <div><dt>Pekerjaan sesuai bidang</dt><dd>{{ $relevant }}</dd></div>
</dl>

<div class="split">
    <section class="card stack-sm">
        <h2 class="section-title">Per program keahlian</h2>
        <p class="hint">Kelompok dengan kurang dari {{ \App\Http\Controllers\Spec\TracerController::MIN_GROUP }} responden disembunyikan untuk melindungi identitas. Korelasi dengan PKL tidak membuktikan sebab akibat.</p>
        @forelse($byMajor as $major => $counts)
            <div class="border-t border-border pt-2"><strong>{{ $major }}</strong><p class="hint">{{ $counts->map(fn ($c, $k) => config('pkl.tracer_statuses')[$k].' '.$c)->implode(' · ') }}</p></div>
        @empty
            <p class="hint">Belum cukup responden per kelompok.</p>
        @endforelse
    </section>
    <section class="card stack-sm">
        <h2 class="section-title">Pemantauan respons</h2>
        <p class="hint">Jawaban individual tidak ditampilkan. Bagikan tautan pribadi hanya ke kontak alumni yang benar.</p>
        @foreach($responses as $r)
            <div class="flex flex-wrap justify-between gap-2 border-t border-border pt-2 items-center">
                <span>{{ $r->student->user->name }}</span>
                <span class="flex gap-2 items-center">
                    <span class="badge {{ in_array($r->status, ['submitted', 'corrected'], true) ? 'badge-ok' : 'badge-warn' }}">{{ ['invited' => 'Diundang', 'draft' => 'Draf', 'submitted' => 'Terkirim', 'corrected' => 'Dikoreksi'][$r->status] }}</span>
                    @if(! in_array($r->status, ['submitted', 'corrected'], true) && ($wa = \App\Http\Controllers\Spec\TracerController::waFor($r, $campaign)))<a class="link text-sm" href="{{ $wa }}" target="_blank" rel="noopener">Kirim pengingat</a>@endif
                </span>
            </div>
        @endforeach
    </section>
</div>
@endsection

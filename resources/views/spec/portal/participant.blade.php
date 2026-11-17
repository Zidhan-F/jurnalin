@extends('spec.public.layout')
@section('title', $placement->student->user->name)
@section('context', 'Pembimbing lapangan')

@section('content')
@php $t = request()->attributes->get('portal_token'); @endphp
<a href="{{ route('portal.hub', $t) }}" class="link text-sm">Kembali ke daftar peserta</a>
<div class="mentor-intro">
    <h1>{{ $placement->student->user->name }}</h1>
    <p class="hint">{{ $placement->student->major }} · {{ $placement->department_divisi ?: $placement->company->name }} · {{ $placement->pklPeriod->name }}</p>
</div>

<dl class="mentor-summary">
    @foreach($attendance as $label => $count)<div><dt>{{ $label }}</dt><dd class="tabular-nums">{{ $count }}</dd></div>@endforeach
</dl>

<section class="mentor-card stack-sm">
    <div class="flex items-center justify-between gap-2">
        <a class="btn btn-outline btn-sm" href="{{ route('portal.participant', [$t, $placement->id, 'minggu' => $weekStart->subWeek()->toDateString()]) }}">Minggu lalu</a>
        <h2 class="section-title tabular-nums">{{ $weekStart->format('d/m') }} s.d. {{ $weekStart->endOfWeek()->format('d/m') }}</h2>
        <a class="btn btn-outline btn-sm" href="{{ route('portal.participant', [$t, $placement->id, 'minggu' => $weekStart->addWeek()->toDateString()]) }}">Minggu depan</a>
    </div>
    @if($grant->can_review)<p class="hint">Tidak ada tombol setujui semua: tinjau setiap jurnal satu per satu.</p>@endif
    <ul class="mentor-journals">
        @forelse($journals as $j)
            <li>
                <div class="flex justify-between gap-2"><span class="hint tabular-nums">{{ $j->journal_date->translatedFormat('l, d/m') }}</span><span class="badge {{ $j->status === \App\Enums\JournalStatus::Approved ? 'badge-ok' : 'badge-warn' }}">{{ $j->status->label() }}</span></div>
                <h3>{{ $j->activity_title }}</h3>
                <p>{{ $j->activity_description }}</p>
                @if($j->mentor_note)<p class="callout mt-1">Catatan Anda: {{ $j->mentor_note }}</p>@endif
                @if($grant->can_review)
                    <details class="mentor-note">
                        <summary>Tinjau jurnal ini</summary>
                        <form method="POST" action="{{ route('portal.journal', [$t, $j->id]) }}" class="mentor-sign">
                            @csrf
                            <label class="label" for="n-{{ $j->id }}">Catatan</label>
                            <textarea id="n-{{ $j->id }}" name="note"></textarea>
                            <label class="mentor-check"><input type="checkbox" name="reviewed" value="1"> Saya sudah membaca jurnal ini</label>
                            <button name="decision" value="comment" class="btn btn-outline">Simpan catatan</button>
                            @if($canApprove && $j->status === \App\Enums\JournalStatus::Submitted)
                                <button name="decision" value="revision_needed" class="btn btn-warn">Minta revisi</button>
                                <button name="decision" value="approved" class="mentor-submit">Setujui</button>
                            @endif
                        </form>
                    </details>
                @endif
            </li>
        @empty
            <li class="hint">Belum ada jurnal yang diajukan pada minggu ini.</li>
        @endforelse
    </ul>
</section>

@if($grant->can_evaluate)
    <section class="mentor-card stack-sm">
        <h2 class="section-title">Evaluasi akhir</h2>
        @if($evaluation?->status === 'final')
            <p class="callout callout-ok">Evaluasi disahkan pada {{ $evaluation->finalized_at->timezone($placement->school->timezone)->format('d/m/Y H:i') }}. Kode integritas: <span class="tabular-nums">{{ substr($evaluation->content_hash, 0, 16) }}</span></p>
        @else
            <form method="POST" action="{{ route('portal.evaluation', [$t, $placement->id]) }}" class="mentor-sign">
                @csrf
                <p class="hint">Skala 0 sampai 100. Pilih "Tidak dapat dinilai" untuk kemampuan yang tidak Anda amati.</p>
                @foreach($aspects as $a)
                    @php $val = $evaluation->scores[$a['code']] ?? ''; @endphp
                    <div x-data="{ na: @js($val === 'NA') }">
                        <label class="label" for="s-{{ $a['code'] }}">{{ $a['label'] }}</label>
                        <div class="flex gap-2 items-center">
                            <input id="s-{{ $a['code'] }}" name="scores[{{ $a['code'] }}]" type="number" min="0" max="100" class="field tabular-nums" value="{{ $val === 'NA' ? '' : $val }}" :disabled="na">
                            <label class="mentor-check whitespace-nowrap"><input type="checkbox" x-model="na"> Tidak dapat dinilai</label>
                        </div>
                        <input type="hidden" name="scores[{{ $a['code'] }}]" value="NA" :disabled="! na">
                    </div>
                @endforeach
                <div><label class="label" for="ev-notes">Catatan untuk sekolah</label><textarea id="ev-notes" name="notes">{{ $evaluation?->notes }}</textarea></div>
                <button class="btn btn-outline">Simpan draf evaluasi</button>
            </form>
            @if($evaluation)
                @include('spec.portal.otp')
                <form method="POST" action="{{ route('portal.confirm', [$t, $evaluation->id]) }}" class="mentor-sign">
                    @csrf
                    <label class="mentor-check"><input type="checkbox" name="statement" value="1"> Saya, {{ $grant->contact->name }}, menyatakan penilaian ini sesuai pengamatan saya selama membimbing peserta. Persetujuan elektronik ini bukan tanda tangan elektronik tersertifikasi.</label>
                    <button class="mentor-submit" @disabled(! $otpFresh)>Sahkan evaluasi</button>
                </form>
            @endif
        @endif
    </section>
@endif
@endsection

@extends('spec.public.layout')
@section('title', 'Akses pembimbing')
@section('context', $grant->purpose === 'examiner' ? 'Penguji UKK' : 'Pembimbing lapangan')

@section('content')
<div class="mentor-intro">
    <p class="hint">{{ $grant->school->name }}</p>
    <h1>Halo, {{ $grant->contact->name }}</h1>
    <p class="hint">Tautan berlaku sampai {{ $grant->expires_at->timezone($grant->school->timezone)->format('d/m/Y') }}. Anda hanya melihat peserta yang ditugaskan kepada Anda.</p>
</div>

@if($grant->purpose === 'examiner' && $session)
    @include('spec.portal.otp')
    <section class="mentor-card stack-sm">
        <h2 class="section-title">{{ $session->name }}</h2>
        <p class="hint">{{ $session->concentration }} · {{ $session->venue }} · {{ $session->scheduled_at->timezone($grant->school->timezone)->format('d/m/Y H:i') }} · instrumen versi {{ $session->instrument_version }}</p>
        @if($session->status === 'final')<p class="callout callout-ok">Sesi sudah difinalkan koordinator. Nilai tidak dapat diubah.</p>@endif
        @foreach($session->participants as $part)
            @php $mine = $part->scores->firstWhere('examiner_contact_id', $grant->partner_contact_id); @endphp
            <details class="border-t border-border pt-2" @if(! $mine) open @endif>
                <summary class="font-semibold min-h-11 flex items-center justify-between gap-2"><span>{{ $part->participant_number }} · {{ $part->student->user->name }}</span><span class="badge {{ $mine ? 'badge-ok' : 'badge-warn' }}">{{ $mine ? 'Sudah dinilai' : 'Belum dinilai' }}</span></summary>
                <form method="POST" action="{{ route('portal.ukk.score', [request()->attributes->get('portal_token'), $part->id]) }}" class="mentor-sign">
                    @csrf
                    @foreach($session->instrument['aspects'] ?? [] as $a)
                        <div><label class="label" for="u-{{ $part->id }}-{{ $a['code'] }}">{{ $a['label'] }} (bobot {{ $a['weight'] }}%)</label>
                            <input id="u-{{ $part->id }}-{{ $a['code'] }}" name="scores[{{ $a['code'] }}]" type="number" min="0" max="100" required class="field tabular-nums" value="{{ $mine->scores[$a['code']] ?? '' }}"></div>
                    @endforeach
                    <div><label class="label" for="un-{{ $part->id }}">Catatan</label><textarea id="un-{{ $part->id }}" name="notes" class="field">{{ $mine?->notes }}</textarea></div>
                    <button class="mentor-submit" @disabled($session->status === 'final' || ! $otpFresh)>Simpan nilai</button>
                </form>
            </details>
        @endforeach
    </section>
@else
    <section class="mentor-card stack-sm">
        <h2 class="section-title">Peserta yang Anda bimbing ({{ $placements->count() }})</h2>
        @if($pending)<p class="callout callout-warn">{{ $pending }} jurnal terbaru menunggu ditinjau.</p>@endif
        @forelse($placements as $p)
            @php $ev = $evaluations->firstWhere('placement_id', $p->id); @endphp
            <a href="{{ route('portal.participant', [request()->attributes->get('portal_token'), $p->id]) }}" class="flex justify-between gap-2 border-t border-border pt-2 min-h-12 items-center">
                <span><strong>{{ $p->student->user->name }}</strong><span class="block hint">{{ $p->department_divisi ?: $p->company->name }}</span></span>
                <span class="badge {{ $ev?->status === 'final' ? 'badge-ok' : ($ev ? 'badge-warn' : '') }}">{{ $ev?->status === 'final' ? 'Evaluasi disahkan' : ($ev ? 'Draf evaluasi' : 'Belum dievaluasi') }}</span>
            </a>
        @empty
            <p class="hint">Tidak ada peserta aktif dalam tautan ini. Penugasan yang sudah berakhir tidak ditampilkan.</p>
        @endforelse
    </section>
    @if($internships->isNotEmpty())
        <section class="mentor-card stack-sm">
            <h2 class="section-title">Guru peserta magang</h2>
            @foreach($internships as $i)
                <a href="{{ route('portal.internship', [request()->attributes->get('portal_token'), $i->id]) }}" class="flex justify-between gap-2 border-t border-border pt-2 min-h-12 items-center"><strong>{{ $i->teacher->user->name }}</strong><span class="hint">{{ $i->title }}</span></a>
            @endforeach
        </section>
    @endif
    <details class="mentor-card">
        <summary class="font-semibold min-h-11 flex items-center">Panduan singkat</summary>
        <ol class="legal-list mt-2">
            <li>Buka nama peserta untuk melihat jurnal per minggu.</li>
            <li>Beri catatan, minta revisi, atau setujui bila sekolah menetapkan Anda sebagai penyetuju.</li>
            <li>Di akhir PKL, isi evaluasi. Pilih "Tidak dapat dinilai" untuk kemampuan yang tidak Anda amati.</li>
            <li>Sahkan evaluasi dengan kode verifikasi dari email. Formulir kertas tetap bisa dipakai bila lebih mudah.</li>
        </ol>
    </details>
@endif
@endsection

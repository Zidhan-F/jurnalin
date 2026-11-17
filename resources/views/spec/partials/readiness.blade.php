{{-- Cek Kelengkapan PKL (P18). Sistem tidak menyatakan lulus; keputusan akhir tetap pada sekolah. --}}
@php $done = collect($readiness)->where('done', true)->count(); @endphp
<section class="card stack-sm">
    <div class="flex flex-wrap justify-between gap-2">
        <h2 class="section-title">Cek kelengkapan PKL</h2>
        <span class="badge {{ $done === count($readiness) ? 'badge-ok' : 'badge-warn' }}">{{ $done }}/{{ count($readiness) }} syarat</span>
    </div>
    <div class="progress" aria-hidden="true"><span style="width: {{ count($readiness) ? round($done / count($readiness) * 100) : 0 }}%"></span></div>
    <ul class="check-list">
        @foreach($readiness as $item)
            <li class="{{ $item['done'] ? 'is-done' : '' }}">
                <span class="check-mark">@if($item['done'])<x-icon name="check" />@else{{ $loop->iteration }}@endif</span>
                <span><strong>{{ $item['label'] }}</strong><span class="block hint">{{ $item['detail'] }}</span></span>
                <span class="hint">{{ $item['done'] ? 'Selesai' : $item['owner'] }}</span>
            </li>
        @endforeach
    </ul>
    <p class="hint">Daftar ini membantu melihat syarat yang belum terpenuhi. Kelulusan PKL tetap diputuskan sekolah.</p>
</section>

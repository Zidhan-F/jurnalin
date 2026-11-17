{{-- Kalender PKL (P10): penanda berbeda untuk belum tercatat, izin, libur, dan menunggu review. Status selalu berteks. --}}
@php
    $labels = ['missing' => 'Belum tercatat', 'leave' => 'Izin/sakit', 'holiday' => 'Libur', 'pending' => 'Menunggu review', 'approved' => 'Disetujui', 'recorded' => 'Tercatat', 'off' => 'Di luar jadwal', 'future' => 'Mendatang'];
    $short = ['missing' => 'Kosong', 'leave' => 'Izin', 'holiday' => 'Libur', 'pending' => 'Review', 'approved' => 'Setuju', 'recorded' => 'Ada', 'off' => '', 'future' => ''];
    $classes = ['missing' => 'is-missing', 'leave' => 'is-off', 'holiday' => 'is-off', 'off' => 'is-off', 'pending' => 'is-pending'];
    $prev = $month->subMonthNoOverflow()->format('Y-m');
    $next = $month->addMonthNoOverflow()->format('Y-m');
@endphp
<div class="stack-sm">
    <div class="flex items-center justify-between gap-2">
        <a class="btn btn-outline btn-sm" href="{{ request()->fullUrlWithQuery(['bulan' => $prev]) }}">Bulan sebelumnya</a>
        <h2 class="section-title">{{ $month->translatedFormat('F Y') }}</h2>
        <a class="btn btn-outline btn-sm" href="{{ request()->fullUrlWithQuery(['bulan' => $next]) }}">Bulan berikutnya</a>
    </div>
    <div class="cal-grid" role="grid" aria-label="Kalender PKL {{ $month->translatedFormat('F Y') }}">
        @foreach(['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $h)<div class="cal-head" role="columnheader">{{ $h }}</div>@endforeach
        @for($i = 1; $i < ($calendar[0]['dow'] ?? 1); $i++)<div aria-hidden="true"></div>@endfor
        @foreach($calendar as $day)
            <div role="gridcell" class="cal-day {{ $classes[$day['state']] ?? '' }} {{ $day['today'] ? 'is-today' : '' }}" aria-label="{{ $day['date'] }}: {{ $labels[$day['state']] }}{{ $day['holiday'] ? ' ('.$day['holiday'].')' : '' }}">
                <span class="cal-num">{{ $day['day'] }}</span>
                <span aria-hidden="true">{{ $short[$day['state']] }}</span>
            </div>
        @endforeach
    </div>
    <p class="cal-legend"><span>Kosong: belum tercatat (garis merah)</span><span>Menunggu diperiksa guru (garis kiri oranye)</span><span>Setuju: jurnal disetujui</span><span>Latar abu: libur, izin, di luar jadwal</span></p>
</div>

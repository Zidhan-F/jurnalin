@props([
    'type' => 'line',
    'labels' => [],
    'values' => [],
    'seriesLabel' => '',
    'caption' => '',
    // Nama token warna dari app.css (tanpa awalan --color-), satu per irisan donat atau satu untuk garis.
    'colors' => null,
    'height' => 220,
])
@php
    $colors = $colors ?? ($type === 'doughnut' ? ['emerald-check', 'amber-warn', 'danger', 'navy'] : ['navy']);
    $payload = ['type' => $type, 'labels' => array_values($labels), 'values' => array_values($values), 'label' => $seriesLabel, 'colors' => $colors];
    $total = array_sum($values);
@endphp
<figure {{ $attributes->class(['chart', 'chart-'.$type]) }}>
    <div class="chart-canvas" style="height: {{ (int) $height }}px">
        <canvas data-chart='@json($payload)' role="img" aria-label="{{ $caption }}"></canvas>
    </div>
    <table @class(['chart-data', 'sr-only' => $type !== 'doughnut'])>
        <caption class="sr-only">{{ $caption }}</caption>
        <tbody>
            @foreach($labels as $i => $label)
                <tr>
                    <th scope="row">@if($type === 'doughnut')<span class="chart-swatch" style="background: var(--color-{{ $colors[$i % count($colors)] }})" aria-hidden="true"></span>@endif{{ $label }}</th>
                    <td class="tabular-nums">{{ $values[$i] ?? 0 }}</td>
                    @if($type === 'doughnut')<td class="tabular-nums text-muted">({{ $total > 0 ? round(($values[$i] ?? 0) / $total * 100) : 0 }}%)</td>@endif
                </tr>
            @endforeach
        </tbody>
    </table>
</figure>

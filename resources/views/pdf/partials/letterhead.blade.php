@php
    $cfg = $school->letterhead_config ?? [];
    $logoFile = \App\Support\MediaStorage::absolutePath($school->logo_path);
@endphp
<table style="width: 100%; border-bottom: 2px solid #18181B; padding-bottom: 8px; margin-bottom: 14px;">
    <tr>
        @if($logoFile)
            <td style="width: 68px; vertical-align: middle;">
                <img src="{{ $logoFile }}" style="width: 58px; height: auto;" alt="Logo">
            </td>
        @endif
        <td style="text-align: center; vertical-align: middle;">
            @if(!empty($cfg['header_line_1']))
                <div style="font-size: 10px; font-weight: bold; letter-spacing: 0.5px;">{{ $cfg['header_line_1'] }}</div>
            @endif
            <div style="font-size: 15px; font-weight: bold;">{{ $cfg['header_line_2'] ?? strtoupper($school->name) }}</div>
            <div style="font-size: 9px; color: #3F3F46;">{{ $cfg['sub_header'] ?? ($school->address . ', ' . $school->city . ', ' . $school->province) }}</div>
            <div style="font-size: 9px; color: #3F3F46;">NPSN: {{ $school->npsn }}</div>
        </td>
    </tr>
</table>

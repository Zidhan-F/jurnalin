@extends('layouts.app', ['title' => 'Modul Sekolah - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Aktivasi modul per sekolah</h1>
        <p>Aktifkan add-on (Hubin, Magang Guru, AI, UKK, Tracer, WhatsApp) atau fitur Pro sebagai uji coba pilot. Menu, rute, dan job modul hanya tersedia bila aktif. Penonaktifan tidak menghapus data.</p>
    </div>
</div>

@foreach($schools as $school)
    @php $rows = ($features[$school->id] ?? collect())->keyBy('feature'); @endphp
    <section class="card mb-4 stack-sm">
        <div class="flex flex-wrap justify-between gap-2"><h2 class="section-title">{{ $school->name }}</h2><span class="hint">{{ $school->plan()->label() }} · NPSN {{ $school->npsn }}</span></div>
        <div class="overflow-x-auto" role="region" aria-label="Modul {{ $school->name }}" tabindex="0">
            <table class="data-table">
                <thead><tr><th>Modul</th><th>Status</th><th>Sumber</th><th>Tindakan</th></tr></thead>
                <tbody>
                    @foreach($catalog as $key => $label)
                        @php $on = $school->hasFeature($key); $row = $rows[$key] ?? null; @endphp
                        <tr>
                            <td>{{ $label }}</td>
                            <td><span class="badge {{ $on ? 'badge-ok' : '' }}">{{ $on ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td class="hint">{{ $row ? (['addon' => 'Add-on', 'plan' => 'Paket', 'pilot' => 'Pilot'][$row->source] ?? $row->source) : 'Bawaan paket' }}</td>
                            <td>
                                <form method="POST" action="{{ route('super-admin.modules.toggle', $school) }}" class="flex flex-wrap gap-2">
                                    @csrf
                                    <input type="hidden" name="feature" value="{{ $key }}">
                                    <input type="hidden" name="enabled" value="{{ $on ? 0 : 1 }}">
                                    @unless($on)
                                        <select name="source" class="field" aria-label="Sumber aktivasi {{ $label }}"><option value="addon">Add-on</option><option value="pilot">Pilot</option><option value="plan">Paket</option></select>
                                    @else
                                        <input type="hidden" name="source" value="{{ $row->source ?? 'plan' }}">
                                    @endunless
                                    <button class="btn {{ $on ? 'btn-danger' : 'btn-primary' }} btn-sm">{{ $on ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endforeach
@endsection

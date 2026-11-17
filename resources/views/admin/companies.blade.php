@extends('layouts.app', ['title' => 'Tempat PKL & Koordinat Peta - Jurnalin'])

@section('content')
<div class="grid lg:grid-cols-12 gap-8 items-start">
    <div class="lg:col-span-5 rounded-xl border border-border bg-stone p-6 space-y-4">
        <div>
            <h1 class="text-xl font-bold text-ink">Tambah tempat PKL</h1>
            <p class="text-sm text-muted mt-1">Tempat PKL juga disebut DUDI (Dunia Usaha dan Dunia Industri): perusahaan, kantor, bengkel, hotel, atau instansi tempat siswa praktik.</p>
            <p class="text-xs text-muted mt-1">Klik peta untuk menentukan titik koordinat tempat PKL yang dipakai sebagai pusat radius absen siswa.</p>
        </div>

        <form method="POST" action="/admin/companies" class="space-y-3.5">
            @csrf
            <div>
                <label class="block text-sm font-semibold mb-1" for="name">Nama Perusahaan / Instansi</label>
                <input id="name" name="name" type="text" required value="{{ old('name') }}" placeholder="Contoh: PT Telkom Indonesia, Bengkel Astra, RS Hasan Sadikin"
                    class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-semibold mb-1" for="industry_type">Kategori Industri</label>
                    <input id="industry_type" name="industry_type" type="text" required list="industry-type-list" value="{{ old('industry_type') }}" placeholder="Pilih atau ketik kategori"
                        class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1" for="city">Kota</label>
                    <input id="city" name="city" type="text" required value="{{ old('city', $school->city) }}"
                        class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1" for="address">Alamat Lengkap</label>
                <textarea id="address" name="address" rows="2" required class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm">{{ old('address') }}</textarea>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-semibold mb-1" for="contact_person_name">Kontak HRD / Training</label>
                    <input id="contact_person_name" name="contact_person_name" type="text" value="{{ old('contact_person_name') }}" placeholder="Nama HRD"
                        class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1" for="contact_person_phone">Telepon / WA HRD</label>
                    <input id="contact_person_phone" name="contact_person_phone" type="tel" inputmode="numeric" autocomplete="off" value="{{ old('contact_person_phone') }}" placeholder="628..."
                        class="w-full rounded-md border border-field bg-paper px-3 py-2 text-sm tabular-nums">
                </div>
            </div>

            <div class="space-y-2 pt-1">
                <div id="judul-peta" class="text-sm font-semibold text-navy">Pilih Titik Peta OpenStreetMap</div>
                <p id="bantuan-peta" class="text-sm text-muted">Klik lokasi di peta, atau ketik Latitude dan Longitude di bawah. Penanda ikut berpindah setelah kolom ditinggalkan.</p>
                <div id="company-picker-map" role="region" aria-labelledby="judul-peta" aria-describedby="bantuan-peta" class="map-picker w-full h-56 z-0"></div>
                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <label class="block text-sm font-semibold text-ink" for="latitude">Titik lintang <span class="block font-normal text-muted text-xs mt-0.5">Terisi sendiri saat Anda mengklik peta.</span></label>
                        <input id="latitude" name="latitude" type="text" inputmode="decimal" required value="{{ old('latitude', '-6.9175000') }}"
                            class="w-full rounded-md border border-field bg-paper px-2 py-1.5 text-xs tabular-nums">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-ink" for="longitude">Titik bujur <span class="block font-normal text-muted text-xs mt-0.5">Terisi sendiri saat Anda mengklik peta.</span></label>
                        <input id="longitude" name="longitude" type="text" inputmode="decimal" required value="{{ old('longitude', '107.6191000') }}"
                            class="w-full rounded-md border border-field bg-paper px-2 py-1.5 text-xs tabular-nums">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-ink" for="geofence_radius_m">Jarak absen (meter) <span class="block font-normal text-muted text-xs mt-0.5">Siswa bisa absen hadir bila berada dalam jarak ini dari titik peta.</span></label>
                        <input id="geofence_radius_m" name="geofence_radius_m" type="number" min="30" max="2000" value="{{ old('geofence_radius_m', $school->default_geofence_radius_m) }}"
                            class="w-full rounded-md border border-field bg-paper px-2 py-1.5 text-xs tabular-nums">
                    </div>
                </div>
            </div>

            <button type="submit" class="w-full py-2.5 px-4 rounded-lg bg-emerald-check hover:bg-emerald-strong text-white font-semibold text-sm min-h-11 cursor-pointer">
                Simpan Tempat PKL
            </button>
        </form>
    </div>

    <div class="lg:col-span-7 space-y-4">
        <h2 class="text-xl font-bold text-ink">Daftar Mitra Tempat PKL ({{ $companies->count() }})</h2>
        @if($companies->isEmpty())
            <div class="rounded-xl border border-border bg-stone p-8 text-center text-sm text-muted">
                Belum ada tempat PKL terdaftar. Tambahkan tempat PKL pertama menggunakan formulir di samping.
            </div>
        @else
            <div class="space-y-3">
                @foreach($companies as $company)
                    <div class="rounded-xl border border-border bg-paper p-5 flex flex-wrap justify-between gap-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-ink">{{ $company->name }}</h3>
                                <span class="px-2 py-0.5 rounded-md bg-stone text-xs font-medium text-muted">{{ $company->industry_type }}</span>
                            </div>
                            <div class="text-xs text-muted">{{ $company->address }}, {{ $company->city }}</div>
                            <div class="text-xs text-muted tabular-nums">
                                Koordinat: {{ number_format($company->latitude, 6) }}, {{ number_format($company->longitude, 6) }} | Radius: <strong>{{ $company->effectiveRadiusMeters() }} m</strong>
                            </div>
                            @if($company->contact_person_name)
                                <div class="text-xs text-muted">Kontak HRD: {{ $company->contact_person_name }} ({{ $company->contact_person_phone ?: '-' }})</div>
                            @endif
                        </div>
                        <div class="text-right text-xs tabular-nums text-muted">
                            <div class="font-bold text-base text-navy">{{ $company->placements_count }}</div>
                            <div>penempatan</div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    if (window.jurnalinInitCompanyMap) {
        window.jurnalinInitCompanyMap('company-picker-map', 'latitude', 'longitude', 'geofence_radius_m');
    }
});
</script>
@endsection

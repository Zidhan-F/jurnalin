@extends('layouts.app', ['title' => 'Admin - Verifikasi Sekolah Jurnalin'])

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-ink">Verifikasi &amp; Kuota Sekolah</h1>
            <p class="text-sm text-muted mt-1">
                Cocokkan NPSN pendaftar baru dengan referensi Kemendikdasmen sebelum mengaktifkan akses tenant.
            </p>
        </div>
        <div class="flex gap-4 text-sm">
            <div class="px-4 py-2 rounded-lg bg-stone border border-border">
                Menunggu Verifikasi: <span class="tabular-nums font-bold text-amber-strong">{{ $pendingCount }}</span>
            </div>
            <div class="px-4 py-2 rounded-lg bg-stone border border-border">
                Sekolah Aktif: <span class="tabular-nums font-bold text-emerald-check">{{ $activeCount }}</span>
            </div>
        </div>
    </div>

    @if($schools->isEmpty())
        <div class="rounded-xl border border-border bg-stone p-8 text-center text-sm text-muted">
            Belum ada sekolah yang mendaftar di platform.
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-border bg-paper">
            <table class="w-full text-sm text-left">
                <thead class="bg-stone text-xs uppercase text-muted border-b border-border">
                    <tr>
                        <th class="px-4 py-3">NPSN &amp; Sekolah</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Penggunaan</th>
                        <th class="px-4 py-3">Paket &amp; Kuota</th>
                        <th class="px-4 py-3">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($schools as $school)
                        <tr class="{{ $school->status === \App\Enums\SchoolStatus::PendingVerification ? 'border-l-4 border-l-amber-warn' : '' }}">
                            <td class="px-4 py-3.5 align-top">
                                <div class="font-bold text-ink">{{ $school->name }}</div>
                                <div class="text-xs text-muted tabular-nums mt-0.5">NPSN: {{ $school->npsn }} | {{ $school->city }}, {{ $school->province }}</div>
                                @foreach($school->users as $schoolAdmin)
                                    <div class="mt-2 text-xs text-muted">
                                        Admin: {{ $schoolAdmin->name }} ({{ $schoolAdmin->email }})
                                        <form method="POST" action="{{ route('super-admin.users.reset-password', $schoolAdmin) }}" class="inline" onsubmit="return confirm('Reset kata sandi admin {{ $schoolAdmin->name }} ke sandi awal?')">
                                            @csrf
                                            <button type="submit" class="document-link text-xs">Reset sandi<span class="sr-only"> admin {{ $schoolAdmin->name }}</span></button>
                                        </form>
                                    </div>
                                @endforeach
                                @if($school->deletion_requested_at)
                                    <div class="text-xs font-semibold text-danger-strong mt-1">Minta dihapus sejak {{ $school->deletion_requested_at->format('d/m/Y') }}, batas {{ $school->deletion_requested_at->addDays(30)->format('d/m/Y') }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 align-top">
                                @if($school->status === \App\Enums\SchoolStatus::Active)
                                    <span class="inline-block px-2.5 py-1 rounded-md text-xs font-semibold bg-emerald-check/15 text-emerald-strong">Aktif</span>
                                @elseif($school->status === \App\Enums\SchoolStatus::PendingVerification)
                                    <span class="inline-block px-2.5 py-1 rounded-md text-xs font-semibold bg-amber-warn/15 text-amber-strong">Menunggu Verifikasi</span>
                                @else
                                    <span class="inline-block px-2.5 py-1 rounded-md text-xs font-semibold bg-danger/15 text-danger-strong">Ditangguhkan</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 align-top tabular-nums text-xs text-muted">
                                <div>Siswa: <strong class="text-ink">{{ $school->students_count }}</strong> / {{ $school->max_students_quota }}</div>
                                <div>Guru: {{ $school->teachers_count }} | Penempatan: {{ $school->placements_count }}</div>
                            </td>
                            <td class="px-4 py-3.5 align-top">
                                <form method="POST" action="/super-admin/schools/{{ $school->id }}/quota" class="flex flex-wrap items-center gap-2">
                                    @csrf
                                    <label class="grid gap-1 text-sm">Paket
                                        <select name="subscription_tier" class="rounded-md border border-field bg-white px-2 py-1 text-xs">
                                        @foreach(\App\Enums\SchoolPlan::cases() as $plan)
                                            <option value="{{ $plan->value }}" @selected($school->plan() === $plan)>{{ $plan->label() }} ({{ $plan->formattedPrice() }}{{ $plan === \App\Enums\SchoolPlan::Free ? '' : ' per siswa aktif per bulan' }})</option>
                                        @endforeach
                                        </select>
                                    </label>
                                    <label class="grid gap-1 text-sm">Kuota siswa
                                        <input type="number" name="max_students_quota" value="{{ $school->max_students_quota }}" min="10" max="5000"
                                        class="w-20 rounded-md border border-field bg-white px-2 py-1 text-xs tabular-nums">
                                    </label>
                                    <button type="submit" class="px-2.5 py-1 rounded-lg bg-navy text-white text-xs font-semibold cursor-pointer">
                                        Simpan
                                    </button>
                                </form>
                            </td>
                            <td class="px-4 py-3.5 align-top">
                                <div class="flex flex-wrap gap-2">
                                    @if($school->status === \App\Enums\SchoolStatus::PendingVerification)
                                        <form method="POST" action="/super-admin/schools/{{ $school->id }}/approve">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-check hover:bg-emerald-strong text-white text-xs font-semibold cursor-pointer">
                                                Verifikasi &amp; Aktifkan
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="/super-admin/schools/{{ $school->id }}/suspend">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 rounded-lg border border-border bg-white hover:bg-stone text-xs font-semibold cursor-pointer">
                                                {{ $school->status === \App\Enums\SchoolStatus::Suspended ? 'Aktifkan Kembali' : 'Tangguhkan' }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection

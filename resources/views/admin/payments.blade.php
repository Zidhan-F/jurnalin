@extends('layouts.app', ['title' => 'Pembayaran | Jurnalin'])

@section('content')
@php
    $rupiah = fn ($amount) => 'Rp'.number_format($amount, 0, ',', '.');
@endphp
<div class="mx-auto max-w-4xl space-y-8">
    <header class="school-page-heading">
        <h1>Pembayaran</h1>
        <p>Kelola akses dan tagihan Jurnalin untuk periode PKL sekolah Anda.</p>
    </header>
    @include('admin.plan-box')
    @if($periods->isEmpty())
        <p>Buat periode PKL dan penempatan siswa terlebih dahulu. <a href="{{ route('admin.periods') }}" class="document-link">Atur periode</a></p>
    @else
        <div class="period-summary">
        <div>
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <label class="grid gap-1 font-semibold">Periode PKL
                <select name="period" class="min-h-12 max-w-full rounded-md border border-field bg-paper px-3">
                    @foreach($periods as $item)<option value="{{ $item->id }}" @selected($period->is($item))>{{ $item->name }}</option>@endforeach
                </select>
            </label>
            @if($periods->count() > 1)<button class="document-link min-h-12">Lihat periode</button>@endif
        </form>
        <p class="mt-2 text-sm text-muted">{{ $period->start_date->format('d/m/Y') }} sampai {{ $period->end_date->format('d/m/Y') }}</p>
        </div>
        <dl class="period-facts">
            @if($school->plan()->price() === 0)
                <div><dt>Paket sekolah</dt><dd>{{ $school->plan()->label() }}</dd></div>
            @else
                <div><dt>Reguler</dt><dd>{{ \App\Enums\SchoolPlan::Regular->formattedPrice() }} <span class="font-normal text-muted">/siswa/bulan</span></dd></div>
                <div><dt>Pro</dt><dd>{{ \App\Enums\SchoolPlan::Pro->formattedPrice() }} <span class="font-normal text-muted">/siswa/bulan</span></dd></div>
            @endif
        </dl>
        </div>
        <section class="space-y-4" aria-labelledby="tagihan-periode">
            <h2 id="tagihan-periode" class="text-xl font-bold">Tagihan periode</h2>
            @if($school->plan()->price() === 0)
                <p>Periode uji coba gratis untuk maksimal 35 siswa. Untuk mengaktifkan kembali periode yang telah dinonaktifkan, hubungi admin Jurnalin untuk memilih paket berbayar.</p>
            @else
                <p class="text-muted">Sekolah membayar {{ $months }} bulan di awal untuk periode ini. Setelah periode berbayar selesai, sekolah mendapat tambahan satu bulan gratis dengan seluruh fitur tetap aktif penuh.</p>
                <details class="billing-help"><summary>Cara menghitung biaya dan tanggal aktif</summary>
                    <p>Total biaya adalah jumlah siswa dalam periode × tarif paket × jumlah bulan. Sisa bulan dibulatkan ke atas. Akses penuh dimulai setelah admin Jurnalin memverifikasi pembayaran, atau pada tanggal mulai PKL jika periode belum dimulai.</p>
                </details>
            @endif
            @forelse($groups as $group)
                @php
                    $pending = $payments->where('billing_group_id', $group->id)->firstWhere('status', 'pending');
                    $label = $group->statusLabel($today);
                    $total = $months * $group->members_count * $school->plan()->price();
                @endphp
                <article class="space-y-3 rounded-lg border border-border bg-paper p-5">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h3 class="text-lg font-bold">{{ $group->name }}</h3>
                        <p class="font-semibold">{{ $label }}{{ $pending ? ' · Menunggu verifikasi' : '' }}</p>
                    </div>
                    <p>{{ $group->members_count }} siswa
                        @if($group->starts_on && ! $group->stopped)
                            · Periode berbayar {{ \Carbon\Carbon::parse($group->starts_on)->format('d/m/Y') }} sampai {{ \Carbon\Carbon::parse($group->paid_until)->format('d/m/Y') }}.
                            Bulan tambahan gratis sampai {{ \Carbon\Carbon::parse($group->journal_until)->format('d/m/Y') }}.
                        @endif
                    </p>
                    @if($pending)
                        <p class="text-muted">Bukti sudah diterima. Admin Jurnalin akan memeriksanya. Hasilnya akan tampil di riwayat pembayaran.</p>
                    @endif
                    @if(! $pending && $total > 0 && ! in_array($label, ['Aktif','Terjadwal']))
                        <div class="rounded-md bg-stone p-4">
                            <p>{{ $group->members_count }} siswa × {{ $rupiah($school->plan()->price()) }} × {{ $months }} bulan</p>
                            <p class="text-lg font-bold tabular-nums">Total {{ $rupiah($total) }}</p>
                        </div>
                        <p class="whitespace-pre-line">{{ config('jurnalin.payment_instructions') ?: 'Gunakan rekening yang diberikan langsung oleh admin Jurnalin kepada sekolah.' }}</p>
                        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.payments.submit', $group) }}" class="grid justify-items-start gap-3">
                            @csrf
                            <input type="hidden" name="quote" value="{{ $total }}">
                            <label class="grid max-w-full gap-1">Bukti transfer untuk {{ $group->name }} (PDF, JPG, atau PNG, maksimal 4 MB)
                                <input class="min-h-12 max-w-full" type="file" name="proof" accept="application/pdf,image/jpeg,image/png" required>
                            </label>
                            <button class="primary-link min-h-12">Kirim bukti pembayaran</button>
                        </form>
                    @endif
                    @if($pending || in_array($label, ['Aktif','Terjadwal','Bulan tambahan gratis','Khusus jurnal']))
                        @include('admin.group-stop', ['target' => $group, 'stopUrl' => route('admin.payments.groups.stop', $group)])
                    @endif
                </article>
            @empty
                <p class="text-muted">Belum ada siswa dengan penempatan pada periode ini. Siapkan penempatan siswa terlebih dahulu.</p>
                <a class="document-link" href="{{ route('admin.placements') }}">Atur penempatan siswa</a>
            @endforelse
        </section>
        @if($payments->isNotEmpty())
            <section aria-labelledby="riwayat" class="space-y-3">
                <h2 id="riwayat" class="text-xl font-bold">Riwayat pembayaran</h2>
                <ul class="divide-y divide-border">@foreach($payments as $payment)
                    <li class="py-3">
                        <p class="font-semibold">{{ $payment->title() }} · {{ $rupiah($payment->amount()) }}</p>
                        <p>{{ $payment->created_at->format('d/m/Y') }} · {{ $payment->statusLabel() }}</p>
                        @if($payment->review_note)<p>{{ $payment->review_note }}</p>@endif
                        <a class="document-link" href="{{ route('admin.payments.proof', $payment) }}">Unduh bukti {{ $payment->title() }}</a>
                    </li>
                @endforeach</ul>
            </section>
        @endif
    @endif
    @include('admin.group-stop', ['target' => null, 'stopUrl' => route('admin.payments.stop-school')])
</div>
@endsection

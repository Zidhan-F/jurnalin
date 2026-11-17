@extends('layouts.app', ['title' => 'Grup dan pembayaran | Jurnalin'])

@section('content')
@php
    $rupiah = fn ($amount) => 'Rp'.number_format($amount, 0, ',', '.');
@endphp
<div class="mx-auto max-w-4xl space-y-8">
    <header class="school-page-heading">
        <h1>Grup dan pembayaran</h1>
        <p>Kelola akses Jurnalin per kelas atau untuk seluruh siswa dalam satu periode PKL.</p>
    </header>
    @include('admin.plan-box')
    @if($periods->isEmpty())
        <p>Buat periode PKL dan penempatan siswa untuk menyusun grup. <a href="{{ route('admin.periods') }}" class="document-link">Atur periode</a></p>
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
        <section class="space-y-4" aria-labelledby="grup">
            <h2 id="grup" class="text-xl font-bold">Grup siswa</h2>
            @if($school->plan()->price() === 0)
                <p>Periode uji coba gratis untuk maksimal 35 siswa. Untuk mengaktifkan kembali grup yang telah dinonaktifkan, hubungi admin Jurnalin untuk memilih paket berbayar.</p>
            @else
                <p class="text-muted">Sekolah membayar {{ $months }} bulan di awal untuk setiap grup. Setelah periode akses penuh selesai, siswa dan guru mendapat satu bulan gratis khusus jurnal.</p>
                <details class="billing-help"><summary>Cara menghitung biaya dan tanggal aktif</summary>
                    <p>Total biaya adalah jumlah siswa dalam grup × tarif paket × jumlah bulan. Sisa bulan dibulatkan ke atas. Akses penuh dimulai setelah admin Jurnalin memverifikasi pembayaran, atau pada tanggal mulai PKL jika periode belum dimulai.</p>
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
                            · Akses penuh {{ \Carbon\Carbon::parse($group->starts_on)->format('d/m/Y') }} sampai {{ \Carbon\Carbon::parse($group->paid_until)->format('d/m/Y') }}.
                            Batas jurnal {{ \Carbon\Carbon::parse($group->journal_until)->format('d/m/Y') }}.
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
                    @if($pending || in_array($label, ['Aktif','Terjadwal','Khusus jurnal']))
                        @include('admin.group-stop', ['target' => $group, 'stopUrl' => route('admin.payments.groups.stop', $group)])
                    @endif
                </article>
            @empty
                @if($classes->isEmpty())<p class="text-muted">Belum ada siswa dengan penempatan yang bisa dimasukkan ke grup. Siapkan penempatan siswa terlebih dahulu.</p>@endif
            @endforelse
            @if($classes->isNotEmpty())
                @php
                    $initialMembers = (int) $classes->sum('students');
                    $rate = $school->plan()->price();
                @endphp
                <details class="group-builder" @if($groups->isEmpty() || $errors->hasAny(['name','class_name','confirm_members'])) open @endif>
                    <summary class="cursor-pointer py-4 font-semibold">Buat grup siswa</summary>
                    <form method="POST" action="{{ route('admin.payments.groups.create', $period) }}" class="grid gap-4 pb-5" x-data="{ members: {{ $initialMembers }} }">
                        @csrf
                        <label class="grid gap-1">Nama grup
                            <input name="name" value="{{ old('name') }}" maxlength="100" required placeholder="Contoh: Bimbingan Bu Sari" class="min-h-12 rounded-md border border-field bg-paper px-3">
                        </label>
                        <label class="grid gap-1">Ambil siswa dari
                            <select name="source" @change="members = Number($el.selectedOptions[0].dataset.members)" class="min-h-12 rounded-md border border-field bg-paper px-3">
                                <option value="" data-members="{{ $classes->sum('students') }}">Semua siswa yang belum masuk grup ({{ $classes->sum('students') }} siswa)</option>
                                @if($teachers->isNotEmpty())
                                    <optgroup label="Siswa bimbingan satu guru (bisa dari beberapa kelas)">
                                        @foreach($teachers as $t)<option value="teacher:{{ $t['id'] }}" data-members="{{ $t['students'] }}" @selected(old('source') === 'teacher:'.$t['id'])>{{ $t['name'] }} ({{ $t['students'] }} siswa)</option>@endforeach
                                    </optgroup>
                                @endif
                                <optgroup label="Satu kelas">
                                    @foreach($classes as $class)<option value="class:{{ $class->class_name }}" data-members="{{ $class->students }}" @selected(old('source') === 'class:'.$class->class_name)>{{ $class->class_name }} ({{ $class->students }} siswa)</option>@endforeach
                                </optgroup>
                            </select>
                            <span class="text-sm text-muted">Pilih nama guru agar satu grup berisi semua siswa bimbingannya, walaupun berbeda kelas.</span>
                        </label>
                        <div class="group-estimate" x-cloak aria-live="polite" aria-atomic="true">
                            <span>Perkiraan biaya satu periode</span>
                            <strong x-text="'Rp' + (members * {{ $rate }} * {{ $months }}).toLocaleString('id-ID')">{{ $rupiah($initialMembers * $rate * $months) }}</strong>
                            <small><span x-text="members">{{ $initialMembers }}</span> siswa × {{ $rupiah($rate) }} × {{ $months }} bulan</small>
                        </div>
                        <label class="flex min-h-12 items-center gap-3"><input type="checkbox" name="confirm_members" required value="1">
                            Saya sudah memeriksa kelas dan jumlah siswa. Anggota ditetapkan saat grup dibuat.@if($rate > 0) Akses grup menunggu verifikasi pembayaran.@endif
                        </label>
                        <button class="primary-link min-h-12 justify-self-start">Buat grup</button>
                    </form>
                </details>
            @elseif($groups->isEmpty())
                <a class="document-link" href="{{ route('admin.placements') }}">Atur penempatan siswa</a>
            @endif
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

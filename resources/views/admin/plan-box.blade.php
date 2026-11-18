{{-- Paket sekolah: berlaku untuk seluruh sekolah dan ditagihkan per periode PKL. --}}
@php
    use App\Enums\SchoolPlan;
    $current = $school->plan();
    $rp = fn ($n) => 'Rp'.number_format($n, 0, ',', '.');
    $diffRate = SchoolPlan::Pro->price() - SchoolPlan::Regular->price();
    $studentMonths = $upgradeRows->sum(fn ($r) => $r['students'] * $r['months']);
    $upgradeTotal = $studentMonths * $diffRate;
@endphp
<section class="plan-box" aria-labelledby="judul-paket">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="judul-paket" class="text-xl font-bold">Paket sekolah</h2>
        <p class="font-semibold">Sekarang: {{ $current->label() }}</p>
    </div>

    <div class="plan-options">
        @foreach([SchoolPlan::Regular, SchoolPlan::Pro] as $option)
            <div class="plan-option" @if($option === $current) data-current @endif>
                <span>
                    <strong>{{ $option->label() }} · {{ $option->formattedPrice() }}<span class="font-normal text-muted">/siswa/bulan</span></strong>
                    <small>
                        @if($option === SchoolPlan::Pro)
                            Semua fitur Reguler, ditambah {{ implode(', ', array_map('lcfirst', \App\Support\Features::PRO)) }}, dan rekap Hubin.
                        @else
                            Absen, jurnal harian, validasi guru, nilai akhir, dan PDF ber-kop sekolah.
                        @endif
                    </small>
                    @if($option === $current)<small class="font-semibold text-ink">Paket aktif</small>@endif
                </span>
            </div>
        @endforeach
    </div>

    @if($current === SchoolPlan::Free)
        <p class="text-muted">Sekolah masih dalam uji coba gratis. Hubungi <a class="document-link" href="{{ route('contact') }}">Admin Jurnalin</a> untuk memilih paket berbayar.</p>
    @elseif($pendingUpgrade)
        <p class="callout callout-warn">Bukti transfer selisih {{ $rp($pendingUpgrade->amount()) }} sedang diperiksa Admin Jurnalin. Paket berubah menjadi Pro setelah diverifikasi.</p>
    @elseif($current === SchoolPlan::Pro && $school->scheduled_plan)
        <div class="stack-sm">
            <p>Turun ke Reguler dijadwalkan mulai <strong>{{ $school->scheduled_plan_on->translatedFormat('j F Y') }}</strong>. Sampai tanggal itu fitur Pro tetap aktif karena sudah dibayar.</p>
            <form method="POST" action="{{ route('admin.plan.upgrade') }}">@csrf<button class="document-link min-h-12">Batalkan, tetap di Pro</button></form>
        </div>
    @elseif($current === SchoolPlan::Pro)
        <details class="plan-change">
            <summary class="document-link">Turun ke Reguler</summary>
            <form method="POST" action="{{ route('admin.plan.downgrade') }}" class="grid gap-3 justify-items-start pt-3">
                @csrf
                <p class="text-muted">Bila ada periode yang sudah dibayar dengan tarif Pro, fitur Pro tetap aktif sampai periode itu selesai, lalu paket otomatis menjadi Reguler. Tidak ada pengembalian dana. Periode berikutnya dibayar dengan tarif Reguler.</p>
                <label class="flex min-h-12 items-center gap-3"><input type="checkbox" name="confirm_downgrade" value="1" required> Saya mengerti fitur Pro akan berhenti setelah tanggal itu.</label>
                <button class="primary-link min-h-12">Turun ke Reguler</button>
            </form>
        </details>
    @elseif($upgradeRows->isEmpty())
        <form method="POST" action="{{ route('admin.plan.upgrade') }}" class="grid gap-2 justify-items-start">
            @csrf
            <p class="text-muted">Belum ada periode yang sedang berjalan, jadi paket langsung berubah. Periode berikutnya dibayar dengan tarif Pro.</p>
            <button class="primary-link min-h-12">Naik ke Pro sekarang</button>
        </form>
    @else
        <details class="plan-change" @if($errors->hasAny(['quote', 'proof'])) open @endif>
            <summary class="document-link">Naik ke Pro</summary>
            <div class="grid gap-3 pt-3">
                <p class="text-muted">Periode berikut sudah dibayar dengan tarif Reguler. Untuk sisa waktunya, sekolah membayar selisih {{ $rp($diffRate) }} per siswa per bulan. Paket berubah menjadi Pro setelah Admin Jurnalin memverifikasi bukti transfer.</p>
                <ul class="plan-diff">
                    @foreach($upgradeRows as $row)
                        <li><span>{{ $row['group']->name }}</span><span class="tabular-nums">{{ $row['students'] }} siswa × {{ $row['months'] }} bulan × {{ $rp($diffRate) }}</span></li>
                    @endforeach
                    <li class="font-bold"><span>Total selisih</span><span class="tabular-nums">{{ $rp($upgradeTotal) }}</span></li>
                </ul>
                <p class="whitespace-pre-line">{{ config('jurnalin.payment_instructions') ?: 'Gunakan rekening yang diberikan langsung oleh admin Jurnalin kepada sekolah.' }}</p>
                <form method="POST" enctype="multipart/form-data" action="{{ route('admin.plan.upgrade') }}" class="grid justify-items-start gap-3">
                    @csrf
                    <input type="hidden" name="quote" value="{{ $upgradeTotal }}">
                    <label class="grid max-w-full gap-1">Bukti transfer selisih (PDF, JPG, atau PNG, maksimal 4 MB)
                        <input class="min-h-12 max-w-full" type="file" name="proof" accept="application/pdf,image/jpeg,image/png" required>
                    </label>
                    <button class="primary-link min-h-12">Kirim bukti dan naik ke Pro</button>
                </form>
            </div>
        </details>
    @endif
</section>

{{-- Verifikasi identitas proporsional (M03): kode OTP ke email kontak tervalidasi sebelum mengesahkan. --}}
<section class="mentor-card stack-sm" aria-labelledby="otp-title">
    <h2 id="otp-title" class="section-title">Verifikasi identitas</h2>
    @if($otpFresh)
        <p class="callout callout-ok">Identitas terverifikasi. Anda dapat mengesahkan penilaian.</p>
    @else
        <p class="hint">Membaca dan memberi catatan tidak memerlukan kode. Untuk mengesahkan nilai atau menilai sebagai penguji, minta kode yang dikirim ke email Anda.</p>
        <form method="POST" action="{{ route('portal.otp', request()->attributes->get('portal_token')) }}">@csrf<button class="btn btn-outline btn-block">Kirim kode verifikasi</button></form>
        <form method="POST" action="{{ route('portal.otp', request()->attributes->get('portal_token')) }}" class="flex gap-2 items-end">
            @csrf
            <div class="flex-1"><label class="label" for="otp">Kode 6 angka</label><input id="otp" name="otp" inputmode="numeric" autocomplete="one-time-code" maxlength="6" class="field tabular-nums" required></div>
            <button class="btn btn-navy">Verifikasi</button>
        </form>
    @endif
</section>

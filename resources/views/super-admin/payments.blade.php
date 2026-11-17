@extends('layouts.app', ['title' => 'Verifikasi pembayaran | Jurnalin'])
@section('content')
<div class="space-y-5">
    <h1 class="text-2xl font-bold">Verifikasi pembayaran sekolah</h1>
    @forelse($payments as $payment)
        <section class="border border-border rounded-xl p-5 space-y-2">
            <h2 class="font-bold text-lg">{{ $payment->school->name }} · {{ $payment->period->name }}</h2>
            <p>{{ $payment->title() }} · {{ $payment->plan->label() }} · {{ $payment->statusLabel() }}</p>
            <p>{{ $payment->student_count }} {{ $payment->kind === 'upgrade' ? 'siswa-bulan' : 'siswa' }} × Rp{{ number_format($payment->unit_price, 0, ',', '.') }} × {{ $payment->months }} bulan = <strong>Rp{{ number_format($payment->amount(), 0, ',', '.') }}</strong></p>
            <a href="{{ route('super-admin.payments.proof', $payment) }}" class="document-link">Unduh bukti transfer {{ $payment->school->name }}</a>
            @if($payment->status === 'pending')
                <p class="text-sm text-muted">Cocokkan nominal, tanggal, dan referensi transfer dengan dana masuk sebelum memverifikasi.</p>
                <form method="POST" action="{{ route('super-admin.payments.review', $payment) }}" class="space-y-2">
                    @csrf
                    <input type="hidden" name="version" value="{{ hash('sha256', $payment->proof_path) }}">
                    <label class="grid gap-1">Catatan untuk sekolah (wajib jika dikembalikan)
                        <textarea name="note" rows="2" maxlength="500" class="border rounded-md bg-paper px-3"></textarea>
                    </label>
                    <div class="flex flex-wrap gap-4">
                        <button name="decision" value="verified" class="primary-link">Verifikasi pembayaran</button>
                        <button name="decision" value="returned" class="document-link">Kembalikan untuk diperbaiki</button>
                    </div>
                </form>
            @elseif($payment->review_note)
                <p>{{ $payment->review_note }}</p>
            @endif
        </section>
    @empty
        <p>Belum ada bukti pembayaran yang dikirim sekolah.</p>
    @endforelse
    {{ $payments->links() }}
</div>
@endsection

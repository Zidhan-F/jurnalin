@extends('layouts.app', ['title' => 'Laporan Siswa - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Laporan siswa</h1>
        <p>Laporan dari tombol Lapor ke Guru. Laporan sensitif hanya tampil untuk admin sekolah. Catat setiap tindak lanjut agar penanganan dapat ditelusuri.</p>
    </div>
</div>

@forelse($tickets as $ticket)
    @php
        $urgent = $ticket->urgency !== 'normal' && $ticket->status !== 'resolved';
        $st = ['open' => ['Baru', 'badge-warn'], 'in_progress' => ['Ditangani', 'badge-navy'], 'resolved' => ['Selesai', 'badge-ok']][$ticket->status];
    @endphp
    <article class="card mb-3 {{ $urgent ? 'card-danger' : ($ticket->status === 'open' ? 'card-attention' : '') }}">
        <div class="flex flex-wrap justify-between gap-2">
            <div>
                <h2 class="font-bold">{{ $ticket->student->user->name }} <span class="hint font-normal">· {{ $ticket->placement?->company?->name ?? 'tanpa penempatan aktif' }}</span></h2>
                <p class="hint">{{ $categories[$ticket->category]['label'] ?? $ticket->category }} · {{ ['normal' => 'Biasa', 'urgent' => 'Mendesak', 'emergency' => 'Darurat'][$ticket->urgency] }} · <span class="tabular-nums">{{ $ticket->created_at->format('d/m/Y H:i') }}</span></p>
            </div>
            <span class="badge {{ $st[1] }}">{{ $st[0] }}</span>
        </div>
        <p class="mt-3 whitespace-pre-line">{{ $ticket->body }}</p>
        <details class="mt-3">
            <summary class="link">Riwayat penanganan ({{ count($ticket->activity ?? []) }})</summary>
            <ol class="timeline mt-3">
                @foreach($ticket->activity ?? [] as $a)
                    <li><time>{{ \Carbon\Carbon::parse($a['at'])->format('d/m H:i') }}</time> <strong>{{ $a['by'] }}</strong>: {{ $a['note'] }}</li>
                @endforeach
            </ol>
        </details>
        <form method="POST" action="{{ route('tickets.update', $ticket) }}" class="grid sm:grid-cols-4 gap-2 mt-3 items-end">
            @csrf
            <div class="sm:col-span-2">
                <label class="label" for="note-{{ $ticket->id }}">Catatan tindak lanjut</label>
                <input id="note-{{ $ticket->id }}" name="note" required class="field" placeholder="Contoh: sudah menelepon pembimbing industri">
            </div>
            <div>
                <label class="label" for="status-{{ $ticket->id }}">Status</label>
                <select id="status-{{ $ticket->id }}" name="status" class="field">
                    <option value="in_progress" @selected($ticket->status === 'in_progress')>Ditangani</option>
                    <option value="resolved">Selesai</option>
                    <option value="open" @selected($ticket->status === 'open')>Baru</option>
                </select>
            </div>
            <button class="btn btn-navy">Simpan</button>
        </form>
    </article>
@empty
    <div class="empty">Tidak ada laporan siswa. Laporan baru akan muncul di sini dan sebagai notifikasi.</div>
@endforelse
@endsection

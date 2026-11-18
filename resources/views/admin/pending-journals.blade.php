@extends('layouts.app', ['title' => 'Jurnal Tertunda per Guru - Jurnalin'])

@section('content')
<div class="space-y-6">
    <div>
        <a href="{{ route('admin.index') }}" class="link text-sm">Kembali ke Ringkasan</a>
        <h1 class="text-2xl font-bold text-ink mt-2">Jurnal tertunda per guru</h1>
        <p class="hint mt-1 max-w-2xl">Jurnal yang sudah dikirim siswa tetapi belum disetujui atau dikembalikan oleh guru pembimbing. Guru dengan jurnal paling lama menunggu tampil paling atas. Ingatkan guru lewat WhatsApp bila perlu.</p>
    </div>

    <nav class="flex flex-wrap gap-2" aria-label="Saring jurnal">
        <a href="{{ route('admin.pending-journals') }}" @class(['btn btn-sm', 'btn-navy' => ! $onlyStale, 'btn-outline' => $onlyStale]) @if(! $onlyStale) aria-current="page" @endif>Semua jurnal menunggu</a>
        <a href="{{ route('admin.pending-journals', ['filter' => 'lama']) }}" @class(['btn btn-sm', 'btn-navy' => $onlyStale, 'btn-outline' => ! $onlyStale]) @if($onlyStale) aria-current="page" @endif>Lebih dari {{ $staleDays }} hari</a>
    </nav>

    @if($groups->isEmpty())
        <div class="callout {{ $onlyStale ? '' : 'callout-ok' }}" role="status">
            @if($onlyStale)
                Tidak ada jurnal yang menunggu lebih dari {{ $staleDays }} hari. <a href="{{ route('admin.pending-journals') }}" class="link">Lihat semua jurnal menunggu</a>
            @else
                Semua jurnal yang dikirim siswa sudah diperiksa guru pembimbing. Tidak ada yang perlu diingatkan.
            @endif
        </div>
    @else
        <p class="text-sm text-ink tabular-nums" role="status"><strong>{{ $total }}</strong> jurnal menunggu pada <strong>{{ $groups->count() }}</strong> guru pembimbing.</p>

        @foreach($groups as $group)
            @php
                $teacherUser = $group['teacher']->user;
                $waMessage = 'Halo '.$teacherUser->name.', ada '.$group['count'].' jurnal PKL siswa bimbingan yang menunggu pemeriksaan di Jurnalin. Jurnal terlama bertanggal '.$group['oldest_date']->translatedFormat('d M Y').'. Mohon dapat diperiksa. Terima kasih.';
                $waLink = $teacherUser->whatsappUrl($waMessage);
            @endphp
            <section @class(['card space-y-3', 'card-danger' => $group['stale'] > 0]) aria-labelledby="guru-{{ $group['teacher']->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 id="guru-{{ $group['teacher']->id }}" class="section-title">{{ $teacherUser->name }}</h2>
                        <p class="text-sm text-ink tabular-nums mt-1">
                            {{ $group['count'] }} jurnal menunggu
                            @if($group['stale'] > 0)
                                · <span class="font-semibold text-danger-strong">{{ $group['stale'] }} lebih dari {{ $staleDays }} hari</span>
                            @endif
                        </p>
                        <p class="hint tabular-nums">Terlama: {{ $group['oldest_date']->translatedFormat('d M Y') }} ({{ $group['oldest_days'] === 0 ? 'hari ini' : $group['oldest_days'].' hari lalu' }})</p>
                    </div>
                    @if($waLink)
                        <a href="{{ $waLink }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-sm">Ingatkan lewat WhatsApp</a>
                    @else
                        <div class="text-sm text-right">
                            <p class="hint">No. WhatsApp guru belum diisi.</p>
                            <a href="{{ route('admin.users.edit', $teacherUser) }}" class="link">Isi nomor WhatsApp</a>
                        </div>
                    @endif
                </div>

                <details @if($loop->first) open @endif>
                    <summary class="cursor-pointer py-2 font-semibold text-navy">Lihat {{ $group['count'] }} jurnal <span class="sr-only">milik siswa bimbingan {{ $teacherUser->name }}</span></summary>
                    <div class="overflow-x-auto rounded-xl border border-border bg-paper mt-2" role="region" aria-label="Jurnal menunggu untuk {{ $teacherUser->name }}" tabindex="0">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-stone text-xs uppercase text-muted border-b border-border">
                                <tr>
                                    <th class="px-4 py-3">Tanggal jurnal</th>
                                    <th class="px-4 py-3">Siswa</th>
                                    <th class="px-4 py-3">Kegiatan</th>
                                    <th class="px-4 py-3">Menunggu</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach($group['journals'] as $journal)
                                    @php
                                        $days = (int) $journal->journal_date->copy()->startOfDay()->diffInDays($today);
                                        $isStale = $days > $staleDays;
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-3 tabular-nums">{{ $journal->journal_date->translatedFormat('d M Y') }}</td>
                                        <td class="px-4 py-3">
                                            <a href="{{ route('monitor.participant', $journal->placement) }}" class="link">{{ $journal->student->user->name }}</a>
                                            <div class="text-xs text-muted">{{ $journal->placement->company->name }}</div>
                                        </td>
                                        <td class="px-4 py-3">{{ $journal->activity_title }}</td>
                                        <td class="px-4 py-3 tabular-nums">
                                            @if($isStale)
                                                <span class="badge badge-danger">{{ $days }} hari</span>
                                            @else
                                                <span class="badge">{{ $days === 0 ? 'Hari ini' : $days.' hari' }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            </section>
        @endforeach
    @endif
</div>
@endsection

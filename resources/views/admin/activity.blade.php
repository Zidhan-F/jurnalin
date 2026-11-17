@extends('layouts.app', ['title' => 'Log Aktivitas - Jurnalin'])

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-ink">Log aktivitas</h1>
        <p class="text-sm text-muted mt-1">Siapa menyetujui jurnal, mengubah nilai, mereset sandi, atau mengunduh data, dan kapan. Menampilkan 200 catatan terbaru. Catatan tidak bisa diubah atau dihapus.</p>
    </div>

    @if($logs->isEmpty())
        <div class="rounded-xl border border-border bg-stone p-6">
            <h2 class="font-semibold text-ink">Belum ada aktivitas tercatat</h2>
            <p class="text-sm text-muted mt-1">Catatan muncul setelah guru menyetujui jurnal, nilai disimpan, atau pembimbing DUDI menandatangani jurnal.</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-border">
            <table class="w-full text-sm text-left">
                <caption class="sr-only">Log aktivitas sekolah</caption>
                <thead class="bg-stone text-xs text-muted border-b border-border">
                    <tr>
                        <th scope="col" class="px-4 py-3">Waktu</th>
                        <th scope="col" class="px-4 py-3">Pelaku</th>
                        <th scope="col" class="px-4 py-3">Aktivitas</th>
                        <th scope="col" class="px-4 py-3">Rincian</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($logs as $log)
                        <tr>
                            <td class="px-4 py-3 tabular-nums text-xs whitespace-nowrap">{{ $log->created_at->timezone(auth()->user()->school->timezone)->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $log->user?->name ?? $log->actor_label ?? 'Sistem' }}</div>
                                <div class="text-xs text-muted">{{ $log->user?->role->label() ?? ($log->actor_label ? 'Pembimbing DUDI (tanpa akun)' : '') }}</div>
                            </td>
                            <td class="px-4 py-3">{{ $log->label() }}</td>
                            <td class="px-4 py-3 text-xs text-muted">
                                @foreach(($log->meta ?? []) as $key => $value)
                                    <span class="inline-block mr-2">{{ str_replace('_', ' ', $key) }}: {{ is_bool($value) ? ($value ? 'ya' : 'tidak') : (is_array($value) ? implode(', ', $value) : $value) }}</span>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection

@extends($layout, ['title' => 'Notifikasi - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Notifikasi</h1>
        <p>Pemberitahuan pengajuan, revisi, persetujuan, dan izin. Isi singkat, tanpa nilai atau data sensitif.</p>
    </div>
    @if($notifications->whereNull('read_at')->isNotEmpty())
        <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn btn-outline btn-sm">Tandai semua dibaca</button></form>
    @endif
</div>

@if($notifications->isEmpty())
    <div class="empty">Belum ada notifikasi. Pemberitahuan baru muncul di sini saat ada jurnal, izin, atau tindak lanjut yang perlu Anda lihat.</div>
@else
    <ul class="stack-sm">
        @foreach($notifications as $n)
            <li class="card {{ $n->read_at ? '' : 'card-attention' }}">
                <a href="{{ route('notifications.open', $n) }}" class="flex flex-wrap items-start justify-between gap-2">
                    <span class="min-w-0">
                        <span class="block font-semibold text-ink">{{ $n->title }}</span>
                        @if($n->body)<span class="block hint mt-1">{{ \Illuminate\Support\Str::limit($n->body, 160) }}</span>@endif
                    </span>
                    <span class="flex items-center gap-2">
                        @unless($n->read_at)<span class="badge badge-warn">Baru</span>@endunless
                        <time class="hint tabular-nums" datetime="{{ $n->created_at->toIso8601String() }}">{{ $n->created_at->timezone(auth()->user()->school->timezone)->format('d/m H:i') }}</time>
                    </span>
                </a>
            </li>
        @endforeach
    </ul>
@endif
@endsection

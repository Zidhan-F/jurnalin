@extends('layouts.student-pwa', ['title' => 'Lapor ke Guru - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Lapor ke Guru</h1>
        <p>Laporkan kendala, kecelakaan kerja, atau perlakuan tidak pantas. Laporan sensitif langsung ke admin sekolah dan tidak diteruskan ke pihak yang dilaporkan.</p>
    </div>
</div>

<section class="callout callout-danger stack-sm" aria-labelledby="darurat">
    <h2 id="darurat" class="font-bold">Dalam bahaya sekarang?</h2>
    <p>Pergi ke tempat aman lalu hubungi:</p>
    <ul class="grid gap-2">
        @foreach($emergency as $line)
            <li><a class="btn btn-danger btn-block justify-between" href="tel:{{ $line['number'] }}"><span>{{ $line['label'] }}</span><span class="tabular-nums">{{ $line['number'] }}</span></a></li>
        @endforeach
    </ul>
</section>

<form method="POST" action="{{ route('student.tickets.store') }}" class="card-muted stack-sm" x-data="{ category: '{{ old('category', 'pekerjaan') }}', sensitive: @js(collect($categories)->filter(fn ($c) => $c['sensitive'])->keys()->values()) }">
    @csrf
    <div>
        <label for="category" class="label">Kategori</label>
        <select id="category" name="category" class="field" x-model="category">
            @foreach($categories as $key => $c)
                <option value="{{ $key }}" @selected(old('category') === $key)>{{ $c['label'] }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="urgency" class="label">Tingkat urgensi</label>
        <select id="urgency" name="urgency" class="field">
            <option value="normal">Biasa</option>
            <option value="urgent">Mendesak (butuh respons hari ini)</option>
            <option value="emergency">Darurat</option>
        </select>
    </div>
    <fieldset x-show="! sensitive.includes(category)">
        <legend class="label">Kirim ke</legend>
        <label class="flex items-center gap-2"><input type="radio" name="recipient" value="teacher" checked :disabled="sensitive.includes(category)"> Guru pembimbing</label>
        <label class="flex items-center gap-2"><input type="radio" name="recipient" value="school_admin" :disabled="sensitive.includes(category)"> Admin sekolah (koordinator PKL)</label>
    </fieldset>
    <input type="hidden" name="recipient" value="school_admin" :disabled="! sensitive.includes(category)">
    <p class="callout callout-warn" x-show="sensitive.includes(category)" x-cloak>Kategori ini rahasia: laporan hanya dibaca admin sekolah.</p>
    <div>
        <label for="body" class="label">Ceritakan yang terjadi</label>
        <textarea id="body" name="body" required minlength="10" class="field" rows="5" placeholder="Kapan, di mana, dan apa yang terjadi">{{ old('body') }}</textarea>
    </div>
    <button class="btn btn-navy btn-block">Kirim laporan</button>
</form>

<section class="stack-sm">
    <h2 class="section-title">Laporan saya</h2>
    @forelse($tickets as $ticket)
        @php $st = ['open' => ['Terkirim', 'badge-warn'], 'in_progress' => ['Ditangani', 'badge-navy'], 'resolved' => ['Selesai', 'badge-ok']][$ticket->status]; @endphp
        <article class="card">
            <div class="flex justify-between gap-2"><span class="font-semibold">{{ $categories[$ticket->category]['label'] ?? $ticket->category }}</span><span class="badge {{ $st[1] }}">{{ $st[0] }}</span></div>
            <p class="hint tabular-nums">{{ $ticket->created_at->format('d/m/Y H:i') }} · ke {{ $ticket->recipient === 'teacher' ? 'guru pembimbing' : 'admin sekolah' }}</p>
            @if($ticket->resolution)<p class="callout callout-ok mt-2">{{ $ticket->resolution }}</p>@endif
        </article>
    @empty
        <div class="empty">Belum ada laporan. Gunakan formulir di atas kapan pun Anda butuh bantuan.</div>
    @endforelse
</section>
@endsection

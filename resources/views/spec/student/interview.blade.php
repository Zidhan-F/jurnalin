@extends('layouts.student-pwa', ['title' => 'Latihan Wawancara - Jurnalin'])

@section('content')
<div class="page-head">
    <div>
        <h1>Latihan wawancara</h1>
        <p>Latih cara menceritakan pengalaman PKL. Pertanyaan disusun dari proyek dan jurnal Anda. Umpan balik hanya tentang kejelasan cerita, bukan nilai atau prediksi diterima kerja.</p>
    </div>
</div>
@foreach($questions as $q)
    <section class="card stack-sm" x-data="{ answer: '', feedback: '', loading: false, async ask() {
        this.loading = true; this.feedback = '';
        try {
            const r = await fetch(@js(route('student.interview.feedback')), { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || @js(csrf_token()) }, body: JSON.stringify({ question: @js($q), answer: this.answer }) });
            const d = await r.json();
            this.feedback = r.ok ? d.feedback : (d.message || 'Jawaban minimal 10 karakter.');
        } catch (e) { this.feedback = 'Gagal memuat umpan balik. Periksa koneksi lalu coba lagi.'; }
        finally { this.loading = false; }
    } }">
        <h2 class="font-semibold">{{ $q }}</h2>
        <label class="sr-only" for="ans-{{ $loop->index }}">Jawaban Anda</label>
        <textarea id="ans-{{ $loop->index }}" class="field" x-model="answer" placeholder="Tulis jawaban Anda"></textarea>
        <button type="button" class="btn btn-outline btn-sm" @click="ask()" :disabled="loading" x-text="loading ? 'Menyusun umpan balik...' : 'Minta umpan balik'"></button>
        <p role="status" class="callout whitespace-pre-line" x-show="feedback" x-text="feedback"></p>
    </section>
@endforeach
@unless($aiEnabled)<p class="hint">Umpan balik memakai panduan dasar. Sekolah dapat mengaktifkan add-on AI untuk umpan balik yang lebih rinci.</p>@endunless
@endsection

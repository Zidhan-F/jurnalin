@extends('layouts.app', ['title' => 'Penilaian Akhir & Cetak PDF - Jurnalin'])

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-ink">Nilai akhir PKL</h1>
        <p class="text-sm text-muted mt-1">
            Salin nilai dari formulir kertas yang diisi pembimbing industri, unggah foto form asli sebagai bukti, lalu finalisasi untuk menghasilkan PDF nilai siap cetak.
        </p>
    </div>

    @if($placements->isEmpty())
        <div class="rounded-xl border border-border bg-stone p-8 text-center text-sm text-muted">
            Belum ada siswa bimbingan yang dapat dinilai.
        </div>
    @else
        <div class="space-y-6">
            @foreach($placements as $placement)
                @php
                    $rubric = $placement->pklPeriod->rubric();
                    $assessment = $placement->assessment;
                    $indScores = $assessment?->industry_scores['scores'] ?? [];
                    $schScores = $assessment?->school_scores['scores'] ?? [];
                @endphp
                <div class="rounded-xl border border-border bg-paper p-6 space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-border pb-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-lg font-bold text-ink">{{ $placement->student->user->name }}</h2>
                                @if($assessment?->finalized_at)
                                    <span class="px-2.5 py-0.5 rounded-md bg-emerald-check/15 text-emerald-strong text-xs font-semibold tabular-nums">
                                        Final: {{ number_format($assessment->final_score, 2) }} ({{ $assessment->predicate }})
                                    </span>
                                @elseif($assessment)
                                    <span class="px-2.5 py-0.5 rounded-md bg-amber-warn/15 text-amber-strong text-xs font-semibold tabular-nums">
                                        Draf: {{ number_format($assessment->final_score, 2) }}
                                    </span>
                                @endif
                            </div>
                            <div class="text-xs text-muted mt-1">
                                {{ $placement->student->class_name }} | {{ $placement->company->name }} ({{ $placement->department_divisi ?: 'Umum' }}) | Periode: {{ $placement->pklPeriod->name }}
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <a href="/reports/placements/{{ $placement->id }}/logbook.pdf" class="btn btn-navy btn-sm">
                                Unduh PDF Buku Jurnal &amp; Nilai
                            </a>
                            <a href="{{ route('reports.industry-form', $placement) }}" class="btn btn-outline btn-sm">Cetak formulir nilai industri</a>
                            <a href="{{ route('monitor.participant', $placement) }}" class="btn btn-outline btn-sm">Pantau siswa</a>
                        </div>
                    </div>

                    @php
                        $linkActive = $placement->mentor_link_token && $placement->mentor_link_expires_at?->isFuture();
                        $mentorUrl = $linkActive ? route('mentor.show', $placement->mentor_link_token) : null;
                        $waText = $mentorUrl ? 'Yth. '.($placement->industry_mentor_name ?: 'Bapak/Ibu pembimbing').', mohon tanda tangan jurnal PKL '.$placement->student->user->name.' lewat tautan berikut: '.$mentorUrl : '';
                        $waPhone = preg_replace('/\D+/', '', (string) $placement->industry_mentor_phone);
                        $waPhone = str_starts_with($waPhone, '0') ? '62'.substr($waPhone, 1) : $waPhone;
                        $lastSign = $placement->mentorSignoffs->first();
                    @endphp
                    <div class="mentor-link-box">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <h3 class="font-semibold text-ink text-sm">Tanda tangan pembimbing DUDI</h3>
                            <span class="text-xs text-muted">{{ $placement->mentorSignoffs->count() }} rentang ditandatangani @if($lastSign) (terakhir {{ $lastSign->signed_at->timezone($school->timezone)->format('d/m/Y') }} oleh {{ $lastSign->signer_name }})@endif</span>
                        </div>
                        @if($mentorUrl)
                            <label for="mentor-url-{{ $placement->id }}" class="text-xs text-muted">Tautan khusus pembimbing (berlaku sampai {{ $placement->mentor_link_expires_at->timezone($school->timezone)->format('d/m/Y') }})</label>
                            <input id="mentor-url-{{ $placement->id }}" type="text" readonly value="{{ $mentorUrl }}" onfocus="this.select()">
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                                <a class="document-link text-sm" target="_blank" rel="noopener" href="https://wa.me/{{ $waPhone }}?text={{ rawurlencode($waText) }}">Kirim lewat WhatsApp{{ $waPhone ? '' : ' (pilih kontak)' }}</a>
                                <button type="button" class="document-link text-sm" onclick="navigator.clipboard.writeText(document.getElementById('mentor-url-{{ $placement->id }}').value).then(() => { this.textContent = 'Tautan disalin'; })">Salin tautan</button>
                                <form method="POST" action="{{ route('mentor-link.store', $placement) }}" onsubmit="return confirm('Buat tautan baru? Tautan lama langsung tidak berlaku.')">@csrf<button type="submit" class="document-link text-sm">Buat tautan baru</button></form>
                                <form method="POST" action="{{ route('mentor-link.destroy', $placement) }}" onsubmit="return confirm('Cabut tautan? Pembimbing tidak bisa membukanya lagi.')">@csrf<button type="submit" class="document-link text-sm text-danger-strong">Cabut tautan</button></form>
                            </div>
                        @else
                            <p class="text-sm text-muted">Buat tautan, lalu kirim ke {{ $placement->industry_mentor_name ?: 'pembimbing di tempat PKL' }}. Pembimbing tidak perlu akun; cukup buka tautan di HP untuk membaca jurnal dan menandatanganinya.</p>
                            <form method="POST" action="{{ route('mentor-link.store', $placement) }}">@csrf<button type="submit" class="px-3.5 py-2 rounded-lg border border-navy text-navy hover:bg-stone text-sm font-semibold min-h-11">Buat tautan tanda tangan</button></form>
                        @endif
                    </div>

                    @php $mentorEval = \App\Models\MentorEvaluation::query()->with('contact')->where('placement_id', $placement->id)->where('status', 'final')->latest('finalized_at')->first(); @endphp
                    @if($mentorEval && ! $assessment?->finalized_at)
                        <div class="callout callout-ok stack-sm" x-data>
                            <p><strong>Evaluasi pembimbing lapangan sudah disahkan</strong> oleh {{ $mentorEval->contact->name }} ({{ $mentorEval->finalized_at->format('d/m/Y') }}), kode integritas {{ substr($mentorEval->content_hash, 0, 12) }}.</p>
                            <p class="text-sm">Skor: @foreach($mentorEval->scores as $code => $score){{ $code }} = {{ $score === 'NA' ? 'tidak dapat dinilai' : $score }}@if(! $loop->last), @endif @endforeach</p>
                            <button type="button" class="btn btn-outline btn-sm" @click="@foreach($mentorEval->scores as $code => $score) @if($score !== 'NA') document.getElementById('ind-{{ $placement->id }}-{{ $code }}') && (document.getElementById('ind-{{ $placement->id }}-{{ $code }}').value = {{ (int) $score }}); @endif @endforeach document.getElementById('src-{{ $placement->id }}').value = 'mentor_access'">Pakai skor ini sebagai nilai industri</button>
                        </div>
                    @endif

                    @if($assessment?->achievement_description)
                        <p class="callout"><strong>Deskripsi capaian:</strong> {{ $assessment->achievement_description }}</p>
                    @endif

                    <form method="POST" action="/teacher/assessments/{{ $placement->id }}" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" id="src-{{ $placement->id }}" name="industry_source" value="{{ old('industry_source', $assessment?->industry_source ?? 'manual_form') }}">
                        @csrf
                        <div class="grid lg:grid-cols-2 gap-6">
                            <div class="rounded-xl bg-stone border border-border p-4 space-y-3">
                                <div class="text-sm font-bold text-navy">
                                    A. Nilai Dunia Kerja / DUDI (Bobot {{ $rubric->weights['industry_percentage'] }}%)
                                </div>
                                @foreach($rubric->industryAspects as $aspect)
                                    <div>
                                        <label for="industry-{{ $placement->id }}-{{ $loop->index }}" class="block text-xs font-medium text-ink mb-1">
                                            {{ $aspect['label'] }} (Bobot {{ $aspect['weight'] }}%)
                                        </label>
                                        <input id="industry-{{ $placement->id }}-{{ $loop->index }}" type="number" step="0.01" min="0" max="100" required
                                            id="ind-{{ $placement->id }}-{{ $aspect['code'] }}" name="industry_scores[{{ $aspect['code'] }}]"
                                            value="{{ old("industry_scores.{$aspect['code']}", $indScores[$aspect['code']] ?? '') }}"
                                            class="w-full rounded-md border border-field bg-white px-3 py-2 text-sm tabular-nums">
                                    </div>
                                @endforeach
                                <div>
                                    <label for="signed_by_name-{{ $placement->id }}" class="block text-xs font-medium text-ink mb-1">Penandatangan Form DUDI</label>
                                    <input type="text" id="signed_by_name-{{ $placement->id }}" name="signed_by_name"
                                        value="{{ old('signed_by_name', $assessment?->industry_scores['signed_by_name'] ?? $placement->industry_mentor_name) }}"
                                        placeholder="Nama Pembimbing Industri / HRD"
                                        class="w-full rounded-md border border-field bg-white px-3 py-2 text-sm">
                                </div>
                                <div>
                                    <label for="industry_notes-{{ $placement->id }}" class="block text-xs font-medium text-ink mb-1">Catatan dari DUDI</label>
                                    <textarea id="industry_notes-{{ $placement->id }}" name="industry_notes" rows="2" class="w-full rounded-md border border-field bg-white px-3 py-2 text-sm">{{ old('industry_notes', $assessment?->industry_notes) }}</textarea>
                                </div>
                            </div>

                            <div class="rounded-xl bg-stone border border-border p-4 space-y-3">
                                <div class="text-sm font-bold text-navy">
                                    B. Nilai Pembimbing Sekolah (Bobot {{ $rubric->weights['school_percentage'] }}%) + Bukti Form
                                </div>
                                @foreach($rubric->schoolAspects as $aspect)
                                    <div>
                                        <label for="school-{{ $placement->id }}-{{ $loop->index }}" class="block text-xs font-medium text-ink mb-1">
                                            {{ $aspect['label'] }} (Bobot {{ $aspect['weight'] }}%)
                                        </label>
                                        <input id="school-{{ $placement->id }}-{{ $loop->index }}" type="number" step="0.01" min="0" max="100" required
                                            name="school_scores[{{ $aspect['code'] }}]"
                                            value="{{ old("school_scores.{$aspect['code']}", $schScores[$aspect['code']] ?? '') }}"
                                            class="w-full rounded-md border border-field bg-white px-3 py-2 text-sm tabular-nums">
                                    </div>
                                @endforeach
                                <div>
                                    <label for="industry_form_photo-{{ $placement->id }}" class="block text-xs font-medium text-ink mb-1">
                                        Foto Bukti Lembar Penilaian Kertas dari DUDI
                                        @if($school->require_industry_form_photo)
                                            <span class="text-danger">(Wajib saat finalisasi)</span>
                                        @endif
                                    </label>
                                    @if($assessment?->industry_form_photo_path)
                                        <a href="/media/{{ $assessment->industry_form_photo_path }}" target="_blank" class="inline-block mb-2">
                                            <img src="/media/{{ $assessment->industry_form_photo_path }}" alt="Bukti form penilaian DUDI" class="h-20 rounded-md border border-field">
                                        </a>
                                    @endif
                                    <input type="file" id="industry_form_photo-{{ $placement->id }}" name="industry_form_photo" accept="image/*" class="block w-full text-xs text-muted">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label for="certificate_number-{{ $placement->id }}" class="block text-xs font-medium text-ink mb-1">Nomor Sertifikat (Opsional)</label>
                                        <input type="text" id="certificate_number-{{ $placement->id }}" name="certificate_number" value="{{ old('certificate_number', $assessment?->certificate_number) }}"
                                            class="w-full rounded-md border border-field bg-white px-3 py-2 text-sm tabular-nums">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-ink mb-1">Nilai Kehadiran (Otomatis {{ $rubric->weights['attendance_percentage'] }}%)</label>
                                        <div class="rounded-md border border-field bg-paper px-3 py-2 text-sm tabular-nums text-muted">
                                            {{ $assessment ? number_format($assessment->attendance_score, 2) : 'Dihitung saat simpan' }}
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label for="teacher_notes-{{ $placement->id }}" class="block text-xs font-medium text-ink mb-1">Catatan Pembimbing Sekolah</label>
                                    <textarea id="teacher_notes-{{ $placement->id }}" name="teacher_notes" rows="2" class="w-full rounded-md border border-field bg-white px-3 py-2 text-sm">{{ old('teacher_notes', $assessment?->teacher_notes) }}</textarea>
                                </div>
                            </div>
                        </div>

                        @if($assessment?->finalized_at)
                        <p class="text-right text-xs font-semibold text-emerald-strong">Nilai final versi {{ $assessment->version }} terkunci ({{ $assessment->industrySourceLabel() }}). Snapshot: {{ substr((string) $assessment->snapshot_hash, 0, 12) }}</p>
                        @else
                        @if($assessment?->revision_reason)<p class="callout callout-warn">Revisi versi {{ $assessment->version }}: {{ $assessment->revision_reason }}</p>@endif
                        <div class="flex flex-wrap justify-end gap-3">
                            <button type="submit" name="finalize" value="0" class="px-4 py-2.5 rounded-lg border border-navy text-navy hover:bg-stone text-xs font-semibold min-h-11 cursor-pointer">
                                Simpan sebagai Draf
                            </button>
                            <button type="submit" name="finalize" value="1" class="px-5 py-2.5 rounded-lg bg-emerald-check hover:bg-emerald-strong text-white text-xs font-semibold min-h-11 cursor-pointer">
                                Hitung &amp; Finalisasi Nilai Akhir
                            </button>
                        </div>
                        @endif
                    </form>

                    @if($assessment?->finalized_at)
                        <details class="card-muted">
                            <summary class="link">Buka revisi nilai (versi baru dengan alasan)</summary>
                            <form method="POST" action="{{ route('teacher.assessments.revision', $placement) }}" class="flex flex-wrap gap-2 mt-2 items-end">
                                @csrf
                                <div class="flex-1 min-w-60"><label class="label" for="rev-{{ $placement->id }}">Alasan revisi</label><input id="rev-{{ $placement->id }}" name="reason" class="field" required minlength="10"></div>
                                <button class="btn btn-warn btn-sm">Buka revisi</button>
                            </form>
                        </details>
                    @endif
                    @if($assessment && $assessment->versions->isNotEmpty())
                        <details>
                            <summary class="link text-sm">Riwayat versi nilai ({{ $assessment->versions->count() }})</summary>
                            <ul class="timeline mt-2">
                                @foreach($assessment->versions as $v)<li><time>{{ $v->created_at->format('d/m/Y H:i') }}</time> Versi {{ $v->version }}: {{ number_format($v->snapshot['final_score'] ?? 0, 2) }} ({{ $v->snapshot['predicate'] ?? '-' }}) · {{ $v->reason }} · <span class="tabular-nums">{{ substr($v->snapshot_hash, 0, 10) }}</span></li>@endforeach
                            </ul>
                        </details>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection

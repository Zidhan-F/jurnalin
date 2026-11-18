@extends('layouts.student-pwa', ['title' => 'Jurnal Kegiatan Harian - Jurnalin'])

@section('content')
@if($placements->count() > 1 && ! $editingJournal)
    <form method="GET" action="{{ route('student.journals') }}" class="grid gap-2">
        <label class="grid gap-1">Penempatan untuk jurnal
            <select name="placement" class="field">
                @foreach($placements as $item)
                    <option value="{{ $item->id }}" @selected($item->is($placement))>{{ $item->pklPeriod->name }} · {{ $item->company->name }}</option>
                @endforeach
            </select>
        </label>
        <button class="document-link min-h-12 justify-self-start">Pilih penempatan</button>
    </form>
@endif
@if(request()->attributes->get('grace_placements') && ! request()->attributes->get('full_placements'))
    <p class="callout">Bulan tambahan khusus jurnal. Anda masih dapat menulis jurnal dan memperbaiki entri yang dikembalikan guru.</p>
@endif
@if($placement === null)
    <div class="empty">
        <h1 class="font-bold">Jurnal kegiatan</h1>
        Penempatan PKL aktif belum tersedia. Hubungi admin sekolah Anda.
    </div>
@else
    @php
        $draftKey = 'jurnalin-draft-'.$placement->id.'-'.($editingJournal?->id ?? 'baru');
        $initial = [
            'journal_date' => old('journal_date', $editingJournal?->journal_date->toDateString() ?? $todayDate),
            'duration_minutes' => (string) old('duration_minutes', $editingJournal?->duration_minutes ?? 480),
            'work_unit' => old('work_unit', $editingJournal?->work_unit ?? $placement->department_divisi),
            'activity_title' => old('activity_title', $editingJournal?->activity_title),
            'activity_description' => old('activity_description', $editingJournal?->activity_description),
            'tools_materials' => old('tools_materials', $editingJournal?->tools_materials),
            'learning_outcome' => old('learning_outcome', $editingJournal?->learning_outcome),
            'obstacles' => old('obstacles', $editingJournal?->obstacles),
            'template_key' => old('template_key', $editingJournal?->template_key),
        ];
        $claimed = $editingJournal ? $editingJournal->objectives->pluck('id')->all() : old('objective_ids', []);
        $existingPhotos = $editingJournal?->photos() ?? [];
    @endphp
    <section class="card-muted stack-sm"
        x-data="{
            f: @js($initial),
            key: @js($draftKey),
            savedAt: '',
            restored: false,
            preview: false,
            online: navigator.onLine,
            compressing: false,
            photos: [],
            photoInfo: '',
            maxNew: {{ max(0, $maxPhotos - count($existingPhotos)) }},
            objectivesByCode: @js($objectives->pluck('id', 'code')),
            examples: @js($examples),
            exampleMatchers: @js($exampleMatchers),
            defaultExample: @js($defaultExample),
            get ex() {
                const text = ' ' + String(this.f.work_unit || '').toLowerCase().trim() + ' ';
                if (text.trim() !== '') {
                    for (const [key, words] of Object.entries(this.exampleMatchers)) {
                        for (const w of words) {
                            const esc = w.trim().replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                            if (new RegExp('(^|[^a-z])' + esc + '(?![a-z])').test(text)) return this.examples[key];
                        }
                    }
                }
                return this.examples[this.defaultExample] || this.examples.umum;
            },
            claimed: @js(array_values($claimed)),
            init() {
                try {
                    const raw = localStorage.getItem(this.key);
                    if (raw && ! @js((bool) old('activity_title'))) {
                        const d = JSON.parse(raw);
                        if (d.f && (d.f.activity_title || d.f.activity_description)) { this.f = { ...this.f, ...d.f }; this.restored = true; this.savedAt = d.at; }
                    }
                } catch (e) {}
                this.$watch('f', () => this.save(), { deep: true });
                window.addEventListener('online', () => this.online = true);
                window.addEventListener('offline', () => this.online = false);
            },
            save() {
                try { const at = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }); localStorage.setItem(this.key, JSON.stringify({ f: this.f, at })); this.savedAt = at; } catch (e) {}
            },
            clearDraft() { try { localStorage.removeItem(this.key); } catch (e) {} },
            useTemplate(t) {
                this.f.activity_title = t.title;
                this.f.activity_description = t.description;
                this.f.template_key = t.key;
                if (t.work_unit) this.f.work_unit = t.work_unit;
                if (t.tools) this.f.tools_materials = t.tools;
                const id = this.objectivesByCode[t.objective];
                if (id && ! this.claimed.includes(id)) this.claimed.push(id);
                this.$nextTick(() => document.getElementById('activity_description').focus());
            },
            async addPhotos(e) {
                const files = [...(e.target.files || [])].slice(0, this.maxNew - this.photos.length);
                this.compressing = true;
                this.photoInfo = 'Mengompresi foto sebelum unggah...';
                try {
                    for (const file of files) { this.photos.push(await window.jurnalinCompressImage(file, 1280, 0.7)); }
                    const kb = Math.round(this.photos.reduce((s, p) => s + p.length * 3 / 4, 0) / 1024);
                    this.photoInfo = `${this.photos.length} foto siap dikirim (total ${kb} KB).`;
                } catch (err) { this.photoInfo = err.message || 'Gagal memproses foto.'; }
                finally { this.compressing = false; e.target.value = ''; }
            },
            submitGuard(e) {
                if (! this.online) { e.preventDefault(); this.save(); alert('Anda sedang offline. Draf tersimpan di perangkat ini. Kirim lagi setelah koneksi kembali.'); return; }
                this.clearDraft();
            }
        }">
        <div class="flex items-center justify-between gap-2">
            <h1 class="text-lg font-bold text-ink">{{ $editingJournal ? 'Perbaiki Jurnal Kegiatan' : 'Tulis Jurnal Kegiatan' }}</h1>
            @if($editingJournal)<a href="/student/journals" class="link text-sm">Batal edit</a>@endif
        </div>
        @if($editingJournal?->teacher_feedback)
            <p class="callout callout-warn"><strong>Catatan guru:</strong> {{ $editingJournal->teacher_feedback }}</p>
        @endif
        @if($reviewNotice)<p class="callout">{{ $reviewNotice }}</p>@endif
        <p role="status" class="hint" x-text="online ? (savedAt ? 'Draf tersimpan otomatis di perangkat pukul ' + savedAt + (restored ? ' (draf terakhir dipulihkan)' : '') : 'Draf tersimpan otomatis di perangkat saat Anda mengetik.') : 'Offline: draf tetap tersimpan di perangkat. Kirim setelah koneksi kembali.'"></p>

        <details class="card">
            <summary class="font-semibold min-h-11 flex items-center">Isi cepat dari template atau aktivitas terakhir</summary>
            <p class="hint mt-1">Contoh kalimat ini hanya bantuan. Sesuaikan isinya dengan yang benar-benar Anda kerjakan hari ini.</p>
            @if($recent)
                <h2 class="label mt-3">Aktivitas terakhir saya</h2>
                <div class="grid gap-2">
                    @foreach($recent as $r)
                        <button type="button" class="btn btn-outline btn-sm justify-start text-left" @click='useTemplate(@json($r))'>{{ $r['title'] }}</button>
                    @endforeach
                </div>
            @endif
            @foreach($templates as $pack)
                <h2 class="label mt-3">{{ $pack['label'] }}</h2>
                <div class="grid gap-2">
                    @foreach($pack['items'] as $t)
                        <button type="button" class="btn btn-outline btn-sm justify-start text-left" @click='useTemplate(@json($t))'>{{ $t['title'] }}</button>
                    @endforeach
                </div>
            @endforeach
        </details>

        <form method="POST" action="/student/journals" enctype="multipart/form-data" class="stack-sm" @submit="submitGuard($event)">
            @csrf
            <input type="hidden" name="placement_id" value="{{ $placement->id }}">
            <input type="hidden" name="template_key" :value="f.template_key">
            @if($editingJournal)<input type="hidden" name="journal_id" value="{{ $editingJournal->id }}">@endif

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label" for="journal_date">Tanggal</label>
                    <input id="journal_date" name="journal_date" type="date" required min="{{ $editingJournal ? '' : $earliestDate }}" max="{{ $todayDate }}" x-model="f.journal_date" class="field tabular-nums">
                </div>
                <div>
                    <label class="label" for="duration_minutes">Durasi (menit)</label>
                    <input id="duration_minutes" name="duration_minutes" type="number" min="30" max="960" required x-model="f.duration_minutes" class="field tabular-nums">
                </div>
            </div>
            <div>
                <label class="label" for="work_unit">Unit kerja / divisi hari ini</label>
                <input id="work_unit" name="work_unit" type="text" required x-model="f.work_unit" :placeholder="'Contoh: ' + examples[defaultExample].work_unit" class="field">
            </div>
            <div>
                <label class="label" for="activity_title">Judul aktivitas utama</label>
                <input id="activity_title" name="activity_title" type="text" required x-model="f.activity_title" :placeholder="'Contoh: ' + ex.title" class="field">
            </div>
            <div>
                <label class="label" for="activity_description">Kegiatan & hasil</label>
                <textarea id="activity_description" name="activity_description" rows="4" required minlength="15" x-model="f.activity_description" :placeholder="'Contoh: ' + ex.description" class="field"></textarea>
            </div>
            @if(auth()->user()->school->hasFeature('ai'))
                <details class="card" x-data="{ points: '', busy: false, note: '', result: null }">
                    <summary class="font-semibold min-h-11 flex items-center">Asisten jurnal: susun dari poin singkat</summary>
                    <p class="hint mt-1">Tulis poin kegiatan yang benar-benar Anda kerjakan. Asisten mengurutkan poin menjadi uraian, merapikan salah ketik umum, memisahkan kendala, dan menyarankan TP. Asisten tidak menambah kegiatan baru.</p>
                    <label class="label mt-2" for="ai-points">Poin kegiatan (satu per baris)</label>
                    <textarea id="ai-points" class="field" rows="4" x-model="points" :placeholder="ex.points"></textarea>
                    <button type="button" class="btn btn-outline btn-sm mt-2" :disabled="busy || points.trim().length < 5" x-text="busy ? 'Menyusun draf...' : 'Susun draf uraian'"
                        @click="busy = true; note = ''; result = null; fetch(@js(route('student.ai.journal-draft')), { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) }, body: JSON.stringify({ points, work_unit: f.work_unit }) })
                            .then(r => r.json()).then(d => { if (d.draft) { result = d; } note = d.notice || d.message || ''; })
                            .catch(() => note = 'Asisten tidak dapat dihubungi. Tulis uraian secara manual.').finally(() => busy = false)"></button>
                    <template x-if="result">
                        <div class="card-muted stack-sm mt-3">
                            <p class="label">Pratinjau draf</p>
                            <p class="text-sm text-ink" x-text="result.draft"></p>
                            <p class="hint" x-show="result.obstacles">Kendala: <span x-text="result.obstacles"></span></p>
                            <button type="button" class="btn btn-navy btn-sm" @click="f.activity_description = result.draft;
                                if (! f.activity_title && result.title) f.activity_title = result.title;
                                if (! f.obstacles && result.obstacles) f.obstacles = result.obstacles;
                                (result.suggested_objectives || []).forEach(id => { if (! claimed.includes(id)) claimed.push(id) });
                                note = 'Draf dimasukkan ke Kegiatan & hasil. Tambahkan hasil kerjanya, lalu periksa sebelum dikirim.'; result = null;
                                $nextTick(() => { const el = document.getElementById('activity_description'); el.scrollIntoView({ behavior: 'smooth', block: 'center' }); el.focus(); })">Pakai draf ini</button>
                        </div>
                    </template>
                    <p role="status" class="hint mt-1" x-text="note"></p>
                </details>
            @endif
            <div>
                <label class="label" for="learning_outcome">Pelajaran hari ini (opsional)</label>
                <input id="learning_outcome" name="learning_outcome" type="text" x-model="f.learning_outcome" :placeholder="'Contoh: ' + ex.learning" class="field">
            </div>
            <div>
                <label class="label" for="obstacles">Kendala (opsional)</label>
                <input id="obstacles" name="obstacles" type="text" x-model="f.obstacles" :placeholder="'Contoh: ' + ex.obstacles" class="field">
            </div>
            <div>
                <label class="label" for="tools_materials">Alat / bahan / sistem (opsional)</label>
                <input id="tools_materials" name="tools_materials" type="text" x-model="f.tools_materials" :placeholder="'Contoh: ' + ex.tools" class="field">
            </div>

            <fieldset>
                <legend class="label">Terkait Tujuan Pembelajaran (opsional)</legend>
                <p class="hint">Penautan ini klaim Anda. Guru memeriksa sebelum dinyatakan terverifikasi.</p>
                <div class="grid gap-1 mt-1">
                    @foreach($objectives as $o)
                        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="objective_ids[]" value="{{ $o->id }}" x-model="claimed" class="mt-1"> <span><strong>{{ $o->code }}</strong> {{ $o->label }}</span></label>
                    @endforeach
                </div>
            </fieldset>

            <div class="stack-sm">
                <span class="label">Foto dokumentasi (maksimal {{ $maxPhotos }} foto, dikompres otomatis)</span>
                @if($existingPhotos)
                    <div class="grid grid-cols-3 gap-2">
                        @foreach($existingPhotos as $path)
                            <label class="grid gap-1 text-sm">
                                <img src="/media/{{ $path }}" alt="Foto jurnal tersimpan" class="h-20 w-full rounded-md object-cover border border-border">
                                <span class="flex items-center gap-1"><input type="checkbox" name="remove_photos[]" value="{{ $path }}"> Hapus</span>
                            </label>
                        @endforeach
                    </div>
                @endif
                <template x-for="(p, i) in photos" :key="i"><input type="hidden" name="photo_data_urls[]" :value="p"></template>
                <div class="grid grid-cols-3 gap-2" x-show="photos.length">
                    <template x-for="(p, i) in photos" :key="'p'+i">
                        <div class="grid gap-1"><img :src="p" alt="Pratinjau foto baru" class="h-20 w-full rounded-md object-cover border border-border"><button type="button" class="link text-sm" @click="photos.splice(i, 1)">Batal</button></div>
                    </template>
                </div>
                <label for="journal-photo" class="btn btn-outline btn-sm" x-show="photos.length < maxNew">Tambah foto</label>
                <input id="journal-photo" type="file" accept="image/*" multiple class="sr-only" @change="addPhotos($event)">
                <p role="status" class="hint" x-text="photoInfo"></p>
                <p class="hint">Jangan memotret data pelanggan, dokumen internal, atau layar berisi rahasia perusahaan.</p>
            </div>

            <label class="flex items-start gap-2 card"><input type="checkbox" name="confirmed_done" value="1" class="mt-1" @checked(old('confirmed_done', $editingJournal?->confirmed_done))> <span>Saya menyatakan kegiatan ini benar-benar saya lakukan pada tanggal tersebut.</span></label>

            <button type="button" class="link text-sm" @click="preview = ! preview" :aria-expanded="preview">Pratinjau sebelum kirim</button>
            <div x-show="preview" x-cloak class="card stack-sm" aria-live="polite">
                <p class="hint tabular-nums" x-text="f.journal_date + ' · ' + f.work_unit + ' · ' + f.duration_minutes + ' menit'"></p>
                <h2 class="font-bold" x-text="f.activity_title || '(judul belum diisi)'"></h2>
                <p class="whitespace-pre-line" x-text="f.activity_description"></p>
                <p class="hint" x-show="f.obstacles" x-text="'Kendala: ' + f.obstacles"></p>
            </div>

            <div class="grid grid-cols-2 gap-2 pt-1">
                <button type="submit" name="submit_action" value="draft" :disabled="compressing" class="btn btn-outline">Simpan draf</button>
                <button type="submit" name="submit_action" value="submitted" :disabled="compressing" class="btn btn-primary">Kirim ke guru</button>
            </div>
        </form>
    </section>

    <section class="stack-sm">
        <div class="flex items-center justify-between gap-2">
            <h2 class="section-title">Riwayat jurnal saya ({{ $journals->count() }})</h2>
            <a href="{{ route('student.calendar') }}" class="link text-sm">Lihat kalender</a>
        </div>
        <form method="GET" action="{{ route('student.journals') }}" class="card grid grid-cols-2 gap-2">
            <div class="col-span-2"><label class="label" for="q">Cari judul atau isi</label><input id="q" name="q" class="field" value="{{ $filters['q'] ?? '' }}"></div>
            <div><label class="label" for="status">Status</label>
                <select id="status" name="status" class="field">
                    <option value="">Semua</option>
                    @foreach(\App\Enums\JournalStatus::cases() as $st)<option value="{{ $st->value }}" @selected(($filters['status'] ?? '') === $st->value)>{{ $st->label() }}</option>@endforeach
                </select>
            </div>
            <div><label class="label" for="from">Dari tanggal</label><input id="from" name="from" type="date" class="field" value="{{ $filters['from'] ?? '' }}"></div>
            <button class="btn btn-navy btn-sm col-span-2">Terapkan filter</button>
        </form>
        @forelse($journals as $journal)
            @php
                $needsRev = $journal->status === \App\Enums\JournalStatus::RevisionNeeded;
                $isApproved = $journal->status === \App\Enums\JournalStatus::Approved;
            @endphp
            <article id="jurnal-{{ $journal->id }}" class="card stack-sm {{ $needsRev ? 'card-attention' : '' }}">
                <div class="flex items-start justify-between gap-2">
                    <div class="hint tabular-nums">{{ $journal->journal_date->format('d M Y') }} · {{ $journal->work_unit }}</div>
                    <span class="badge {{ $isApproved ? 'badge-ok' : ($needsRev ? 'badge-warn' : '') }}">{{ $journal->status->label() }}</span>
                </div>
                <h3 class="font-bold text-ink">{{ $journal->activity_title }}</h3>
                <p class="text-sm text-muted leading-relaxed">{{ $journal->activity_description }}</p>
                @if($journal->obstacles)<p class="hint">Kendala: {{ $journal->obstacles }}</p>@endif
                @if($journal->teacher_feedback)<div class="callout callout-warn"><strong>Catatan guru:</strong> {{ $journal->teacher_feedback }}</div>@endif
                @if($journal->mentor_note)<div class="callout"><strong>Catatan pembimbing di tempat PKL:</strong> {{ $journal->mentor_note }}</div>@endif
                @if($journal->status->isEditableByStudent())
                    <a href="/student/journals?edit={{ $journal->id }}" class="link text-sm">{{ $needsRev ? 'Perbaiki & kirim ulang' : 'Lanjutkan draf' }}</a>
                @endif
            </article>
        @empty
            <div class="empty">Belum ada jurnal yang cocok. Isi formulir di atas untuk mengirim laporan pertama Anda, atau ubah filter.</div>
        @endforelse
    </section>
@endif
@endsection

{{-- Isi Paspor Kompetensi, dipakai di layar publik, pratinjau siswa, dan PDF. --}}
@php $tag = fn ($label) => $pdf ?? false ? '['.$label.']' : $label; @endphp
@if($data['about'])
    <section class="{{ ($pdf ?? false) ? '' : 'card' }}"><h2 class="section-title">Tentang saya <span class="badge">{{ $tag('Klaim pribadi') }}</span></h2><p>{{ $data['about'] }}</p></section>
@endif
<section class="{{ ($pdf ?? false) ? '' : 'card stack-sm' }}">
    <h2 class="section-title">Pengalaman PKL <span class="badge badge-ok">{{ $tag('Terverifikasi') }}</span></h2>
    @foreach($data['placements'] as $p)
        <div>
            <strong>{{ $p['company'] }}</strong> @if($p['division'])· {{ $p['division'] }}@endif
            <div class="hint muted">{{ $p['period'] }} · {{ $p['from']->format('d/m/Y') }} s.d. {{ $p['until']->format('d/m/Y') }}</div>
            @if($p['grade'])<div>Nilai akhir {{ number_format($p['grade']['score'], 2) }} ({{ $p['grade']['predicate'] }}). {{ $p['grade']['description'] }}</div>@endif
        </div>
    @endforeach
</section>
@if($data['certificates']->isNotEmpty())
    <section class="{{ ($pdf ?? false) ? '' : 'card stack-sm' }}">
        <h2 class="section-title">Sertifikat & surat keterangan <span class="badge badge-ok">{{ $tag('Terverifikasi') }}</span></h2>
        @foreach($data['certificates'] as $c)<div>{{ $c->kind === 'pkl' ? 'Sertifikat PKL' : 'Surat hasil UKK' }} No. {{ $c->number }} · kode verifikasi {{ $c->verification_code }}</div>@endforeach
    </section>
@endif
@if($data['ukk']->isNotEmpty())
    <section class="{{ ($pdf ?? false) ? '' : 'card stack-sm' }}">
        <h2 class="section-title">Uji Kompetensi Keahlian <span class="badge badge-ok">{{ $tag('Terverifikasi') }}</span></h2>
        @foreach($data['ukk'] as $u)<div>{{ $u->session->concentration }} ({{ $u->session->academic_year }}): {{ $u->predicate }} · nilai {{ number_format((float) $u->final_score, 2) }}</div>@endforeach
        <p class="hint muted">Hasil UKK sekolah, bukan sertifikat kompetensi BNSP/LSP.</p>
    </section>
@endif
@if($data['objectives']->isNotEmpty())
    <section class="{{ ($pdf ?? false) ? '' : 'card stack-sm' }}">
        <h2 class="section-title">Capaian Tujuan Pembelajaran <span class="badge">{{ $tag('Ditinjau pembimbing') }}</span></h2>
        @foreach($data['objectives'] as $r)<div>{{ $r['objective']->code }} {{ $r['objective']->label }} ({{ $r['verified'] }} bukti terverifikasi)</div>@endforeach
    </section>
@endif
@if($data['proofs']->isNotEmpty())
    <section class="{{ ($pdf ?? false) ? '' : 'card stack-sm' }}">
        <h2 class="section-title">Bukti Kerja <span class="badge">{{ $tag('Ditinjau pembimbing') }}</span></h2>
        @foreach($data['proofs'] as $w)<div><strong>{{ $w->title }}</strong>: {{ $w->result }}</div>@endforeach
    </section>
@endif
@if($data['journals']->isNotEmpty())
    <section class="{{ ($pdf ?? false) ? '' : 'card stack-sm' }}">
        <h2 class="section-title">Contoh kegiatan <span class="badge">{{ $tag('Disetujui guru') }}</span></h2>
        @foreach($data['journals'] as $j)<div>{{ $j->journal_date->format('d/m/Y') }} · {{ $j->activity_title }} ({{ $j->work_unit }})</div>@endforeach
    </section>
@endif
@if($data['readiness']->isNotEmpty())
    <section class="{{ ($pdf ?? false) ? '' : 'card stack-sm' }}">
        <h2 class="section-title">Pembekalan pra-PKL <span class="badge">{{ $tag('Selesai') }}</span></h2>
        @foreach($data['readiness'] as $r)<div>{{ $r->module->title }} · skor {{ $r->score }}</div>@endforeach
    </section>
@endif

@extends('spec.public.layout')
@section('title', 'Survei alumni')
@section('context', 'Survei alumni internal')

@section('content')
@php $a = $r->answers ?? []; $submitted = in_array($r->status, ['submitted', 'corrected'], true); @endphp
<div class="mentor-intro">
    <p class="hint">{{ $r->school->name }} · {{ $r->campaign->name }}</p>
    <h1>Halo, {{ $r->student->user->name }}</h1>
    <p class="hint">{{ $r->campaign->intro ?: 'Survei ini membantu sekolah memperbaiki PKL dan pembelajaran.' }} Jawaban Anda hanya untuk analisis internal sekolah, tidak dipakai untuk pemasaran atau rekrutmen, dan bukan laporan resmi pemerintah.</p>
</div>
@if($submitted)<div class="mentor-flash">Jawaban Anda sudah terkirim. Anda dapat mengoreksinya bila kondisi berubah.</div>@endif
<form method="POST" action="{{ route('tracer.submit', $token) }}" class="mentor-card mentor-sign">
    @csrf
    <fieldset><legend class="label">Kegiatan Anda saat ini (boleh lebih dari satu)</legend>
        @foreach($statuses as $k => $l)<label class="mentor-check"><input type="checkbox" name="statuses[]" value="{{ $k }}" @checked(in_array($k, $r->statuses ?? [], true))> {{ $l }}</label>@endforeach
    </fieldset>
    <div><label class="label" for="wp">Tempat kerja (bila bekerja)</label><input id="wp" name="workplace" class="field" value="{{ $a['workplace'] ?? '' }}"></div>
    <div><label class="label" for="sp">Tempat studi (bila kuliah)</label><input id="sp" name="study_place" class="field" value="{{ $a['study_place'] ?? '' }}"></div>
    <div><label class="label" for="bz">Usaha (bila wirausaha)</label><input id="bz" name="business" class="field" value="{{ $a['business'] ?? '' }}"></div>
    <div><label class="label" for="rl">Kesesuaian dengan program keahlian</label><select id="rl" name="relevance" class="field"><option value="">-</option>@foreach(['sesuai' => 'Sesuai', 'sebagian' => 'Sebagian', 'tidak' => 'Tidak sesuai'] as $k => $l)<option value="{{ $k }}" @selected(($a['relevance'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div><label class="label" for="so">Mulai sejak</label><input id="so" name="started_on" type="date" class="field" value="{{ $a['started_on'] ?? '' }}"></div>
    <div><label class="label" for="pf">Masukan tentang pengalaman PKL</label><textarea id="pf" name="pkl_feedback">{{ $a['pkl_feedback'] ?? '' }}</textarea></div>
    @if($submitted)<div><label class="label" for="cr">Alasan koreksi</label><input id="cr" name="correction_reason" class="field"></div>@endif
    <label class="mentor-check"><input type="checkbox" name="contact_ok" value="1" @checked($r->student->alumni_contact_ok)> Sekolah boleh menghubungi saya untuk survei internal berikutnya</label>
    <button name="action" value="draft" class="btn btn-outline">Simpan sementara</button>
    <button name="action" value="submit" class="mentor-submit">{{ $submitted ? 'Kirim koreksi' : 'Kirim jawaban' }}</button>
</form>
@endsection

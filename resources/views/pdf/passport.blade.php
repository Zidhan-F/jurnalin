<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><title>Paspor Kompetensi</title>@include('pdf.partials.style')</head>
<body>
    @include('pdf.partials.letterhead', ['school' => $school])
    <h1>PASPOR KOMPETENSI</h1>
    <p class="center">{{ $student->user->name }} · {{ $student->major }}</p>
    @if($qr)<p class="center"><img src="{{ $qr }}" width="100" height="100" alt=""><br><span class="small muted">Pindai untuk memeriksa versi daring yang berlaku</span></p>@endif
    <p class="small muted center">Terverifikasi = data final dari sekolah. Ditinjau pembimbing = diperiksa guru atau pembimbing lapangan. Klaim pribadi = ditulis siswa.</p>
    @include('spec.partials.passport-body', ['data' => $data, 'pdf' => true])
</body></html>

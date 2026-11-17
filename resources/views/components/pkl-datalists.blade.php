{{-- Saran isian lintas program keahlian SMK. Dipakai lewat atribut list="..." pada input teks. --}}
<datalist id="program-keahlian-list">
    @foreach (config('jurnalin.program_keahlian', []) as $bidang => $programs)
        @foreach ($programs as $program)
            <option value="{{ $program }}">{{ $bidang }}</option>
        @endforeach
    @endforeach
</datalist>
<datalist id="industry-type-list">
    @foreach (config('jurnalin.industry_types', []) as $type)
        <option value="{{ $type }}"></option>
    @endforeach
</datalist>

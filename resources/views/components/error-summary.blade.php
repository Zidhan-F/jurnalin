{{-- WCAG 3.3.1 / 3.3.3: ringkasan error yang menerima fokus, setiap butir menautkan ke field-nya.
     app.js membaca #form-errors untuk menandai field (aria-invalid) dan menaruh pesan tepat di bawahnya. --}}
@if($errors->any())
    <div role="alert" tabindex="-1" data-error-summary aria-labelledby="judul-error"
        {{ $attributes->class('error-summary rounded-xl border border-danger/40 bg-danger/10 px-4 py-3 text-sm text-danger-ink') }}>
        <h2 id="judul-error" class="font-semibold mb-1">Ada {{ $errors->count() }} isian yang perlu diperbaiki:</h2>
        <ul class="list-disc pl-5 space-y-1">
            @foreach($errors->getMessages() as $field => $messages)
                @foreach($messages as $message)
                    <li><a href="#" data-error-field="{{ $field }}">{{ $message }}</a></li>
                @endforeach
            @endforeach
        </ul>
    </div>
    <script type="application/json" id="form-errors">@json($errors->getMessages())</script>
@endif

@extends($layout, ['title' => 'Foto Profil Pribadi - Jurnalin'])

@section('content')
<div class="max-w-lg mx-auto space-y-6">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-ink">Foto Profil Pribadi</h1>
            <p class="text-sm text-muted mt-1">
                Foto ini ditampilkan pada menu akun Anda. Jika belum diunggah, Jurnalin menampilkan avatar bawaan.
            </p>
        </div>
        <a href="{{ $user->role->homePath() }}" class="btn btn-outline btn-sm shrink-0">Kembali</a>
    </div>

    <section
        x-data="{
            previewUrl: '',
            photoDataUrl: '',
            compressing: false,
            statusMsg: '',
            errorMsg: '',
            async handleFile(event) {
                const file = event.target.files?.[0];
                this.errorMsg = '';
                this.statusMsg = '';
                if (!file) {
                    this.previewUrl = '';
                    this.photoDataUrl = '';
                    return;
                }
                if (file.size > 4 * 1024 * 1024) {
                    this.errorMsg = 'Ukuran berkas melebihi 4 MB. Pilih foto yang lebih kecil.';
                    event.target.value = '';
                    return;
                }
                if (!window.jurnalinCompressImage) {
                    this.previewUrl = URL.createObjectURL(file);
                    return;
                }
                this.compressing = true;
                this.statusMsg = 'Mengompresi foto sebelum unggah...';
                try {
                    const compressed = await window.jurnalinCompressImage(file, 512, 0.85);
                    this.photoDataUrl = compressed;
                    this.previewUrl = compressed;
                    const kb = Math.round((compressed.length * 3) / 4 / 1024);
                    this.statusMsg = `Foto siap disimpan (${kb} KB).`;
                } catch (err) {
                    this.errorMsg = err?.message || 'Format gambar tidak dapat diproses.';
                    this.photoDataUrl = '';
                    this.previewUrl = '';
                } finally {
                    this.compressing = false;
                }
            }
        }"
        class="rounded-xl border border-border bg-stone p-6 space-y-5"
    >
        <div class="flex items-center gap-4">
            <template x-if="previewUrl">
                <img :src="previewUrl" width="96" height="96" alt="Pratinjau foto profil baru" class="avatar avatar-photo avatar-xl">
            </template>
            <template x-if="!previewUrl">
                <x-avatar :user="$user" size="xl" />
            </template>
            <div class="min-w-0 space-y-1">
                <div class="font-bold text-base text-ink truncate">{{ $user->name }}</div>
                <div class="text-xs text-muted">
                    {{ $user->role->label() }}
                    @if($user->school)
                        · {{ $user->school->name }}
                    @endif
                </div>
                <div class="text-xs text-muted">
                    {{ $user->avatar_path ? 'Menggunakan foto profil khusus' : 'Menggunakan avatar bawaan' }}
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('profile.photo.update') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <input type="hidden" name="photo_data_url" :value="photoDataUrl">

            <div>
                <label for="profile-photo-input" class="block text-sm font-semibold text-ink mb-1.5">
                    Pilih Foto Baru (JPG, PNG, WebP, atau GIF, maks. 4 MB)
                </label>
                <input
                    id="profile-photo-input"
                    name="photo"
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/gif"
                    @change="handleFile($event)"
                    class="block w-full text-sm text-muted file:mr-3 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:bg-navy file:text-white file:font-semibold file:cursor-pointer"
                >
                <p x-show="statusMsg" x-cloak class="mt-2 text-xs font-medium text-emerald-ink" role="status" x-text="statusMsg"></p>
                <p x-show="errorMsg" x-cloak class="mt-2 text-xs font-medium text-danger" role="alert" x-text="errorMsg"></p>
            </div>

            <button
                type="submit"
                :disabled="compressing"
                class="w-full py-3 px-4 rounded-lg bg-emerald-check hover:bg-emerald-strong disabled:opacity-60 text-white font-semibold min-h-12 cursor-pointer"
            >
                Simpan Foto Profil
            </button>
        </form>

        @if($user->avatar_path)
            <form method="POST" action="{{ route('profile.photo.destroy') }}" class="pt-4 border-t border-border">
                @csrf
                @method('DELETE')
                <button
                    type="submit"
                    class="w-full py-2.5 px-4 rounded-lg border border-danger text-danger hover:bg-danger/10 text-sm font-semibold min-h-11 cursor-pointer"
                >
                    Hapus Foto &amp; Pakai Avatar Bawaan
                </button>
            </form>
        @endif
    </section>
</div>
@endsection

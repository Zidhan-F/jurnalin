{{-- Kotak pencarian header untuk admin sekolah dan guru. Tanpa JavaScript tetap bekerja lewat halaman /cari. --}}
@if(in_array(auth()->user()->role, [\App\Enums\UserRole::SchoolAdmin, \App\Enums\UserRole::Teacher], true))
<form action="{{ route('search.index') }}" method="GET" role="search" class="panel-search"
    x-data="{
        q: '', open: false, items: [], active: -1, timer: null,
        fetchNow() {
            clearTimeout(this.timer);
            if (this.q.trim().length < 2) { this.items = []; this.open = false; return; }
            this.timer = setTimeout(async () => {
                try {
                    const r = await fetch('{{ route('search.suggest') }}?q=' + encodeURIComponent(this.q), { headers: { Accept: 'application/json' } });
                    this.items = r.ok ? (await r.json()).results : [];
                } catch (e) { this.items = []; }
                this.active = -1; this.open = true;
            }, 250);
        },
        move(step) { if (!this.items.length) return; this.active = (this.active + step + this.items.length) % this.items.length; },
        go(e) { if (this.open && this.active >= 0) { e.preventDefault(); window.location = this.items[this.active].url; } },
    }"
    @keydown.window.slash="if (! ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) { $event.preventDefault(); $refs.input.focus(); }"
    @click.outside="open = false">
    <label for="cari-header" class="sr-only">Cari siswa, guru, tempat PKL, atau jurnal</label>
    <x-icon name="search" />
    <input id="cari-header" x-ref="input" name="q" x-model="q" @input="fetchNow()" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)"
        @keydown.enter="go($event)" @keydown.escape="open = false" autocomplete="off" role="combobox" aria-autocomplete="list"
        aria-controls="cari-saran" :aria-expanded="open" placeholder="Cari siswa, jurnal, penempatan, atau mitra...">
    <kbd aria-hidden="true">/</kbd>
    <div id="cari-saran" class="panel-search-list" role="listbox" x-show="open" x-cloak>
        <template x-for="(item, i) in items" :key="item.url + i">
            <a :href="item.url" role="option" :aria-selected="i === active" :class="{ 'is-active': i === active }">
                <span x-text="item.label"></span><small x-text="item.hint"></small>
            </a>
        </template>
        <p x-show="items.length === 0" class="hint px-3 py-2">Tidak ada hasil. Tekan Enter untuk pencarian lengkap.</p>
    </div>
</form>
<a href="{{ route('search.index') }}" class="panel-search-mobile notif-bell"><x-icon name="search" /><span class="sr-only">Cari</span></a>
@endif

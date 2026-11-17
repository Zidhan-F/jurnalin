<dialog id="choice-dialog" class="choice-dialog" aria-labelledby="choice-title">
    <div class="choice-heading">
        <h2 id="choice-title"></h2>
        <button type="button" data-close-choice>Tutup</button>
    </div>
    <div class="choice-search">
        <label for="choice-query" class="sr-only">Cari pilihan</label>
        <x-icon name="search" />
        <input id="choice-query" role="combobox" aria-autocomplete="list" aria-expanded="true"
            aria-controls="choice-results" autocomplete="off" placeholder="Ketik untuk mencari" autofocus>
        <button type="button" data-clear-choice hidden>Hapus</button>
    </div>
    <p class="choice-status" role="status" aria-live="polite"></p>
    <ul id="choice-results" role="listbox" aria-labelledby="choice-title"></ul>
</dialog>

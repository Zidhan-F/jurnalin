// Ekstrak ilustrasi Microsoft Fluent Emoji (MIT) (gaya flat) dari paket @iconify-json/fluent-emoji-flat
// ke public/images/illustrations/*.svg. Jalankan manual: node scripts/extract-illustrations.mjs
// Hasilnya di-commit; aplikasi tidak bergantung pada paket ini saat berjalan.
// Ilustrasi orang memakai warna kulit "medium" agar dekat dengan pengguna di Indonesia.
import { writeFileSync, mkdirSync } from 'node:fs';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const set = require('@iconify-json/fluent-emoji-flat/icons.json');
const outDir = new URL('../public/images/illustrations/', import.meta.url);

// Avatar dan maskot orang dibuat oleh scripts/generate-characters.mjs; skrip ini hanya untuk ikon pendukung.
const map = {
    superadmin: 'gear',
    // Ilustrasi pendukung untuk landing page dan program kemitraan.
    partnership: 'handshake',
    chart: 'bar-chart',
    journal: 'open-book',
    calendar: 'calendar',
    'industry-worker': 'factory-worker-medium',
    cook: 'cook-medium',
};

mkdirSync(outDir, { recursive: true });
for (const [file, name] of Object.entries(map)) {
    const icon = set.icons[name];
    if (!icon) {
        throw new Error(`Ikon ${name} tidak ada di paket.`);
    }
    const w = icon.width ?? set.width ?? 32;
    const h = icon.height ?? set.height ?? 32;
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${w} ${h}" width="128" height="128">${icon.body}</svg>\n`;
    writeFileSync(new URL(`${file}.svg`, outDir), svg);
    console.log(file, '<-', name, `${(svg.length / 1024).toFixed(1)} KB`);
}

// Paket iconify tidak menyertakan berkas LICENSE; teks MIT berikut dari repositori microsoft/fluentui-emoji.
writeFileSync(new URL('LICENSE.txt', outDir), `Karakter kartun (student-*, teacher, admin, mentor, intern_teacher, examiner, alumni, mascot-*):
kepala Avataaars karya Pablo Stanley (https://avataaars.com/), lisensi: bebas untuk penggunaan pribadi dan komersial,
dibuat lewat DiceBear (https://www.dicebear.com/, kode MIT). Badan maskot digambar khusus untuk Jurnalin.

Ikon pendukung (superadmin, partnership, chart, journal, calendar, industry-worker, cook):
Microsoft Fluent Emoji (gaya flat), https://github.com/microsoft/fluentui-emoji

MIT License

Copyright (c) Microsoft Corporation.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
`);

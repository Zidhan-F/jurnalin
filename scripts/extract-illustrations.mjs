// Ekstrak dan selaraskan ilustrasi Microsoft Fluent Emoji (MIT, gaya flat) dari paket @iconify-json/fluent-emoji-flat
// ke public/images/illustrations/*.svg dengan palet hangat Jurnalin serta aksen visual kontekstual.
// Jalankan manual: node scripts/extract-illustrations.mjs
// Hasilnya di-commit; aplikasi tidak bergantung pada paket ini saat berjalan.
import { writeFileSync, mkdirSync } from 'node:fs';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const set = require('@iconify-json/fluent-emoji-flat/icons.json');
const outDir = new URL('../public/images/illustrations/', import.meta.url);

const PALETTE = {
    paper: '#FAF8F5',
    stone: '#F3EFEA',
    stoneDeep: '#E7E2DA',
    border: '#D5CFC7',
    navy: '#1E293B',
    navyDeep: '#0F172A',
    slate: '#334155',
    slateSoft: '#64748B',
    emerald: '#047857',
    emeraldSoft: '#10B981',
    amber: '#D97706',
    gold: '#F59E0B',
    crimson: '#B91C1C',
};

// Pemetaan warna bawaan Fluent Emoji (ungu-abu, magenta neon, sian terang) ke palet Jurnalin.
const COLOR_MAP = {
    '#f70a8d': PALETTE.amber,
    '#00d26a': PALETTE.emerald,
    '#00a6ed': PALETTE.navy,
    '#0084ce': PALETTE.navy,
    '#0074ba': PALETTE.navyDeep,
    '#e1d8ec': PALETTE.paper,
    '#f3eef8': PALETTE.paper,
    '#b4acbc': PALETTE.slateSoft,
    '#998ea4': PALETTE.slate,
    '#cdc4d6': PALETTE.stoneDeep,
    '#8d65c5': PALETTE.gold,
    '#533566': PALETTE.amber,
    '#321b41': PALETTE.navyDeep,
    '#50e2ff': PALETTE.emeraldSoft,
    '#26eafc': PALETTE.gold,
    '#b4f6fc': PALETTE.paper,
    '#f92f60': PALETTE.crimson,
};

function harmonizeColors(body) {
    let result = body;
    for (const [from, to] of Object.entries(COLOR_MAP)) {
        result = result.replaceAll(from, to).replaceAll(from.toUpperCase(), to);
    }
    return result;
}

const specs = {
    superadmin: {
        icon: 'gear',
        render: (body) => `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="128" height="128" fill="none">
<circle cx="32" cy="32" r="30" fill="${PALETTE.stone}" stroke="${PALETTE.border}" stroke-width="1.5"/>
<circle cx="32" cy="32" r="23" fill="${PALETTE.stoneDeep}" opacity="0.6"/>
<g transform="translate(8 8) scale(1.5)">${body}</g>
<circle cx="32" cy="32" r="7.5" fill="${PALETTE.gold}" stroke="${PALETTE.paper}" stroke-width="2"/>
<g transform="translate(36 35)">
    <path d="M11 2 L20 5.5 V12.5 C20 18.2 16.2 22.8 11 24.5 C5.8 22.8 2 18.2 2 12.5 V5.5 L11 2 Z" fill="${PALETTE.emerald}" stroke="${PALETTE.paper}" stroke-width="2" stroke-linejoin="round"/>
    <path d="M7.5 13 L10 15.5 L15 10.5" stroke="${PALETTE.paper}" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
</g>
</svg>\n`,
    },
    partnership: {
        icon: 'handshake',
        render: (body) => `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="128" height="128" fill="none">
<rect x="3" y="3" width="58" height="58" rx="14" fill="${PALETTE.stone}" stroke="${PALETTE.border}" stroke-width="1.5"/>
<circle cx="32" cy="32" r="21" fill="${PALETTE.paper}" stroke="${PALETTE.stoneDeep}" stroke-width="1.5"/>
<path d="M8 22 L18 14 L24 22 L14 31 Z" fill="${PALETTE.navy}"/>
<path d="M56 22 L46 14 L40 22 L50 31 Z" fill="${PALETTE.emerald}"/>
<g transform="translate(10 11) scale(1.375)">${body}</g>
<circle cx="49" cy="15" r="7" fill="${PALETTE.emerald}" stroke="${PALETTE.paper}" stroke-width="2"/>
<path d="M46 15 L48.2 17.2 L52.5 13" stroke="${PALETTE.paper}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
</svg>\n`,
    },
    chart: {
        icon: 'bar-chart',
        render: (body) => `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="128" height="128" fill="none">
<rect x="3" y="3" width="58" height="58" rx="14" fill="${PALETTE.stone}" stroke="${PALETTE.border}" stroke-width="1.5"/>
<g transform="translate(9 10) scale(1.4375)">${body}</g>
<path d="M16 26 L29 19 L44 12" stroke="${PALETTE.emerald}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
<circle cx="16" cy="26" r="3" fill="${PALETTE.paper}" stroke="${PALETTE.emerald}" stroke-width="2"/>
<circle cx="29" cy="19" r="3" fill="${PALETTE.paper}" stroke="${PALETTE.emerald}" stroke-width="2"/>
<circle cx="44" cy="12" r="3.5" fill="${PALETTE.gold}" stroke="${PALETTE.navy}" stroke-width="2"/>
</svg>\n`,
    },
    journal: {
        icon: 'open-book',
        render: (body) => `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="128" height="128" fill="none">
<rect x="3" y="3" width="58" height="58" rx="14" fill="${PALETTE.stone}" stroke="${PALETTE.border}" stroke-width="1.5"/>
<g transform="translate(8 9) scale(1.5)">${body}</g>
<!-- Baris catatan di halaman kiri dan paraf pembimbing di halaman kanan -->
<path d="M16 25 H27 M16 30 H27 M16 35 H24" stroke="${PALETTE.slateSoft}" stroke-width="2" stroke-linecap="round"/>
<path d="M36 25 H47 M36 30 H44" stroke="${PALETTE.slateSoft}" stroke-width="2" stroke-linecap="round"/>
<path d="M37 36 C40 34 44 34 46 35 C44 37 44 41 42 42 C40 43 39 41 41 40 C43 39 46 41 48 39" stroke="${PALETTE.emerald}" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
<path d="M30 48 V56 L33 53.5 L36 56 V48 Z" fill="${PALETTE.gold}"/>
</svg>\n`,
    },
    calendar: {
        icon: 'calendar',
        render: (body) => `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="128" height="128" fill="none">
<rect x="3" y="3" width="58" height="58" rx="14" fill="${PALETTE.stone}" stroke="${PALETTE.border}" stroke-width="1.5"/>
<g transform="translate(8 8) scale(1.5)">${body}</g>
<rect x="20" y="9" width="4" height="8" rx="2" fill="${PALETTE.gold}"/>
<rect x="40" y="9" width="4" height="8" rx="2" fill="${PALETTE.gold}"/>
<circle cx="46" cy="46" r="10" fill="${PALETTE.emerald}" stroke="${PALETTE.paper}" stroke-width="2.5"/>
<path d="M41.5 46 L44.5 49 L50.5 43" stroke="${PALETTE.paper}" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
</svg>\n`,
    },
    'industry-worker': {
        icon: 'factory-worker-medium',
        render: (body) => `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="128" height="128" fill="none">
<circle cx="32" cy="32" r="30" fill="${PALETTE.stone}" stroke="${PALETTE.border}" stroke-width="1.5"/>
<circle cx="32" cy="34" r="23" fill="${PALETTE.stoneDeep}" opacity="0.55"/>
<g transform="translate(8 7) scale(1.5)">${body}</g>
</svg>\n`,
    },
    cook: {
        icon: 'cook-medium',
        render: (body) => `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="128" height="128" fill="none">
<circle cx="32" cy="32" r="30" fill="${PALETTE.stone}" stroke="${PALETTE.border}" stroke-width="1.5"/>
<circle cx="32" cy="34" r="23" fill="${PALETTE.stoneDeep}" opacity="0.55"/>
<g transform="translate(8 7) scale(1.5)">${body}</g>
</svg>\n`,
    },
};

mkdirSync(outDir, { recursive: true });
for (const [file, spec] of Object.entries(specs)) {
    const icon = set.icons[spec.icon];
    if (!icon) {
        throw new Error(`Ikon ${spec.icon} tidak ada di paket.`);
    }
    const body = harmonizeColors(icon.body);
    const svg = spec.render(body);
    writeFileSync(new URL(`${file}.svg`, outDir), svg);
    console.log(file, '<-', spec.icon, `${(svg.length / 1024).toFixed(1)} KB`);
}

// Paket iconify tidak menyertakan berkas LICENSE; teks MIT berikut dari repositori microsoft/fluentui-emoji.
writeFileSync(new URL('LICENSE.txt', outDir), `Karakter kartun (student-*, teacher, admin, mentor, intern_teacher, examiner, alumni, mascot-*):
kepala Avataaars karya Pablo Stanley (https://avataaars.com/), lisensi: bebas untuk penggunaan pribadi dan komersial,
dibuat lewat DiceBear (https://www.dicebear.com/, kode MIT). Badan maskot dan atribut peran digambar khusus untuk Jurnalin.

Ikon pendukung (superadmin, partnership, chart, journal, calendar, industry-worker, cook):
Microsoft Fluent Emoji (gaya flat), https://github.com/microsoft/fluentui-emoji, diselaraskan dengan palet Jurnalin.

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

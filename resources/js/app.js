import Alpine from 'alpinejs';
import { initChoiceDialog } from './choice-dialog';

window.Alpine = Alpine;

window.jurnalinHaversineMeters = function (lat1, lon1, lat2, lon2) {
    const R = 6371000;
    const toRad = (deg) => (deg * Math.PI) / 180;
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a =
        Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) * Math.sin(dLon / 2);
    return Math.round(R * (2 * Math.asin(Math.sqrt(a))));
};

// Durasi periode PKL: tanggal selesai = tanggal mulai + N bulan - 1 hari (format YYYY-MM-DD, zona lokal).
// Tanggal 31 Januari + 1 bulan menjadi 28/29 Februari, bukan meluber ke Maret.
const toIsoDate = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const parseIsoDate = (iso) => {
    const [y, m, d] = String(iso || '').split('-').map(Number);
    return y && m && d ? new Date(y, m - 1, d) : null;
};
window.jurnalinAddMonths = function (startIso, months) {
    const start = parseIsoDate(startIso);
    if (!start) return '';
    const targetMonth = start.getMonth() + months;
    const lastDay = new Date(start.getFullYear(), targetMonth + 1, 0).getDate();
    const sameDay = new Date(start.getFullYear(), targetMonth, Math.min(start.getDate(), lastDay));
    sameDay.setDate(sameDay.getDate() - 1);
    return toIsoDate(sameDay);
};
window.jurnalinDaysInclusive = function (startIso, endIso) {
    const start = parseIsoDate(startIso);
    const end = parseIsoDate(endIso);
    if (!start || !end || end < start) return 0;
    return Math.round((end - start) / 86400000) + 1;
};

window.jurnalinCompressImage = function (file, maxDim = 1280, quality = 0.75) {
    return new Promise((resolve, reject) => {
        if (!file) {
            reject(new Error('Berkas gambar tidak tersedia.'));
            return;
        }
        const reader = new FileReader();
        reader.onerror = () => reject(new Error('Gagal membaca berkas kamera.'));
        reader.onload = (e) => {
            const img = new Image();
            img.onerror = () => reject(new Error('Format gambar tidak valid.'));
            img.onload = () => {
                let { width, height } = img;
                if (width > maxDim || height > maxDim) {
                    if (width > height) {
                        height = Math.round((height * maxDim) / width);
                        width = maxDim;
                    } else {
                        width = Math.round((width * maxDim) / height);
                        height = maxDim;
                    }
                }
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);

                let dataUrl = canvas.toDataURL('image/webp', quality);
                if (!dataUrl.startsWith('data:image/webp')) {
                    dataUrl = canvas.toDataURL('image/jpeg', quality);
                }
                resolve(dataUrl);
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
};

// Leaflet (~150 KB) dimuat hanya di halaman yang punya peta, bukan di setiap halaman.
window.jurnalinInitCompanyMap = async function (elId, latInputId, lngInputId, radiusInputId, defaultLat = -6.9175, defaultLng = 107.6191) {
    const container = document.getElementById(elId);
    if (!container) return;
    const [{ default: L }] = await Promise.all([import('leaflet'), import('leaflet/dist/leaflet.css')]);

    const latInput = document.getElementById(latInputId);
    const lngInput = document.getElementById(lngInputId);
    const radiusInput = document.getElementById(radiusInputId);

    const startLat = parseFloat(latInput?.value || defaultLat);
    const startLng = parseFloat(lngInput?.value || defaultLng);
    const startRadius = parseInt(radiusInput?.value || '200', 10);

    const map = L.map(elId).setView([startLat, startLng], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    const marker = L.circleMarker([startLat, startLng], {
        radius: 8,
        color: '#1E293B',
        fillColor: '#047857',
        fillOpacity: 1,
        weight: 2,
    }).addTo(map);

    const circle = L.circle([startLat, startLng], {
        radius: startRadius,
        color: '#047857',
        fillColor: '#047857',
        fillOpacity: 0.12,
        weight: 1.5,
    }).addTo(map);

    const updatePoint = (lat, lng) => {
        const roundedLat = Number(lat).toFixed(7);
        const roundedLng = Number(lng).toFixed(7);
        if (latInput) latInput.value = roundedLat;
        if (lngInput) lngInput.value = roundedLng;
        marker.setLatLng([lat, lng]);
        circle.setLatLng([lat, lng]);
    };

    map.on('click', (e) => updatePoint(e.latlng.lat, e.latlng.lng));

    // WCAG 2.5.7: input Latitude/Longitude adalah alternatif tanpa klik peta, jadi ketikan ikut menggeser penanda.
    const syncFromInputs = () => {
        const lat = parseFloat(latInput?.value);
        const lng = parseFloat(lngInput?.value);
        if (Number.isNaN(lat) || Number.isNaN(lng) || Math.abs(lat) > 90 || Math.abs(lng) > 180) return;
        marker.setLatLng([lat, lng]);
        circle.setLatLng([lat, lng]);
        map.panTo([lat, lng]);
    };
    latInput?.addEventListener('change', syncFromInputs);
    lngInput?.addEventListener('change', syncFromInputs);

    if (radiusInput) {
        radiusInput.addEventListener('input', () => {
            const val = parseInt(radiusInput.value || '200', 10);
            if (!Number.isNaN(val) && val > 0) {
                circle.setRadius(val);
            }
        });
    }

    return { map, updatePoint };
};

// WCAG 3.3.1 / 3.3.3: tandai field yang gagal validasi, taruh pesan tepat di bawahnya,
// tautkan ringkasan error ke field, lalu pindahkan fokus ke ringkasan agar langsung terbaca.
function jurnalinMarkFieldErrors() {
    const source = document.getElementById('form-errors');
    if (!source) return;
    let bag = {};
    try { bag = JSON.parse(source.textContent || '{}'); } catch { return; }

    const findFields = (key) => {
        const parts = key.split('.');
        const bracket = parts[0] + parts.slice(1).map((p) => `[${p}]`).join('');
        const names = [key, bracket, `${parts[0]}[]`];
        for (const name of names) {
            const found = document.querySelectorAll(`main [name="${CSS.escape(name)}"]:not([type=hidden])`);
            if (found.length) return [...found].map((field) => field.hidden && field.previousElementSibling?.matches('.choice-trigger') ? field.previousElementSibling : field);
        }
        return [];
    };

    Object.entries(bag).forEach(([key, messages]) => {
        const fields = findFields(key);
        const safeKey = key.replace(/[^a-zA-Z0-9_-]/g, '-');
        fields.forEach((field, i) => {
            const errId = `galat-${safeKey}-${i}`;
            if (!field.id) field.id = `isian-${safeKey}-${i}`;
            field.setAttribute('aria-invalid', 'true');
            const described = (field.getAttribute('aria-describedby') || '').split(' ').filter(Boolean);
            if (!described.includes(errId)) field.setAttribute('aria-describedby', [...described, errId].join(' '));
            if (!document.getElementById(errId)) {
                const msg = document.createElement('p');
                msg.id = errId;
                msg.className = 'field-error';
                msg.textContent = messages[0];
                const anchor = field.closest('label') && field.type !== 'checkbox' ? field.closest('label') : field;
                anchor.insertAdjacentElement('afterend', msg);
            }
            field.addEventListener('input', () => field.removeAttribute('aria-invalid'), { once: true });
        });

        const target = fields.find((f) => f.offsetParent !== null) || fields[0];
        document.querySelectorAll(`[data-error-field="${CSS.escape(key)}"]`).forEach((link) => {
            if (!target) { link.replaceWith(...link.childNodes); return; }
            link.href = `#${target.id}`;
            link.addEventListener('click', (e) => { e.preventDefault(); target.focus(); });
        });
    });

    document.querySelector('[data-error-summary]')?.focus();
}

Alpine.data('localClock', () => ({
    dateLabel: '',
    timeLabel: '',
    timer: null,
    init() {
        this.sync();
    },
    sync() {
        clearTimeout(this.timer);
        if (document.hidden) return;

        const now = new Date();
        this.dateLabel = now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'short' });
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        this.timeLabel = `${hours}:${minutes}`;
        this.timer = setTimeout(() => this.sync(), 60_000 - (now.getTime() % 60_000));
    },
    destroy() {
        clearTimeout(this.timer);
    },
}));

// Landing page: garis bawah pada menu Tentang/Harga/FAQ mengikuti section yang sedang dibaca.
// aria-current="location" membuat pembaca layar ikut mengumumkan section aktif.
function jurnalinScrollSpy() {
    const items = [...document.querySelectorAll('.public-menu a[href*="#"]')]
        .map((link) => ({ link, section: document.getElementById(link.hash.slice(1)) }))
        .filter((item) => item.section);
    if (!items.length) return;

    const update = () => {
        const headerOffset = parseFloat(getComputedStyle(document.documentElement).scrollPaddingTop) || 0;
        const readingLine = headerOffset + 48;
        const atBottom = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4;
        let current = null;
        items.forEach((item) => {
            if (item.section.getBoundingClientRect().top <= readingLine) current = item;
        });
        if (atBottom) current = items[items.length - 1];
        items.forEach(({ link }) => {
            if (current && link === current.link) link.setAttribute('aria-current', 'location');
            else link.removeAttribute('aria-current');
        });
    };
    // Event scroll sudah dibatasi browser sekali per frame; update hanya mengukur beberapa section.
    window.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    window.addEventListener('hashchange', update);
    update();
}

Alpine.start();
initChoiceDialog();
jurnalinMarkFieldErrors();
jurnalinScrollSpy();

// ---------- PWA & adaptasi mobile ----------
const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
document.documentElement.classList.toggle('is-standalone', isStandalone);

if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}

const storage = {
    get: (key) => { try { return window.localStorage.getItem(key); } catch { return null; } },
    set: (key, value) => { try { window.localStorage.setItem(key, value); } catch { /* mode privat: abaikan */ } },
};

const installBox = document.querySelector('[data-install]');
if (installBox && !isStandalone && storage.get('jurnalin-install-dismissed') !== '1') {
    let deferredPrompt = null;
    const goButton = installBox.querySelector('[data-install-go]');
    const isIos = /iphone|ipad|ipod/i.test(window.navigator.userAgent);

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredPrompt = event;
        installBox.hidden = false;
    });
    goButton.addEventListener('click', async () => {
        if (!deferredPrompt) return;
        deferredPrompt.prompt();
        await deferredPrompt.userChoice;
        deferredPrompt = null;
        installBox.hidden = true;
    });
    // Safari iOS tidak punya prompt pemasangan: tampilkan petunjuk manual.
    if (isIos) {
        goButton.hidden = true;
        installBox.querySelector('[data-install-android]').hidden = true;
        installBox.querySelector('[data-install-ios]').hidden = false;
        installBox.hidden = false;
    }
    installBox.querySelector('[data-install-close]').addEventListener('click', () => {
        installBox.hidden = true;
        storage.set('jurnalin-install-dismissed', '1');
    });
    window.addEventListener('appinstalled', () => { installBox.hidden = true; });
}

// Tabel lebar menjadi kartu bertumpuk di layar kecil. Label kolom disalin dari <th>,
// jadi tanpa JavaScript tabel tetap bisa digeser ke samping seperti biasa.
document.querySelectorAll('main .overflow-x-auto > table').forEach((table) => {
    const heads = [...table.querySelectorAll('thead th')].map((th) => th.textContent.trim());
    if (heads.length === 0) return;
    table.querySelectorAll('tbody tr').forEach((row) => {
        let column = 0;
        [...row.children].forEach((cell) => {
            const span = cell.colSpan || 1;
            if (span === 1 && heads[column]) cell.dataset.label = heads[column];
            else cell.classList.add('cell-full');
            column += span;
        });
    });
    table.classList.add('table-stack');
});

// Karakter kartun Jurnalin (gaya mockup di docs/desain/mockup): avatar peran dan maskot siswa seluruh badan.
// Kepala memakai Avataaars karya Pablo Stanley lewat DiceBear (bebas untuk penggunaan pribadi dan komersial);
// badan maskot (seragam putih abu-abu, dasi, lencana SMK, ransel, buku jurnal berlogo paraf, tangan menyapa)
// serta atribut khas tiap peran digambar khusus untuk Jurnalin.
// Jalankan manual: node scripts/generate-characters.mjs. Hasilnya di-commit; aplikasi tidak bergantung pada paket ini saat berjalan.
import { createAvatar } from '@dicebear/core';
import * as avataaars from '@dicebear/avataaars';
import { writeFileSync, mkdirSync } from 'node:fs';

const outDir = new URL('../public/images/illustrations/', import.meta.url);

const PALETTE = {
    paper: '#FAF8F5',
    stone: '#F3EFEA',
    stoneDeep: '#E7E2DA',
    border: '#D5CFC7',
    navy: '#1E293B',
    navyDeep: '#0F172A',
    slate: '#334155',
    slateSoft: '#475569',
    greyUniform: '#64748B',
    greyShadow: '#475569',
    emerald: '#047857',
    emeraldSoft: '#10B981',
    amber: '#D97706',
    gold: '#F59E0B',
    crimson: '#B91C1C',
    rose: '#9D174D',
};

const HAIR = '231815';
const HAIR_SOFT = '3b2820';
const SKIN = 'd99b66';
const SKIN_LIGHT = 'edb98a';

const base = {
    eyes: ['happy'],
    mouth: ['smile'],
    eyebrows: ['defaultNatural'],
    facialHairProbability: 0,
    accessoriesProbability: 0,
};

const glasses = (style) => ({
    accessories: [style],
    accessoriesProbability: 100,
    accessoriesColor: ['1e293b'],
});

const stripOuter = (svg) =>
    svg
        .replace(/<metadata[\s\S]*?<\/metadata>/, '')
        .replace(/^<svg[^>]*>/, '')
        .replace(/<\/svg>\s*$/, '');

// Pakaian seragam SMK (kerah putih tegas, dasi abu-navy, tali ransel, dan lencana saku) di koordinat lokal (8, 0).
function studentClothes() {
    return `<g>
        <path d="M28 280 L32 244 C36 212 64 196 96 192 L132 220 L168 192 C200 196 228 212 232 244 L236 280 Z" fill="#FFFFFF" stroke="${PALETTE.border}" stroke-width="2"/>
        <path d="M28 280 L64 280 L72 200 C52 206 36 220 32 244 Z" fill="${PALETTE.stone}" opacity="0.6"/>
        <path d="M236 280 L200 280 L192 200 C212 206 228 220 232 244 Z" fill="${PALETTE.stone}" opacity="0.6"/>
        <polygon points="96,188 132,224 110,238 84,204" fill="#FFFFFF" stroke="${PALETTE.border}" stroke-width="2" stroke-linejoin="round"/>
        <polygon points="168,188 132,224 154,238 180,204" fill="#FFFFFF" stroke="${PALETTE.border}" stroke-width="2" stroke-linejoin="round"/>
        <polygon points="124,218 140,218 144,232 132,240 120,232" fill="${PALETTE.navy}"/>
        <polygon points="126,234 138,234 143,280 121,280" fill="${PALETTE.slate}"/>
        <path d="M125 250 L140 242 M123 266 L141 256" stroke="${PALETTE.paper}" stroke-width="2.5" opacity="0.75"/>
        <path d="M62 202 C56 226 54 252 56 280 L74 280 C72 252 74 226 80 198 Z" fill="${PALETTE.navy}"/>
        <path d="M202 202 C208 226 210 252 208 280 L190 280 C192 252 190 226 184 198 Z" fill="${PALETTE.navy}"/>
        <rect x="58" y="236" width="16" height="8" rx="2" fill="${PALETTE.stoneDeep}" stroke="${PALETTE.slate}" stroke-width="1.5"/>
        <rect x="190" y="236" width="16" height="8" rx="2" fill="${PALETTE.stoneDeep}" stroke="${PALETTE.slate}" stroke-width="1.5"/>
    </g>`;
}

const studentBadgeFront = `<g>
    <rect x="164" y="246" width="22" height="24" rx="4" fill="#FFFFFF" stroke="${PALETTE.border}" stroke-width="1.8"/>
    <circle cx="175" cy="258" r="6" fill="${PALETTE.gold}"/>
    <circle cx="175" cy="258" r="3" fill="${PALETTE.emerald}"/>
</g>`;

const roleConfigs = {
    'student-male': {
        bg: '#EFECE6',
        ring: '#E2DDD3',
        opts: {
            top: ['shortFlat'],
            hairColor: [HAIR],
            skinColor: [SKIN_LIGHT],
            eyebrows: ['raisedExcitedNatural'],
            clothing: ['collarAndSweater'],
            clothesColor: ['ffffff'],
        },
        clothes: studentClothes(),
        front: studentBadgeFront,
    },
    'student-female': {
        bg: '#EFECE6',
        ring: '#E2DDD3',
        opts: {
            top: ['straight01'],
            hairColor: [HAIR],
            skinColor: [SKIN_LIGHT],
            eyebrows: ['defaultNatural'],
            clothing: ['collarAndSweater'],
            clothesColor: ['ffffff'],
        },
        clothes: studentClothes(),
        front: `${studentBadgeFront}<rect x="82" y="88" width="16" height="6" rx="3" transform="rotate(-18 82 88)" fill="${PALETTE.gold}" stroke="${PALETTE.navy}" stroke-width="1.5"/>`,
    },
    'student-neutral': {
        bg: '#EFECE6',
        ring: '#E2DDD3',
        opts: {
            top: ['shortRound'],
            hairColor: [HAIR],
            skinColor: [SKIN],
            eyebrows: ['defaultNatural'],
            clothing: ['collarAndSweater'],
            clothesColor: ['ffffff'],
        },
        clothes: studentClothes(),
        front: studentBadgeFront,
    },
    teacher: {
        bg: '#F3EFEA',
        ring: '#E5DEC9',
        opts: {
            top: ['hijab'],
            hatColor: ['b45309'],
            skinColor: [SKIN_LIGHT],
            clothing: ['blazerAndShirt'],
            clothesColor: ['1e293b'],
            ...glasses('round'),
        },
        clothes: `<g>
            <path d="M28 280 L32 246 C38 214 64 198 96 194 L132 238 L168 194 C200 198 226 214 232 246 L236 280 Z" fill="${PALETTE.navy}"/>
            <polygon points="100,194 132,244 164,194" fill="${PALETTE.paper}"/>
            <path d="M88 196 L132 248 L108 258 L74 210 Z" fill="${PALETTE.slate}"/>
            <path d="M176 196 L132 248 L156 258 L190 210 Z" fill="${PALETTE.slate}"/>
        </g>`,
        front: `<g>
            <rect x="126" y="252" width="28" height="22" rx="4" fill="${PALETTE.paper}" stroke="${PALETTE.border}" stroke-width="2"/>
            <rect x="131" y="258" width="18" height="4" rx="2" fill="${PALETTE.emerald}"/>
            <circle cx="180" cy="242" r="7.5" fill="${PALETTE.gold}" stroke="${PALETTE.paper}" stroke-width="2"/>
            <circle cx="180" cy="242" r="3" fill="${PALETTE.amber}"/>
        </g>`,
    },
    admin: {
        bg: '#EFECE6',
        ring: '#DCE3EA',
        opts: {
            top: ['shortWaved'],
            hairColor: [HAIR],
            skinColor: [SKIN_LIGHT],
            clothing: ['blazerAndShirt'],
            clothesColor: ['1e293b'],
            ...glasses('prescription02'),
        },
        clothes: `<g>
            <path d="M28 280 L32 246 C38 214 64 198 96 194 L132 238 L168 194 C200 198 226 214 232 246 L236 280 Z" fill="${PALETTE.navy}"/>
            <polygon points="98,194 132,246 166,194" fill="#FFFFFF"/>
            <polygon points="125,216 139,216 142,228 132,236 122,228" fill="${PALETTE.amber}"/>
            <polygon points="127,230 137,230 141,280 123,280" fill="${PALETTE.crimson}"/>
            <path d="M84 196 L124 254 L104 260 L72 212 Z" fill="${PALETTE.slate}"/>
            <path d="M180 196 L140 254 L160 260 L192 212 Z" fill="${PALETTE.slate}"/>
        </g>`,
        front: `<g>
            <rect x="164" y="242" width="26" height="18" rx="3" fill="${PALETTE.paper}" stroke="${PALETTE.gold}" stroke-width="2"/>
            <line x1="169" y1="249" x2="185" y2="249" stroke="${PALETTE.navy}" stroke-width="2.5" stroke-linecap="round"/>
            <line x1="169" y1="254" x2="180" y2="254" stroke="${PALETTE.emerald}" stroke-width="2.5" stroke-linecap="round"/>
        </g>`,
    },
    mentor: {
        bg: '#F3EFEA',
        ring: '#E8DFC8',
        opts: {
            top: ['shortFlat'],
            hairColor: [HAIR],
            skinColor: [SKIN],
            clothing: ['overall'],
            clothesColor: ['1e293b'],
        },
        clothes: `<g>
            <path d="M28 280 L32 246 C38 214 64 198 96 194 L132 230 L168 194 C200 198 226 214 232 246 L236 280 Z" fill="${PALETTE.navy}"/>
            <path d="M56 200 L104 194 L118 280 L48 280 Z" fill="${PALETTE.gold}"/>
            <path d="M208 200 L160 194 L146 280 L216 280 Z" fill="${PALETTE.gold}"/>
            <rect x="50" y="242" width="60" height="10" fill="${PALETTE.paper}"/>
            <rect x="154" y="242" width="60" height="10" fill="${PALETTE.paper}"/>
            <rect x="50" y="252" width="60" height="6" fill="${PALETTE.emerald}"/>
            <rect x="154" y="252" width="60" height="6" fill="${PALETTE.emerald}"/>
        </g>`,
        // Helm proyek K3 di atas kepala pembimbing industri.
        front: `<g>
            <path d="M74 84 C74 42 102 20 140 20 C178 20 206 42 206 84 Z" fill="${PALETTE.gold}" stroke="${PALETTE.amber}" stroke-width="3"/>
            <path d="M126 18 L154 18 L158 78 L122 78 Z" fill="${PALETTE.amber}"/>
            <rect x="64" y="76" width="152" height="14" rx="7" fill="${PALETTE.amber}"/>
            <path d="M84 82 H196" stroke="${PALETTE.gold}" stroke-width="3" stroke-linecap="round"/>
            <circle cx="140" cy="52" r="11" fill="${PALETTE.paper}" stroke="${PALETTE.navy}" stroke-width="2"/>
            <path d="M135 52 L139 56 L146 48" fill="none" stroke="${PALETTE.emerald}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
        </g>`,
    },
    intern_teacher: {
        bg: '#F3EFEA',
        ring: '#E6D8DD',
        opts: {
            top: ['hijab'],
            hatColor: ['9d174d'],
            skinColor: [SKIN_LIGHT],
            clothing: ['collarAndSweater'],
            clothesColor: ['334155'],
        },
        clothes: `<g>
            <path d="M28 280 L32 246 C38 214 64 198 96 194 L132 232 L168 194 C200 198 226 214 232 246 L236 280 Z" fill="${PALETTE.slate}"/>
            <polygon points="100,194 132,238 164,194" fill="${PALETTE.paper}"/>
        </g>`,
        front: `<g>
            <rect x="124" y="250" width="32" height="24" rx="4" fill="#FFFFFF" stroke="${PALETTE.navy}" stroke-width="2"/>
            <rect x="129" y="256" width="22" height="5" rx="2" fill="${PALETTE.rose}"/>
            <rect x="129" y="264" width="16" height="4" rx="2" fill="${PALETTE.slateSoft}"/>
            <circle cx="180" cy="244" r="7" fill="${PALETTE.gold}" stroke="${PALETTE.paper}" stroke-width="2"/>
        </g>`,
    },
    examiner: {
        bg: '#EFECE6',
        ring: '#D6E4DE',
        opts: {
            top: ['theCaesarAndSidePart'],
            hairColor: [HAIR],
            skinColor: [SKIN],
            clothing: ['blazerAndShirt'],
            clothesColor: ['1e293b'],
            ...glasses('prescription01'),
        },
        clothes: `<g>
            <path d="M28 280 L32 246 C38 214 64 198 96 194 L132 238 L168 194 C200 198 226 214 232 246 L236 280 Z" fill="${PALETTE.navyDeep}"/>
            <polygon points="98,194 132,246 166,194" fill="#FFFFFF"/>
            <polygon points="125,216 139,216 142,228 132,236 122,228" fill="${PALETTE.emerald}"/>
            <polygon points="127,230 137,230 141,280 123,280" fill="${PALETTE.emerald}"/>
            <path d="M84 196 L124 254 L104 260 L72 212 Z" fill="${PALETTE.navy}"/>
            <path d="M180 196 L140 254 L160 260 L192 212 Z" fill="${PALETTE.navy}"/>
        </g>`,
        front: `<g>
            <circle cx="176" cy="242" r="11" fill="${PALETTE.gold}" stroke="${PALETTE.paper}" stroke-width="2"/>
            <path d="M171 242 L175 246 L182 238" fill="none" stroke="${PALETTE.navyDeep}" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
        </g>`,
    },
    alumni: {
        bg: '#F3EFEA',
        ring: '#DCE6E1',
        opts: {
            top: ['shortCurly'],
            hairColor: [HAIR_SOFT],
            skinColor: [SKIN_LIGHT],
            eyebrows: ['raisedExcitedNatural'],
            clothing: ['hoodie'],
            clothesColor: ['1e293b'],
        },
        clothes: `<g>
            <path d="M28 280 L32 246 C38 214 64 198 96 194 L132 232 L168 194 C200 198 226 214 232 246 L236 280 Z" fill="${PALETTE.navy}"/>
            <polygon points="102,194 132,238 162,194" fill="${PALETTE.paper}"/>
            <path d="M94 194 L132 240 L170 194" fill="none" stroke="${PALETTE.emerald}" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/>
            <line x1="132" y1="240" x2="132" y2="280" stroke="${PALETTE.stoneDeep}" stroke-width="3"/>
        </g>`,
        front: `<g>
            <circle cx="174" cy="240" r="9.5" fill="${PALETTE.gold}" stroke="${PALETTE.paper}" stroke-width="1.5"/>
            <path d="M174 234 L176 239 L181 239 L177 242 L178 247 L174 244 L170 247 L171 242 L167 239 L172 239 Z" fill="${PALETTE.paper}"/>
        </g>`,
    },
};

function renderRoleAvatar(name, cfg) {
    const raw = stripOuter(
        createAvatar(avataaars, {
            ...base,
            ...cfg.opts,
            seed: name,
        }).toString()
    );
    // Ganti pakaian bawaan Avataaars (di z-index antara leher dan rambut/hijab) dengan pakaian khusus peran.
    const inner = raw.replace(/<g transform="translate\(0 170\)">[\s\S]*?<\/g>/, cfg.clothes);

    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 280 280" width="128" height="128" fill="none">
<defs>
    <clipPath id="avatarClip">
        <circle cx="140" cy="140" r="134"/>
    </clipPath>
</defs>
<g clip-path="url(#avatarClip)">
    <circle cx="140" cy="140" r="134" fill="${cfg.bg}"/>
    <circle cx="140" cy="154" r="108" fill="${cfg.ring}" opacity="0.55"/>
    <path d="M22 236 Q140 196 258 236 L258 280 L22 280 Z" fill="${cfg.ring}" opacity="0.75"/>
    ${inner}
    ${cfg.front}
</g>
<circle cx="140" cy="140" r="132" stroke="${PALETTE.border}" stroke-width="4"/>
</svg>\n`;
}

function mascotHead(opts) {
    const svg = createAvatar(avataaars, {
        ...base,
        eyebrows: ['raisedExcitedNatural'],
        clothing: ['collarAndSweater'],
        clothesColor: ['ffffff'],
        ...opts,
    }).toString();
    // Hapus pakaian bawaan Avataaars dan pangkas hanya bahu telanjang di bawah leher agar rambut panjang tetap utuh.
    const inner = stripOuter(svg)
        .replace(/<g transform="translate\(0 170\)">[\s\S]*?<\/g>/, '')
        .replace('<path d="M132 36', '<path clip-path="url(#leher)" d="M132 36');
    return `<defs><clipPath id="leher"><path d="M0 0 H280 V192 H164 V216 H100 V192 H0 Z"/></clipPath></defs><svg x="40" y="4" width="280" height="280" viewBox="0 0 280 280" overflow="visible">${inner}</svg>`;
}

function mascotBack({ female }) {
    const lowerBody = female
        ? `
        <!-- Rok lipit abu-abu SMK dan sepatu sekolah -->
        <path d="M126 324 L234 324 L248 418 C204 424 156 424 112 418 Z" fill="${PALETTE.greyUniform}" stroke="${PALETTE.greyShadow}" stroke-width="2"/>
        <path d="M154 328 L146 420 M180 328 L180 421 M206 328 L214 420" stroke="${PALETTE.greyShadow}" stroke-width="2.5" stroke-linecap="round"/>
        <rect x="125" y="318" width="110" height="12" rx="4" fill="${PALETTE.slate}"/>
        <rect x="142" y="418" width="22" height="24" rx="6" fill="#FFFFFF" stroke="${PALETTE.border}" stroke-width="1.5"/>
        <rect x="196" y="418" width="22" height="24" rx="6" fill="#FFFFFF" stroke="${PALETTE.border}" stroke-width="1.5"/>
        <path d="M132 436 C132 428 142 426 156 426 L168 426 C174 426 178 431 178 438 L178 444 L132 444 Z" fill="${PALETTE.navyDeep}"/>
        <path d="M182 436 C182 431 186 426 192 426 L204 426 C218 426 228 428 228 436 L228 444 L182 444 Z" fill="${PALETTE.navyDeep}"/>
        <rect x="130" y="441" width="50" height="6" rx="3" fill="#FFFFFF" stroke="${PALETTE.border}" stroke-width="1"/>
        <rect x="180" y="441" width="50" height="6" rx="3" fill="#FFFFFF" stroke="${PALETTE.border}" stroke-width="1"/>`
        : `
        <!-- Celana panjang abu-abu SMK, sabuk hitam, dan sepatu sekolah -->
        <path d="M128 324 L178 324 L174 432 L134 432 Z" fill="${PALETTE.greyUniform}" stroke="${PALETTE.greyShadow}" stroke-width="2"/>
        <path d="M182 324 L232 324 L226 432 L186 432 Z" fill="${PALETTE.greyUniform}" stroke="${PALETTE.greyShadow}" stroke-width="2"/>
        <path d="M154 338 L152 424 M206 338 L208 424" stroke="${PALETTE.greyShadow}" stroke-width="2" stroke-linecap="round" opacity="0.65"/>
        <rect x="126" y="317" width="108" height="12" rx="4" fill="${PALETTE.navyDeep}"/>
        <rect x="171" y="316" width="18" height="14" rx="3" fill="${PALETTE.stoneDeep}" stroke="${PALETTE.slate}" stroke-width="1.5"/>
        <path d="M124 432 C124 424 136 420 152 420 L174 420 L176 442 L124 442 Z" fill="${PALETTE.navyDeep}"/>
        <path d="M184 420 L208 420 C224 420 236 424 236 432 L236 442 L184 442 Z" fill="${PALETTE.navyDeep}"/>
        <path d="M140 424 L148 424 M142 428 L150 428 M210 424 L218 424 M208 428 L216 428" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round"/>
        <rect x="122" y="439" width="56" height="7" rx="3.5" fill="#FFFFFF" stroke="${PALETTE.border}" stroke-width="1"/>
        <rect x="182" y="439" width="56" height="7" rx="3.5" fill="#FFFFFF" stroke="${PALETTE.border}" stroke-width="1"/>`;

    return `
    <!-- Latar gelombang lembut di belakang maskot -->
    <circle cx="182" cy="212" r="118" fill="${PALETTE.stone}"/>
    <circle cx="192" cy="198" r="94" fill="${PALETTE.stoneDeep}" opacity="0.55"/>
    <ellipse cx="180" cy="445" rx="76" ry="9" fill="${PALETTE.stoneDeep}" opacity="0.8"/>

    <!-- Ransel sekolah 3D di belakang bahu -->
    <rect x="108" y="198" width="144" height="116" rx="28" fill="${PALETTE.navyDeep}"/>
    <rect x="114" y="202" width="132" height="104" rx="24" fill="${PALETTE.slate}"/>
    <path d="M126 226 H234" stroke="${PALETTE.navyDeep}" stroke-width="3" opacity="0.5"/>
    <rect x="102" y="252" width="14" height="44" rx="7" fill="${PALETTE.navy}"/>
    <rect x="244" y="252" width="14" height="44" rx="7" fill="${PALETTE.navy}"/>

    ${lowerBody}

    <!-- Kemeja putih seragam SMK berlapis bayangan lipatan kain -->
    <path d="M120 208 C120 196 136 190 152 190 L168 190 L180 212 L192 190 L208 190 C224 190 240 196 240 208 L242 322 L118 322 Z" fill="#FFFFFF" stroke="${PALETTE.border}" stroke-width="2.5" stroke-linejoin="round"/>
    <path d="M122 260 L132 320 L118 320 Z" fill="${PALETTE.stone}"/>
    <path d="M238 260 L228 320 L242 320 Z" fill="${PALETTE.stone}"/>`;
}

function mascotFront({ skin, skinShadow, female }) {
    const hairClip = female
        ? `<rect x="122" y="92" width="16" height="6" rx="3" transform="rotate(-18 122 92)" fill="${PALETTE.gold}" stroke="${PALETTE.navy}" stroke-width="1.5"/>`
        : '';

    return `
    ${hairClip}
    <!-- Kerah kemeja dan dasi SMK di depan pangkal leher -->
    <polygon points="150,188 180,218 162,230 140,200" fill="#FFFFFF" stroke="${PALETTE.border}" stroke-width="2" stroke-linejoin="round"/>
    <polygon points="210,188 180,218 198,230 220,200" fill="#FFFFFF" stroke="${PALETTE.border}" stroke-width="2" stroke-linejoin="round"/>
    <polygon points="172,212 188,212 191,225 180,233 169,225" fill="${PALETTE.navy}"/>
    <polygon points="174,227 186,227 190,298 180,308 170,298" fill="${PALETTE.slate}"/>
    <path d="M173 242 L187 235 M172 256 L188 248" stroke="#FFFFFF" stroke-width="2" opacity="0.6"/>

    <!-- Saku kemeja dan lencana SMK -->
    <rect x="198" y="242" width="24" height="26" rx="4" fill="#FFFFFF" stroke="${PALETTE.border}" stroke-width="1.8"/>
    <circle cx="210" cy="255" r="6.5" fill="${PALETTE.gold}"/>
    <circle cx="210" cy="255" r="3.2" fill="${PALETTE.emerald}"/>

    <!-- Tali ransel di atas bahu lengkap dengan gesper -->
    <path d="M136 194 C130 232 130 278 136 318" stroke="${PALETTE.navy}" stroke-width="13" stroke-linecap="round" fill="none"/>
    <path d="M224 194 C230 232 230 278 224 318" stroke="${PALETTE.navy}" stroke-width="13" stroke-linecap="round" fill="none"/>
    <rect x="125" y="246" width="16" height="8" rx="2" fill="${PALETTE.stoneDeep}" stroke="${PALETTE.slate}" stroke-width="1.5"/>
    <rect x="219" y="246" width="16" height="8" rx="2" fill="${PALETTE.stoneDeep}" stroke="${PALETTE.slate}" stroke-width="1.5"/>

    <!-- Lengan kanan (menyapa / semangat) dengan lengan kemeja berlipat dan jari tangan utuh -->
    <path d="M234 200 C250 202 262 214 266 232 L244 244 C238 232 234 224 228 220 Z" fill="#FFFFFF" stroke="${PALETTE.border}" stroke-width="2" stroke-linejoin="round"/>
    <path d="M242 242 L266 230" stroke="${PALETTE.stoneDeep}" stroke-width="4" stroke-linecap="round"/>
    <path d="M246 234 C258 238 272 206 276 160 L256 156 C252 192 244 214 238 224 Z" fill="${skin}" stroke="${skinShadow}" stroke-width="1.5"/>
    <!-- Telapak dan jari tangan menyapa -->
    <g transform="translate(252 124)">
        <rect x="4" y="14" width="26" height="24" rx="10" fill="${skin}" stroke="${skinShadow}" stroke-width="1.5"/>
        <rect x="-2" y="18" width="10" height="15" rx="5" transform="rotate(-24 3 25)" fill="${skin}" stroke="${skinShadow}" stroke-width="1.5"/>
        <rect x="5" y="2" width="6.5" height="17" rx="3.2" fill="${skin}" stroke="${skinShadow}" stroke-width="1.2"/>
        <rect x="12" y="0" width="6.5" height="19" rx="3.2" fill="${skin}" stroke="${skinShadow}" stroke-width="1.2"/>
        <rect x="19" y="2" width="6.5" height="17" rx="3.2" fill="${skin}" stroke="${skinShadow}" stroke-width="1.2"/>
        <rect x="25.5" y="6" width="6" height="15" rx="3" fill="${skin}" stroke="${skinShadow}" stroke-width="1.2"/>
        <path d="M9 24 Q17 28 25 23" fill="none" stroke="${skinShadow}" stroke-width="1.8" stroke-linecap="round"/>
    </g>
    <!-- Garis aksen semangat hangat -->
    <path d="M294 120 L305 110 M298 138 L312 135 M284 106 L289 92" stroke="${PALETTE.gold}" stroke-width="4.5" stroke-linecap="round"/>

    <!-- Lengan kiri mendekap buku jurnal -->
    <path d="M126 200 C110 202 98 214 94 232 L116 244 C122 232 126 224 132 220 Z" fill="#FFFFFF" stroke="${PALETTE.border}" stroke-width="2" stroke-linejoin="round"/>
    <path d="M94 230 L118 242" stroke="${PALETTE.stoneDeep}" stroke-width="4" stroke-linecap="round"/>
    <path d="M102 234 C98 268 112 296 146 302 L150 284 C124 278 116 258 118 238 Z" fill="${skin}" stroke="${skinShadow}" stroke-width="1.5"/>

    <!-- Buku Jurnal PKL Jurnalin (hardcover navy, punggung emerald, lembaran kertas, logo paraf) -->
    <g transform="rotate(-6 178 282)">
        <rect x="140" y="234" width="78" height="88" rx="8" fill="${PALETTE.paper}" stroke="${PALETTE.navy}" stroke-width="2"/>
        <rect x="138" y="238" width="78" height="88" rx="8" fill="${PALETTE.navy}"/>
        <rect x="138" y="238" width="11" height="88" rx="4" fill="${PALETTE.emerald}"/>
        <rect x="155" y="246" width="54" height="72" rx="4" fill="none" stroke="#FFFFFF" stroke-opacity="0.25" stroke-width="1.8"/>
        <path d="M167 268 C174 264 183 263 190 264 C187 267 186 275 185 283 C184 291 180 297 174 297 C168 297 167 291 171 289 C177 286 185 291 192 286" fill="none" stroke="#FFFFFF" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"/>
        <rect x="165" y="304" width="34" height="5" rx="2.5" fill="${PALETTE.gold}"/>
    </g>

    <!-- Jari tangan kiri memegang tepi buku jurnal -->
    <g transform="translate(136 280)">
        <ellipse cx="14" cy="14" rx="13" ry="10" fill="${skin}" stroke="${skinShadow}" stroke-width="1.5"/>
        <path d="M10 9 H22 M11 14 H23 M10 19 H21" stroke="${skinShadow}" stroke-width="1.8" stroke-linecap="round"/>
    </g>`;
}

const mascots = {
    'mascot-male': {
        opts: { top: ['shortFlat'], hairColor: [HAIR], skinColor: [SKIN_LIGHT] },
        skin: '#EDB98A',
        skinShadow: '#C68B59',
        female: false,
    },
    'mascot-female': {
        opts: { top: ['straight01'], hairColor: [HAIR], skinColor: [SKIN_LIGHT] },
        skin: '#EDB98A',
        skinShadow: '#C68B59',
        female: true,
    },
    'mascot-neutral': {
        opts: { top: ['shortRound'], hairColor: [HAIR], skinColor: [SKIN] },
        skin: '#D99B66',
        skinShadow: '#B57844',
        female: false,
    },
};

mkdirSync(outDir, { recursive: true });

for (const [name, cfg] of Object.entries(roleConfigs)) {
    const svg = renderRoleAvatar(name, cfg);
    writeFileSync(new URL(`${name}.svg`, outDir), svg);
    console.log(name, `${(svg.length / 1024).toFixed(1)} KB`);
}

for (const [name, v] of Object.entries(mascots)) {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="60 14 256 438" width="240" height="410" fill="none">${mascotBack(v)}${mascotHead({ ...v.opts, seed: name })}${mascotFront(v)}</svg>\n`;
    writeFileSync(new URL(`${name}.svg`, outDir), svg);
    console.log(name, `${(svg.length / 1024).toFixed(1)} KB`);
}

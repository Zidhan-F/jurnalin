// Karakter kartun Jurnalin (gaya mockup di docs/desain/mockup): avatar peran dan maskot siswa seluruh badan.
// Kepala memakai Avataaars karya Pablo Stanley lewat DiceBear (bebas untuk penggunaan pribadi dan komersial);
// badan maskot (seragam putih abu-abu, ransel, buku jurnal berlogo paraf, tangan melambai) digambar khusus untuk Jurnalin.
// Jalankan manual: node scripts/generate-characters.mjs. Hasilnya di-commit; aplikasi tidak bergantung pada paket ini saat berjalan.
import { createAvatar } from '@dicebear/core';
import * as avataaars from '@dicebear/avataaars';
import { writeFileSync, mkdirSync } from 'node:fs';

const outDir = new URL('../public/images/illustrations/', import.meta.url);
const NAVY = '#1E293B', NAVY2 = '#334155', SHIRT = '#FFFFFF', SHADE = '#E7E2DA', GREY = '#6B7280', GREY2 = '#565D69', SHOE = '#262E33', EMERALD = '#047857';
const HAIR = '2c1b18', SKIN = 'd08b5b', SKIN_LIGHT = 'edb98a';
const base = { eyes: ['happy'], mouth: ['smile'], eyebrows: ['defaultNatural'], facialHairProbability: 0, accessoriesProbability: 0 };
const glasses = (style) => ({ accessories: [style], accessoriesProbability: 100, accessoriesColor: ['262e33'] });

// Avatar peran (kepala dan bahu). Siswa memakai seragam putih; guru pembimbing berhijab dan berkacamata seperti poster.
const avatars = {
    'student-male': { top: ['shortFlat'], hairColor: [HAIR], skinColor: [SKIN], clothing: ['collarAndSweater'], clothesColor: ['ffffff'] },
    'student-female': { top: ['straight01'], hairColor: [HAIR], skinColor: [SKIN_LIGHT], clothing: ['collarAndSweater'], clothesColor: ['ffffff'] },
    'student-neutral': { top: ['shortRound'], hairColor: [HAIR], skinColor: [SKIN], clothing: ['collarAndSweater'], clothesColor: ['ffffff'] },
    teacher: { top: ['hijab'], hatColor: ['3c4f5c'], skinColor: [SKIN_LIGHT], clothing: ['blazerAndShirt'], clothesColor: ['262e33'], ...glasses('round') },
    admin: { top: ['shortWaved'], hairColor: [HAIR], skinColor: [SKIN], clothing: ['blazerAndSweater'], clothesColor: ['3c4f5c'], ...glasses('prescription02') },
    mentor: { top: ['shortFlat'], hairColor: [HAIR], skinColor: [SKIN], clothing: ['overall'], clothesColor: ['25557c'] },
    intern_teacher: { top: ['hijab'], hatColor: ['ffafb9'], skinColor: [SKIN_LIGHT], clothing: ['collarAndSweater'], clothesColor: ['65c9ff'] },
    examiner: { top: ['theCaesarAndSidePart'], hairColor: [HAIR], skinColor: [SKIN], clothing: ['blazerAndShirt'], clothesColor: ['262e33'], ...glasses('prescription01') },
    alumni: { top: ['shortCurly'], hairColor: [HAIR], skinColor: [SKIN], clothing: ['hoodie'], clothesColor: ['5199e4'] },
};

const strip = (svg) => svg.replace(/<metadata[\s\S]*?<\/metadata>/, '');

function head(opts) {
    const svg = createAvatar(avataaars, { ...base, clothing: ['collarAndSweater'], clothesColor: ['ffffff'], ...opts }).toString();
    // Hanya kepala dan leher dari Avataaars; bahu dan badan digambar sendiri agar menyambung dengan lengan.
    const inner = strip(svg).replace(/^<svg[^>]*>/, '').replace(/<\/svg>\s*$/, '');
    return `<defs><clipPath id="kepala"><rect x="0" y="0" width="280" height="226"/></clipPath></defs><svg x="40" y="0" width="280" height="280" viewBox="0 0 280 280" overflow="visible"><g clip-path="url(#kepala)">${inner}</g></svg>`;
}

function body({ skin, female }) {
  const legs = female
    ? `<path d="M132 326 L228 326 L242 444 L118 444 Z" fill="${GREY}"/><path d="M180 326 L182 444" stroke="${GREY2}" stroke-width="3"/>
       <rect x="138" y="440" width="38" height="14" rx="7" fill="${SHOE}"/><rect x="186" y="440" width="38" height="14" rx="7" fill="${SHOE}"/>`
    : `<path d="M134 326 L178 326 L174 440 L138 440 Z" fill="${GREY}"/><path d="M182 326 L226 326 L222 440 L186 440 Z" fill="${GREY}"/>
       <rect x="132" y="322" width="96" height="14" rx="4" fill="${GREY2}"/>
       <rect x="128" y="436" width="50" height="16" rx="8" fill="${SHOE}"/><rect x="182" y="436" width="50" height="16" rx="8" fill="${SHOE}"/>`;
  return `
  <rect x="112" y="210" width="136" height="104" rx="28" fill="${NAVY2}"/>
  ${legs}
  <path d="M128 208 C128 200 140 196 152 196 L170 196 L180 214 L190 196 L208 196 C220 196 232 200 232 208 L236 330 L124 330 Z" fill="${SHIRT}"/>
  <path d="M152 196 L170 196 L180 214 M190 196 L208 196 M180 214 L190 196" stroke="${SHADE}" stroke-width="3" fill="none" stroke-linejoin="round"/>
  <path d="M180 216 V330" stroke="${SHADE}" stroke-width="3"/>
  <path d="M142 200 C138 240 138 290 142 328" stroke="${NAVY}" stroke-width="11" stroke-linecap="round" fill="none"/>
  <path d="M218 200 C222 240 222 290 218 328" stroke="${NAVY}" stroke-width="11" stroke-linecap="round" fill="none"/>
  <path d="M232 206 C246 206 256 214 258 228 L242 238 C238 230 234 226 230 226 Z" fill="${SHIRT}"/>
  <path d="M250 232 C262 210 270 184 272 158" stroke="${skin}" stroke-width="18" stroke-linecap="round" fill="none"/>
  <circle cx="273" cy="146" r="15" fill="${skin}"/>
  <path d="M262 141 h16 M262 149 h18" stroke="#0000001f" stroke-width="2.5" stroke-linecap="round"/>
  <path d="M292 126 l9 -9 M296 144 l13 -2 M285 112 l4 -13" stroke="#F4B400" stroke-width="5" stroke-linecap="round"/>
  <path d="M128 206 C114 206 104 214 102 228 L118 238 C122 230 126 226 130 226 Z" fill="${SHIRT}"/>
  <path d="M110 234 C108 266 120 290 148 294" stroke="${skin}" stroke-width="18" stroke-linecap="round" fill="none"/>
  <g transform="rotate(-6 180 284)">
    <rect x="142" y="242" width="76" height="86" rx="8" fill="${NAVY}"/>
    <rect x="148" y="248" width="64" height="74" rx="5" fill="none" stroke="#FFFFFF33" stroke-width="2"/>
    <path d="M160 268C167 264 176 263 183 264C180 267 179 275 178 283C177 291 173 297 167 297C161 297 160 291 164 289C170 286 178 291 185 286" fill="none" stroke="#FFFFFF" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"/>
    <rect x="142" y="242" width="9" height="86" rx="4" fill="${EMERALD}"/>
  </g>
  <circle cx="150" cy="294" r="13" fill="${skin}"/>`;
}

const mascots = {
    'mascot-male': { opts: { top: ['shortFlat'], hairColor: [HAIR], skinColor: [SKIN] }, skin: '#D08B5B', female: false },
    'mascot-female': { opts: { top: ['straight01'], hairColor: [HAIR], skinColor: [SKIN_LIGHT] }, skin: '#EDB98A', female: true },
    'mascot-neutral': { opts: { top: ['shortRound'], hairColor: [HAIR], skinColor: [SKIN] }, skin: '#D08B5B', female: false },
};

mkdirSync(outDir, { recursive: true });
for (const [name, opts] of Object.entries(avatars)) {
    const svg = strip(createAvatar(avataaars, { ...base, ...opts, seed: name }).toString()).replace('<svg ', '<svg width="128" height="128" ');
    writeFileSync(new URL(`${name}.svg`, outDir), svg + '\n');
    console.log(name, `${(svg.length / 1024).toFixed(1)} KB`);
}
for (const [name, v] of Object.entries(mascots)) {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="84 14 240 446" width="240" height="446">${head({ ...v.opts, seed: name })}${body(v)}</svg>\n`;
    writeFileSync(new URL(`${name}.svg`, outDir), svg);
    console.log(name, `${(svg.length / 1024).toFixed(1)} KB`);
}

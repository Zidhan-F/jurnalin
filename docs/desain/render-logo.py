"""Render ulang ikon raster Jurnalin dari path paraf J.

Jalankan dari root repo setelah mengubah public/images/jurnalin-paraf.svg:
    python docs/desain/render-logo.py

Butuh Pillow. Path di bawah harus sama dengan atribut d di SVG.
Menghasilkan public/images/jurnalin-paraf.png (512px, PWA) dan
public/favicon.ico (16 sampai 256px). Naikkan juga ?v= favicon di layout.
"""
from pathlib import Path

from PIL import Image, ImageDraw

ROOT = Path(__file__).resolve().parents[2]
COLOR = (0x1E, 0x29, 0x3B, 255)
STROKE = 8.0
START = (18, 20)
SEGMENTS = [
    ((30, 14), (46, 12), (58, 14)),
    ((52, 18), (50, 32), (48, 46)),
    ((46, 60), (40, 70), (30, 70)),
    ((20, 70), (18, 60), (26, 56)),
    ((36, 52), (50, 60), (62, 52)),
]


def points(steps=400):
    p0 = START
    for c1, c2, p3 in SEGMENTS:
        for i in range(steps + 1):
            t = i / steps
            u = 1 - t
            x = u**3 * p0[0] + 3 * u * u * t * c1[0] + 3 * u * t * t * c2[0] + t**3 * p3[0]
            y = u**3 * p0[1] + 3 * u * u * t * c1[1] + 3 * u * t * t * c2[1] + t**3 * p3[1]
            yield x, y
        p0 = p3


def render(size):
    big = size * 8
    scale = big / 80
    im = Image.new("RGBA", (big, big), (0, 0, 0, 0))
    draw = ImageDraw.Draw(im)
    r = STROKE / 2 * scale
    for x, y in points():
        cx, cy = x * scale, y * scale
        draw.ellipse((cx - r, cy - r, cx + r, cy + r), fill=COLOR)
    return im.resize((size, size), Image.LANCZOS)


if __name__ == "__main__":
    render(512).save(ROOT / "public/images/jurnalin-paraf.png", optimize=True)
    sizes = [16, 24, 32, 48, 64, 128, 256]
    icons = [render(s) for s in sizes]
    icons[-1].save(
        ROOT / "public/favicon.ico",
        format="ICO",
        sizes=[(s, s) for s in sizes],
        append_images=icons[:-1],
    )
    print("OK: jurnalin-paraf.png dan favicon.ico diperbarui")

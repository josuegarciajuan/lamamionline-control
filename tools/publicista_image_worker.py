#!/usr/bin/env python3
import argparse
import json
import os
from typing import Dict, Tuple

import cv2
import numpy as np
from PIL import Image


# ---------------------------------------------------------------------------
# Utilidades de E/S
# ---------------------------------------------------------------------------

def imread_unicode(path: str) -> np.ndarray:
    data = np.fromfile(path, dtype=np.uint8)
    img = cv2.imdecode(data, cv2.IMREAD_UNCHANGED)
    if img is None:
        raise RuntimeError(f"No se pudo leer la imagen: {path}")
    if img.ndim == 2:
        img = cv2.cvtColor(img, cv2.COLOR_GRAY2BGR)
    return img


def imwrite_unicode(path: str, image: np.ndarray, quality: int = 92) -> None:
    os.makedirs(os.path.dirname(path), exist_ok=True)
    ext = os.path.splitext(path)[1].lower()
    if image.ndim == 3 and image.shape[2] == 4:
        if ext in ('.jpg', '.jpeg', '.webp'):
            bgr = rgba_to_bgr_for_jpg(image)
            return imwrite_unicode(path, bgr, quality=quality)
        ok, buf = cv2.imencode('.png', image)
    else:
        if ext in ('.jpg', '.jpeg'):
            ok, buf = cv2.imencode('.jpg', image, [int(cv2.IMWRITE_JPEG_QUALITY), int(quality)])
        elif ext == '.png':
            ok, buf = cv2.imencode('.png', image)
        elif ext == '.webp':
            ok, buf = cv2.imencode('.webp', image, [int(cv2.IMWRITE_WEBP_QUALITY), int(quality)])
        else:
            ok, buf = cv2.imencode('.jpg', image, [int(cv2.IMWRITE_JPEG_QUALITY), int(quality)])
    if not ok:
        raise RuntimeError(f"No se pudo codificar la imagen de salida: {path}")
    buf.tofile(path)


def pil_to_rgba_array(image: Image.Image) -> np.ndarray:
    rgba = image.convert('RGBA')
    arr = np.array(rgba, dtype=np.uint8)
    return cv2.cvtColor(arr, cv2.COLOR_RGBA2BGRA)


def rgba_to_bgr_for_jpg(image_bgra: np.ndarray, bg_color: Tuple[int, int, int] = (248, 248, 248)) -> np.ndarray:
    if image_bgra.ndim != 3 or image_bgra.shape[2] != 4:
        return image_bgra
    alpha = image_bgra[:, :, 3:4].astype(np.float32) / 255.0
    bgr = image_bgra[:, :, :3].astype(np.float32)
    bg = np.zeros_like(bgr)
    bg[:, :, 0] = bg_color[2]
    bg[:, :, 1] = bg_color[1]
    bg[:, :, 2] = bg_color[0]
    out = bgr * alpha + bg * (1.0 - alpha)
    return np.clip(out, 0, 255).astype(np.uint8)


# ---------------------------------------------------------------------------
# Preparación 1:1 sin deformar
# ---------------------------------------------------------------------------

def build_square_canvas(input_path: str, square_size: int) -> Dict:
    source = Image.open(input_path)
    source = source.convert('RGBA')
    width, height = source.size
    if width <= 0 or height <= 0:
        raise RuntimeError('La imagen de entrada tiene dimensiones inválidas.')

    side = max(width, height)
    scale = float(square_size) / float(side)
    resized_w = max(1, int(round(width * scale)))
    resized_h = max(1, int(round(height * scale)))
    resized = source.resize((resized_w, resized_h), Image.LANCZOS)

    canvas = Image.new('RGBA', (square_size, square_size), (0, 0, 0, 0))
    offset_x = (square_size - resized_w) // 2
    offset_y = (square_size - resized_h) // 2
    canvas.paste(resized, (offset_x, offset_y), resized)

    return {
        'canvas_bgra': pil_to_rgba_array(canvas),
        'original_width': int(width),
        'original_height': int(height),
        'placed_width': int(resized_w),
        'placed_height': int(resized_h),
        'offset_x': int(offset_x),
        'offset_y': int(offset_y),
        'scale': float(scale),
        'transparent_padding': bool(resized_w != square_size or resized_h != square_size),
    }


# ---------------------------------------------------------------------------
# Blur manual
# ---------------------------------------------------------------------------

def blur_face_elliptical(square_bgr: np.ndarray, face_box_square: Tuple[int, int, int, int], intensity: float = 8.0) -> np.ndarray:
    x, y, w, h = face_box_square
    h_img, w_img = square_bgr.shape[:2]

    intensity = max(1.0, min(20.0, float(intensity)))
    cx = x + w / 2.0
    cy = y + h / 2.0
    expand = 0.12 + ((intensity - 1.0) / 19.0) * 0.22
    rx = int(round(w / 2.0 * (1.0 + expand)))
    ry = int(round(h / 2.0 * (1.0 + expand * 1.3)))
    rx = min(rx, int(cx), int(w_img - cx))
    ry = min(ry, int(cy), int(h_img - cy))
    if rx < 5 or ry < 5:
        return square_bgr.copy()

    out = square_bgr.copy().astype(np.float32)
    mask = np.zeros((h_img, w_img), dtype=np.float32)
    cv2.ellipse(mask, (int(cx), int(cy)), (rx, ry), 0, 0, 360, 1.0, -1)

    feather_radius = max(9, int(round(min(rx, ry) * (0.18 + intensity * 0.022))))
    if feather_radius % 2 == 0:
        feather_radius += 1
    mask = cv2.GaussianBlur(mask, (feather_radius, feather_radius), 0)

    scale = 0.65 + (intensity / 10.0)
    k1 = max(21, int(round((rx // 3) * scale)) | 1)
    k2 = max(41, int(round((rx // 2) * scale)) | 1)
    k3 = max(61, int(round(rx * scale)) | 1)
    blur1 = cv2.GaussianBlur(out, (k1, k1), 0)
    blur2 = cv2.GaussianBlur(out, (k2, k2), 0)
    blur3 = cv2.GaussianBlur(out, (k3, k3), 0)
    blurred = blur1 * 0.2 + blur2 * 0.3 + blur3 * 0.5

    mask3 = np.stack([mask, mask, mask], axis=2)
    result = out * (1.0 - mask3) + blurred * mask3
    return np.clip(result, 0, 255).astype(np.uint8)


# ---------------------------------------------------------------------------
# Preview
# ---------------------------------------------------------------------------

def save_preview(image: np.ndarray, preview_path: str, preview_size: int) -> None:
    resized = cv2.resize(image, (preview_size, preview_size), interpolation=cv2.INTER_AREA)
    if resized.ndim == 3 and resized.shape[2] == 4:
        resized = rgba_to_bgr_for_jpg(resized)
    imwrite_unicode(preview_path, resized, quality=90)


# ---------------------------------------------------------------------------
# Comando principal: prepare-source
# ---------------------------------------------------------------------------

def prepare_source(args: argparse.Namespace) -> Dict:
    prepared = build_square_canvas(args.input, args.square_size)
    square = prepared['canvas_bgra']
    preview = rgba_to_bgr_for_jpg(square)

    imwrite_unicode(args.output_square, square, quality=92)
    save_preview(preview, args.output_preview, args.preview_size)

    result = {
        'ok': True,
        'input_path': args.input,
        'original_width': prepared['original_width'],
        'original_height': prepared['original_height'],
        'square_size': int(args.square_size),
        'preview_size': int(args.preview_size),
        'placed_image': {
            'x': prepared['offset_x'],
            'y': prepared['offset_y'],
            'w': prepared['placed_width'],
            'h': prepared['placed_height'],
        },
        'scale': prepared['scale'],
        'transparent_padding': prepared['transparent_padding'],
        'face_detected': False,
        'face_blur_applied': False,
        'output_square': args.output_square,
        'output_preview': args.output_preview,
        'warnings': [
            'Preparación 1:1 sin deformar: la imagen original se mantiene intacta y se centra sobre un lienzo cuadrado.',
            'No se ha aplicado detección facial ni blur automático en esta preparación local.',
        ],
    }

    os.makedirs(os.path.dirname(args.output_json), exist_ok=True)
    with open(args.output_json, 'w', encoding='utf-8') as f:
        json.dump(result, f, ensure_ascii=False, indent=2)
    return result



# ---------------------------------------------------------------------------
# Comando: export-png
# ---------------------------------------------------------------------------

def export_png(args: argparse.Namespace) -> Dict:
    image = Image.open(args.input)
    image = image.convert('RGBA')
    os.makedirs(os.path.dirname(args.output_png), exist_ok=True)
    image.save(args.output_png, format='PNG', optimize=False)

    result = {
        'ok': True,
        'input_path': args.input,
        'output_png': args.output_png,
        'width': int(image.size[0]),
        'height': int(image.size[1]),
        'mime_type': 'image/png',
    }

    os.makedirs(os.path.dirname(args.output_json), exist_ok=True)
    with open(args.output_json, 'w', encoding='utf-8') as f:
        json.dump(result, f, ensure_ascii=False, indent=2)
    return result


# ---------------------------------------------------------------------------
# Comando: apply-manual-blur
# ---------------------------------------------------------------------------

def apply_manual_blur(args: argparse.Namespace) -> Dict:
    img = imread_unicode(args.input)
    if img.ndim == 3 and img.shape[2] == 4:
        img = rgba_to_bgr_for_jpg(img)
    img_h, img_w = img.shape[:2]

    bx_px = int(args.bx * img_w)
    by_px = int(args.by * img_h)
    bw_px = int(args.bw * img_w)
    bh_px = int(args.bh * img_h)

    bx_px = max(0, bx_px)
    by_px = max(0, by_px)
    bw_px = min(bw_px, img_w - bx_px)
    bh_px = min(bh_px, img_h - by_px)

    face_box = (bx_px, by_px, bw_px, bh_px)
    blurred = blur_face_elliptical(img, face_box, intensity=args.intensity)
    imwrite_unicode(args.output_face_blur, blurred, quality=92)
    save_preview(blurred, args.output_preview, args.preview_size)

    result = {
        'ok': True,
        'input_path': args.input,
        'manual_blur': True,
        'bx': args.bx, 'by': args.by, 'bw': args.bw, 'bh': args.bh, 'intensity': args.intensity,
        'bx_px': bx_px, 'by_px': by_px, 'bw_px': bw_px, 'bh_px': bh_px,
        'output_face_blur': args.output_face_blur,
        'output_preview': args.output_preview,
    }

    os.makedirs(os.path.dirname(args.output_json), exist_ok=True)
    with open(args.output_json, 'w', encoding='utf-8') as f:
        json.dump(result, f, ensure_ascii=False, indent=2)
    return result


# ---------------------------------------------------------------------------
# Outpainting: crear lienzo con ratio de móvil + máscara
# ---------------------------------------------------------------------------

PHONE_RATIOS = [
    (2, 3),
    (3, 4),
    (4, 5),
    (9, 16),
]


def pad_canvas_for_outpaint(args: argparse.Namespace) -> Dict:
    source = Image.open(args.input).convert('RGBA')
    sw, sh = source.size
    if sw <= 0 or sh <= 0:
        raise RuntimeError('La imagen de entrada tiene dimensiones inválidas.')

    if args.ratio:
        parts = args.ratio.split(':')
        ratio_w, ratio_h = int(parts[0]), int(parts[1])
    else:
        import random
        ratio_w, ratio_h = random.choice(PHONE_RATIOS)

    base_size = int(args.base_size)
    if ratio_w >= ratio_h:
        canvas_w = base_size
        canvas_h = max(1, int(round(base_size * ratio_h / ratio_w)))
    else:
        canvas_h = base_size
        canvas_w = max(1, int(round(base_size * ratio_w / ratio_h)))

    if canvas_w < sw or canvas_h < sh:
        scale_factor = max(sw / canvas_w, sh / canvas_h)
        canvas_w = max(sw, int(round(canvas_w * scale_factor)))
        canvas_h = max(sh, int(round(canvas_h * scale_factor)))

    canvas = Image.new('RGBA', (canvas_w, canvas_h), (255, 255, 255, 255))
    ox = (canvas_w - sw) // 2
    oy = (canvas_h - sh) // 2
    canvas.paste(source, (ox, oy), source)

    canvas_rgb = canvas.convert('RGB')
    os.makedirs(os.path.dirname(args.output_padded), exist_ok=True)
    canvas_rgb.save(args.output_padded, 'PNG', optimize=False)

    mask = Image.new('L', (canvas_w, canvas_h), 255)
    mask_draw_area = Image.new('L', (sw, sh), 0)
    mask.paste(mask_draw_area, (ox, oy))
    os.makedirs(os.path.dirname(args.output_mask), exist_ok=True)
    mask.save(args.output_mask, 'PNG', optimize=False)

    result = {
        'ok': True,
        'input_path': args.input,
        'output_padded': args.output_padded,
        'output_mask': args.output_mask,
        'ratio': f'{ratio_w}:{ratio_h}',
        'canvas_width': int(canvas_w),
        'canvas_height': int(canvas_h),
        'original_width': int(sw),
        'original_height': int(sh),
        'offset_x': int(ox),
        'offset_y': int(oy),
    }

    if args.output_json:
        os.makedirs(os.path.dirname(args.output_json), exist_ok=True)
        with open(args.output_json, 'w', encoding='utf-8') as f:
            json.dump(result, f, ensure_ascii=False, indent=2)
    return result


# ---------------------------------------------------------------------------
# Detección de fondo gris neutro para máscara binaria
# ---------------------------------------------------------------------------

def extract_mask_for_neutro_bg(args: argparse.Namespace) -> Dict:
    """Detecta el fondo gris neutro y genera una máscara binaria.
    Blanco (255) = fondo a reemplazar, Negro (0) = persona a preservar."""
    img = cv2.imread(args.input)
    if img is None:
        raise RuntimeError(f"No se pudo leer la imagen: {args.input}")

    h, w = img.shape[:2]

    # Convertir a HSV para detectar grises (baja saturación, valor medio-alto)
    hsv = cv2.cvtColor(img, cv2.COLOR_BGR2HSV)
    
    # Grises: saturación baja (< 40), valor entre 100 y 240
    lower = np.array([0, 0, 100])
    upper = np.array([180, 40, 240])
    mask_gray = cv2.inRange(hsv, lower, upper)

    # Dilatar ligeramente para capturar bordes suaves
    kernel = np.ones((5, 5), np.uint8)
    mask_gray = cv2.dilate(mask_gray, kernel, iterations=2)

    # Invertir: el fondo gris debe ser blanco (editar), persona negra (preservar)
    # Pero queremos que la máscara para OpenAI sea: blanco=editar, negro=preservar
    # OpenCV: inRange devuelve 255 para los píxeles que coinciden (grises)
    # Ya tenemos: gris=255, persona=0 → esto es correcto para OpenAI edit
    
    # Suavizar bordes
    mask_gray = cv2.GaussianBlur(mask_gray, (21, 21), 0)

    os.makedirs(os.path.dirname(args.output_mask), exist_ok=True)
    cv2.imwrite(args.output_mask, mask_gray)

    # Calcular % de fondo detectado
    total_pixels = h * w
    bg_pixels = np.count_nonzero(mask_gray > 128)
    bg_percent = round(bg_pixels / total_pixels * 100, 1)

    result = {
        'ok': True,
        'input_path': args.input,
        'output_mask': args.output_mask,
        'width': int(w),
        'height': int(h),
        'bg_percent': bg_percent,
        'bg_detected': bg_percent > 20,
    }

    if args.output_json:
        os.makedirs(os.path.dirname(args.output_json), exist_ok=True)
        with open(args.output_json, 'w', encoding='utf-8') as f:
            json.dump(result, f, ensure_ascii=False, indent=2)
    return result


# ---------------------------------------------------------------------------
# CLI
# ---------------------------------------------------------------------------

def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description='Worker local de imagen para Publicista')
    sub = parser.add_subparsers(dest='command', required=True)

    p = sub.add_parser('prepare-source', help='Prepara una base 1:1 sin deformar y sin blur automático')
    p.add_argument('--input', required=True)
    p.add_argument('--output-json', required=True)
    p.add_argument('--output-square', required=True)
    p.add_argument('--output-preview', required=True)
    p.add_argument('--preview-size', type=int, default=320)
    p.add_argument('--square-size', type=int, default=1024)

    e = sub.add_parser('export-png', help='Convierte cualquier imagen soportada a PNG real para endpoints estrictos')
    e.add_argument('--input', required=True)
    e.add_argument('--output-png', required=True)
    e.add_argument('--output-json', required=True)

    m = sub.add_parser('apply-manual-blur', help='Aplica blur elíptico en bounding-box definido manualmente (bx/by/bw/bh como fracción 0-1)')
    m.add_argument('--input', required=True)
    m.add_argument('--output-face-blur', required=True)
    m.add_argument('--output-preview', required=True)
    m.add_argument('--output-json', required=True)
    m.add_argument('--bx', type=float, required=True, help='X top-left del bounding box como fracción 0-1')
    m.add_argument('--by', type=float, required=True, help='Y top-left del bounding box como fracción 0-1')
    m.add_argument('--bw', type=float, required=True, help='Ancho del bounding box como fracción 0-1')
    m.add_argument('--bh', type=float, required=True, help='Alto del bounding box como fracción 0-1')
    m.add_argument('--intensity', type=float, default=8.0, help='Intensidad del blur manual entre 1 y 20')
    m.add_argument('--preview-size', type=int, default=320)

    pc = sub.add_parser('pad-canvas', help='Crea lienzo con ratio de móvil y máscara para outpainting')
    pc.add_argument('--input', required=True)
    pc.add_argument('--output-padded', required=True)
    pc.add_argument('--output-mask', required=True)
    pc.add_argument('--output-json', default='')
    pc.add_argument('--ratio', default='', help='Ratio destino (ej: 2:3). Si no se especifica, aleatorio.')
    pc.add_argument('--base-size', type=int, default=1024, help='Tamaño base del lado más largo')

    em = sub.add_parser('extract-mask', help='Detecta fondo gris neutro y genera mascara binaria para GPT outpainting')
    em.add_argument('--input', required=True)
    em.add_argument('--output-mask', required=True)
    em.add_argument('--output-json', default='')

    return parser


def main() -> int:
    parser = build_parser()
    args = parser.parse_args()
    try:
        if args.command == 'prepare-source':
            result = prepare_source(args)
        elif args.command == 'export-png':
            result = export_png(args)
        elif args.command == 'apply-manual-blur':
            result = apply_manual_blur(args)
        elif args.command == 'pad-canvas':
            result = pad_canvas_for_outpaint(args)
        elif args.command == 'extract-mask':
            result = extract_mask_for_neutro_bg(args)
        else:
            raise RuntimeError('Comando no soportado')
        print(json.dumps(result, ensure_ascii=False))
        return 0
    except Exception as exc:
        error = {
            'ok': False,
            'error': str(exc),
            'command': getattr(args, 'command', ''),
        }
        try:
            output_json = getattr(args, 'output_json', '')
            if output_json:
                os.makedirs(os.path.dirname(output_json), exist_ok=True)
                with open(output_json, 'w', encoding='utf-8') as f:
                    json.dump(error, f, ensure_ascii=False, indent=2)
        except Exception:
            pass
        print(json.dumps(error, ensure_ascii=False))
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
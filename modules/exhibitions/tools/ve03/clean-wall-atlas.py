from __future__ import annotations

import json
import sys
from collections import deque
from pathlib import Path

import numpy as np
import cv2
from PIL import Image


def flood_component(luminance: np.ndarray, seed: tuple[int, int], threshold: int = 75) -> set[tuple[int, int]]:
    height, width = luminance.shape
    queue = deque([seed])
    seen = {seed}
    while queue:
        x, y = queue.popleft()
        for nx, ny in ((x - 1, y), (x + 1, y), (x, y - 1), (x, y + 1)):
            point = (nx, ny)
            if 0 <= nx < width and 0 <= ny < height and point not in seen and luminance[ny, nx] < threshold:
                seen.add(point)
                queue.append(point)
    return seen


def fill_rectangle(image: np.ndarray, mask: np.ndarray, bounds: tuple[int, int, int, int], margin_x: int, margin_y: int) -> None:
    height, width = mask.shape
    x0, y0, x1, y1 = bounds
    x0, y0 = max(0, x0 - margin_x), max(0, y0 - margin_y)
    x1, y1 = min(width - 1, x1 + margin_x), min(height - 1, y1 + margin_y)
    mask[y0:y1 + 1, x0:x1 + 1] = 255
    top = image[max(0, y0 - 1), x0:x1 + 1].astype(np.float32)
    bottom = image[min(height - 1, y1 + 1), x0:x1 + 1].astype(np.float32)
    left = image[y0:y1 + 1, max(0, x0 - 1)].astype(np.float32)
    right = image[y0:y1 + 1, min(width - 1, x1 + 1)].astype(np.float32)
    patch_height, patch_width = y1 - y0 + 1, x1 - x0 + 1
    vertical = np.linspace(0, 1, patch_height, dtype=np.float32)[:, None, None]
    horizontal = np.linspace(0, 1, patch_width, dtype=np.float32)[None, :, None]
    from_vertical = top[None, :, :] * (1 - vertical) + bottom[None, :, :] * vertical
    from_horizontal = left[:, None, :] * (1 - horizontal) + right[:, None, :] * horizontal
    image[y0:y1 + 1, x0:x1 + 1] = np.clip((from_vertical + from_horizontal) / 2, 0, 255).astype(np.uint8)


def main() -> None:
    if len(sys.argv) not in (4, 5):
        raise SystemExit('Usage: clean-wall-atlas.py <source.jpg> <slot-uvs.json> <output.jpg> [replacement.png]')
    source, uv_path, output = map(Path, sys.argv[1:4])
    replacement_path = Path(sys.argv[4]) if len(sys.argv) == 5 else None
    image = np.asarray(Image.open(source).convert('RGB')).copy()
    rgb = image.astype(np.int32)
    luminance = (rgb[:, :, 0] * 299 + rgb[:, :, 1] * 587 + rgb[:, :, 2] * 114) // 1000
    slots = json.loads(uv_path.read_text(encoding='utf-8-sig'))
    height, width = image.shape[:2]
    cleaned = []
    mask = np.zeros((height, width), dtype=np.uint8)

    for slot in slots:
        if not slot.get('uv'):
            raise RuntimeError(f"No wall UV intersection for {slot['slot_code']}")
        u, v = slot['uv']
        seed = (round(u * (width - 1)), round(v * (height - 1)))
        component = flood_component(luminance, seed)
        if len(component) < 400:
            # WEST-H-08's center is crossed by a baked highlight. A nearby dark pixel
            # remains inside the same authored opening and yields its full component.
            radius = 64
            candidates = []
            for y in range(max(0, seed[1] - radius), min(height, seed[1] + radius + 1)):
                for x in range(max(0, seed[0] - radius), min(width, seed[0] + radius + 1)):
                    rgb = image[y, x].astype(np.int32)
                    pixel_luminance = int((rgb[0] * 299 + rgb[1] * 587 + rgb[2] * 114) // 1000)
                    if pixel_luminance < 55:
                        candidates.append(((x - seed[0]) ** 2 + (y - seed[1]) ** 2, (x, y)))
            checked = set()
            for _, candidate in sorted(candidates):
                if candidate in checked:
                    continue
                possible = flood_component(luminance, candidate)
                checked.update(possible)
                if 400 <= len(possible) <= 20000:
                    component = possible
                    break
        if not 400 <= len(component) <= 20000:
            raise RuntimeError(f"Unexpected display component for {slot['slot_code']}: {len(component)} pixels")
        xs = [point[0] for point in component]
        ys = [point[1] for point in component]
        bounds = (min(xs), min(ys), max(xs), max(ys))
        component_width = bounds[2] - bounds[0] + 1
        component_height = bounds[3] - bounds[1] + 1
        # The baked atlas contains the frame, its wall shadow, and the black opening.
        # Expand by a proportion of the authored opening so all three disappear.
        margin_x = max(10, round(component_width * 0.22))
        margin_y = max(10, round(component_height * 0.22))
        fill_rectangle(image, mask, bounds, margin_x, margin_y)
        cleaned.append({'slot_code': slot['slot_code'], 'bounds': bounds, 'pixels': len(component)})

    if replacement_path:
        replacement = np.asarray(Image.open(replacement_path).convert('RGB').resize((width, height), Image.Resampling.LANCZOS))
        alpha = cv2.GaussianBlur(mask, (0, 0), 3).astype(np.float32)[:, :, None] / 255
        cleaned_image = image.astype(np.float32) * (1 - alpha) + replacement.astype(np.float32) * alpha
    else:
        cleaned_image = image.astype(np.float32)
        inside = mask.astype(bool)
        for _ in range(350):
            averaged = cv2.blur(cleaned_image, (3, 3), borderType=cv2.BORDER_REPLICATE)
            cleaned_image[inside] = averaged[inside]
    Image.fromarray(np.clip(cleaned_image, 0, 255).astype(np.uint8), 'RGB').save(output, quality=95, subsampling=0)
    print(json.dumps(cleaned, indent=2))


if __name__ == '__main__':
    main()

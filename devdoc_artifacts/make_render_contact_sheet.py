from pathlib import Path

from PIL import Image, ImageDraw


render_dir = Path("devdoc_artifacts/rendered")
files = sorted(render_dir.glob("page-*.png"), key=lambda p: int(p.stem.split("-")[1]))
thumb_w, thumb_h = 240, 310
margin = 20
cols = 4
rows = (len(files) + cols - 1) // cols
sheet = Image.new("RGB", (cols * (thumb_w + margin) + margin, rows * (thumb_h + margin) + margin), "white")
draw = ImageDraw.Draw(sheet)

for idx, path in enumerate(files):
    image = Image.open(path).convert("RGB")
    image.thumbnail((thumb_w, thumb_h))
    x = margin + (idx % cols) * (thumb_w + margin)
    y = margin + (idx // cols) * (thumb_h + margin)
    sheet.paste(image, (x, y))
    draw.text((x, y + image.height + 3), path.name, fill=(0, 0, 0))

sheet.save(render_dir / "contact_sheet.png")

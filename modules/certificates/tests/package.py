"""Build the distributable plugin without local tools, tests or migration data."""
import argparse
import hashlib
import json
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile

parser = argparse.ArgumentParser()
parser.add_argument("output", type=Path)
args = parser.parse_args()
root = Path(__file__).resolve().parent.parent
args.output.parent.mkdir(parents=True, exist_ok=True)
allowed = {"src", "routes", "resources", "database", "docs"}
files = sorted(path for path in root.rglob("*") if path.is_file() and (path.name == "module.json" or path.relative_to(root).parts[0] in allowed))
with ZipFile(args.output, "w", ZIP_DEFLATED) as archive:
    for path in files:
        archive.write(path, "certificates/" + path.relative_to(root).as_posix())
print(json.dumps({"file": str(args.output), "files": len(files), "sha256": hashlib.sha256(args.output.read_bytes()).hexdigest()}))

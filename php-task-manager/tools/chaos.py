"""Inject malformed records into local task storage, or restore its backup."""

import argparse
import json
import os
from pathlib impo


def run(action: str, data_dir: Path) -> None:
    data_dir.mkdir(parents=True, exist_ok=True)
    tasks = data_dir / "tasks.json"
    backup = data_dir / "tasks.chaos-backup.json"
    temporary = data_dir / "tasks.chaos.tmp"

    if action == "restore":
        if not backup.exists():
            raise ValueError("No chaos backup exists. Nothing was changed.")
        os.replace(backup, tasks)
        print("Original task data restored.")
        return

    if backup.exists():
        raise ValueError("A backup already exists. Run restore before injecting again.")

    original = tasks.read_bytes() if tasks.exists() else b"[]\n"
    records = json.loads(original)
    if not isinstance(records, list):
        raise ValueError("Expected a JSON list. Nothing was changed.")

    records.extend([
        None,
        42,
        "unexpected scalar task",
        [],
        {},
        {"id": "missing-done", "title": "Missing completion flag"},
        {"id": "wrong-done", "title": "String instead of boolean", "done": "yes"},
        {"id": ["wrong-id"], "title": "Array instead of ID", "done": False},
        {"id": "wrong-title", "title": {"unexpected": "object"}, "done": False},
        {"id": "empty-title", "title": "  ", "done": False},
    ])
    # Exclusive creation protects the recovery copy from repeated injections.
    with backup.open("xb") as handle:
        handle.write(original)
    try:
        temporary.write_text(json.dumps(records, indent=2), encoding="utf-8")
        os.replace(temporary, tasks)
    finally:
        temporary.unlink(missing_ok=True)
    print("Injected 10 malformed records. Valid tasks should still render normally.")
    print("Recovery: python tools/chaos.py restore")


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("action", choices=["inject", "restore"])
    args = parser.parse_args()
    try:
        run(args.action, Path(__file__).resolve().parents[1] / "data")
    except (OSError, ValueError) as error:
        parser.exit(1, f"Error: {error}\n")


if __name__ == "__main__":
    main()

#!/usr/bin/env python3
"""Pemeriksa sintaks DDL MySQL untuk berkas migrasi MO (MyOffice).

Sandbox pengembangan tidak memiliki MySQL Server, sehingga sintaks MySQL
diverifikasi dengan parser pihak ketiga (sqlglot) selain dieksekusi dalam
bentuk SQLite oleh SqliteDialect.

Penggunaan:
    python3 tools/sql_check.py            # periksa database/migrations/*.sql
    python3 tools/sql_check.py --json     # keluaran JSON
"""
from __future__ import annotations

import glob
import json
import os
import sys

try:
    import sqlglot
except ImportError:  # pragma: no cover - tergantung lingkungan
    print("sqlglot belum terpasang. Jalankan: python3 -m pip install sqlglot", file=sys.stderr)
    sys.exit(2)

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))


def main(argv: list[str]) -> int:
    as_json = "--json" in argv
    files = sorted(glob.glob(os.path.join(BASE, "database", "migrations", "*.sql")))

    results = []
    failures = 0

    for path in files:
        name = os.path.basename(path)
        with open(path, encoding="utf-8") as handle:
            sql = handle.read()

        entry = {"file": name, "statements": 0, "errors": []}

        try:
            parsed = sqlglot.parse(sql, dialect="mysql")
            entry["statements"] = len([p for p in parsed if p is not None])
        except Exception as exc:  # noqa: BLE001 - parser melemparkan beragam tipe
            entry["errors"].append(str(exc).splitlines()[0] if str(exc) else "kesalahan parse")

        if entry["errors"]:
            failures += 1

        results.append(entry)

    if as_json:
        print(json.dumps({"checked": len(results), "failed": failures, "files": results}, indent=2))
    else:
        for entry in results:
            status = "OK  " if not entry["errors"] else "FAIL"
            print(f"{status} {entry['file']} ({entry['statements']} statement)")
            for error in entry["errors"]:
                print(f"       -> {error}")
        print(f"\n{len(results)} berkas diperiksa, {failures} bermasalah.")

    return 1 if failures else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))

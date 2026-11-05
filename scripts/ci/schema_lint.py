#!/usr/bin/env python3
# Copyright (C) 2026 Adan Lozano
# SPDX-License-Identifier: GPL-2.0-or-later
# GNU General Public License version 2 or later; see LICENSE.txt
"""Lint Wendfold SQL for constructs the 1.0 schema does not allow.

Rejects, in every .sql file under the given directory:

- INSERT IGNORE
- CREATE VIEW (including CREATE OR REPLACE VIEW)
- FOREIGN KEY constraints
- ON UPDATE CURRENT_TIMESTAMP
- the YEAR data type (YEAR() is allowed)
- signed id columns (a column named id or ending in _id whose integer type
  is not UNSIGNED)
- ALTER TABLE statements that change more than one thing

Comments are removed before the checks, so a comment that names a banned
construct is not a failure. Exit 0 when the tree is clean, 1 when it is not.

  python3 scripts/ci/schema_lint.py com_wendfold/admin/sql
  python3 scripts/ci/schema_lint.py --self-test
"""

from __future__ import annotations

import argparse
import re
import sys
import tempfile
from pathlib import Path


def strip_comments(sql: str) -> str:
    """Remove line comments and block comments. Strings are left in place."""
    out = []
    i = 0
    n = len(sql)
    while i < n:
        if sql.startswith("--", i):
            nl = sql.find("\n", i)
            if nl < 0:
                break
            out.append("\n")
            i = nl + 1
            continue
        if sql.startswith("/*", i):
            end = sql.find("*/", i + 2)
            if end < 0:
                break
            i = end + 2
            continue
        quote = sql[i]
        if quote in ("'", '"', "`"):
            out.append(quote)
            i += 1
            while i < n:
                out.append(sql[i])
                if sql[i] == "\\":
                    if i + 1 < n:
                        out.append(sql[i + 1])
                        i += 2
                        continue
                if sql[i] == quote:
                    i += 1
                    break
                i += 1
            continue
        out.append(sql[i])
        i += 1
    return "".join(out)


def statements(sql: str) -> list[str]:
    """Split on semicolons that sit outside quotes and parentheses."""
    body = strip_comments(sql)
    parts: list[str] = []
    buf: list[str] = []
    depth = 0
    quote = ""
    i = 0
    while i < len(body):
        ch = body[i]
        if quote:
            buf.append(ch)
            if ch == "\\" and i + 1 < len(body):
                buf.append(body[i + 1])
                i += 2
                continue
            if ch == quote:
                quote = ""
            i += 1
            continue
        if ch in ("'", '"', "`"):
            quote = ch
            buf.append(ch)
            i += 1
            continue
        if ch == "(":
            depth += 1
        elif ch == ")" and depth:
            depth -= 1
        if ch == ";" and depth == 0:
            stmt = "".join(buf).strip()
            if stmt:
                parts.append(stmt)
            buf = []
            i += 1
            continue
        buf.append(ch)
        i += 1
    tail = "".join(buf).strip()
    if tail:
        parts.append(tail)
    return parts


def _alter_changes(stmt: str) -> int:
    """Count comma-separated actions in an ALTER TABLE body."""
    upper = stmt.lstrip().upper()
    if not upper.startswith("ALTER TABLE"):
        return 0
    rest = stmt.split(None, 2)
    if len(rest) < 3:
        return 1
    body = rest[2]
    # Drop the table name token.
    depth = 0
    quote = ""
    name_done = False
    start = 0
    i = 0
    while i < len(body):
        ch = body[i]
        if quote:
            if ch == quote:
                quote = ""
            i += 1
            continue
        if ch in ("'", '"', "`"):
            quote = ch
            if not name_done and depth == 0:
                # quoted table name
                end = body.find(ch, i + 1)
                if end < 0:
                    return 1
                start = end + 1
                name_done = True
                i = end + 1
                continue
            i += 1
            continue
        if not name_done and depth == 0 and not ch.isspace():
            # unquoted table name
            while i < len(body) and not body[i].isspace() and body[i] != "(":
                i += 1
            start = i
            name_done = True
            continue
        if ch == "(":
            depth += 1
        elif ch == ")" and depth:
            depth -= 1
        i += 1
    actions = body[start:]
    chunks: list[str] = []
    buf: list[str] = []
    depth = 0
    quote = ""
    for ch in actions:
        if quote:
            buf.append(ch)
            if ch == quote:
                quote = ""
            continue
        if ch in ("'", '"', "`"):
            quote = ch
            buf.append(ch)
            continue
        if ch == "(":
            depth += 1
        elif ch == ")" and depth:
            depth -= 1
        if ch == "," and depth == 0:
            piece = "".join(buf).strip()
            if piece:
                chunks.append(piece)
            buf = []
            continue
        buf.append(ch)
    piece = "".join(buf).strip()
    if piece:
        chunks.append(piece)
    return len(chunks)


_ID_TYPE = re.compile(
    r"`(?P<name>[A-Za-z0-9_]+)`\s+"
    r"(?P<type>tinyint|smallint|mediumint|int|integer|bigint)"
    r"(?:\s*\(\d+\))?"
    r"(?P<rest>(?:\s+[A-Za-z0-9_]+)*)",
    re.IGNORECASE,
)
_YEAR_TYPE = re.compile(
    r"`[A-Za-z0-9_]+`\s+year\b",
    re.IGNORECASE,
)


def lint_text(sql: str, label: str) -> list[str]:
    """Return human-readable failures for one SQL string."""
    errors: list[str] = []
    plain = strip_comments(sql)
    folded = plain.upper()
    if "INSERT IGNORE" in folded:
        errors.append(f"{label}: INSERT IGNORE")
    if re.search(r"\bCREATE\s+(OR\s+REPLACE\s+)?VIEW\b", folded):
        errors.append(f"{label}: CREATE VIEW")
    if re.search(r"\bFOREIGN\s+KEY\b", folded):
        errors.append(f"{label}: FOREIGN KEY")
    if "ON UPDATE CURRENT_TIMESTAMP" in folded:
        errors.append(f"{label}: ON UPDATE CURRENT_TIMESTAMP")
    for match in _YEAR_TYPE.finditer(plain):
        errors.append(f"{label}: YEAR data type near `{match.group(0)[:80]}`")
    for match in _ID_TYPE.finditer(plain):
        name = match.group("name")
        if name.lower() != "id" and not name.lower().endswith("_id"):
            continue
        rest = (match.group("rest") or "").lower()
        if "unsigned" not in rest.split():
            errors.append(f"{label}: signed id column `{name}`")
    for stmt in statements(sql):
        if stmt.lstrip().upper().startswith("ALTER TABLE") and _alter_changes(stmt) > 1:
            errors.append(f"{label}: ALTER changes more than one thing: {stmt[:120]}")
    return errors


def lint_tree(root: Path) -> list[str]:
    errors: list[str] = []
    files = sorted(root.rglob("*.sql"))
    if not files:
        return [f"{root}: no .sql files"]
    for path in files:
        errors.extend(lint_text(path.read_text(encoding="utf-8"), str(path)))
    return errors


def self_test() -> int:
    """The linter must reject each banned construct and accept a clean file."""
    samples = {
        "ignore": "INSERT IGNORE INTO `#__wendfold_x` (`id`) VALUES (1);\n",
        "view": "CREATE OR REPLACE VIEW `#__wendfold_v` AS SELECT 1 AS id;\n",
        "fk": "CREATE TABLE `#__t` (`id` int unsigned NOT NULL, FOREIGN KEY (`id`) REFERENCES `#__u` (`id`));\n",
        "onupdate": "CREATE TABLE `#__t` (`id` int unsigned NOT NULL, `modified` datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP);\n",
        "year": "CREATE TABLE `#__t` (`id` int unsigned NOT NULL, `award_year` YEAR NOT NULL);\n",
        "signed": "CREATE TABLE `#__t` (`id` int NOT NULL, `profile_id` int unsigned NOT NULL);\n",
        "alter": "ALTER TABLE `#__t` ADD COLUMN `a` int unsigned NULL, ADD INDEX `idx_a` (`a`);\n",
    }
    clean = (
        "-- mentions FOREIGN KEY and YEAR() only as words in a comment\n"
        "CREATE TABLE `#__t` (\n"
        "  `id` int unsigned NOT NULL AUTO_INCREMENT,\n"
        "  `profile_id` int unsigned NOT NULL,\n"
        "  `published` tinyint NOT NULL DEFAULT 1,\n"
        "  `ordering` int NOT NULL DEFAULT 0,\n"
        "  PRIMARY KEY (`id`)\n"
        ");\n"
        "ALTER TABLE `#__t` ADD INDEX `idx_profile` (`profile_id`);\n"
        "SELECT YEAR(CURDATE());\n"
    )
    failed = False
    clean_errors = lint_text(clean, "clean")
    if clean_errors:
        print("self-test: clean sample was rejected:", *clean_errors, sep="\n  ")
        failed = True
    for name, sql in samples.items():
        found = lint_text(sql, name)
        if not found:
            print(f"self-test: {name} was not rejected")
            failed = True
    if failed:
        return 1
    print(f"self-test: {len(samples)} banned samples rejected, clean sample accepted")
    return 0


def main(argv: list[str]) -> int:
    parser = argparse.ArgumentParser(description="Lint Wendfold SQL files.")
    parser.add_argument("root", nargs="?", help="Directory of .sql files")
    parser.add_argument("--self-test", action="store_true", help="Check the linter against built-in samples")
    args = parser.parse_args(argv)
    if args.self_test:
        return self_test()
    if not args.root:
        parser.error("give a directory, or pass --self-test")
    root = Path(args.root)
    if not root.is_dir():
        print(f"{root} is not a directory", file=sys.stderr)
        return 2
    errors = lint_tree(root)
    if errors:
        print("\n".join(errors))
        return 1
    count = len(list(root.rglob("*.sql")))
    print(f"SQL lint: {count} file(s) under {root} accepted")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))

#!/usr/bin/env python3
# Copyright (C) 2026 Adan Lozano
# SPDX-License-Identifier: GPL-2.0-or-later
# GNU General Public License version 2 or later; see LICENSE.txt
"""Fresh-install versus upgrade equivalence, update re-run, and uninstall.

Joomla's installer splits update SQL on a semicolon at the end of a line,
strips a trailing /** CAN FAIL **/ marker, and stops on the first error that
is not marked CAN FAIL. This script does the same.

A fresh database loads install.mysql.utf8.sql only. An upgrade database loads
that same file and then every file in sql/updates/mysql, ordered by PHP
version_compare on the filename. The two information_schema dumps (columns and
indexes) must match. The update files are applied a second time; every failure
on that pass must be a CAN FAIL statement. Uninstall must leave no
jos_wendfold_% tables.

  python3 scripts/ci/schema_check.py \
      --sql-dir com_wendfold/admin/sql \
      --host 127.0.0.1 --port 3306 --user root --label mariadb11

Optional --target path loads that install file into a third database and
prints a column and index diff against this install file. A non-empty target
diff is reported and does not fail the run: the baseline keeps legacy columns
that current code still uses. --strict-target fails the run when that diff is
not empty.

The mysql client must be on PATH. The password is read from MYSQL_PWD or
--password (empty is allowed). sql_mode is the Joomla strict mode used by the
schema review.
"""

from __future__ import annotations

import argparse
import os
import re
import subprocess
import sys
import tempfile
from pathlib import Path

SQL_MODE = "STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION"
PREFIX = "jos_"
CAN_FAIL = "/** CAN FAIL **/"


def php_sort(names: list[str]) -> list[str]:
    """Order schema filenames the way PHP version_compare does."""
    if len(names) < 2:
        return list(names)
    script = r"""
$names = array_slice($argv, 1);
usort($names, static function ($a, $b) {
    return version_compare($a, $b);
});
echo implode("\n", $names);
"""
    result = subprocess.run(
        ["php", "-r", script, *names],
        check=True,
        capture_output=True,
        text=True,
    )
    return [line for line in result.stdout.splitlines() if line]


def mysql_base(host: str, port: int, user: str) -> list[str]:
    return [
        "mysql",
        "--protocol=TCP",
        "-h",
        host,
        "-P",
        str(port),
        "-u",
        user,
        "--batch",
        "--raw",
        "--skip-column-names",
    ]


def run_sql(cmd: list[str], database: str | None, sql: str) -> subprocess.CompletedProcess[str]:
    args = list(cmd)
    if database:
        args.append(database)
    wrapped = f"SET SESSION sql_mode='{SQL_MODE}';\n{sql}"
    return subprocess.run(args, input=wrapped, capture_output=True, text=True)


def require_ok(result: subprocess.CompletedProcess[str], what: str) -> None:
    if result.returncode != 0:
        sys.stderr.write(result.stderr)
        sys.stderr.write(result.stdout)
        raise SystemExit(f"{what} failed (exit {result.returncode})")


def prepare(sql_text: str) -> str:
    """Replace the Joomla prefix and drop full-line comments."""
    text = sql_text.replace("#__", PREFIX)
    lines = [line for line in text.splitlines() if not line.strip().startswith("--")]
    return "\n".join(lines) + "\n"


def split_statements(sql_text: str) -> list[tuple[str, bool]]:
    """Split the way the review harness does: on a semicolon at end of line."""
    body = prepare(sql_text)
    found: list[tuple[str, bool]] = []
    for chunk in re.split(r";\s*\n", body):
        stmt = chunk.strip().rstrip(";").strip()
        if not stmt:
            continue
        can = stmt.upper().endswith(CAN_FAIL.upper())
        if can:
            stmt = stmt[: -len(CAN_FAIL)].strip()
        if stmt:
            found.append((stmt, can))
    return found


def apply_file(cmd: list[str], database: str, path: Path) -> tuple[int, int]:
    """Apply one update file. Return (ok_count, can_fail_count).

    A non-CAN-FAIL error aborts the process.
    """
    ok = 0
    skipped = 0
    for stmt, can in split_statements(path.read_text(encoding="utf-8")):
        result = run_sql(cmd, database, stmt + ";")
        if result.returncode == 0:
            ok += 1
            continue
        if can:
            skipped += 1
            continue
        sys.stderr.write(result.stderr or result.stdout)
        raise SystemExit(f"update statement failed in {path.name}: {stmt[:160]}")
    return ok, skipped


def apply_file_rerun(cmd: list[str], database: str, path: Path) -> tuple[int, int]:
    """Second apply. Every error must be CAN FAIL."""
    ok = 0
    skipped = 0
    for stmt, can in split_statements(path.read_text(encoding="utf-8")):
        result = run_sql(cmd, database, stmt + ";")
        if result.returncode == 0:
            ok += 1
            continue
        if can:
            skipped += 1
            continue
        sys.stderr.write(result.stderr or result.stdout)
        raise SystemExit(f"re-run error was not CAN FAIL in {path.name}: {stmt[:160]}")
    return ok, skipped


def create_db(cmd: list[str], name: str) -> None:
    sql = (
        f"DROP DATABASE IF EXISTS `{name}`;\n"
        f"CREATE DATABASE `{name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n"
    )
    require_ok(run_sql(cmd, None, sql), f"create {name}")


def load_install(cmd: list[str], database: str, path: Path) -> None:
    text = prepare(path.read_text(encoding="utf-8"))
    require_ok(run_sql(cmd, database, text), f"load {path.name} into {database}")


def dump_schema(cmd: list[str], database: str) -> str:
    sql = """
SELECT 'COLUMN', TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE,
       IFNULL(COLUMN_DEFAULT, 'NULL'), IFNULL(COLLATION_NAME, ''),
       IFNULL(EXTRA, ''), IFNULL(COLUMN_COMMENT, '')
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME, ORDINAL_POSITION;
SELECT 'INDEX', TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME,
       IFNULL(SUB_PART, ''), IFNULL(COLLATION, ''), INDEX_TYPE
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;
"""
    result = run_sql(cmd, database, sql)
    require_ok(result, f"dump {database}")
    return result.stdout


def table_count(cmd: list[str], database: str) -> int:
    sql = (
        "SELECT COUNT(*) FROM information_schema.TABLES "
        f"WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE '{PREFIX}wendfold_%';"
    )
    result = run_sql(cmd, database, sql)
    require_ok(result, f"count {database}")
    return int(result.stdout.strip() or "0")


def assert_alias_unique(cmd: list[str], database: str) -> None:
    """A second event with the same alias must fail, including against a trashed row."""
    first = (
        "INSERT INTO jos_wendfold_events "
        "(title, alias, event_date, event_type_id, published, created, modified) VALUES "
        "('First', 'chapter-ride', '2026-01-01', 1, -2, "
        "'2026-01-01 00:00:00', '2026-01-01 00:00:00');"
    )
    require_ok(run_sql(cmd, database, first), "insert first event alias")
    duplicate = (
        "INSERT INTO jos_wendfold_events "
        "(title, alias, event_date, event_type_id, published, created, modified) VALUES "
        "('Second', 'chapter-ride', '2026-01-02', 1, 1, "
        "'2026-01-01 00:00:00', '2026-01-01 00:00:00');"
    )
    result = run_sql(cmd, database, duplicate)
    if result.returncode == 0:
        raise SystemExit("duplicate event alias was accepted")
    blob = (result.stderr + result.stdout).lower()
    if "duplicate" not in blob:
        sys.stderr.write(result.stderr)
        sys.stderr.write(result.stdout)
        raise SystemExit("duplicate event alias failed for a reason other than the unique index")
    print("  unique alias rejects a duplicate insert")


def diff_dumps(left: str, right: str, left_name: str, right_name: str) -> str:
    if left == right:
        return ""
    import difflib

    return "".join(
        difflib.unified_diff(
            left.splitlines(keepends=True),
            right.splitlines(keepends=True),
            fromfile=left_name,
            tofile=right_name,
        )
    )


def structural_diff(ours: str, target: str) -> str:
    """Compare column and index identity, ignoring comments and ordinal."""
    def rows(dump: str, kind: str) -> dict[tuple, tuple]:
        found = {}
        for line in dump.splitlines():
            parts = line.split("\t")
            if not parts or parts[0] != kind:
                continue
            if kind == "COLUMN":
                # kind, table, column, type, nullable, default, collation, extra, comment
                key = (parts[1], parts[2])
                found[key] = tuple(parts[3:8])
            else:
                # kind, table, index, non_unique, seq, column, sub_part, collation, type
                key = (parts[1], parts[2], parts[4], parts[5])
                found[key] = tuple(parts[3:9])
        return found

    lines = []
    for kind in ("COLUMN", "INDEX"):
        a = rows(ours, kind)
        b = rows(target, kind)
        for key in sorted(set(a) - set(b)):
            lines.append(f"only in install\t{kind}\t" + "\t".join(key) + "\t" + "\t".join(a[key]))
        for key in sorted(set(b) - set(a)):
            lines.append(f"only in target\t{kind}\t" + "\t".join(key) + "\t" + "\t".join(b[key]))
        for key in sorted(set(a) & set(b)):
            if a[key] != b[key]:
                lines.append(
                    f"changed\t{kind}\t" + "\t".join(key)
                    + "\tinstall=" + "|".join(a[key])
                    + "\ttarget=" + "|".join(b[key])
                )
    return "\n".join(lines) + ("\n" if lines else "")


def main(argv: list[str]) -> int:
    parser = argparse.ArgumentParser(description="Check Wendfold schema equivalence.")
    parser.add_argument("--sql-dir", required=True, type=Path)
    parser.add_argument("--host", required=True)
    parser.add_argument("--port", required=True, type=int)
    parser.add_argument("--user", required=True)
    parser.add_argument("--password", default=os.environ.get("MYSQL_PWD", ""))
    parser.add_argument("--label", default="db")
    parser.add_argument("--target", type=Path, default=None)
    parser.add_argument("--strict-target", action="store_true")
    args = parser.parse_args(argv)

    if args.password:
        os.environ["MYSQL_PWD"] = args.password

    install = args.sql_dir / "install.mysql.utf8.sql"
    uninstall = args.sql_dir / "uninstall.mysql.utf8.sql"
    updates_dir = args.sql_dir / "updates" / "mysql"
    if not install.is_file() or not uninstall.is_file():
        print("install or uninstall SQL is missing", file=sys.stderr)
        return 2

    update_files = []
    if updates_dir.is_dir():
        stems = php_sort([path.name[:-4] for path in updates_dir.glob("*.sql")])
        update_files = [updates_dir / f"{stem}.sql" for stem in stems]

    cmd = mysql_base(args.host, args.port, args.user)
    tag = re.sub(r"[^A-Za-z0-9_]", "", args.label) or "db"
    fresh = f"wf_fresh_{tag}"
    upgraded = f"wf_up_{tag}"
    removed = f"wf_un_{tag}"

    print(f"== {args.label} {args.host}:{args.port} ==")
    create_db(cmd, fresh)
    create_db(cmd, upgraded)
    create_db(cmd, removed)

    load_install(cmd, fresh, install)
    load_install(cmd, upgraded, install)
    load_install(cmd, removed, install)

    statements = sum(len(split_statements(p.read_text(encoding="utf-8"))) for p in update_files)
    if statements == 0:
        print(
            f"  NOTE: {len(update_files)} update file(s) hold no SQL statements, so the "
            "upgraded database is the install SQL alone and the fresh-versus-upgrade "
            "comparison cannot find drift yet. It becomes meaningful once an update "
            "file changes the schema."
        )
    for path in update_files:
        ok, skipped = apply_file(cmd, upgraded, path)
        print(f"  update {path.name}: {ok} ok, {skipped} CAN FAIL")
    for path in update_files:
        ok, skipped = apply_file_rerun(cmd, upgraded, path)
        print(f"  re-run {path.name}: {ok} ok, {skipped} CAN FAIL")

    fresh_dump = dump_schema(cmd, fresh)
    up_dump = dump_schema(cmd, upgraded)
    schema_diff = diff_dumps(fresh_dump, up_dump, "fresh", "upgraded")
    if schema_diff:
        sys.stderr.write(schema_diff)
        print("fresh install and upgrade dumps differ", file=sys.stderr)
        return 1
    print("  fresh install matches upgrade")
    assert_alias_unique(cmd, fresh)

    load_text = prepare(uninstall.read_text(encoding="utf-8"))
    require_ok(run_sql(cmd, removed, load_text), "uninstall")
    left = table_count(cmd, removed)
    print(f"  tables after uninstall: {left}")
    if left != 0:
        print("uninstall left wendfold tables", file=sys.stderr)
        return 1

    if args.target:
        target_db = f"wf_target_{tag}"
        create_db(cmd, target_db)
        load_install(cmd, target_db, args.target)
        target_dump = dump_schema(cmd, target_db)
        report = structural_diff(fresh_dump, target_dump)
        print("  diff versus target (empty means identical columns and indexes):")
        if report.strip():
            print(report.rstrip())
            if args.strict_target:
                return 1
        else:
            print("  (no differences)")

    print(f"  {args.label}: ok")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))

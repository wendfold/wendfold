#!/usr/bin/env bash
# Schema checks for the Wendfold 1.0.0 baseline.
#
# Copyright (C) 2026 Adan Lozano
# SPDX-License-Identifier: GPL-2.0-or-later
# GNU General Public License version 2 or later; see LICENSE.txt
#
# Runs the SQL lint, then the installer-layout check (uninstall from the
# installed copy of script.php, and install preflight from both layouts),
# then fresh-install versus upgrade equivalence, update re-run safety, and
# uninstall SQL on each MySQL-compatible server listed in
# SCHEMA_SERVERS. A server is host:port:user:password. An empty password is
# allowed (the field is still required, so a trailing colon). Multiple servers
# are separated by spaces.
#
#   SCHEMA_SERVERS='127.0.0.1:3306:root: 127.0.0.1:3307:root:' \
#       bash scripts/ci/schema-check.sh
#
# Optional SCHEMA_TARGET_SQL is a second install file. The script prints a
# column and index diff of this branch against that file. The diff is allowed
# to be non-empty. SCHEMA_STRICT_TARGET=1 fails the run when it is not.
#
# Requirements on the runner: bash, python3, php (for version_compare), and
# the mysql command-line client. The script sets the session sql_mode to
# STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION.
#
# What the CI workflow needs to add (do not edit the workflow in the schema
# pull request; this script is the entry point):
#
#   A separate job, for example "Schema", on ubuntu-24.04, with two services:
#
#     mariadb:
#       image: mariadb:11.8
#       env:
#         MARIADB_ROOT_PASSWORD: schema
#         MARIADB_DATABASE: wendfold
#       ports:
#         - 3306:3306
#       options: >-
#         --health-cmd="healthcheck.sh --connect --innodb_initialized"
#         --health-interval=10s
#         --health-timeout=5s
#         --health-retries=10
#     mysql:
#       image: mysql:8.4
#       env:
#         MYSQL_ROOT_PASSWORD: schema
#         MYSQL_DATABASE: wendfold
#       ports:
#         - 3307:3306
#       options: >-
#         --health-cmd="mysqladmin ping -h 127.0.0.1 -uroot -pschema"
#         --health-interval=10s
#         --health-timeout=5s
#         --health-retries=20
#
#   Steps: actions/checkout, then install a MySQL client
#   (apt-get install -y mariadb-client), then:
#
#     SCHEMA_SERVERS='127.0.0.1:3306:root:schema 127.0.0.1:3307:root:schema' \
#         bash scripts/ci/schema-check.sh
#
#   PHPUnit already runs scripts/ci/schema_lint.py, so the existing PHP jobs
#   catch banned SQL without a database. They do not replace this job: the
#   equivalence, re-run, and uninstall checks need the database services.
#
# MariaDB 10.6 and MySQL 8.0.13 are also in range. Pass them as extra
# SCHEMA_SERVERS entries when those services exist.

set -euo pipefail

root="$(cd "$(dirname "$0")/../.." && pwd)"
sql_dir="${root}/com_wendfold/admin/sql"
lint="${root}/scripts/ci/schema_lint.py"
check="${root}/scripts/ci/schema_check.py"

python3 "${lint}" --self-test
python3 "${lint}" "${sql_dir}"
php "${root}/scripts/ci/installer-layout-check.php"

servers="${SCHEMA_SERVERS:-127.0.0.1:3306:root: 127.0.0.1:3307:root:}"
target_args=()
if [[ -n "${SCHEMA_TARGET_SQL:-}" ]]; then
    target_args+=(--target "${SCHEMA_TARGET_SQL}")
    if [[ "${SCHEMA_STRICT_TARGET:-}" == "1" ]]; then
        target_args+=(--strict-target)
    fi
fi

n=0
for spec in ${servers}; do
    n=$((n + 1))
    IFS=':' read -r host port user password <<< "${spec}"
    if [[ -z "${host}" || -z "${port}" || -z "${user}" ]]; then
        echo "SCHEMA_SERVERS entry must be host:port:user:password (got '${spec}')" >&2
        exit 2
    fi
    label="server${n}"
    case "${port}" in
        3306) label="mariadb" ;;
        3307) label="mysql84" ;;
    esac
    MYSQL_PWD="${password}" python3 "${check}" \
        --sql-dir "${sql_dir}" \
        --host "${host}" \
        --port "${port}" \
        --user "${user}" \
        --label "${label}" \
        "${target_args[@]}"
done

echo "Schema checks passed on ${n} server(s)."

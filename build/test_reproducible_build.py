#!/usr/bin/env python3
"""Reproducible release build and manifest files checks.

Run from the repository root:

  python3 -m unittest discover -s build -p 'test_*.py'
"""

from __future__ import annotations

import os
import tempfile
import unittest
from pathlib import Path

import build_release


class ReproducibleBuildTest(unittest.TestCase):
    def test_two_builds_produce_identical_sha256sums(self) -> None:
        date_time = build_release.archive_date_time()

        with tempfile.TemporaryDirectory() as first_tmp, tempfile.TemporaryDirectory() as second_tmp:
            first_root = Path(first_tmp)
            second_root = Path(second_tmp)

            # Host env must not affect archives when date_time is pinned.
            previous_epoch = os.environ.get('SOURCE_DATE_EPOCH')
            os.environ['SOURCE_DATE_EPOCH'] = '1700000000'
            try:
                first = build_release.build_release(
                    dist_dir=first_root / 'dist',
                    staging_dir=first_root / 'staging',
                    date_time=date_time,
                )
                second = build_release.build_release(
                    dist_dir=second_root / 'dist',
                    staging_dir=second_root / 'staging',
                    date_time=date_time,
                )
            finally:
                if previous_epoch is None:
                    os.environ.pop('SOURCE_DATE_EPOCH', None)
                else:
                    os.environ['SOURCE_DATE_EPOCH'] = previous_epoch

            first_sums = (first_root / 'dist' / 'SHA256SUMS.txt').read_bytes()
            second_sums = (second_root / 'dist' / 'SHA256SUMS.txt').read_bytes()
            self.assertEqual(first_sums, second_sums)
            self.assertTrue(first_sums.endswith(b'\n'))
            self.assertNotIn(b'\r', first_sums)

            first_lines = first_sums.decode('utf-8').splitlines()
            filenames = [line.split('  ', 1)[1] for line in first_lines]
            self.assertEqual(filenames, sorted(filenames))

            for name in first:
                self.assertEqual(
                    build_release.sha256_file(first[name]),
                    build_release.sha256_file(second[name]),
                    msg=f'{name} ZIP digest differs between builds',
                )

            # ZIP entry metadata must be pinned (not host mtime / mode).
            from zipfile import ZipFile

            with ZipFile(first['com_wendfold'], 'r') as zip_file:
                for info in zip_file.infolist():
                    self.assertEqual(info.date_time, date_time)
                    self.assertEqual(info.create_system, build_release.ZIP_CREATE_SYSTEM)
                    self.assertEqual(info.external_attr, build_release.ZIP_FILE_EXTERNAL_ATTR)
                    self.assertEqual(info.compress_type, build_release.ZIP_DEFLATED)

    def test_manifest_files_check_passes_for_built_zips(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            generated = build_release.build_release(
                dist_dir=root / 'dist',
                staging_dir=root / 'staging',
            )
            errors = build_release.check_built_packages(generated)
            self.assertEqual(errors, [], msg='\n'.join(errors))

    def test_package_contains_every_child_zip(self) -> None:
        from zipfile import ZipFile

        self.assertIn('plg_privacy_wendfold', build_release.PACKAGE_SOURCES)
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            generated = build_release.build_release(
                dist_dir=root / 'dist',
                staging_dir=root / 'staging',
            )
            with ZipFile(generated['pkg_wendfold'], 'r') as zip_file:
                names = set(zip_file.namelist())
            for key in build_release.PACKAGE_SOURCES:
                self.assertIn(f'constituents/{generated[key].name}', names)


if __name__ == '__main__':
    unittest.main()

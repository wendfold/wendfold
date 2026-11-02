#!/usr/bin/env python3
from __future__ import annotations

import argparse
import calendar
import hashlib
import os
import shutil
import subprocess
import sys
import time
import xml.etree.ElementTree as ET
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile, ZipInfo


REPO_ROOT = Path(__file__).resolve().parents[1]
BUILD_DIR = REPO_ROOT / 'build'
DIST_DIR = BUILD_DIR / 'dist'
STAGING_DIR = BUILD_DIR / 'staging'

# Fixed ZIP metadata for reproducible archives.
ZIP_CREATE_SYSTEM = 3  # Unix
ZIP_COMPRESS_LEVEL = 6
ZIP_FILE_EXTERNAL_ATTR = 0o644 << 16
ZIP_DIR_EXTERNAL_ATTR = 0o755 << 16
ZIP_MIN_DATE_TIME = (1980, 1, 1, 0, 0, 0)
ZIP_MIN_EPOCH = calendar.timegm(ZIP_MIN_DATE_TIME)

# Root entries Joomla installers accept outside <files>/<media>/<languages>/sql.
ALLOWED_ROOT_EXTRAS = frozenset({
    'LICENSE.txt',
    'README.md',
    'THIRD-PARTY.md',
})

PACKAGE_SOURCES = {
    'com_wendfold': {
        'source': REPO_ROOT / 'com_wendfold',
        'manifest': REPO_ROOT / 'com_wendfold' / 'wendfold.xml',
        'zip_name': 'com_wendfold_v{version}.zip',
        'exclude': {
            'BACKLOG.md',
            'updates/',
            '.gitignore',
        },
    },
    'plg_user_wendfold': {
        'source': REPO_ROOT / 'plg_user_wendfold',
        'manifest': REPO_ROOT / 'plg_user_wendfold' / 'wendfold.xml',
        'zip_name': 'plg_user_wendfold_v{version}.zip',
        'exclude': {
            'updates/',
            '.gitignore',
        },
    },
    'plg_task_wendfold': {
        'source': REPO_ROOT / 'plg_task_wendfold',
        'manifest': REPO_ROOT / 'plg_task_wendfold' / 'wendfold.xml',
        'zip_name': 'plg_task_wendfold_v{version}.zip',
        'exclude': {
            'updates/',
            '.gitignore',
        },
    },
    'plg_privacy_wendfold': {
        'source': REPO_ROOT / 'plg_privacy_wendfold',
        'manifest': REPO_ROOT / 'plg_privacy_wendfold' / 'wendfold.xml',
        'zip_name': 'plg_privacy_wendfold_v{version}.zip',
        'exclude': {
            'updates/',
            '.gitignore',
        },
    },
}


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description='Build Wendfold release ZIPs.')
    parser.add_argument(
        '--dist-dir',
        type=Path,
        default=DIST_DIR,
        help='Output directory for ZIPs and SHA256SUMS.txt',
    )
    parser.add_argument(
        '--staging-dir',
        type=Path,
        default=STAGING_DIR,
        help='Temporary staging directory for the package tree',
    )
    parser.add_argument(
        '--check',
        action='store_true',
        help='After building, run the manifest files check on every ZIP',
    )
    args = parser.parse_args(argv)

    generated = build_release(dist_dir=args.dist_dir, staging_dir=args.staging_dir)
    print(f'Built release packages in {args.dist_dir}')

    if args.check:
        errors = check_built_packages(generated)
        if errors:
            for error in errors:
                print(error, file=sys.stderr)
            return 1
        print('Manifest files check: OK')

    return 0


def build_release(
    dist_dir: Path = DIST_DIR,
    staging_dir: Path = STAGING_DIR,
    date_time: tuple[int, int, int, int, int, int] | None = None,
) -> dict[str, Path]:
    """Build child and package ZIPs into dist_dir. Returns map of name -> zip path."""
    version = None

    for config in PACKAGE_SOURCES.values():
        current = read_manifest_version(config['manifest'])
        if version is None:
            version = current
            continue
        if current != version:
            raise SystemExit('Manifest versions do not match.')

    package_version = read_manifest_version(REPO_ROOT / 'pkg_wendfold' / 'pkg_wendfold.xml')
    if package_version != version:
        raise SystemExit('Package manifest version does not match child extension versions.')

    if date_time is None:
        date_time = archive_date_time()

    reset_directory(dist_dir)
    reset_directory(staging_dir)

    generated: dict[str, Path] = {}

    for name, config in PACKAGE_SOURCES.items():
        zip_path = dist_dir / config['zip_name'].format(version=version)
        create_zip_from_source(config['source'], zip_path, config['exclude'], date_time)
        generated[name] = zip_path

    package_stage = staging_dir / 'pkg_wendfold'
    copy_tree(REPO_ROOT / 'pkg_wendfold', package_stage)
    reset_directory(package_stage / 'constituents')

    for key in PACKAGE_SOURCES:
        target = package_stage / 'constituents' / generated[key].name
        # Content-only copy: package ZIP metadata comes from date_time, not source mtime.
        shutil.copyfile(generated[key], target)

    rewrite_package_manifest(
        package_stage / 'pkg_wendfold.xml',
        {
            'component': generated['com_wendfold'].name,
            'user_plugin': generated['plg_user_wendfold'].name,
            'task_plugin': generated['plg_task_wendfold'].name,
            'privacy_plugin': generated['plg_privacy_wendfold'].name,
        },
    )

    package_zip = dist_dir / f'pkg_wendfold_v{version}.zip'
    create_zip_from_source(package_stage, package_zip, {'.gitignore'}, date_time)
    generated['pkg_wendfold'] = package_zip

    checksum_lines = [
        f'{sha256_file(path)}  {path.name}'
        for path in sorted(generated.values(), key=lambda item: item.name)
    ]
    sums_path = dist_dir / 'SHA256SUMS.txt'
    sums_path.write_bytes(('\n'.join(checksum_lines) + '\n').encode('utf-8'))

    return generated


def archive_date_time() -> tuple[int, int, int, int, int, int]:
    """ZIP date_time from SOURCE_DATE_EPOCH or HEAD commit time, clamped to 1980-01-01."""
    raw = os.environ.get('SOURCE_DATE_EPOCH')
    if raw is not None and raw != '':
        try:
            timestamp = int(raw)
        except ValueError as exc:
            raise SystemExit(f'Invalid SOURCE_DATE_EPOCH: {raw!r}') from exc
    else:
        try:
            output = subprocess.check_output(
                ['git', 'log', '-1', '--format=%ct'],
                cwd=REPO_ROOT,
                text=True,
            )
        except (OSError, subprocess.CalledProcessError) as exc:
            raise SystemExit(f'Unable to read HEAD commit time: {exc}') from exc
        timestamp = int(output.strip())

    if timestamp < ZIP_MIN_EPOCH:
        timestamp = ZIP_MIN_EPOCH

    # ZIP stores DOS time with 2-second resolution; odd seconds round down on readback.
    timestamp -= timestamp % 2
    return time.gmtime(timestamp)[:6]


def read_manifest_version(manifest_path: Path) -> str:
    if not manifest_path.is_file():
        raise SystemExit(f'Missing manifest: {manifest_path}')

    tree = ET.parse(manifest_path)
    version = tree.getroot().findtext('version')

    if not version:
        raise SystemExit(f'Unable to read version from manifest: {manifest_path}')

    return version.strip()


def reset_directory(path: Path) -> None:
    if path.exists():
        shutil.rmtree(path)
    path.mkdir(parents=True, exist_ok=True)


def copy_tree(source: Path, destination: Path) -> None:
    shutil.copytree(source, destination, dirs_exist_ok=True)


def rewrite_package_manifest(manifest_path: Path, filenames: dict[str, str]) -> None:
    tree = ET.parse(manifest_path)
    root = tree.getroot()

    replacements = {
        ('component', 'com_wendfold'): filenames['component'],
        ('plugin', 'user'): filenames['user_plugin'],
        ('plugin', 'task'): filenames['task_plugin'],
        ('plugin', 'privacy'): filenames['privacy_plugin'],
    }

    files_node = root.find('files')
    if files_node is None:
        raise SystemExit(f'Missing files node in package manifest: {manifest_path}')

    for file_node in files_node.findall('file'):
        file_type = file_node.attrib.get('type', '')
        group = file_node.attrib.get('group', '')
        key = (file_type, group if file_type == 'plugin' else file_node.attrib.get('id', ''))

        if key in replacements:
            file_node.text = replacements[key]

    # Deterministic serialization: no timestamps; fixed declaration and empty elements.
    tree.write(
        manifest_path,
        encoding='utf-8',
        xml_declaration=True,
        short_empty_elements=True,
    )
    # Normalize to a trailing LF so the staged file is byte-stable across runs.
    data = manifest_path.read_bytes()
    if not data.endswith(b'\n'):
        manifest_path.write_bytes(data + b'\n')


def create_zip_from_source(
    source_dir: Path,
    zip_path: Path,
    exclude_patterns: set[str],
    date_time: tuple[int, int, int, int, int, int],
) -> None:
    entries: list[tuple[str, Path]] = []
    for path in source_dir.rglob('*'):
        relative = path.relative_to(source_dir).as_posix()
        if not relative:
            continue
        if should_exclude(relative, exclude_patterns):
            continue
        if path.is_dir():
            continue
        entries.append((relative, path))

    entries.sort(key=lambda item: item[0])
    # Defense in depth: ZIP DOS time only stores even seconds.
    date_time = (
        date_time[0],
        date_time[1],
        date_time[2],
        date_time[3],
        date_time[4],
        date_time[5] - (date_time[5] % 2),
    )

    with ZipFile(
        zip_path,
        'w',
        compression=ZIP_DEFLATED,
        compresslevel=ZIP_COMPRESS_LEVEL,
    ) as zip_file:
        for relative, path in entries:
            info = ZipInfo(filename=relative, date_time=date_time)
            info.compress_type = ZIP_DEFLATED
            info.create_system = ZIP_CREATE_SYSTEM
            info.external_attr = ZIP_FILE_EXTERNAL_ATTR
            zip_file.writestr(
                info,
                path.read_bytes(),
                compress_type=ZIP_DEFLATED,
                compresslevel=ZIP_COMPRESS_LEVEL,
            )


def should_exclude(relative_path: str, exclude_patterns: set[str]) -> bool:
    for pattern in exclude_patterns:
        if pattern.endswith('/'):
            prefix = pattern.rstrip('/')
            if relative_path == prefix or relative_path.startswith(pattern):
                return True
            continue
        if relative_path == pattern:
            return True
    return False


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open('rb') as handle:
        for chunk in iter(lambda: handle.read(65536), b''):
            digest.update(chunk)
    return digest.hexdigest()


def check_built_packages(generated: dict[str, Path]) -> list[str]:
    """Run the manifest files check on each built ZIP. Returns error messages."""
    errors: list[str] = []
    for name, zip_path in sorted(generated.items()):
        manifest_name = 'pkg_wendfold.xml' if name == 'pkg_wendfold' else 'wendfold.xml'
        errors.extend(check_manifest_files(zip_path, manifest_name))
    return errors


def check_manifest_files(zip_path: Path, manifest_name: str) -> list[str]:
    """Verify every manifest path exists in the ZIP and flag unexpected extras.

    Covers <files>, <media>, <languages>, and sql install/update/uninstall paths.
    Root extras limited to the manifest, scriptfile, and ALLOWED_ROOT_EXTRAS.
    """
    errors: list[str] = []
    label = zip_path.name

    with ZipFile(zip_path, 'r') as zip_file:
        names = {info.filename for info in zip_file.infolist() if not info.is_dir()}
        if manifest_name not in names:
            return [f'{label}: missing manifest {manifest_name} at ZIP root']

        try:
            root = ET.fromstring(zip_file.read(manifest_name))
        except ET.ParseError as exc:
            return [f'{label}: invalid manifest XML: {exc}']

        required: set[str] = {manifest_name}
        covered_prefixes: set[str] = set()

        scriptfile = (root.findtext('scriptfile') or '').strip()
        if scriptfile:
            required.add(scriptfile)

        _collect_files_node(root.find('files'), required, covered_prefixes)
        admin = root.find('administration')
        if admin is not None:
            _collect_files_node(admin.find('files'), required, covered_prefixes)
            _collect_language_nodes(admin.findall('languages'), required)

        _collect_language_nodes(root.findall('languages'), required)
        _collect_media_nodes(root.findall('media'), required, covered_prefixes)
        # Component install/update/uninstall SQL paths are relative to admin/.
        sql_base = 'admin' if root.attrib.get('type') == 'component' else ''
        _collect_sql_nodes(root, required, covered_prefixes, base_folder=sql_base)

        for path in sorted(required):
            if path.endswith('/'):
                prefix = path
                if not any(name.startswith(prefix) for name in names):
                    errors.append(f'{label}: manifest path missing from ZIP: {path}')
                continue
            if path not in names and not any(name.startswith(path + '/') for name in names):
                errors.append(f'{label}: manifest path missing from ZIP: {path}')

        for name in sorted(names):
            if _zip_entry_is_covered(name, required, covered_prefixes, manifest_name, scriptfile):
                continue
            errors.append(f'{label}: unexpected ZIP entry not listed in manifest: {name}')

    return errors


def _collect_files_node(
    files_node: ET.Element | None,
    required: set[str],
    covered_prefixes: set[str],
) -> None:
    if files_node is None:
        return

    folder = (files_node.attrib.get('folder') or '').strip().strip('/')

    for child in list(files_node):
        tag = _local(child.tag)
        text = (child.text or '').strip().strip('/')
        if not text and tag not in ('folder', 'filename', 'file'):
            continue

        if tag == 'folder':
            rel = _join_folder(folder, text)
            required.add(rel + '/')
            covered_prefixes.add(rel + '/')
            continue

        if tag in ('filename', 'file'):
            rel = _join_folder(folder, text)
            required.add(rel)
            continue


def _collect_language_nodes(nodes: list[ET.Element], required: set[str]) -> None:
    for languages in nodes:
        folder = (languages.attrib.get('folder') or '').strip().strip('/')
        for language in languages.findall('language'):
            text = (language.text or '').strip().strip('/')
            if not text:
                continue
            required.add(_join_folder(folder, text))


def _collect_media_nodes(
    nodes: list[ET.Element],
    required: set[str],
    covered_prefixes: set[str],
) -> None:
    for media in nodes:
        folder = (media.attrib.get('folder') or '').strip().strip('/')
        for child in list(media):
            tag = _local(child.tag)
            text = (child.text or '').strip().strip('/')
            if not text:
                continue
            rel = _join_folder(folder, text)
            if tag == 'folder':
                required.add(rel + '/')
                covered_prefixes.add(rel + '/')
            elif tag == 'filename':
                required.add(rel)


def _collect_sql_nodes(
    root: ET.Element,
    required: set[str],
    covered_prefixes: set[str],
    base_folder: str = '',
) -> None:
    for section_name in ('install', 'uninstall'):
        section = root.find(section_name)
        if section is None:
            continue
        sql = section.find('sql')
        if sql is None:
            continue
        for file_node in sql.findall('file'):
            text = (file_node.text or '').strip().strip('/')
            if text:
                required.add(_join_folder(base_folder, text))

    update = root.find('update')
    if update is None:
        return
    schemas = update.find('schemas')
    if schemas is None:
        return
    for schemapath in schemas.findall('schemapath'):
        text = (schemapath.text or '').strip().strip('/')
        if text:
            rel = _join_folder(base_folder, text)
            required.add(rel + '/')
            covered_prefixes.add(rel + '/')


def _join_folder(folder: str, relative: str) -> str:
    relative = relative.strip('/')
    if not folder:
        return relative
    if not relative:
        return folder
    return f'{folder}/{relative}'


def _local(tag: str) -> str:
    if '}' in tag:
        return tag.rsplit('}', 1)[-1]
    return tag


def _zip_entry_is_covered(
    name: str,
    required: set[str],
    covered_prefixes: set[str],
    manifest_name: str,
    scriptfile: str,
) -> bool:
    if name == manifest_name:
        return True
    if scriptfile and name == scriptfile:
        return True
    if '/' not in name and name in ALLOWED_ROOT_EXTRAS:
        return True
    if name in required:
        return True
    for prefix in covered_prefixes:
        if name.startswith(prefix):
            return True
    # Exact folder markers in required use a trailing slash.
    for path in required:
        if path.endswith('/') and name.startswith(path):
            return True
    return False


if __name__ == '__main__':
    raise SystemExit(main())

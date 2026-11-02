#!/usr/bin/env python3
"""Write the sha256 of a release ZIP into a Joomla update XML, in place.

Usage:
    python3 scripts/release/set-update-sha256.py \
        --xml updates/pkg_wendfold.xml \
        --version 1.2.3 \
        --zip build/dist/pkg_wendfold_v1.2.3.zip

What it does:
  1. Parses the update XML and finds the single <update> entry whose
     <version> equals --version. Exits 3 if there is none or more than one.
  2. Checks that the entry's <downloadurl> ends with the ZIP's file name, so a
     stale URL can't be paired with a fresh hash. --allow-url-mismatch skips
     this check.
  3. Computes the ZIP's sha256 and sets <sha256>HEX</sha256> in that entry.
     An existing element is replaced in place, whatever form it takes:
     <sha256>SHA256_PLACEHOLDER</sha256>, a stale hash, an empty
     <sha256></sha256>, or a self-closing <sha256/> or <sha256 />. If the
     entry has no <sha256>, a new one is inserted right after </downloads>,
     with the same indentation. Joomla checks this value when it downloads
     the update.
  4. Edits the text directly (no XML re-serialization), so comments, attribute
     order, and formatting outside that one element don't change. Then it
     re-parses the file to confirm it's still well-formed and contains the
     expected hash.

Exit codes: 0 ok, 2 usage or missing file, 3 no matching <update> (or more
than one <sha256> in it), 4 downloadurl mismatch, 5 result failed validation.

The script only edits the update XML it's given. It never creates one,
because update XML content is maintained by hand in the repository.
Standard library only. Python 3.9 or newer.
"""
from __future__ import annotations

import argparse
import hashlib
import re
import sys
import xml.etree.ElementTree as ET
from pathlib import Path

UPDATE_BLOCK = re.compile(r'<update\b[^>]*>.*?</update\s*>', re.DOTALL)
VERSION_EL = re.compile(r'<version\s*>\s*([^<]*?)\s*</version\s*>')
# One <sha256> element: paired (<sha256>...</sha256>, content may be empty) or
# self-closing (<sha256/>, <sha256 />). Attributes are tolerated but dropped.
SHA_EL = re.compile(r'<sha256\b[^>]*?/\s*>|<sha256\b[^>]*>[^<]*</sha256\s*>')
DOWNLOADS_CLOSE = re.compile(r'([ \t]*)<downloads\b.*?</downloads\s*>', re.DOTALL)


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open('rb') as handle:
        for chunk in iter(lambda: handle.read(65536), b''):
            digest.update(chunk)
    return digest.hexdigest()


def fail(code: int, message: str) -> None:
    print(f'set-update-sha256: ERROR: {message}', file=sys.stderr)
    raise SystemExit(code)


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument('--xml', required=True, type=Path, help='update XML to edit in place')
    parser.add_argument('--version', required=True, help='version of the <update> entry to edit, without a leading v')
    parser.add_argument('--zip', required=True, type=Path, help='built ZIP to hash')
    parser.add_argument('--allow-url-mismatch', action='store_true',
                        help='do not require <downloadurl> to end with the ZIP file name')
    args = parser.parse_args(argv)

    version = args.version.lstrip('v')
    if not args.xml.is_file():
        fail(2, f'update XML not found: {args.xml} (it must exist in the repository; this script never creates it)')
    if not args.zip.is_file():
        fail(2, f'ZIP not found: {args.zip}')

    text = args.xml.read_text(encoding='utf-8')
    try:
        root = ET.fromstring(text.encode('utf-8'))
    except ET.ParseError as exc:
        fail(5, f'{args.xml} is not well-formed XML before editing: {exc}')

    matches = [u for u in root.iter('update') if (u.findtext('version') or '').strip() == version]
    if len(matches) != 1:
        found = sorted({(u.findtext('version') or '').strip() for u in root.iter('update')})
        fail(3, f'expected exactly one <update> with <version>{version}</version> in {args.xml}, '
                f'found {len(matches)} (versions present: {", ".join(found) or "none"})')

    url = (matches[0].findtext('downloads/downloadurl') or '').strip()
    if not url:
        fail(4, f'the <update> entry for {version} has no <downloads><downloadurl>')
    if not args.allow_url_mismatch and not url.endswith('/' + args.zip.name):
        fail(4, f'<downloadurl> {url!r} does not end with {args.zip.name!r}; '
                'bump the update XML for this version first')

    digest = sha256_file(args.zip)

    # Find the matching block in the raw text and edit only that block.
    target = None
    for block in UPDATE_BLOCK.finditer(text):
        vm = VERSION_EL.search(block.group(0))
        if vm and vm.group(1) == version:
            target = block
            break
    if target is None:
        fail(3, 'could not locate the matching <update> block in the raw text')

    block_text = target.group(0)
    new_el = f'<sha256>{digest}</sha256>'
    existing = SHA_EL.findall(block_text)
    if len(existing) > 1:
        fail(3, f'the <update> entry for {version} has {len(existing)} <sha256> elements; '
                'keep exactly one (or none) and re-run')
    if existing:
        new_block = SHA_EL.sub(new_el, block_text, count=1)
        action = 'replaced'
    else:
        dm = DOWNLOADS_CLOSE.search(block_text)
        if dm is None:
            fail(3, f'no <downloads> element in the <update> entry for {version}')
        indent = dm.group(1)
        new_block = block_text[:dm.end()] + '\n' + indent + new_el + block_text[dm.end():]
        action = 'inserted'

    new_text = text[:target.start()] + new_block + text[target.end():]

    try:
        check_root = ET.fromstring(new_text.encode('utf-8'))
    except ET.ParseError as exc:
        fail(5, f'edited XML is not well-formed: {exc}')
    check = [u for u in check_root.iter('update') if (u.findtext('version') or '').strip() == version]
    if len(check) != 1:
        fail(5, 'edited XML does not contain exactly one matching <update> entry')
    sha_values = [(e.text or '').strip() for e in check[0].findall('sha256')]
    if sha_values != [digest]:
        fail(5, f'edited <update> entry must hold exactly one <sha256> equal to {digest}, found {sha_values}')

    if new_text != text:
        args.xml.write_text(new_text, encoding='utf-8')
    print(f'{action} <sha256>{digest}</sha256> for version {version} in {args.xml} ({args.zip.name})')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())

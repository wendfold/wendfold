#!/usr/bin/env python3
# Copyright (C) 2026 Adan Lozano
# SPDX-License-Identifier: GPL-2.0-or-later
# GNU General Public License version 2 or later; see LICENSE.txt
"""Turn a CHANGELOG.md version section into the wendfold.com changelog page
text and a Joomla changelog.xml (https://manual.joomla.org/docs/building-extensions/install-update/installation/change-log/).

Standard library only, like build/build_release.py.

  python3 scripts/release/generate-changelog.py --changelog CHANGELOG.md \
      --section 1.0.0 --version 1.0.0 --date 2026-11-02 \
      --manifest pkg_wendfold/pkg_wendfold.xml --update-xml updates/pkg_wendfold.xml \
      --page-out /tmp/wendfold-changelog-page.md \
      --xml-out updates/changelog.xml

--xml-out updates/changelog.xml is the file that gets committed and published at
https://wendfold.com/updates/changelog.xml. The page text is for
https://wendfold.com/changelog and is not committed. The XML has no date in it,
so its output depends only on CHANGELOG.md and the manifests.

Release notes (used by .github/workflows/release.yml for the draft GitHub release):

  python3 scripts/release/generate-changelog.py --changelog CHANGELOG.md \
      --section 1.0.0 --version 1.0.0 --manifest pkg_wendfold/pkg_wendfold.xml \
      --notes-out build/release-notes.md

--notes-out writes the section as Markdown, with its ### headings and the
original bullet text (links, code spans, nested bullets), minus the bullets
tagged <!-- internal --> and minus every HTML comment. At least one of
--page-out, --xml-out and --notes-out is required.

--section can be repeated (e.g. --section Unreleased --section 1.0.0) to merge
sections. Bullets that contain <!-- internal --> are left out of both outputs.

Exit codes: 0 ok, 2 usage/input error, 3 consistency error (element/type/version).
"""
import argparse
import re
import sys
import xml.etree.ElementTree as ET
from xml.sax.saxutils import escape

# Keep a Changelog heading -> Joomla changelog type.
HEADING_TO_TYPE = {
    "added": "addition", "changed": "change", "fixed": "fix", "security": "security",
    "removed": "remove", "deprecated": "note", "language": "language", "notes": "note",
    "note": "note",
}
# Order of the Joomla docs example.
XML_ORDER = ["security", "fix", "language", "addition", "change", "remove", "note"]
# Public page: the four main groups, then notes. Removed/Language fold into Changed.
PAGE_GROUPS = [("Added", ["addition"]), ("Changed", ["change", "remove", "language"]),
               ("Fixed", ["fix"]), ("Security", ["security"]), ("Notes", ["note"])]
INTERNAL = "<!-- internal -->"
# Folded types get a prefix on the page so they still read correctly.
PAGE_PREFIX = {"remove": "Removed: ", "language": "Language: "}


def fail(code, msg):
    print(f"generate-changelog: {msg}", file=sys.stderr)
    sys.exit(code)


def read_section(text, name):
    """Return the body of '## [name]' (up to the next '## ')."""
    m = re.search(rf"^## \[{re.escape(name)}\][^\n]*\n(.*?)(?=^## |\Z)", text, re.M | re.S)
    if not m:
        fail(2, f"section [{name}] not found")
    return re.sub(r"<!--(?!\s*internal\s*-->).*?-->", "", m.group(1), flags=re.S)


def parse_bullets(body):
    """Yield (heading, [top-level bullet text with nested bullets appended])."""
    out = {}
    heading = None
    cur = None
    for raw in body.splitlines():
        line = raw.rstrip()
        h = re.match(r"^###\s+(.+)$", line)
        if h:
            heading = h.group(1).strip()
            key = heading.lower()
            if key not in HEADING_TO_TYPE:
                fail(2, f"unknown heading '### {heading}' (known: {', '.join(sorted(HEADING_TO_TYPE))})")
            cur = None
            continue
        if heading is None or not line.strip():
            continue
        top = re.match(r"^- (.*)$", line)
        sub = re.match(r"^\s{2,}- (.*)$", line)
        if top:
            cur = {"text": top.group(1).strip(), "subs": []}
            out.setdefault(HEADING_TO_TYPE[heading.lower()], []).append(cur)
        elif sub and cur is not None:
            cur["subs"].append(sub.group(1).strip())
        elif cur is not None:  # continuation line
            if cur["subs"]:
                cur["subs"][-1] += " " + line.strip()
            else:
                cur["text"] += " " + line.strip()
    return out


def plain(s):
    s = s.replace(INTERNAL, "")
    s = re.sub(r"\[([^\]]+)\]\(([^)]+)\)", r"\1 (\2)", s)   # [text](url)
    s = re.sub(r"<(https?://[^>]+)>", r"\1", s)              # <url>
    s = s.replace("`", "").replace("**", "")
    return re.sub(r"\s+", " ", s).strip()


def collect(text, sections):
    merged = {}
    for name in sections:
        for typ, items in parse_bullets(read_section(text, name)).items():
            for it in items:
                if INTERNAL in it["text"] or any(INTERNAL in s for s in it["subs"]):
                    continue
                merged.setdefault(typ, []).append(
                    {"text": plain(it["text"]), "subs": [plain(s) for s in it["subs"]]})
    if not merged:
        fail(2, "no public entries found")
    return merged


def render_notes(text, sections):
    """Public Markdown release notes: the section's own headings and bullet text.

    A bullet block is a top-level '- ' line plus every following line up to the
    next top-level bullet or heading (the same grouping as parse_bullets).
    Blocks tagged internal are dropped, and so is any HTML comment left in the
    text.
    """
    groups = {}  # heading -> list of blocks, in order of first appearance
    for name in sections:
        heading, block = None, None
        for raw in read_section(text, name).splitlines():
            line = raw.rstrip()
            h = re.match(r"^###\s+(.+)$", line)
            if h:
                heading, block = h.group(1).strip(), None
                continue
            if heading is None:
                continue
            if not line.strip():
                if block is not None:
                    block.append("")
                continue
            if re.match(r"^- ", line):
                block = [line]
                groups.setdefault(heading, []).append(block)
            elif block is not None:
                block.append(line)
    out = []
    for heading, blocks in groups.items():
        public = []
        for b in blocks:
            body = "\n".join(b)
            if INTERNAL in body:
                continue
            body = re.sub(r"<!--.*?-->", "", body, flags=re.S)
            public.append("\n".join(l.rstrip() for l in body.splitlines()).strip("\n"))
        if public:
            out += [f"### {heading}", ""] + public + [""]
    notes = "\n".join(out).rstrip() + "\n"
    if "<!--" in notes or "-->" in notes:
        fail(3, "release notes still contain an HTML comment")
    return notes


def manifest_identity(path):
    root = ET.parse(path).getroot()
    typ = root.get("type")
    if typ == "package":
        element = "pkg_" + (root.findtext("packagename") or "").strip()
    else:
        fail(2, f"{path}: only package manifests are supported (type={typ})")
    return element, typ, (root.findtext("version") or "").strip(), (root.findtext("changelogurl") or "").strip()


def render_page(entries, version, date, download):
    lines = ["Wendfold changelog", "", f"Version {version}", f"Released: {date}",
             f"Download: {download}", ""]
    for title, types in PAGE_GROUPS:
        items = [(t, it) for t in types for it in entries.get(t, [])]
        if not items:
            continue
        lines.append(title)
        for t, it in items:
            lines.append(f"- {PAGE_PREFIX.get(t, '')}{it['text']}")
            lines.extend(f"  - {s}" for s in it["subs"])
        lines.append("")
    return "\n".join(lines).rstrip() + "\n"


def render_xml(entries, element, typ, version, url):
    x = ['<?xml version="1.0" encoding="utf-8"?>', "<!--",
         "  Copyright (C) 2026 Adan Lozano",
         "  GNU General Public License version 2 or later; see LICENSE.txt", "",
         f"  Joomla changelog for the Wendfold package. Published at {url}.",
         "  Generated by scripts/release/generate-changelog.py from CHANGELOG.md.", "-->",
         "<changelogs>", "    <changelog>",
         f"        <element>{escape(element)}</element>", f"        <type>{escape(typ)}</type>",
         f"        <version>{escape(version)}</version>"]
    for t in XML_ORDER:
        if not entries.get(t):
            continue
        x.append(f"        <{t}>")
        for it in entries[t]:
            text = it["text"] + (": " + "; ".join(it["subs"]) if it["subs"] else "")
            x.append(f"            <item>{escape(text)}</item>")
        x.append(f"        </{t}>")
    x += ["    </changelog>", "</changelogs>", ""]
    return "\n".join(x)


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--changelog", required=True)
    ap.add_argument("--section", action="append", required=True)
    ap.add_argument("--version", required=True)
    ap.add_argument("--date", default="YYYY-MM-DD")
    ap.add_argument("--download", default="https://wendfold.com/download")
    ap.add_argument("--manifest", required=True, help="pkg_wendfold/pkg_wendfold.xml")
    ap.add_argument("--update-xml", help="updates/pkg_wendfold.xml (element/type cross-check)")
    ap.add_argument("--changelog-url", default="https://wendfold.com/updates/changelog.xml")
    ap.add_argument("--page-out", help="wendfold.com changelog page text")
    ap.add_argument("--xml-out", help="Joomla changelog XML (updates/changelog.xml)")
    ap.add_argument("--notes-out", help="public Markdown release notes")
    a = ap.parse_args()
    if not (a.page_out or a.xml_out or a.notes_out):
        ap.error("give at least one of --page-out, --xml-out, --notes-out")

    element, typ, mver, murl = manifest_identity(a.manifest)
    if mver != a.version:
        fail(3, f"manifest version {mver} != --version {a.version}")
    if murl != a.changelog_url:
        print(f"warning: manifest <changelogurl> is '{murl}', expected {a.changelog_url}", file=sys.stderr)
    if a.update_xml:
        ups = [u for u in ET.parse(a.update_xml).getroot().iter("update")
               if (u.findtext("version") or "").strip() == a.version]
        if len(ups) != 1:
            fail(3, f"{a.update_xml}: expected one <update> for {a.version}, found {len(ups)}")
        ue, ut = (ups[0].findtext("element") or "").strip(), (ups[0].findtext("type") or "").strip()
        if (ue, ut) != (element, typ):
            fail(3, f"{a.update_xml}: element/type {ue}/{ut} != manifest {element}/{typ}")
        if (ups[0].findtext("changelogurl") or "").strip() != a.changelog_url:
            print(f"warning: {a.update_xml} <changelogurl> differs from {a.changelog_url}", file=sys.stderr)

    text = open(a.changelog, encoding="utf-8").read()
    entries = collect(text, a.section)
    outputs = []
    if a.page_out:
        outputs.append((a.page_out, render_page(entries, a.version, a.date, a.download)))
    if a.xml_out:
        xml = render_xml(entries, element, typ, a.version, a.changelog_url)
        ET.fromstring(xml.encode("utf-8"))  # well-formed or raise
        outputs.append((a.xml_out, xml))
    if a.notes_out:
        outputs.append((a.notes_out, render_notes(text, a.section)))
    for path, content in outputs:
        with open(path, "w", encoding="utf-8") as f:
            f.write(content)
    print(f"wrote {' and '.join(p for p, _ in outputs)} ({element}/{typ} {a.version}; "
          + ", ".join(f"{t}={len(entries.get(t, []))}" for t in XML_ORDER) + ")")


if __name__ == "__main__":
    main()

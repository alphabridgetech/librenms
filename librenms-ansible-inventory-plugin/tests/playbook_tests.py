#!/usr/bin/env python3
"""
Static tests for every playbook under playbooks/ - no device needed.

For each playbook file three test cases are produced:
  yaml     - file parses as YAML and is a list of plays
  syntax   - ansible-playbook --syntax-check passes
  python   - every Python script the playbook writes (copy: dest=*.py,
             content=...) compiles, after replacing Jinja {{ }} / {% %}

Writes JUnit XML (for Jenkins) and an HTML summary (for the e-mail).

  python3 tests/playbook_tests.py --junit reports/results.xml --html reports/summary.html
"""

import argparse
import html
import os
import re
import subprocess
import sys
import tempfile
import time
import xml.etree.ElementTree as ET
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path

import yaml

PLUGIN_DIR = Path(__file__).resolve().parent.parent
PLAYBOOK_DIR = PLUGIN_DIR / "playbooks"

# Inventory used only for --syntax-check (hosts are never contacted).
DUMMY_INVENTORY = """
all:
  children:
    alphabridge_devices:
      hosts:
        syntax-check-host:
          ansible_host: 127.0.0.1
"""

JINJA_EXPR = re.compile(r"\{\{.*?\}\}", re.S)
JINJA_STMT_LINE = re.compile(r"^[ \t]*\{%.*?%\}[ \t]*\n", re.M | re.S)
JINJA_STMT = re.compile(r"\{%.*?%\}", re.S)
JINJA_COMMENT = re.compile(r"\{#.*?#\}", re.S)
# dest is often a variable ("{{ script_path }}"), so also recognise Python by its imports
PYTHON_HINT = re.compile(r"^\s*(import \w+|from [\w.]+ import )", re.M)


class Result:
    def __init__(self, playbook, check):
        self.playbook = playbook          # relative path, e.g. "vlan/getvlan.yml"
        self.check = check                # yaml | syntax | python
        self.status = "passed"            # passed | failed | skipped
        self.message = ""
        self.duration = 0.0

    def fail(self, message):
        self.status, self.message = "failed", message.strip()
        return self

    def skip(self, message):
        self.status, self.message = "skipped", message
        return self


# ------------------------------------------------------------------ checks

def check_yaml(path, rel):
    r = Result(rel, "yaml")
    try:
        data = yaml.safe_load(path.read_text())
    except yaml.YAMLError as e:
        return r.fail(f"YAML error: {e}"), None
    if not isinstance(data, list) or not data:
        return r.fail("A playbook must be a non-empty list of plays"), None
    for i, play in enumerate(data, 1):
        if not isinstance(play, dict) or not ("hosts" in play or "import_playbook" in play):
            return r.fail(f"Play #{i} has no 'hosts:'"), None
    return r, data


def check_syntax(path, rel, inventory):
    r = Result(rel, "syntax")
    env = dict(os.environ, ANSIBLE_NOCOLOR="1", ANSIBLE_DEPRECATION_WARNINGS="0",
               ANSIBLE_LOCALHOST_WARNING="0", ANSIBLE_INVENTORY_UNPARSED_WARNING="0")
    try:
        p = subprocess.run(["ansible-playbook", "--syntax-check", "-i", inventory, str(path)],
                           capture_output=True, text=True, timeout=120, env=env)
    except subprocess.TimeoutExpired:
        return r.fail("ansible-playbook --syntax-check timed out")
    if p.returncode != 0:
        out = (p.stderr or p.stdout).strip()
        return r.fail("\n".join(out.splitlines()[-25:]))
    return r


def iter_tasks(items):
    """All tasks, including those inside block/rescue/always."""
    for item in items or []:
        if not isinstance(item, dict):
            continue
        yield item
        for key in ("block", "rescue", "always"):
            yield from iter_tasks(item.get(key))


def embedded_scripts(plays):
    for play in plays:
        if not isinstance(play, dict):
            continue
        for section in ("pre_tasks", "tasks", "post_tasks", "handlers"):
            for task in iter_tasks(play.get(section)):
                for module in ("ansible.builtin.copy", "copy"):
                    args = task.get(module)
                    if isinstance(args, dict) and isinstance(args.get("content"), str) \
                            and (str(args.get("dest", "")).endswith(".py") or PYTHON_HINT.search(args["content"])):
                        yield task.get("name", args["dest"]), args["content"]


def render_jinja_placeholders(source):
    source = JINJA_COMMENT.sub("", source)
    source = JINJA_STMT_LINE.sub("", source)
    source = JINJA_STMT.sub("", source)
    return JINJA_EXPR.sub("1", source)


def check_python(plays, rel):
    r = Result(rel, "python")
    if plays is None:
        return r.skip("YAML did not parse")
    scripts = list(embedded_scripts(plays))
    if not scripts:
        return r.skip("No embedded Python script")
    errors = []
    for name, content in scripts:
        code = render_jinja_placeholders(content)
        try:
            compile(code, f"{rel} :: {name}", "exec")
        except SyntaxError as e:
            line = code.splitlines()[e.lineno - 1].strip() if e.lineno and e.lineno <= len(code.splitlines()) else ""
            errors.append(f"[{name}] line {e.lineno}: {e.msg}\n    {line}")
    if errors:
        return r.fail("\n".join(errors))
    r.message = f"{len(scripts)} script(s) compiled"
    return r


def test_playbook(path, inventory):
    rel = path.relative_to(PLAYBOOK_DIR).as_posix()
    results = []

    t = time.time()
    r_yaml, plays = check_yaml(path, rel)
    r_yaml.duration = time.time() - t
    results.append(r_yaml)

    t = time.time()
    r_syn = check_syntax(path, rel, inventory) if plays is not None else Result(rel, "syntax").skip("YAML did not parse")
    r_syn.duration = time.time() - t
    results.append(r_syn)

    t = time.time()
    r_py = check_python(plays, rel)
    r_py.duration = time.time() - t
    results.append(r_py)
    return results


# ------------------------------------------------------------------ reports

def write_junit(results, path):
    suite = ET.Element("testsuite", name="playbooks", tests=str(len(results)),
                       failures=str(sum(r.status == "failed" for r in results)),
                       skipped=str(sum(r.status == "skipped" for r in results)))
    for r in results:
        folder = r.playbook.rsplit("/", 1)[0] if "/" in r.playbook else "(root)"
        case = ET.SubElement(suite, "testcase", classname=f"static.{folder}",
                             name=f"{r.playbook} [static: {r.check}]", time=f"{r.duration:.2f}")
        if r.status == "failed":
            ET.SubElement(case, "failure", message=r.message.splitlines()[0][:200]).text = r.message
        elif r.status == "skipped":
            ET.SubElement(case, "skipped", message=r.message)
    Path(path).parent.mkdir(parents=True, exist_ok=True)
    ET.ElementTree(suite).write(path, encoding="utf-8", xml_declaration=True)


def write_html(results, path, playbooks):
    by_pb = {}
    for r in results:
        by_pb.setdefault(r.playbook, {})[r.check] = r
    failed_pbs = [pb for pb, rs in by_pb.items() if any(x.status == "failed" for x in rs.values())]
    icon = {"passed": "&#9989;", "failed": "&#10060;", "skipped": "&#8211;"}

    rows = []
    for pb in sorted(by_pb, key=lambda p: (p not in failed_pbs, p)):
        rs = by_pb[pb]
        cells = "".join(f'<td style="text-align:center">{icon[rs[c].status]}</td>' for c in ("yaml", "syntax", "python"))
        err = "<br>".join(html.escape(rs[c].message.splitlines()[0][:160]) for c in ("yaml", "syntax", "python")
                          if rs[c].status == "failed")
        rows.append(f"<tr><td>{html.escape(pb)}</td>{cells}<td style='color:#b00'>{err}</td></tr>")

    passed = len(by_pb) - len(failed_pbs)
    Path(path).parent.mkdir(parents=True, exist_ok=True)
    Path(path).write_text(f"""
<div style="font-family:Arial,sans-serif">
<h2 style="margin:0 0 6px">Playbook tests: {passed} of {len(playbooks)} playbooks OK, {len(failed_pbs)} failed</h2>
<p style="margin:0 0 12px;color:#555">Checks per playbook: YAML, Ansible syntax, embedded Python. No device was contacted.</p>
<table cellpadding="5" cellspacing="0" border="1" style="border-collapse:collapse;font-size:13px">
<tr style="background:#eee"><th>Playbook</th><th>YAML</th><th>Syntax</th><th>Python</th><th>Error</th></tr>
{''.join(rows)}
</table></div>""")


# ------------------------------------------------------------------ main

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--junit", default="reports/results.xml")
    ap.add_argument("--html", default="reports/summary.html")
    ap.add_argument("--workers", type=int, default=8)
    args = ap.parse_args()

    playbooks = sorted(p for p in PLAYBOOK_DIR.rglob("*") if p.suffix in (".yml", ".yaml") and p.is_file())
    print(f"Found {len(playbooks)} playbooks in {PLAYBOOK_DIR}")

    with tempfile.NamedTemporaryFile("w", suffix=".yml", delete=False) as inv:
        inv.write(DUMMY_INVENTORY)
    try:
        with ThreadPoolExecutor(args.workers) as pool:
            results = [r for rs in pool.map(lambda p: test_playbook(p, inv.name), playbooks) for r in rs]
    finally:
        os.unlink(inv.name)

    for r in results:
        if r.status != "passed":
            print(f"{r.status.upper():8} {r.playbook} [{r.check}] {r.message.splitlines()[0] if r.message else ''}")

    write_junit(results, args.junit)
    write_html(results, args.html, playbooks)

    failed = sum(r.status == "failed" for r in results)
    print(f"\n{len(results)} checks: {sum(r.status == 'passed' for r in results)} passed, "
          f"{failed} failed, {sum(r.status == 'skipped' for r in results)} skipped")
    # Exit 0 so Jenkins' junit step decides UNSTABLE vs SUCCESS; 2 only if nothing ran.
    sys.exit(0 if results else 2)


if __name__ == "__main__":
    main()

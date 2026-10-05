#!/usr/bin/env python3
"""
Runs the read-only playbooks from tests/device_plan.yml against a REAL switch.

The playbooks hard-code /opt/librenms/librenms-ansible-inventory-plugin (the
path inside the Telequill container). Each playbook is copied into a sandbox
folder with that path pointed at the sandbox, so the original files are never
changed and nothing is written to the live output/ folder.

Credentials: SW_USER / SW_PASS environment variables (Jenkins credential), or
else the hosts/*.yml file whose ansible_host is the switch IP.

  python3 tests/device_tests.py --ip 192.168.200.244 \
      --junit reports/device.xml --html reports/device_summary.html
"""

import argparse
import html
import json
import os
import re
import shutil
import socket
import subprocess
import sys
import time
import xml.etree.ElementTree as ET
from pathlib import Path

import yaml

PLUGIN_DIR = Path(__file__).resolve().parent.parent
PLAYBOOK_DIR = PLUGIN_DIR / "playbooks"
LIVE_ROOT = "/opt/librenms/librenms-ansible-inventory-plugin"

# Text in the playbook run / output file that means the device step failed,
# even when Ansible itself says "ok" (many playbooks use failed_when: false).
ERROR_PATTERNS = re.compile(
    r"Traceback \(most recent call last\)|Authentication failed|Socket is closed|"
    r"No route to host|Connection refused|timed out|Unable to connect|"
    r"Error reading SSH protocol banner|"
    r"\"(?:error|ERROR)\"\s*:\s*\"[^\"]+\"|^\s*error:\s*\S|\bERROR\b(?!S)|% ?Invalid input",
    re.M,
)
RECAP = re.compile(r"^\S+\s*:\s*ok=\d+\s+changed=\d+\s+unreachable=(\d+)\s+failed=(\d+)", re.M)


class Result:
    def __init__(self, playbook):
        self.playbook = playbook
        self.status = "passed"
        self.message = ""
        self.output = ""
        self.duration = 0.0


# ------------------------------------------------------------------ setup

def find_credentials(ip):
    if os.environ.get("SW_USER") and os.environ.get("SW_PASS"):
        return os.environ["SW_USER"], os.environ["SW_PASS"], {}, "Jenkins credential"
    for f in sorted((PLUGIN_DIR / "hosts").glob("*.yml")):
        try:
            data = yaml.safe_load(f.read_text()) or {}
        except yaml.YAMLError:
            continue
        for group in (data.get("all", {}).get("children") or {}).values():
            for hostvars in (group.get("hosts") or {}).values():
                if hostvars and str(hostvars.get("ansible_host")) == ip:
                    return hostvars.get("ansible_user"), hostvars.get("ansible_password"), hostvars, f"hosts/{f.name}"
    return None, None, {}, None


def make_sandbox(root):
    if root.exists():
        shutil.rmtree(root)
    for d in ("tmp", "output", "playbooks/output", "hosts", "bin", "ABOSS/tmp", "ABOSS/output"):
        (root / d).mkdir(parents=True, exist_ok=True)
    # Playbooks call <root>/bin/python3 (the container venv); use the system python here.
    os.symlink(sys.executable, root / "bin" / "python3")
    os.symlink(sys.executable, root / "bin" / "python")


def write_inventory(root, ip, user, password, extra):
    hostvars = {k: v for k, v in extra.items() if not k.startswith("ansible_")}
    hostvars.update(ansible_host=ip, ansible_user=user, ansible_password=password,
                    ansible_connection="local", ansible_python_interpreter=sys.executable)
    inv = {"all": {"children": {"alphabridge_devices": {"hosts": {ip: hostvars}}}}}
    path = root / "hosts" / "inventory.yml"
    path.write_text(yaml.safe_dump(inv))
    path.chmod(0o600)
    return path


def reachable(ip, port=22, timeout=5):
    try:
        with socket.create_connection((ip, port), timeout=timeout):
            return True
    except OSError:
        return False


def output_files(root):
    files = {}
    for d in ("output", "playbooks/output", "ABOSS/output"):
        for f in (root / d).glob("*"):
            if f.is_file():
                files[f] = f.stat().st_mtime
    return files


# ------------------------------------------------------------------ one step

def run_step(step, root, inventory, defaults, timeout):
    rel = step["playbook"]
    r = Result(rel)
    src = PLAYBOOK_DIR / rel

    if step.get("skip"):
        r.status, r.message = "skipped", step["skip"]
        return r
    if not src.is_file():
        r.status, r.message = "failed", f"Playbook file not found: playbooks/{rel}"
        return r

    dst = root / "playbooks" / rel
    dst.parent.mkdir(parents=True, exist_ok=True)
    dst.write_text(src.read_text().replace(LIVE_ROOT, str(root)))

    extra = {k: str(v).format(**defaults) for k, v in (step.get("vars") or {}).items()}
    before = output_files(root)

    env = dict(os.environ, ANSIBLE_NOCOLOR="1", ANSIBLE_HOST_KEY_CHECKING="False",
               ANSIBLE_DEPRECATION_WARNINGS="0", ANSIBLE_LOCALHOST_WARNING="0",
               ANSIBLE_RETRY_FILES_ENABLED="0")
    cmd = ["ansible-playbook", "-i", str(inventory), str(dst)]
    if extra:
        cmd += ["-e", json.dumps(extra)]

    t = time.time()
    try:
        p = subprocess.run(cmd, capture_output=True, text=True, timeout=timeout, env=env, cwd=root)
        out, rc = p.stdout + p.stderr, p.returncode
    except subprocess.TimeoutExpired as e:
        out, rc = (e.stdout or b"").decode(errors="ignore") if isinstance(e.stdout, bytes) else (e.stdout or ""), -1
        out += f"\n[timeout after {timeout}s]"
    r.duration = time.time() - t
    r.output = out[-6000:]

    new_files = [f for f, m in output_files(root).items() if before.get(f) != m]
    problems = []

    if rc == -1:
        problems.append(f"Timed out after {timeout}s")
    elif rc != 0:
        problems.append(f"ansible-playbook exit code {rc}")
    m = RECAP.search(out)
    if m and (int(m.group(1)) or int(m.group(2))):
        problems.append(f"Ansible recap: unreachable={m.group(1)} failed={m.group(2)}")
    hit = ERROR_PATTERNS.search(out)
    if hit:
        line = out[out.rfind("\n", 0, hit.start()) + 1: out.find("\n", hit.end())].strip()
        problems.append(f"Error in run output: {line[:300]}")

    if not new_files:
        problems.append("Playbook did not write any output file")
    for f in new_files:
        text = f.read_text(errors="ignore")
        if not text.strip():
            problems.append(f"Output file {f.name} is empty")
        else:
            hit = ERROR_PATTERNS.search(text)
            if hit:
                problems.append(f"Output file {f.name} contains an error: {text[hit.start():hit.start() + 200].strip()}")

    if problems:
        r.status = "failed"
        r.message = "\n".join(problems) + "\n\n--- last lines of the run ---\n" + "\n".join(out.strip().splitlines()[-25:])
    else:
        r.message = "Output: " + ", ".join(f"{f.name} ({f.stat().st_size} bytes)" for f in new_files)
    return r


# ------------------------------------------------------------------ reports

def write_junit(results, path, ip):
    suite = ET.Element("testsuite", name=f"device {ip}", tests=str(len(results)),
                       failures=str(sum(r.status == "failed" for r in results)),
                       skipped=str(sum(r.status == "skipped" for r in results)))
    for r in results:
        folder = r.playbook.rsplit("/", 1)[0] if "/" in r.playbook else "(root)"
        case = ET.SubElement(suite, "testcase", classname=f"device.{folder}",
                             name=f"{r.playbook} [device {ip}]", time=f"{r.duration:.2f}")
        if r.status == "failed":
            ET.SubElement(case, "failure", message=r.message.splitlines()[0][:200]).text = r.message
        elif r.status == "skipped":
            ET.SubElement(case, "skipped", message=r.message)
        else:
            ET.SubElement(case, "system-out").text = r.message
    Path(path).parent.mkdir(parents=True, exist_ok=True)
    ET.ElementTree(suite).write(path, encoding="utf-8", xml_declaration=True)


def write_html(results, path, ip, note=""):
    icon = {"passed": "&#9989;", "failed": "&#10060;", "skipped": "&#8211;"}
    order = {"failed": 0, "skipped": 1, "passed": 2}
    rows = "".join(
        f"<tr><td>{html.escape(r.playbook)}</td><td style='text-align:center'>{icon[r.status]}</td>"
        f"<td>{r.duration:.0f}s</td><td style='color:{'#b00' if r.status == 'failed' else '#555'}'>"
        f"{html.escape(r.message.splitlines()[0][:200]) if r.message else ''}</td></tr>"
        for r in sorted(results, key=lambda x: (order[x.status], x.playbook)))
    passed = sum(r.status == "passed" for r in results)
    failed = sum(r.status == "failed" for r in results)
    Path(path).parent.mkdir(parents=True, exist_ok=True)
    Path(path).write_text(f"""
<div style="font-family:Arial,sans-serif;margin-bottom:24px">
<h2 style="margin:0 0 6px">Device tests on switch {html.escape(ip)}: {passed} passed, {failed} failed</h2>
<p style="margin:0 0 12px;color:#555">Read-only (get/show) playbooks were run on the real switch. {html.escape(note)}</p>
<table cellpadding="5" cellspacing="0" border="1" style="border-collapse:collapse;font-size:13px">
<tr style="background:#eee"><th>Playbook</th><th>Result</th><th>Time</th><th>Detail</th></tr>
{rows}
</table></div>""")


# ------------------------------------------------------------------ main

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--ip", required=True)
    ap.add_argument("--plan", default=str(PLUGIN_DIR / "tests" / "device_plan.yml"))
    ap.add_argument("--junit", default="reports/device.xml")
    ap.add_argument("--html", default="reports/device_summary.html")
    ap.add_argument("--sandbox", default="device_sandbox")
    ap.add_argument("--timeout", type=int, default=180, help="seconds per playbook")
    ap.add_argument("--set", action="append", default=[], metavar="KEY=VALUE",
                    help="override a plan default, e.g. --set interface=GigaEthernet0/2")
    args = ap.parse_args()

    plan = yaml.safe_load(Path(args.plan).read_text())
    defaults = {k: str(v) for k, v in (plan.get("defaults") or {}).items()}
    for kv in args.set:
        k, _, v = kv.partition("=")
        if v:
            defaults[k] = v
    steps = plan.get("steps") or []

    user, password, extra, source = find_credentials(args.ip)
    print(f"Switch {args.ip} | {len(steps)} playbooks | credentials from: {source or 'NOT FOUND'}")

    fatal = None
    if not user or not password:
        fatal = (f"No credentials for {args.ip}: add a Jenkins credential (SW_USER/SW_PASS) "
                 f"or a hosts/*.yml file with ansible_host: {args.ip}")
    elif not reachable(args.ip):
        fatal = f"Switch {args.ip} is not reachable on SSH port 22 from this machine"

    results = []
    root = Path(args.sandbox).resolve()
    if fatal:
        print("FATAL:", fatal)
        for s in steps:
            r = Result(s["playbook"])
            r.status, r.message = ("skipped", s["skip"]) if s.get("skip") else ("failed", fatal)
            results.append(r)
    else:
        make_sandbox(root)
        inventory = write_inventory(root, args.ip, user, password, extra)
        try:
            for i, s in enumerate(steps, 1):
                r = run_step(s, root, inventory, defaults, args.timeout)
                results.append(r)
                print(f"[{i:2}/{len(steps)}] {r.status.upper():7} {r.playbook} ({r.duration:.0f}s) "
                      f"{r.message.splitlines()[0] if r.message else ''}")
        finally:
            # Keep the output files for the report, drop everything holding the password.
            reports_out = Path(args.junit).resolve().parent / "device_output"
            if reports_out.exists():
                shutil.rmtree(reports_out)
            shutil.copytree(root / "output", reports_out)
            shutil.rmtree(root, ignore_errors=True)

    write_junit(results, args.junit, args.ip)
    write_html(results, args.html, args.ip, note=fatal or "")
    print(f"\n{len(results)} playbooks: {sum(r.status == 'passed' for r in results)} passed, "
          f"{sum(r.status == 'failed' for r in results)} failed, {sum(r.status == 'skipped' for r in results)} skipped")
    sys.exit(0)


if __name__ == "__main__":
    main()

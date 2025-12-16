#!/usr/bin/env python3
import paramiko
import time
import re
import json
import sys

HOST = "192.168.200.245"
USER = "admin"
PASSWORD = "admin"

# Helper: read available data until prompt or timeout
def read_until_prompt(shell, prompt_regex, timeout=10):
    buf = ""
    end_time = time.time() + timeout
    while time.time() < end_time:
        if shell.recv_ready():
            chunk = shell.recv(65535).decode(errors='ignore')
            buf += chunk
            # if paging appears, handle it (we don't send here; caller will send)
            # if we detected the prompt, return
            if re.search(prompt_regex, buf):
                return buf
            # continue reading if more data arriving
            end_time = time.time() + 0.5  # small extension while data flows
        else:
            time.sleep(0.1)
    return buf

# Remove ANSI / VT100 escape sequences
ansi_re = re.compile(r'\x1B(?:[@-Z\\-_]|\[[0-?]*[ -/]*[@-~])')

try:
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(HOST, username=USER, password=PASSWORD, look_for_keys=False, allow_agent=False, timeout=10)

    shell = ssh.invoke_shell()
    time.sleep(0.5)

    # gather initial banner and prompt to detect device prompt (like 'hostname#' or 'switch>')
    initial = read_until_prompt(shell, r'[#>]\s*$' , timeout=3)
    # try to find the prompt (hostname# or hostname>)
    m = re.search(r'([A-Za-z0-9\-\._]+[#>])\s*$', initial.strip())
    if m:
        prompt = re.escape(m.group(1))
    else:
        # fallback: generic prompt regex (ends with # or >)
        prompt = r'[#>]\s*$'

    # Enter enable mode if possible (many devices require enable)
    # Send enable and send password; if device doesn't prompt for password this will be harmless.
    shell.send("enable\n")
    time.sleep(0.4)
    # If enable asked for password, send it (some devices will ignore)
    shell.send(PASSWORD + "\n")
    time.sleep(0.4)
    # consume any output until prompt returns
    out = read_until_prompt(shell, prompt, timeout=2)

    # Try to disable paging (works on Cisco/IOS-like devices)
    shell.send("terminal length 0\n")
    time.sleep(0.2)
    out += read_until_prompt(shell, prompt, timeout=1)

    # Some devices (HP/Aruba) use different command; attempt common alternative
    shell.send("no page\n")
    time.sleep(0.2)
    out += read_until_prompt(shell, prompt, timeout=1)

    # Now send show vlan
    shell.send("show vlan\n")
    time.sleep(0.3)

    full_output = ""
    read_deadline = time.time() + 20  # overall timeout for collecting output
    while time.time() < read_deadline:
        if shell.recv_ready():
            chunk = shell.recv(65535).decode(errors='ignore')
            full_output += chunk

            # If we see a paging marker, send space to continue
            if "--More--" in chunk or " --More-- " in chunk or "[More]" in chunk or "(space)" in chunk:
                shell.send(" ")
                time.sleep(0.2)
                # extend deadline while output continues
                read_deadline = time.time() + 10
                continue

            # If prompt detected in the recent buffer, we have full output
            if re.search(prompt, full_output):
                break
        else:
            time.sleep(0.2)

    # close shell/ssh
    try:
        shell.close()
    except Exception:
        pass
    ssh.close()

    # clean ANSI sequences and paging markers
    cleaned = ansi_re.sub('', full_output)
    cleaned = cleaned.replace('\r\x08', '')  # handle backspace artifacts
    cleaned = cleaned.replace('--More--', '')
    # normalize lines
    lines = [ln.rstrip() for ln in cleaned.splitlines()]

    # VLAN PARSER (flexible: supports names with spaces)
    vlans = []
    current = None

    # Regex: id, status, name (name may contain spaces), optional ports (ports usually separated by two+ spaces from name)
    # We look for: ID {whitespace} STATUS {whitespace} NAME [two+spaces PORTS...]
    vlan_line_re = re.compile(r'^\s*(\d+)\s+(\S+)\s+(.+?)(?:\s{2,}(.+))?$')

    for line in lines:
        # skip header lines
        if not line:
            continue
        # Skip common header lines
        if re.match(r'^\s*VLAN\s+Status', line, re.I):
            continue
        if re.match(r'^\s*-{2,}\s*$', line):
            continue
        if re.search(r'(connecting|closed|login|password)', line, re.I):
            continue

        # skip any still-present paging label
        if '--More--' in line or '[More]' in line:
            continue

        m = vlan_line_re.match(line)
        if m:
            vlan_id = m.group(1)
            status = m.group(2)
            name = m.group(3).strip()
            ports_raw = m.group(4) or ""
            ports = [p.strip() for p in re.split(r',\s*', ports_raw) if p.strip()]
            current = {"id": vlan_id, "status": status, "name": name, "ports": ports}
            vlans.append(current)
            continue

        # If the line looks like a continuation of ports (comma separated)
        if current and re.match(r'^[\s,/0-9a-zA-Z\-\._]+$', line):
            cont_ports = [p.strip() for p in re.split(r',\s*', line.strip()) if p.strip()]
            if cont_ports:
                current["ports"].extend(cont_ports)

    # Output JSON
    sys.stdout.write(json.dumps(vlans))
    sys.stdout.flush()

except Exception as e:
    err = {"error": str(e)}
    try:
        sys.stdout.write(json.dumps(err))
        sys.stdout.flush()
    except Exception:
        # last-resort print
        print(json.dumps(err))

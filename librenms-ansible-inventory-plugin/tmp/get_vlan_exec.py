#!/usr/bin/env python3
import paramiko
import time
import re
import json
import sys

HOST = "192.168.200.245"
USER = "admin"
PASSWORD = "admin"

def read_until_prompt(shell, prompt_regex, timeout=10):
    buf = ""
    end_time = time.time() + timeout
    while time.time() < end_time:
        if shell.recv_ready():
            buf += shell.recv(65535).decode(errors='ignore')
            if re.search(prompt_regex, buf):
                return buf
            end_time = time.time() + 0.5
        else:
            time.sleep(0.1)
    return buf

ansi_re = re.compile(r'\x1B(?:[@-Z\\-_]|\[[0-?]*[ -/]*[@-~])')

try:
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(HOST, username=USER, password=PASSWORD,
                look_for_keys=False, allow_agent=False, timeout=10)

    shell = ssh.invoke_shell()
    time.sleep(0.5)

    initial = read_until_prompt(shell, r'[#>]\s*$', timeout=3)
    m = re.search(r'([A-Za-z0-9\-\._]+[#>])\s*$', initial.strip())
    prompt = re.escape(m.group(1)) if m else r'[#>]\s*$'

    shell.send("enable\n")
    time.sleep(0.3)
    shell.send(PASSWORD + "\n")
    time.sleep(0.3)
    read_until_prompt(shell, prompt, timeout=2)

    shell.send("terminal length 0\n")
    time.sleep(0.2)
    read_until_prompt(shell, prompt, timeout=1)

    shell.send("no page\n")
    time.sleep(0.2)
    read_until_prompt(shell, prompt, timeout=1)

    shell.send("show vlan\n")
    time.sleep(0.3)

    full_output = ""
    deadline = time.time() + 20
    while time.time() < deadline:
        if shell.recv_ready():
            chunk = shell.recv(65535).decode(errors='ignore')
            full_output += chunk
            if "--More--" in chunk:
                shell.send(" ")
                deadline = time.time() + 10
            if re.search(prompt, full_output):
                break
        else:
            time.sleep(0.2)

    shell.close()
    ssh.close()

    cleaned = ansi_re.sub('', full_output).replace('--More--', '')
    lines = [l.rstrip() for l in cleaned.splitlines()]

    vlan_re = re.compile(r'^\s*(\d+)\s+(\S+)\s+(.+?)(?:\s{2,}(.+))?$')
    vlans = []
    current = None

    for line in lines:
        if not line or line.lower().startswith("vlan"):
            continue
        m = vlan_re.match(line)
        if m:
            current = {
                "id": m.group(1),
                "status": m.group(2),
                "name": m.group(3).strip(),
                "ports": []
            }
            if m.group(4):
                current["ports"] = [p.strip() for p in m.group(4).split(",")]
            vlans.append(current)
            continue

        if current and re.match(r'^[\s,/0-9a-zA-Z\-\.]+$', line):
            current["ports"].extend(
                [p.strip() for p in line.split(",") if p.strip()]
            )

    print(json.dumps({
        "ip": HOST,
        "vlans": vlans
    }))

except Exception as e:
    print(json.dumps({"error": str(e)}))

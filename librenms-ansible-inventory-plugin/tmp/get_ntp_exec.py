#!/usr/bin/env python3
import paramiko
import time
import re
import json

HOST = "192.168.200.245"
USER = "admin"
PASSWORD = "admin@123#"

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

    prompt = r'[#>]\s*$'
    read_until_prompt(shell, prompt, timeout=3)

    shell.send("enable\n")
    time.sleep(0.3)
    shell.send(PASSWORD + "\n")
    time.sleep(0.5)
    read_until_prompt(shell, prompt, timeout=2)

    shell.send("terminal length 0\n")
    time.sleep(0.2)

    shell.send("no page\n")
    time.sleep(0.2)

    shell.send("show ntp\n")
    time.sleep(0.5)

    full_output = ""
    deadline = time.time() + 20

    while time.time() < deadline:
        if shell.recv_ready():
            chunk = shell.recv(65535).decode(errors='ignore')
            full_output += chunk
            if re.search(prompt, full_output):
                break
        else:
            time.sleep(0.2)

    shell.close()
    ssh.close()

    cleaned = ansi_re.sub('', full_output)

    data = {}

    patterns = {
        "timezone": r'Time-zone:\s*(.*)',
        "current_time": r'Current time:\s*(.*)',
        "status": r'Clock Status:\s*(.*)',
        "stratum": r'Clock Stratum:\s*(.*)',
        "leap_indicator": r'Leap Indicator:\s*(.*)',
        "reference_id": r'Reference ID:\s*(.*)',
        "jitter": r'Clock Jitter:\s*(.*)',
        "precision": r'Clock Precision:\s*(.*)',
        "offset": r'Clock Offset:\s*(.*)',
        "root_delay": r'Root Delay:\s*(.*)',
        "root_dispersion": r'Root Dispersion:\s*(.*)',
        "packets_sent": r'Packets Sent:\s*(\d+)',
        "packets_received": r'Packets Received:\s*(\d+)',
        "reference_time": r'Reference Time:\s*(.*)',
        "last_update": r'Last Update Time:\s*(.*)',
    }

    for key, pattern in patterns.items():
        match = re.search(pattern, cleaned)
        if match:
            data[key] = match.group(1).strip()

    print(json.dumps({
        "ip": HOST,
        "ntp": data
    }))

except Exception as e:
    print(json.dumps({"error": str(e)}))

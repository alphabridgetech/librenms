#!/usr/bin/env python3
import telnetlib
import time
import sys
import traceback

HOST = "192.168.200.244"
USER = "admin"
PASSWORD = "Alpha@123#"
VLAN_ADD = ""
VLAN_DELETE = "132,133"

CONNECT_TIMEOUT = 15
PROMPT_TIMEOUT = 10
POLL_INTERVAL = 0.2

def parse_vlans(vlan_str):
    result = set()
    if not vlan_str:
        return []
    vlan_str = vlan_str.replace(",", " ")
    for part in vlan_str.split():
        if "-" in part:
            s, e = part.split("-")
            result.update(range(int(s), int(e) + 1))
        else:
            result.add(int(part))
    return sorted(v for v in result if 2 <= v <= 4094)

def wait_for(tn, patterns, timeout=PROMPT_TIMEOUT):
    end = time.time() + timeout
    buf = b""
    while time.time() < end:
        try:
            chunk = tn.read_very_eager()
        except:
            chunk = b""
        if chunk:
            buf += chunk
            for p in patterns:
                if p in buf:
                    return buf, p
        time.sleep(POLL_INTERVAL)
    return buf, None

try:
    add_vlans = parse_vlans(VLAN_ADD)
    del_vlans = parse_vlans(VLAN_DELETE)

    tn = telnetlib.Telnet(HOST, timeout=CONNECT_TIMEOUT)

    buf, _ = wait_for(tn, [b"Username", b"Password", b">", b"#"], 4)

    if b"Username" in buf or b"login" in buf:
        tn.write((USER + "\n").encode())
        buf, _ = wait_for(tn, [b"Password", b">", b"#"])

    if b"Password" in buf:
        tn.write((PASSWORD + "\n").encode())
        buf, _ = wait_for(tn, [b">", b"#"])

    if b">" in buf:
        tn.write(b"enable\n")
        buf, _ = wait_for(tn, [b"Password", b"#"])
        if b"Password" in buf:
            tn.write((PASSWORD + "\n").encode())
            wait_for(tn, [b"#"])

    tn.write(b"config\n")
    wait_for(tn, [b"#", b"config"])

    for vid in add_vlans:
        tn.write(f"vlan {vid}\n".encode())
        wait_for(tn, [b"#", b"config_vlan", b"Error"])

    for vid in del_vlans:
        tn.write(f"no vlan {vid}\n".encode())
        wait_for(tn, [b"#", b"Error"])

    tn.write(b"exit\n")
    time.sleep(0.3)
    tn.write(b"exit\n")
    time.sleep(0.3)

    tn.write(b"write\n")
    buf, _ = wait_for(tn, [b"Confirm", b"saved", b"#"], 4)
    if b"Confirm" in buf:
        tn.write(b"y\n")
        time.sleep(0.5)

    tn.write(b"wr mem\n")
    time.sleep(1)

    tn.close()
    print(f"SUCCESS: VLAN ADD={add_vlans} DELETE={del_vlans}")
    sys.exit(0)

except Exception:
    try:
        tn.close()
    except:
        pass
    traceback.print_exc()
    sys.exit(1)

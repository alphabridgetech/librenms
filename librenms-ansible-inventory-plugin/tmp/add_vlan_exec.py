#!/usr/bin/env python3
import telnetlib
import time
import sys
import traceback

HOST = "192.168.200.245"
USER = "admin"
PASSWORD = "admin"
VLAN_ID = "201"
VLAN_NAME = "VLAN02001"

CONNECT_TIMEOUT = 15
POLL_INTERVAL = 0.2
PROMPT_TIMEOUT = 10

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
    tn = telnetlib.Telnet(HOST, timeout=CONNECT_TIMEOUT)

    buf, _ = wait_for(tn, [b"Username", b"Password", b">", b"#"], timeout=4)

    # Username
    if b"Username" in buf or b"login" in buf:
        tn.write((USER + "\n").encode())
        buf, _ = wait_for(tn, [b"Password", b">", b"#"])

    # Password
    if b"Password" in buf or b"password" in buf:
        tn.write((PASSWORD + "\n").encode())
        buf, _ = wait_for(tn, [b">", b"#"])

    # Enable mode
    if b">" in buf:
        tn.write(b"enable\n")
        buf, _ = wait_for(tn, [b"Password", b"#"])
        if b"Password" in buf:
            tn.write((PASSWORD + "\n").encode())
            buf, _ = wait_for(tn, [b"#"])

    # Config mode
    tn.write(b"config\n")
    buf, _ = wait_for(tn, [b"_config", b"config", b"#"])

    # VLAN create
    tn.write(f"vlan {VLAN_ID}\n".encode())
    buf, _ = wait_for(tn, [b"_config_vlan", b"#", b"Error", b"Invalid"])

    # VLAN name
    if VLAN_NAME.strip():
        tn.write(f"name {VLAN_NAME}\n".encode())
        buf, _ = wait_for(tn, [b"#", b"Error", b"Invalid"])

    # Exit config
    tn.write(b"exit\n")
    time.sleep(0.4)
    tn.write(b"exit\n")
    time.sleep(0.4)

    # Save configuration
    tn.write(b"write\n")
    buf, _ = wait_for(tn, [b"Confirm", b"saved", b"complete", b"#"])

    if buf and (b"Confirm" in buf or b"confirm" in buf):
        tn.write(b"y\n")
        time.sleep(0.4)

    # Fallback save
    tn.write(b"wr mem\n")
    time.sleep(1)

    final, _ = wait_for(tn, [b"#", b"complete", b"saved"], timeout=4)

    tn.close()
    print("SUCCESS: VLAN Added")
    sys.exit(0)

except Exception:
    try:
        tn.close()
    except:
        pass
    traceback.print_exc()
    sys.exit(1)

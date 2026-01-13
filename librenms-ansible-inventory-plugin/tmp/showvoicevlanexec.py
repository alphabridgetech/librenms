import paramiko
import time
import os
import sys
import re

HOST = "192.168.200.244"
USER = "admin"
PASSWORD = "Alpha@123#"
OUTPUT_FILE = "/opt/librenms/librenms-ansible-inventory-plugin/output/192.168.200.244_voicevlanshow.yml"

def read_all(chan, timeout=5):
    end = time.time() + timeout
    output = ""
    while time.time() < end:
        if chan.recv_ready():
            data = chan.recv(65535).decode(errors="ignore")
            output += data
            if "--More--" in data:
                chan.send(" ")
        else:
            time.sleep(0.2)
    return output

try:
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(
        hostname=HOST,
        username=USER,
        password=PASSWORD,
        look_for_keys=False,
        allow_agent=False,
        timeout=10
    )

    shell = ssh.invoke_shell()
    time.sleep(1)
    shell.recv(65535)

    shell.send("enable\n")
    time.sleep(0.5)
    shell.send(PASSWORD + "\n")
    time.sleep(0.5)

    shell.send("terminal length 0\n")
    time.sleep(0.5)

    shell.send("show running-config non-interface | include voice-vlan\n")
    output = read_all(shell, 5)

    ssh.close()

    voice_macs = []

    for line in output.splitlines():
        line = line.strip()
        # Example:
        # voice-vlan mac-address 0800.27ea.e2f5 mask ffff.ffff.ffff
        m = re.search(
            r"voice-vlan\s+mac-address\s+([0-9a-fA-F\.]+)\s+mask\s+([0-9a-fA-F\.]+)",
            line
        )
        if m:
            voice_macs.append({
                "mac": m.group(1),
                "mask": m.group(2)
            })

    os.makedirs(os.path.dirname(OUTPUT_FILE), exist_ok=True)

    with open(OUTPUT_FILE, "w") as f:
        f.write(f"ip: {HOST}\n")
        f.write("mac_addresses:\n")
        for item in voice_macs:
            f.write(f"    - mac: \"{item['mac']}\"\n")
            f.write(f"      mask: \"{item['mask']}\"\n")

    print("SUCCESS")

except Exception as e:
    print("ERROR")
    print(str(e))
    sys.exit(1)

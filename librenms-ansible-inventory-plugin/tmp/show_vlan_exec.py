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

            # Handle pagination
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

    # Disable paging (vendor-safe)
    shell.send("terminal length 0\n")
    time.sleep(0.5)

    shell.send("show running-config non-interface | include voice-vlan\n")
    output = read_all(shell, 6)

    ssh.close()

    voice_vlan_line = ""
    for line in output.splitlines():
        line = line.strip()
        if line.startswith("vlan "):
            voice_vlan_line = line.replace("voice-vlan", "").strip()
            break

    if not voice_vlan_line:
        voice-vlan_list = []
    else:
        voice-vlan_list = voice_vlan_line

    os.makedirs(os.path.dirname(OUTPUT_FILE), exist_ok=True)

    with open(OUTPUT_FILE, "w") as f:
        f.write(f"ip: {HOST}\n")
        f.write("vlans:\n")
        f.write(f"  configured: \"{voice-vlan_list}\"\n")

    print("SUCCESS")

except Exception as e:
    print("ERROR")
    print(str(e))
    sys.exit(1)

import paramiko
import time
import re
import os
import sys

HOST = "192.168.200.244"
USER = "admin"
PASSWORD = "admin"
OUTPUT_FILE = "/opt/librenms/librenms-ansible-inventory-plugin/output/192.168.200.244_getlldp.yml"

def read_channel(chan, wait=1):
    time.sleep(wait)
    output = ""
    while chan.recv_ready():
        output += chan.recv(65535).decode(errors="ignore")
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
    read_channel(shell, 2)

    shell.send("enable\n")
    read_channel(shell, 1)
    shell.send(PASSWORD + "\n")
    read_channel(shell, 1)

    shell.send("terminal length 0\n")
    read_channel(shell, 1)

    shell.send("show running-config non-interface\n")
    output = read_channel(shell, 3)

    ssh.close()

    # ---------- DEFAULT VALUES ----------
    protocol = "close"
    holdtime = "120"
    timer = "30"
    reinit = "2"
    lldp_found = False

    for line in output.splitlines():
        line = line.strip()

        if line == "lldp run":
            protocol = "open"
            lldp_found = True

        m = re.match(r"lldp holdtime (\d+)", line)
        if m:
            holdtime = m.group(1)

        m = re.match(r"lldp timer (\d+)", line)
        if m:
            timer = m.group(1)

        m = re.match(r"lldp reinit (\d+)", line)
        if m:
            reinit = m.group(1)

    if not lldp_found:
        protocol = "close"

    os.makedirs(os.path.dirname(OUTPUT_FILE), exist_ok=True)

    with open(OUTPUT_FILE, "w") as f:
        f.write(f"ip: {HOST}\n")
        f.write("lldp:\n")
        f.write(f"  protocol: {protocol}\n")
        f.write(f"  holdtime: {holdtime}\n")
        f.write(f"  timer: {timer}\n")
        f.write(f"  reinit: {reinit}\n")

    print("SUCCESS")

except Exception as e:
    print("ERROR")
    print(str(e))
    sys.exit(1)

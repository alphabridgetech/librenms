import paramiko
import time
import re
import os

HOST = "192.168.200.244"
USER = "admin"
PASSWORD = "Alpha@123#"
OUTPUT_FILE = "/opt/librenms/librenms-ansible-inventory-plugin/output/192.168.200.244_getlldp.yml"

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
    time.sleep(2)

    if shell.recv_ready():
        shell.recv(65535)

    shell.send("enable\n")
    time.sleep(1)
    shell.send(PASSWORD + "\n")
    time.sleep(1)

    if shell.recv_ready():
        shell.recv(65535)

    shell.send("show running-config non-interface\n")
    time.sleep(2)

    output = ""
    while shell.recv_ready():
        output += shell.recv(65535).decode(errors="ignore")

    ssh.close()

    # ✅ DEFAULT VALUES
    protocol = "close"
    holdtime = "120"   # default
    timer = "30"       # default
    reinit = "2"       # default

    for line in output.splitlines():
        line = line.strip()

        if line == "lldp run":
            protocol = "open"

        m = re.match(r"lldp holdtime (\d+)", line)
        if m:
            holdtime = m.group(1)

        m = re.match(r"lldp timer (\d+)", line)
        if m:
            timer = m.group(1)

        m = re.match(r"lldp reinit (\d+)", line)
        if m:
            reinit = m.group(1)

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

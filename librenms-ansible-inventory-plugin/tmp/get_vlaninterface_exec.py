import paramiko
import time
import re

HOST = "192.168.200.244"
USER = "admin"
PASSWORD = "admin"

try:
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(
        HOST,
        username=USER,
        password=PASSWORD,
        look_for_keys=False,
        allow_agent=False
    )

    shell = ssh.invoke_shell()
    time.sleep(1)

    # Enable mode
    shell.send("enable\n")
    time.sleep(1)
    shell.send(PASSWORD + "\n")
    time.sleep(1)

    # VLAN interface command
    shell.send("show interface brief\n")
    time.sleep(3)

    output = ""
    while shell.recv_ready():
        output += shell.recv(65535).decode(errors="ignore")

    ssh.close()

    # Extract VLAN interface lines
    vlan_interfaces = []
    for line in output.splitlines():
        line = line.strip()
        if re.match(r"^Vlan\d+", line):
            vlan_interfaces.append(line)

    if vlan_interfaces:
        for v in vlan_interfaces:
            print(v)
    else:
        print("ERROR: No VLAN interfaces found")

except Exception as e:
    print("Exception:", e)

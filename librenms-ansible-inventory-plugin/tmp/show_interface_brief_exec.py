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

    # Run command
    shell.send("show interface brief\n")
    time.sleep(3)

    output = ""
    while shell.recv_ready():
        output += shell.recv(65535).decode(errors="ignore")

    ssh.close()

    interfaces = []

    for line in output.splitlines():
        line = line.strip()

        # Skip prompts & headers
        if (
            not line
            or line.startswith("Port")
            or line.startswith("kv>")
            or line.startswith("kv#")
        ):
            continue

        # g0/1  down  Trunk(1) auto auto Giga-TX
        match = re.match(
            r'^(g\d+/\d+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(.+)$',
            line
        )

        if match:
            interfaces.append({
                "port": match.group(1),
                "status": match.group(2),
                "vlan": match.group(3),
                "duplex": match.group(4),
                "speed": match.group(5),
                "type": match.group(6).strip()
            })

    if interfaces:
        for i in interfaces:
            print(
                f"{i['port']} | {i['status']} | VLAN:{i['vlan']} | "
                f"{i['duplex']} | {i['speed']} | {i['type']}"
            )
    else:
        print("ERROR: No interface data found")

except Exception as e:
    print("Exception:", e)

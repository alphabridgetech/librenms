import paramiko
import time
import re

HOST = "192.168.200.244"
USER = "admin"
PASSWORD = "Alpha@123#"

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

    lldp = {
        "protocol_state": "close",
        "holdtime": None,
        "timmer": None,
        "reinit": None
    }

    for line in output.splitlines():
        line = line.strip()

        if line == "lldp run":
            lldp["protocol_state"] = "open"

        match = re.match(r"lldp holdtime (\d+)", line)
        if match:
            lldp["holdtime"] = int(match.group(1))

        match = re.match(r"lldp timer (\d+)", line)
        if match:
            lldp["timmer"] = int(match.group(1))

        match = re.match(r"lldp reinit (\d+)", line)
        if match:
            lldp["reinit"] = int(match.group(1))

    print(
        f"protocol_state={lldp['protocol_state']}\n"
        f"holdtime={lldp['holdtime']}\n"
        f"timmer={lldp['timmer']}\n"
        f"reinit={lldp['reinit']}"
    )

except Exception as e:
    print("ERROR")

import paramiko
import time
import json
from datetime import datetime

HOST = "192.168.200.244"
USER = "admin"
PASSWORD = "Alpha@123#"

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

    shell.send("enable\n")
    time.sleep(1)
    shell.send(PASSWORD + "\n")
    time.sleep(1)

    shell.send("show interface brief\n")
    time.sleep(2)

    output = ""
    while shell.recv_ready():
        output += shell.recv(65535).decode()

    ssh.close()

    interfaces = []
    start = False

    for line in output.splitlines():
        line = line.strip()

        if line.startswith("Port"):
            start = True
            continue

        if not start or not line:
            continue

        parts = line.split()
        if len(parts) < 2:
            continue

        iface = {
            "name": parts[0],
            "status": parts[1],
            "vlan": parts[2] if len(parts) > 2 else None,
            "duplex": parts[3] if len(parts) > 3 else None,
            "speed": parts[4] if len(parts) > 4 else None,
            "type": parts[5] if len(parts) > 5 else None
        }

        interfaces.append(iface)

    result = {
        "status": "success",
        "ip": HOST,
        "current_time": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "interfaces": interfaces,
        "count": len(interfaces)
    }

    print(json.dumps(result))

except Exception as e:
    print(json.dumps({
        "status": "error",
        "message": str(e)
    }))

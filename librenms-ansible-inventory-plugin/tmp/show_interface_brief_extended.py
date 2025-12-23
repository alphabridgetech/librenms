import paramiko
import time
import re
import json

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

    shell.send("enable\n")
    time.sleep(1)
    shell.send(PASSWORD + "\n")
    time.sleep(1)

    shell.send("show interface brief\n")
    time.sleep(3)

    output = ""
    while shell.recv_ready():
        output += shell.recv(65535).decode(errors="ignore")

    ssh.close()

    interfaces = []
    vlan_count = {}

    up_count = 0
    down_count = 0
    access_count = 0
    trunk_count = 0

    for line in output.splitlines():
        line = line.strip()

        if (
            not line
            or line.startswith("Port")
            or line.startswith("kv>")
            or line.startswith("kv#")
        ):
            continue

        match = re.match(
            r'^(g\d+/\d+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(.+)$',
            line
        )

        if not match:
            continue

        port = match.group(1)
        status = match.group(2)
        vlan_raw = match.group(3)
        duplex = match.group(4)
        speed = match.group(5)
        iface_type = match.group(6).strip()

        if status.lower() == "up":
            up_count += 1
        else:
            down_count += 1

        if vlan_raw.lower().startswith("trunk"):
            mode = "TRUNK"
            trunk_count += 1
            vlan_id = vlan_raw
        else:
            mode = "ACCESS"
            access_count += 1
            vlan_id = vlan_raw
            vlan_count[vlan_id] = vlan_count.get(vlan_id, 0) + 1

        interfaces.append({
            "port": port,
            "status": status,
            "mode": mode,
            "vlan": vlan_id,
            "duplex": duplex,
            "speed": speed,
            "type": iface_type
        })

    result = {
        "device": HOST,
        "summary": {
            "total_ports": len(interfaces),
            "up_ports": up_count,
            "down_ports": down_count,
            "access_ports": access_count,
            "trunk_ports": trunk_count,
            "vlan_distribution": vlan_count
        },
        "interfaces": interfaces
    }

    print(json.dumps(result, indent=2))

except Exception as e:
    print(json.dumps({"error": str(e)}))

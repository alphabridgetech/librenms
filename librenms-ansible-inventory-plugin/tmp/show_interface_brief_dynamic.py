import paramiko
import time
import re
import json

HOST = "192.168.200.244"
USER = "admin"
PASSWORD = "admin"

def is_prompt(line):
    # Matches: hostname>, hostname#, sw-1#, etc.
    return bool(re.match(r'^[a-zA-Z0-9._-]+[>#]$', line))

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
    vlan_distribution = {}

    up_ports = 0
    down_ports = 0
    access_ports = 0
    trunk_ports = 0

    for raw_line in output.splitlines():
        line = raw_line.strip()

        # Skip empty lines
        if not line:
            continue

        # Skip header
        if line.lower().startswith("port"):
            continue

        # Skip dynamic prompt
        if is_prompt(line):
            continue

        # Match interface row
        match = re.match(
            r'^(g\d+/\d+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(.+)$',
            line
        )

        if not match:
            continue

        port = match.group(1)
        status = match.group(2).lower()
        vlan_raw = match.group(3)
        duplex = match.group(4)
        speed = match.group(5)
        iface_type = match.group(6).strip()

        if status == "up":
            up_ports += 1
        else:
            down_ports += 1

        if vlan_raw.lower().startswith("trunk"):
            mode = "TRUNK"
            trunk_ports += 1
            vlan = vlan_raw
        else:
            mode = "ACCESS"
            access_ports += 1
            vlan = vlan_raw
            vlan_distribution[vlan] = vlan_distribution.get(vlan, 0) + 1

        interfaces.append({
            "port": port,
            "status": status,
            "mode": mode,
            "vlan": vlan,
            "duplex": duplex,
            "speed": speed,
            "type": iface_type
        })

    result = {
        "device_ip": HOST,
        "summary": {
            "total_ports": len(interfaces),
            "up_ports": up_ports,
            "down_ports": down_ports,
            "access_ports": access_ports,
            "trunk_ports": trunk_ports,
            "vlan_distribution": vlan_distribution
        },
        "interfaces": interfaces
    }

    print(json.dumps(result, indent=2))

except Exception as e:
    print(json.dumps({"error": str(e)}))

import telnetlib
import re
import yaml

HOST = "192.168.200.244"
USER = "admin"
PASSWORD = "admin@123#"

result = {
    "manufacturer": "Alphabridge",
    "model": "",
    "slug": "",
    "part_number": "",
    "u_height": 1.0,
    "weight": "",
    "weight_unit": "kg",
    "is_full_depth": False,
    "airflow": "passive",
    "comments": "",
    "console-ports": [],
    "power-ports": [],
    "interfaces": []
}

try:
    tn = telnetlib.Telnet(HOST, timeout=10)

    tn.read_until(b"Username:")
    tn.write(USER.encode('ascii') + b"\n")

    tn.read_until(b"Password:")
    tn.write(PASSWORD.encode('ascii') + b"\n")

    tn.read_until(b">")
    tn.write(b"enable\n")
    tn.read_until(b"#")

    # Get device info
    tn.write(b"show version\n")
    version_output = tn.read_until(b"#", timeout=5).decode('ascii')

    match = re.search(r'Model\s*:\s*(\S+)', version_output)
    if match:
        result["model"] = match.group(1)
        result["slug"] = match.group(1).lower()
        result["part_number"] = match.group(1)
    else:
        result["model"] = "Alphabridge-Switch"

    # Get interface list
    tn.write(b"show interfaces status\n")
    iface_output = tn.read_until(b"#", timeout=8).decode('ascii')

    for line in iface_output.splitlines():
        if line.startswith("GigabitEthernet") or line.startswith("FastEthernet"):
            iface = line.split()[0]
            result["interfaces"].append({
                "name": iface,
                "type": "1000base-t" if "Gigabit" in iface else "100base-t"
            })

    # Simulated power info
    result["power-ports"].append({
        "name": "PS0",
        "type": "internal",
        "allocated_draw": 24,
        "maximum_draw": 19
    })

    tn.write(b"exit\n")
    tn.write(b"exit\n")
    tn.close()

    with open("/opt/librenms/librenms-ansible-inventory-plugin/tmp/device_info.yml", "w") as f:
        yaml.dump(result, f, sort_keys=False)

except Exception as e:
    with open("/opt/librenms/librenms-ansible-inventory-plugin/tmp/device_info_error.txt", "w") as f:
        f.write("Error: " + str(e))

import re
import yaml
import time
from netmiko import ConnectHandler

HOST = "192.168.200.244"
USER = "admin"
PASSWORD = "admin"

device = {
    "device_type": "cisco_ios",   # closest CLI match
    "ip": HOST,
    "username": USER,
    "password": PASSWORD,
    "fast_cli": False,
    "global_delay_factor": 2,
    "allow_agent": False,
    "use_keys": False
}

result = {
    "manufacturer": "",
    "model": "",
    "serial_number": "",
    "firmware_version": "",
    "hardware_version": "",
    "rom_version": "",
    "pcb_version": "",
    "memory": "",
    "flash": "",
    "base_mac": "",
    "system_image": "",
    "uptime": "",
    "system_time": "",
    "reboot_count": "",
    "snmp_info": {},
    "slug": "",
    "comment": "",
    "interfaces": [],
    "console_ports": [],
    "virtual_ports": [],
    "power_status": []
}

try:
    conn = ConnectHandler(**device)

    # Enter enable if needed
    try:
        conn.enable()
    except:
        pass

    # ===== SHOW VERSION =====
    version_output = conn.send_command("show version", delay_factor=2)

    m = re.search(r'^(Alpha\s+Bridge[^\n]+)', version_output, re.MULTILINE)
    if m:
        result["manufacturer"] = m.group(1).strip()

    m = re.search(r'(AS\d+\w*(?:-\w+)*)', version_output)
    if m:
        result["model"] = m.group(1)
        result["slug"] = m.group(1).lower()

    m = re.search(r'Version\s+([\d\.A-Za-z]+)', version_output)
    if m:
        result["firmware_version"] = m.group(1).strip()

    m = re.search(r'Serial\s*num:\s*([A-Za-z0-9\-]+)', version_output)
    if m:
        result["serial_number"] = m.group(1).strip()

    m = re.search(r'hardware version:([A-Za-z0-9]+)', version_output)
    if m:
        result["hardware_version"] = m.group(1).strip()

    m = re.search(r'ROM:\s*System Bootstrap,\s*Version\s*([\d\.]+)', version_output)
    if m:
        result["rom_version"] = m.group(1).strip()

    m = re.search(r'PCB version:\s*([A-Za-z0-9_.-]+)', version_output)
    if m:
        result["pcb_version"] = m.group(1).strip()

    m = re.search(r'(\d+K)\s*bytes of memory,(\d+K)\s*bytes of flash', version_output)
    if m:
        result["memory"] = m.group(1)
        result["flash"] = m.group(2)

    m = re.search(r'Base ethernet MAC Address:\s*([0-9a-f:]+)', version_output, re.IGNORECASE)
    if m:
        result["base_mac"] = m.group(1)

    m = re.search(r'System image file is\s+"([^"]+)"', version_output)
    if m:
        result["system_image"] = m.group(1)

    snmp = re.search(r'snmp info:\s*vend_ID:(\d+)\s+product_ID:(\d+)\s+system_ID:([^\n]+)', version_output)
    if snmp:
        result["snmp_info"] = {
            "vendor_id": snmp.group(1),
            "product_id": snmp.group(2),
            "system_id": snmp.group(3).strip()
        }

    m = re.search(r'uptime is\s+([0-9:]+)', version_output)
    if m:
        result["uptime"] = m.group(1)

    m = re.search(r'The current time:\s*([0-9\-: ]+)', version_output)
    if m:
        result["system_time"] = m.group(1).strip()

    m = re.search(r'(\d+)\s+times of reboot', version_output)
    if m:
        result["reboot_count"] = m.group(1)

    # ===== SHOW INTERFACE BRIEF =====
    iface_output = conn.send_command("show interface brief", delay_factor=2)

    for line in iface_output.splitlines():
        line = line.strip()
        match = re.match(r'^(g\d+/\d+|v\d+|n\d+)\s+.*?(Giga-[A-Z]+|Fast-[A-Z]+|TenGiga-[A-Z]+|up|down)', line)
        if match:
            name, iface_type = match.groups()
            result["interfaces"].append({
                "name": name,
                "type": iface_type
            })

    # ===== SHOW LINE =====
    line_output = conn.send_command("show line", delay_factor=2)

    for line in line_output.splitlines():
        line = line.strip()
        match = re.match(r'^\*?\s*(\d+)\s+(CTY|VTY)\s+', line)
        if match:
            no, line_type = match.groups()
            entry = {"name": f"line{no}", "type": line_type}
            if line_type == "CTY":
                result["console_ports"].append(entry)
            elif line_type == "VTY":
                result["virtual_ports"].append(entry)

    # ===== SHOW POWER-STATUS =====
    try:
        power_output = conn.send_command("show power-status", delay_factor=2)
        for line in power_output.splitlines():
            line = line.strip()
            if re.match(r'CHASSIS_NUMBER|^-+$|^\s*$', line):
                continue
            m = re.match(r'(\d+)\s+(\d+)\s+(\w+)\s+(\w+)', line)
            if m:
                chassis, power_no, status, presence = m.groups()
                result["power_status"].append({
                    "chassis_number": chassis,
                    "power_number": power_no,
                    "status": status,
                    "presence": presence
                })
    except:
        pass

    conn.disconnect()

    with open("/opt/librenms/librenms-ansible-inventory-plugin/tmp/device_info.yml", "w") as f:
        yaml.dump(result, f, sort_keys=False)

except Exception as e:
    with open("/opt/librenms/librenms-ansible-inventory-plugin/tmp/device_info.yml", "w") as f:
        f.write("Error: " + str(e))

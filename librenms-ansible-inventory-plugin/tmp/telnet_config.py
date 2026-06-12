import telnetlib
import time
import json

HOST = "192.168.200.245"
USER = "admin"
PASSWORD = "admin"

raw_commands = json.loads("[\"!\",\"interface g0\\/4\",\"description customer_1\",\"no spanning-tree\",\"switchport mode trunk\",\"switchport trunk vlan-allowed 2225\",\"switchport pvid 2225\",\"switchport dot1q-translating-tunnel mode QinQ translate 2225 1 0\",\"!\"]")
COMMANDS = raw_commands if isinstance(raw_commands, list) else raw_commands.split("\n")

OUTPUT_FILE = "/opt/librenms/librenms-ansible-inventory-plugin/tmp/telnet_output.txt"

def send_cmd(tn, cmd, waitfor=b"#", timeout=5):
    tn.write(cmd.encode('ascii') + b"\n")
    return tn.read_until(waitfor, timeout).decode(errors="ignore")

try:
    tn = telnetlib.Telnet(HOST, timeout=10)

    tn.read_until(b"Username:")
    tn.write(USER.encode('ascii') + b"\n")

    tn.read_until(b"Password:")
    tn.write(PASSWORD.encode('ascii') + b"\n")

    output = tn.read_until(b">", 5).decode(errors="ignore")

    # ENABLE MODE
    output += send_cmd(tn, "enable")

    # CONFIG MODE
    output += send_cmd(tn, "config")

    # RUN COMMANDS (FAST LOOP)
    for cmd in COMMANDS:
        if cmd.strip():
            output += send_cmd(tn, cmd.strip())

    # SAVE CONFIG
    output += send_cmd(tn, "end")
    output += send_cmd(tn, "write all", timeout=10)

    tn.write(b"exit\n")
    tn.close()

    with open(OUTPUT_FILE, "w") as f:
        f.write(output)

except Exception as e:
    with open(OUTPUT_FILE, "w") as f:
        f.write("ERROR: " + str(e))

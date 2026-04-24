import telnetlib
import time
import json

HOST = "192.168.200.245"
USER = "admin"
PASSWORD = "admin"

raw_commands = json.loads("[\"hostname kunal\"]")
if isinstance(raw_commands, list):
    COMMANDS = raw_commands
else:
    COMMANDS = [line.strip() for line in raw_commands.strip().split('\n') if line.strip()]

OUTPUT_FILE = "/opt/librenms/librenms-ansible-inventory-plugin/tmp/telnet_output.txt"

def read(tn, delay=1):
    time.sleep(delay)
    return tn.read_very_eager().decode(errors="ignore")

try:
    tn = telnetlib.Telnet(HOST, timeout=10)

    tn.read_until(b"Username:")
    tn.write(USER.encode('ascii') + b"\n")

    tn.read_until(b"Password:")
    tn.write(PASSWORD.encode('ascii') + b"\n")

    time.sleep(2)
    output = read(tn)

    # ENABLE MODE
    tn.write(b"enable\n")
    time.sleep(2)
    output += read(tn)

    # ENTER CONFIG MODE
    tn.write(b"config\n")
    time.sleep(2)
    output += read(tn)

    # RUN COMMANDS
    for cmd in COMMANDS:
        tn.write(cmd.encode('ascii') + b"\n")
        time.sleep(2)
        output += read(tn)

    # SAVE CONFIG
    tn.write(b"end\n")
    time.sleep(1)
    tn.write(b"write all\n")
    time.sleep(3)
    output += read(tn)

    tn.write(b"exit\n")
    tn.close()

    with open(OUTPUT_FILE, "w") as f:
        f.write(output)

except Exception as e:
    with open(OUTPUT_FILE, "w") as f:
        f.write("ERROR: " + str(e))

import telnetlib
import time
import json
import sys

HOST = "192.168.200.245"
USER = "admin"
PASSWORD = "admin"

raw_commands = json.loads("[\"interface g0\\/2\",\"descriptiondd kunal_demo\"]")
COMMANDS = raw_commands if isinstance(raw_commands, list) else raw_commands.split("\n")

OUTPUT_FILE = "/opt/librenms/librenms-ansible-inventory-plugin/tmp/telnet_output.txt"

def send_cmd(tn, cmd, waitfor=b"#", timeout=5):
    tn.write(cmd.encode('ascii') + b"\n")
    res = tn.read_until(waitfor, timeout).decode(errors="ignore")
    # Check for common device-level errors
    error_patterns = ["Unknown command", "Invalid input", "Incomplete command", "Ambiguous command", "Invalid command"]
    for pattern in error_patterns:
        if pattern in res:
            raise Exception(f"Device Error on command '{cmd}': {pattern}")
    return res

output = ""
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
    try:
        output += send_cmd(tn, "exit") # Go back one level
        output += send_cmd(tn, "exit") # Go back to privileged EXEC
        output += send_cmd(tn, "write", timeout=10) # Try standard 'write'
    except Exception as save_err:
        output += "\nNOTE: Save/Exit command failed (but user commands were sent): " + str(save_err)

    tn.write(b"exit\n")
    tn.close()

    with open(OUTPUT_FILE, "w") as f:
        f.write(output)
    
    print(output) # Print output so it appears in Ansible result.stdout

except Exception as e:
    error_msg = "ERROR: " + str(e)
    with open(OUTPUT_FILE, "w") as f:
        f.write(error_msg)
    print(error_msg)
    sys.exit(1) # EXIT WITH ERROR CODE SO ANSIBLE KNOWS IT FAILED

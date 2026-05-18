import paramiko
import time
import re
import sys
import json

# Read config from JSON file
CONFIG_FILE = sys.argv[1] if len(sys.argv) > 1 else "/tmp/dayfirst_config.json"

def flush_print(*args, **kwargs):
    print(*args, **kwargs)
    sys.stdout.flush()

def read_until_prompt(shell, timeout=10):
    buf = ""
    end_time = time.time() + timeout
    prompt = r'[>#]\s*$'
    while time.time() < end_time:
        if shell.recv_ready():
            chunk = shell.recv(65535).decode(errors='ignore')
            buf += chunk
            if re.search(prompt, buf):
                return buf
        else:
            time.sleep(0.2)
    return buf

try:
    flush_print(f"DEBUG: Reading config from {CONFIG_FILE}")
    with open(CONFIG_FILE, 'r') as f:
        config = json.load(f)

    HOST = config['host']
    USER = config['user']
    PASSWORD = config['password']
    commands = config['commands']

    flush_print(f"DEBUG: Loaded {len(commands)} commands: {commands}")

    flush_print(f"DEBUG: Connecting to {HOST}...")
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(HOST, username=USER, password=PASSWORD,
                look_for_keys=False, allow_agent=False, timeout=10)
    flush_print("DEBUG: SSH connected")

    shell = ssh.invoke_shell(width=200, height=50)
    time.sleep(2)
    output = read_until_prompt(shell)
    flush_print(f"DEBUG: Initial output: {repr(output)}")

    # ENABLE MODE
    shell.send("enable\n")
    time.sleep(1)
    enable_output = read_until_prompt(shell, timeout=5)
    output += enable_output
    flush_print(f"DEBUG: Enable output: {repr(enable_output)}")
    if "password" in enable_output.lower() or "pass" in enable_output.lower():
        flush_print("DEBUG: Password prompt detected, sending password")
        shell.send(PASSWORD + "\n")
        time.sleep(1)
        enable_output2 = read_until_prompt(shell, timeout=5)
        output += enable_output2
        flush_print(f"DEBUG: After password: {repr(enable_output2)}")

    # DISABLE PAGING
    flush_print("DEBUG: Disabling paging")
    shell.send("terminal length 0\n")
    time.sleep(1)
    output += read_until_prompt(shell, timeout=5)

    # CONFIG MODE
    flush_print("DEBUG: Entering config mode")
    shell.send("config\n")
    time.sleep(1)
    config_output = read_until_prompt(shell, timeout=5)
    output += config_output
    flush_print(f"DEBUG: Config mode output: {repr(config_output)}")

    # RUN COMMANDS
    for cmd in commands:
        flush_print(f"DEBUG: Sending command: {repr(cmd)}")
        for char in cmd:
            shell.send(char)
            time.sleep(0.01)
        shell.send("\n")
        time.sleep(1)
        cmd_output = read_until_prompt(shell, timeout=5)
        output += cmd_output
        flush_print(f"DEBUG: Command output: {repr(cmd_output)}")

    # EXIT & SAVE
    flush_print("DEBUG: Exiting config and saving")
    shell.send("end\n")
    time.sleep(1)
    output += read_until_prompt(shell, timeout=5)
    shell.send("write memory\n")
    output += read_until_prompt(shell, timeout=15)

    flush_print("SUCCESS")
    flush_print(output)
    shell.close()
    ssh.close()

except Exception as e:
    flush_print(f"ERROR: {str(e)}")
    import traceback
    flush_print(traceback.format_exc())

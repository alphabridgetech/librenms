import paramiko
import time
import sys

HOST = "192.168.200.245"
USER = "admin"
PASSWORD = "admin"

TFTP_SERVER = "192.168.200.179"
FILENAME = "startup-config"
DEST_FILE = "192.168.200.245_2026-07-02_startup-config"

def read(shell, wait=1):
    time.sleep(wait)
    out = ""
    while shell.recv_ready():
        out += shell.recv(65535).decode(errors="ignore")
    return out

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
    output = read(shell)

    # ENTER ENABLE MODE ONLY IF REQUIRED
    if output.strip().endswith(">"):
        shell.send("enable\n")
        time.sleep(1)
        shell.send(PASSWORD + "\n")
        time.sleep(2)
        output += read(shell)

    # VERIFY PRIVILEGED MODE
    if not output.strip().endswith("#"):
        print("FAILED: Not in enable mode")
        print(output)
        sys.exit(1)

    # COPY COMMAND
    shell.send(f"copy flash:{FILENAME} tftp: {TFTP_SERVER}\n")
    time.sleep(2)
    output = read(shell)

    if "Destination file name" in output:
        shell.send(DEST_FILE + "\n")

    # READ FULL TRANSFER
    end = time.time() + 120
    tftp_success = False
    while time.time() < end:
        time.sleep(1)
        chunk = read(shell, 0)
        output += chunk
        if "successfully send" in output.lower():
            tftp_success = True
            break
        if "error" in output.lower() or "timeout" in output.lower():
            break

    if not tftp_success:
        print("FAILED: TFTP transfer failed or timed out")
        print(output)
        sys.exit(1)

    # DISABLE PAGINATION
    shell.send("terminal length 0\n")
    time.sleep(1)
    read(shell)

    # FETCH FILE DIRECTLY OVER SSH AND WRITE LOCALLY
    shell.send(f"show {FILENAME}\n")
    time.sleep(3)
    config_output = read(shell, 2)

    # Filter prompt and command echo out of the output
    lines = config_output.splitlines()
    config_lines = []
    started = False
    for line in lines:
        if line.strip().startswith("!") or "version" in line.lower() or started:
            started = True
            config_lines.append(line)
            if line.strip() == "end" or (started and line.strip().endswith("#")):
                break

    if not config_lines:
        config_lines = lines

    import os
    local_dir = "/tftpboot"
    if os.path.exists(local_dir):
        local_path = os.path.join(local_dir, DEST_FILE)
        with open(local_path, "w") as f:
            f.write("\n".join(config_lines))

    print(output)
    print("SUCCESS")
    sys.exit(0)

except Exception as e:
    print("ERROR:", e)
    sys.exit(1)

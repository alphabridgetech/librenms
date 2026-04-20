import paramiko
import time
import sys

HOST = "192.168.200.245"
USER = "admin"
PASSWORD = "admin"

TFTP_SERVER = "192.168.200.147"
SOURCE_FILE = "192.168.200.245_startup-config"
DEST_FILE = "startup-config"

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

    # ENTER ENABLE MODE IF REQUIRED
    if output.strip().endswith(">"):
        shell.send("enable\n")
        time.sleep(1)
        shell.send(PASSWORD + "\n")
        time.sleep(2)
        output += read(shell)

    if not output.strip().endswith("#"):
        print("FAILED: not in enable mode")
        print(output)
        sys.exit(1)

    print("=== STARTING TFTP UPLOAD ===")

    # COPY FROM TFTP TO FLASH
    shell.send(f"copy tftp:{SOURCE_FILE} flash: {TFTP_SERVER}\n")
    time.sleep(2)
    output = read(shell)

    if "Destination file name" in output:
        shell.send(DEST_FILE + "\n")

    # WAIT FOR TRANSFER
    end_time = time.time() + 180
    success = False

    while time.time() < end_time:
        chunk = read(shell, 1)
        if chunk:
            output += chunk

        if "successfully receive" in output.lower():
            print("TFTP UPLOAD SUCCESS")
            success = True
            break

        if "error" in output.lower():
            print(output)
            sys.exit(1)

    if not success:
        print("FAILED: timeout waiting for TFTP upload")
        print(output)
        sys.exit(1)

    # SMALL DELAY BEFORE REBOOT
    time.sleep(2)

    print("=== STARTING REBOOT ===")

    # REBOOT COMMAND
    shell.send("reboot\n")
    time.sleep(2)
    output += read(shell)

    # HANDLE CONFIRMATION
    if any(x in output.lower() for x in ["(y/n)", "yes/no", "[y/n]", "confirm"]):
        shell.send("y\n")
        time.sleep(2)
        output += read(shell)

    print("SUCCESS: Upload + Reboot completed")
    print(output)

    sys.exit(0)

except Exception as e:
    print("ERROR:", e)
    sys.exit(1)

import paramiko
import time
import sys

HOST = "192.168.200.245"
USER = "admin"
PASSWORD = "admin@123#"

TFTP_SERVER = "192.168.200.151"
FILENAME = "startup-config"
DEST_FILE = "192.168.200.245_startup-config"

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
    while time.time() < end:
        time.sleep(1)
        chunk = read(shell, 0)
        output += chunk
        if "successfully send" in output.lower():
            print(output)
            print("SUCCESS")
            sys.exit(0)
        if "error" in output.lower():
            print(output)
            sys.exit(1)

    print("FAILED: Timeout waiting for TFTP completion")
    print(output)
    sys.exit(1)

except Exception as e:
    print("ERROR:", e)
    sys.exit(1)

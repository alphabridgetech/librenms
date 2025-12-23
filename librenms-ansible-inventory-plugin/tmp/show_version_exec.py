import paramiko
import time
import re

HOST = "192.168.200.244"
USER = "admin"
PASSWORD = "Admin@123#"

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
    time.sleep(2)

    # Flush banner
    if shell.recv_ready():
        shell.recv(65535)

    shell.send("enable\n")
    time.sleep(1)
    shell.send(PASSWORD + "\n")
    time.sleep(1)

    if shell.recv_ready():
        shell.recv(65535)

    shell.send("show version\n")
    time.sleep(3)

    output = ""
    while shell.recv_ready():
        output += shell.recv(65535).decode(errors="ignore")

    ssh.close()

    clean_lines = []

    for line in output.splitlines():
        line = line.strip()

        if not line:
            continue

        # Remove echoed commands
        if line.lower() in ("enable", "show version"):
            continue

        # Remove ANY prompt automatically
        if re.match(r"^[A-Za-z0-9\-_\.]+(\(.*\))?[>#]$", line):
            continue

        # Remove CLI noise
        if "unknown command" in line.lower():
            continue

        clean_lines.append(line)

    print("\n".join(clean_lines))

except Exception as e:
    print("ERROR:", e)

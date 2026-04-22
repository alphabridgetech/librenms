import paramiko
import time
import re

HOST = "192.168.200.245"
USER = "admin"
PASSWORD = "admin"

try:
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(
        hostname=HOST,
        username=USER,
        password=PASSWORD,
        port=22,
        look_for_keys=False,
        allow_agent=False,
        timeout=10
    )

    shell = ssh.invoke_shell()
    time.sleep(2)

    if shell.recv_ready():
        shell.recv(65535)

    shell.send("enable\n")
    time.sleep(1)
    shell.send(PASSWORD + "\n")
    time.sleep(1)

    if shell.recv_ready():
        shell.recv(65535)

    shell.send("show system mtu\n")
    time.sleep(2)

    output = ""
    while shell.recv_ready():
        output += shell.recv(65535).decode(errors="ignore")

    ssh.close()

    mtu = None
    for line in output.splitlines():
        line = line.strip()

        # Match: System MTU Jumbo size is 1900 bytes
        match = re.search(r'System MTU Jumbo size is (\d+)', line)
        if match:
            mtu = match.group(1)
            break

    if mtu:
        print(mtu)
    else:
        print("ERROR")

except Exception as e:
    print("ERROR")

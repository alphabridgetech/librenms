import paramiko
import time
import re

HOST = "192.168.200.241"
USER = "admin"
PASSWORD = "admin@123#"

def send_cmd(shell, cmd, wait=0.3):
    shell.send(cmd + "\n")
    time.sleep(wait)

    output = ""
    end_time = time.time() + 3

    while time.time() < end_time:
        if shell.recv_ready():
            output += shell.recv(65535).decode(errors="ignore")
        else:
            time.sleep(0.1)
    return output

try:
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())

    ssh.connect(
        hostname=HOST,
        username=USER,
        password=PASSWORD,
        look_for_keys=False,
        allow_agent=False,
        timeout=5
    )

    shell = ssh.invoke_shell()
    time.sleep(0.5)

    # Clear buffer
    if shell.recv_ready():
        shell.recv(65535)

    send_cmd(shell, "enable", 0.2)
    send_cmd(shell, PASSWORD, 0.2)

    output = send_cmd(shell, "show system mtu", 0.5)

    ssh.close()

    mtu = None
    for line in output.splitlines():
        match = re.search(r'System MTU Jumbo size is (\d+)', line)
        if match:
            mtu = match.group(1)
            break

    if mtu:
        print(mtu)
    else:
        print("ERROR")

except Exception:
    print("ERROR")

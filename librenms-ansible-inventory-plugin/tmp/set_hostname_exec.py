import paramiko
import time

HOST = "192.168.200.245"
USER = "admin"
PASSWORD = "admin@123#"
NEW_HOSTNAME = "AS212XTGG"

def send_cmd(shell, cmd, wait=0.5):
    shell.send(cmd + "\n")
    time.sleep(wait)

    output = ""
    while shell.recv_ready():
        output += shell.recv(65535).decode('utf-8')
    return output

try:
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())

    ssh.connect(
        HOST,
        username=USER,
        password=PASSWORD,
        look_for_keys=False,
        allow_agent=False,
        timeout=5
    )

    shell = ssh.invoke_shell()
    time.sleep(0.5)

    # Fast execution sequence
    send_cmd(shell, "enable", 0.3)
    send_cmd(shell, PASSWORD, 0.3)
    send_cmd(shell, "config", 0.3)
    send_cmd(shell, f"hostname {NEW_HOSTNAME}", 0.3)
    send_cmd(shell, "end", 0.3)
    send_cmd(shell, "write memory", 1)

    print("SUCCESS")

    ssh.close()

except Exception as e:
    print("ERROR:", str(e))

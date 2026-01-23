import paramiko
import time

HOST = "192.168.200.245"
USER = "admin"
PASSWORD = "admin"

TFTP_SERVER = "192.168.200.63"
FILENAME = "startup-config"

def read_all(shell, delay=1):
    time.sleep(delay)
    output = ""
    while shell.recv_ready():
        output += shell.recv(65535).decode(errors="ignore")
    return output

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
    read_all(shell, 2)

    shell.send("enable\n")
    read_all(shell, 1)
    shell.send(PASSWORD + "\n")
    read_all(shell, 2)

    # Correct device-specific TFTP command
    shell.send(f"copy tftp:{FILENAME} flash: {TFTP_SERVER}\n")
    output = read_all(shell, 3)

    if "Destination file name" in output:
        shell.send(FILENAME + "\n")
        output += read_all(shell, 3)

    if "successfully receive" in output.lower():
        print("SUCCESS")
    else:
        print(output)

    ssh.close()

except Exception as e:
    print("ERROR:", e)

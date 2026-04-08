import paramiko
import time

HOST = "192.168.200.244"
USER = "admin"
PASSWORD = "admin"

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
    time.sleep(1)

    shell.send("enable\n")
    time.sleep(1)
    shell.send(PASSWORD + "\n")
    time.sleep(1)

    shell.send("show running-config | include hostname\n")
    time.sleep(2)

    output = ""
    while shell.recv_ready():
        output += shell.recv(65535).decode()

    hostname_value = None
    for line in output.splitlines():
        line = line.strip()
        if line.startswith("hostname "):
            hostname_value = line.split("hostname ")[1].strip()
            break

    if hostname_value:
        print(hostname_value)
    else:
        print("ERROR")

    ssh.close()

except Exception:
    print("ERROR")

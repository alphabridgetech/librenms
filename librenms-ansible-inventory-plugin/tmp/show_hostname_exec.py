import paramiko
import time
import re

HOST = "192.168.200.244"
USER = "admin"
PASSWORD = "admin"

try:
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(HOST, username=USER, password=PASSWORD, look_for_keys=False, allow_agent=False)

    shell = ssh.invoke_shell()
    time.sleep(1)

    # Enter enable mode
    shell.send("enable\n")
    time.sleep(1)
    shell.send(PASSWORD + "\n")
    time.sleep(1)

    # Run hostname command
    shell.send("show running-config | include hostname\n")
    time.sleep(2)

    output = ""
    while shell.recv_ready():
        output += shell.recv(65535).decode()

    # Extract only hostname value
    # Example: "hostname test_switch" → "test_switch"
    match = re.search(r'hostname\s+(\S+)', output)
    if match:
        print(match.group(1))
    else:
        print("Hostname not found")

    ssh.close()

except Exception as e:
    print("Exception:", e)

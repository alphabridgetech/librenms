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

    # Run command to get hostname
    shell.send("show running-config | include hostname\n")
    time.sleep(2)

    output = ""
    while shell.recv_ready():
        output += shell.recv(65535).decode()

    # Extract only exact hostname line
    # Ensure it matches:  hostname test_switch
    lines = output.splitlines()
    hostname_value = None
    for line in lines:
        line = line.strip()
        if line.startswith("hostname "):  # exact match only
            hostname_value = line.split("hostname ")[1].strip()
            break

    if hostname_value:
        print(hostname_value)
    else:
        print("ERROR: Hostname not found")

    ssh.close()

except Exception as e:
    print("Exception:", e)

import paramiko
import time
import sys

HOST = "192.168.200.245"
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

    # Enter enable mode
    shell.send("enable\n")
    time.sleep(1)
    shell.send(PASSWORD + "\n")
    time.sleep(1)

    # Reboot command (your exact command)
    shell.send("reboot\n")
    time.sleep(1)

    # Confirm reboot
    shell.send("y\n")
    time.sleep(1)

    print("SUCCESS: DEVICE_REBOOTING")

    ssh.close()

except Exception as e:
    print("ERROR:", e)

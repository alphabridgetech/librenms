import paramiko
import time
import sys

HOST = "192.168.200.244"
USER = "admin"
PASSWORD = "Alpha@123#"

MAC  = "0800.27ea.e2f8"
MASK = "ffff.ffff.ffff"

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

    shell.send("config\n")
    time.sleep(1)

    cmd = f"no voice-vlan mac-address {MAC} mask {MASK}"
    shell.send(cmd + "\n")
    time.sleep(1)
    print(f"EXECUTED: {cmd}")

    shell.send("exit\n")
    time.sleep(1)

    shell.send("write all\n")
    time.sleep(2)

    print("SUCCESS")

    ssh.close()

except Exception as e:
    print("ERROR:", e)

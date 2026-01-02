import paramiko
import time

HOST = "192.168.200.244"
USER = "admin"
PASSWORD = "Alpha@123#"

PROTOCOL_STATE = "open"
HOLDTIME = "120"
TIMER = "30"
REINIT = "2"

try:
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(
        hostname=HOST,
        username=USER,
        password=PASSWORD,
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

    shell.send("config\n")
    time.sleep(1)

    if PROTOCOL_STATE == "open":
        shell.send("lldp run\n")
        time.sleep(1)
        shell.send(f"lldp holdtime {HOLDTIME}\n")
        time.sleep(0.5)
        shell.send(f"lldp timer {TIMER}\n")
        time.sleep(0.5)
        shell.send(f"lldp reinit {REINIT}\n")
    else:
        shell.send("no lldp run\n")

    time.sleep(1)
    shell.send("end\n")
    time.sleep(1)
    shell.send("write memory\n")
    time.sleep(2)

    ssh.close()
    print("SUCCESS")

except Exception as e:
    print("ERROR")
    print(e)

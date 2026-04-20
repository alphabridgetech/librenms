import paramiko
import time

HOST = "192.168.200.245"
USER = "admin"
PASSWORD = "admin"
VLAN_ID = "444"
INTERFACE = "g0/5"

try:
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(HOST, username=USER, password=PASSWORD, look_for_keys=False, allow_agent=False)

    shell = ssh.invoke_shell()
    time.sleep(1)

    def send_cmd(cmd, delay=1):
        shell.send(cmd + "\n")
        time.sleep(delay)

    send_cmd("enable")
    send_cmd(PASSWORD)
    send_cmd("config")

    # Interface config
    send_cmd(f"interface {INTERFACE}")
    send_cmd("switchport mode access")
    send_cmd(f"switchport pvid {VLAN_ID}")

    send_cmd("end")
    send_cmd("write memory", 2)

    print("SUCCESS")

    ssh.close()

except Exception as e:
    print("ERROR:", e)

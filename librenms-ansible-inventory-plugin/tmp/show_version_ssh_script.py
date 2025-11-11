import paramiko
import warnings
import time

warnings.filterwarnings("ignore", category=DeprecationWarning)

HOST = "192.168.200.243"
USER = "admin"
PASSWORD = "admin"

try:
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())

    # Explicitly allow legacy algorithms
    client = paramiko.Transport((HOST, 22))
    client.connect(username=USER, password=PASSWORD)
    session = client.open_session()
    session.get_pty()
    session.invoke_shell()

    time.sleep(1)
    session.send("enable\n")
    time.sleep(1)
    session.send("show version\n")
    time.sleep(2)

    output = session.recv(99999).decode('utf-8', errors='ignore')
    print(output)

    client.close()

    with open("/opt/librenms/librenms-ansible-inventory-plugin/tmp/show_version_output.txt", "w") as f:
        f.write(output)

except Exception as e:
    with open("/opt/librenms/librenms-ansible-inventory-plugin/tmp/show_version_error.txt", "w") as f:
        f.write("Error: " + str(e))

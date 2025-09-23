import telnetlib
import time

HOST = "192.168.200.242"
USER = "admin"
PASSWORD = "admin"
COMMAND = "show cpu"

try:
    tn = telnetlib.Telnet(HOST, timeout=10)

    tn.read_until(b"Username:")
    tn.write(USER.encode('ascii') + b"\n")

    tn.read_until(b"Password:")
    tn.write(PASSWORD.encode('ascii') + b"\n")

    tn.read_until(b">")
    tn.write(b"enable\n")

    tn.read_until(b"#")
    tn.write(COMMAND.encode('ascii') + b"\n")
    time.sleep(2)  # Give time for output to complete

    output = tn.read_very_eager().decode('ascii')
    tn.write(b"exit\n")
    tn.close()

    with open("/opt/librenms/librenms-ansible-inventory-plugin/tmp/alphabridge_cpu_data.txt", "w") as f:
        f.write(output)

except Exception as e:
    with open("/opt/librenms/librenms-ansible-inventory-plugin/tmp/alphabridge_cpu_data.txt", "w") as f:
        f.write("Error: " + str(e))

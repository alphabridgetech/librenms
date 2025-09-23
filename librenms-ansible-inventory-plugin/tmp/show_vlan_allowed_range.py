import telnetlib
import time

HOST = "192.168.200.244"
USER = "admin"
PASSWORD = "admin"
PORT_NAME = "g0/1"

try:
    tn = telnetlib.Telnet(HOST, timeout=10)

    tn.read_until(b"Username:")
    tn.write(USER.encode('ascii') + b"\n")

    tn.read_until(b"Password:")
    tn.write(PASSWORD.encode('ascii') + b"\n")

    tn.read_until(b">")
    tn.write(b"enable\n")
    tn.read_until(b"#")

    tn.write(f"show run int {PORT_NAME}\n".encode('ascii'))
    output = tn.read_until(b"#", timeout=5)
    print(output.decode('ascii'))

    tn.write(b"exit\n")
    tn.write(b"exit\n")
    tn.close()

except Exception as e:
    with open("/opt/librenms/librenms-ansible-inventory-plugin/tmp/show_vlan_allowed_range_error.txt", "w") as f:
        f.write("Error: " + str(e))

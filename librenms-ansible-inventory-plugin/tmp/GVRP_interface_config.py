import telnetlib
import time

HOST = "192.168.200.244"
USER = "admin"
PASSWORD = "admin"
PORT_NAME = "g0/7"
GVRP = "enable"


try:
    tn = telnetlib.Telnet(HOST, timeout=10)

    tn.read_until(b"Username:")
    tn.write(USER.encode('ascii') + b"\n")

    tn.read_until(b"Password:")
    tn.write(PASSWORD.encode('ascii') + b"\n")

    tn.read_until(b">")
    tn.write(b"enable\n")
    tn.read_until(b"#")

    tn.write(b"config\n")
    tn.read_until(b"config#", timeout=5)

    if GVRP == "enable":

      tn.write(f"interface {PORT_NAME}\n".encode('ascii'))
      tn.read_until(b"#", timeout=5)

      tn.write(f"gvrp \n".encode('ascii'))
      tn.read_until(b"#", timeout=5)

    elif GVRP == "disable":

      tn.write(f"interface {PORT_NAME} \n".encode('ascii'))
      tn.read_until(b"#", timeout=5)

      tn.write(f"no gvrp \n".encode('ascii'))
      tn.read_until(b"#", timeout=5)

    tn.write(f"show running-config interface {PORT_NAME} \n".encode('ascii'))
    output = tn.read_until(b"#", timeout=5)
    print(output.decode('ascii'))

    tn.write(b"exit\n")
    tn.write(b"exit\n")
    tn.close()

except Exception as e:
    with open("/opt/librenms/librenms-ansible-inventory-plugin/tmp/GVRP_interface_config_error.txt", "w") as f:
        f.write("Error: " + str(e))

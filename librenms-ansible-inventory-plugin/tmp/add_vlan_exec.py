import telnetlib
import time
import sys

HOST = "192.168.200.244"
USER = "admin"
PASSWORD = "admin"
VLAN_ID = "127"
VLAN_NAME = "kunal"

try:
    tn = telnetlib.Telnet(HOST, timeout=10)

    # Login
    tn.read_until(b"Username:")
    tn.write(USER.encode() + b"\n")

    tn.read_until(b"Password:")
    tn.write(PASSWORD.encode() + b"\n")

    # Privileged exec mode
    tn.read_until(b">")
    tn.write(b"enable\n")
    tn.read_until(b"Password:")
    tn.write(PASSWORD.encode() + b"\n")

    # Configuration mode
    tn.read_until(b"#")
    tn.write(b"config\n")

    # VLAN creation
    tn.read_until(b"_config#")
    tn.write(f"vlan {VLAN_ID}\n".encode())
    time.sleep(1)

    tn.read_until(b"#")
    tn.write(f"name {VLAN_NAME}\n".encode())

    # Save config
    tn.read_until(b"#")
    tn.write(b"exit\n")
    tn.read_until(b"#")
    tn.write(b"write memory\n")
    time.sleep(2)

    tn.write(b"exit\n")
    tn.close()

    # Only print AFTER closing session
    print("SUCCESS: VLAN Added")
    sys.exit(0)

except Exception as e:
    print("ERROR: " + str(e))
    sys.exit(1)

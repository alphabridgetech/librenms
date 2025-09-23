import telnetlib
import time

HOST = "192.168.200.244"
USER = "admin"
PASSWORD = "admin"
GVRP_GLOBAL = "disable"
DYNAMIC_VLAN = "disable"

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
    
    tn.write(b"terminal length 0\n")
    tn.read_until(b"config#", timeout=5)
    
    if GVRP_GLOBAL == "enable":
      tn.write(f"gvrp \n".encode('ascii'))
      tn.read_until(b"#", timeout=5)

    elif GVRP_GLOBAL == "disable":
      tn.write(f"no gvrp \n".encode('ascii'))
      tn.read_until(b"#", timeout=5)
    
    if DYNAMIC_VLAN == "enable":
      tn.write(f"gvrp dynamic-vlan-pruning \n".encode('ascii'))
      tn.read_until(b"#", timeout=5)
      
    elif DYNAMIC_VLAN == "disable":
      tn.write(f"no gvrp dynamic-vlan-pruning \n".encode('ascii'))
      tn.read_until(b"#", timeout=5)
    
    
    tn.write(f"show running-config non-interface \n".encode('ascii'))
    output = tn.read_until(b"#", timeout=5)
    print(output.decode('ascii'))

    tn.write(b"exit\n")
    tn.write(b"exit\n")
    tn.close()

except Exception as e:
    with open("/opt/librenms/librenms-ansible-inventory-plugin/tmp/GVRP_global_config_error.txt", "w") as f:
        f.write("Error: " + str(e))

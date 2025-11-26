from netmiko import ConnectHandler

HOST = "192.168.200.244"
USER = "admin"
PASSWORD = "admin"

device = {
    "device_type": "terminal_server",   # works for unknown/Alphabridge
    "ip": HOST,
    "username": USER,
    "password": PASSWORD,
    "fast_cli": False,
    "global_delay_factor": 2,
    "allow_agent": False,
    "use_keys": False
}

try:
    conn = ConnectHandler(**device)

    # Auto detect prompt
    prompt = conn.find_prompt()

    # If prompt ends with '>' then enable is required
    if prompt.endswith(">"):
        try:
            conn.enable()
        except:
            pass

    output = conn.send_command("show version", expect_string=r"#", delay_factor=2)

    conn.disconnect()

    with open("/opt/librenms/librenms-ansible-inventory-plugin/tmp/show_version_output.txt", "w") as f:
        f.write(output)

except Exception as e:
    with open("/opt/librenms/librenms-ansible-inventory-plugin/tmp/show_version_output.txt", "w") as f:
        f.write("Error: " + str(e))

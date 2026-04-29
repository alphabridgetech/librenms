import telnetlib
import time
import json

HOST = "192.168.200.245"
USER = "admin"
PASSWORD = "admin"

raw_commands = json.loads("[\"!\",\"system mtu 9216\",\"!\",\"logging 10.133.22.165\",\"logging 192.168.200.235\",\"logging buffered 409600\",\"logging buffered informational\",\"!\",\"error-disable-recovery 60\",\"!\",\"!\",\"hostname AS212XT_CPE\",\"!\",\"mac access-list deny\",\"deny host 0000.0000.0015 any location 5\",\"permit any any location 6\",\"!\",\"!\",\"mac access-list Permit\",\"permit host 0000.0000.0007 any location 3\",\"permit host 0000.0000.0010 any location 4\",\"permit host 0000.0000.0011 any location 5\",\"deny any any location 6\",\"!\",\"!\",\"link scan fast 10\",\"!\",\"aggregator-group max-active-linknumber 2\",\"aggregator-group atleast-linknumber 1\",\"aggregator-group load-balance both-l4-dports\",\"!\",\"lldp run\",\"!\",\"!\",\"no spanning-tree\",\"!\",\"interface Port-aggregator1\",\"agg-period 1\",\"switchport mode trunk\",\"switchport trunk vlan-allowed 2001-3000,4000\",\"!\",\"!\",\"!\",\"!\",\"interface VLAN1\",\"ip address 192.168.0.1 255.255.255.0\",\"no ip directed-broadcast\",\"!\",\"interface VLAN4000\",\"ip address 10.133.27.164  255.255.255.0\",\"no ip directed-broadcast\",\"!\",\"vlan 2-4094\",\"!\",\"vlan 4000\",\"name Mgmt\",\"!\",\"!\",\"interface TGigaEthernet0\\/1\",\"description uplink\",\"no spanning-tree\",\"switchport mode trunk\",\"switchport trunk vlan-allowed 2001-3000,4000\",\"!\",\"!\",\"loopback-detection\",\"!\",\"interface range g0\\/1-8\",\"loopback-detection enable\",\"loopback-detection recovery-time 60\",\"loopback-detection control shutdown\",\"loopback-detection vlan-control 1\",\"!\",\"!\",\"ip route default 10.133.27.1\",\"!\",\"ip sshd enable\",\"ip sshd save\",\"!\",\"ip http server\",\"!\",\"snmp-server group SNMPV3 v3 auth\",\"snmp-server user Pravin SNMPV3 v3 auth sha 0 Pravin@123\",\"snmp-server user manohar SNMPV3 v3 auth sha 0 Testlab@123\",\"snmp-server version v2c v3\",\"snmp-server community 0 punelab RO\",\"snmp-server host 10.133.22.165 version v2c 0 punelab authentication configure snmp\",\"snmp-server trap-add-hostname\",\"snmp-server trap-logs\",\"!\",\"!\",\"line vty 0 4\",\"exec-timeout 0\",\"!\",\"ip exf\",\"!\",\"ipv6 exf\",\"!\",\"time-zone tz 5 30\",\"ntp client enable\",\"ntp server 10.133.27.112\",\"ntp server 10.133.27.113\",\"!\",\"!\",\"aaa authentication login default group TACACS+ local\",\"aaa authentication enable default none\",\"aaa authorization commands 15 default group TACACS+\",\"aaa authorization commands 0 default group TACACS+\",\"aaa authorization exec default group TACACS+ local\",\"aaa accounting commands 0 default start-stop group TACACS+\",\"aaa accounting commands 15 default start-stop group TACACS+\",\"aaa accounting network default start-stop group TACACS+\",\"!\",\"TACACS+-server host 192.168.200.233\",\"TACACS+-server key Alpha@123#\",\"!\",\"!\",\"localauthor 15\",\"exec privilege default 15\",\"!\",\"localauthor 10\",\"exec privilege default 10\",\"!\",\"localauthor 3\",\"exec privilege default 3\",\"!\",\"username admin author-group 15 password admin@123#\",\"!\",\"username pravin author-group 15 password Alpha@123#\",\"!\",\"username tagore author-group 15 password  Test@123#\",\"!\",\"write all\",\"!\",\"ip telnet attack-defense\",\"no ip telnet enable\",\"!\"]")
COMMANDS = raw_commands if isinstance(raw_commands, list) else raw_commands.split("\n")

OUTPUT_FILE = "/opt/librenms/librenms-ansible-inventory-plugin/tmp/telnet_output.txt"

def send_cmd(tn, cmd, waitfor=b"#", timeout=5):
    tn.write(cmd.encode('ascii') + b"\n")
    return tn.read_until(waitfor, timeout).decode(errors="ignore")

try:
    tn = telnetlib.Telnet(HOST, timeout=10)

    tn.read_until(b"Username:")
    tn.write(USER.encode('ascii') + b"\n")

    tn.read_until(b"Password:")
    tn.write(PASSWORD.encode('ascii') + b"\n")

    output = tn.read_until(b">", 5).decode(errors="ignore")

    # ENABLE MODE
    output += send_cmd(tn, "enable")

    # CONFIG MODE
    output += send_cmd(tn, "config")

    # RUN COMMANDS (FAST LOOP)
    for cmd in COMMANDS:
        if cmd.strip():
            output += send_cmd(tn, cmd.strip())

    # SAVE CONFIG
    output += send_cmd(tn, "end")
    output += send_cmd(tn, "write all", timeout=10)

    tn.write(b"exit\n")
    tn.close()

    with open(OUTPUT_FILE, "w") as f:
        f.write(output)

except Exception as e:
    with open(OUTPUT_FILE, "w") as f:
        f.write("ERROR: " + str(e))

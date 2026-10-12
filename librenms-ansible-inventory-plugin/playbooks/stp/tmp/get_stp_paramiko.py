import sys
import time
import paramiko

if len(sys.argv) != 4:
    print("RESULT_START")
    print("STATUS=FAILED")
    print("ERROR_START")
    print("Usage: get_stp_paramiko.py <ip> <username> <password>")
    print("ERROR_END")
    print("RESULT_END")
    sys.exit(1)

host = sys.argv[1]
username = sys.argv[2]
password = sys.argv[3]

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())

try:
    client.connect(
        hostname=host,
        username=username,
        password=password,
        port=22,
        timeout=10,
        look_for_keys=False,
        allow_agent=False
    )

    shell = client.invoke_shell()
    shell.settimeout(10)

    time.sleep(1)

    # Clear initial device output
    if shell.recv_ready():
        shell.recv(65535)

    # Enter enable mode
    shell.send("enable\n")
    time.sleep(1)

    if shell.recv_ready():
        shell.recv(65535)

    # ------------------------------------------------
    # Do NOT use:
    # terminal length 0
    #
    # This command is unsupported on the device.
    # ------------------------------------------------

    # Execute STP command
    shell.send("show spanning-tree\n")
    time.sleep(4)

    output = ""

    end_time = time.time() + 3

    while time.time() < end_time:
        if shell.recv_ready():
            output += shell.recv(65535).decode(
                "utf-8",
                errors="ignore"
            )
            time.sleep(0.2)
        else:
            time.sleep(0.2)

    print("RESULT_START")
    print("STATUS=SUCCESS")
    print("OUTPUT_START")
    print(output)
    print("OUTPUT_END")
    print("ERROR_START")
    print("")
    print("ERROR_END")
    print("RESULT_END")

    client.close()

except Exception as e:
    print("RESULT_START")
    print("STATUS=FAILED")
    print("ERROR_START")
    print(str(e))
    print("ERROR_END")
    print("RESULT_END")

    try:
        client.close()
    except Exception:
        pass

    sys.exit(1)

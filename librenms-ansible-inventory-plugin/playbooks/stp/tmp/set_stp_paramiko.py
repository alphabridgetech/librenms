#!/usr/bin/env python3

import sys
import time
import re
import socket
import paramiko


# ==================================================
# ARGUMENTS FROM ANSIBLE
# ==================================================

HOST = sys.argv[1]
USER = sys.argv[2]
PASSWORD = sys.argv[3]
ACTION = sys.argv[4]

# Dynamic STP values
PRIORITY = sys.argv[5]
HELLO_TIME = sys.argv[6]
MAX_AGE = sys.argv[7]
FORWARD_DELAY = sys.argv[8]

PORT = 22
CONNECT_TIMEOUT = 10


# ==================================================
# PRINT RESULT
# ==================================================

def print_result(success, output="", error=""):

    print("RESULT_START")

    if success:
        print("STATUS=SUCCESS")
    else:
        print("STATUS=FAILED")

    print("OUTPUT_START")
    print(output.strip())
    print("OUTPUT_END")

    print("ERROR_START")
    print(error.strip())
    print("ERROR_END")

    print("RESULT_END")


# ==================================================
# CONNECT TO DEVICE
# ==================================================

def connect_device():

    client = paramiko.SSHClient()

    client.set_missing_host_key_policy(
        paramiko.AutoAddPolicy()
    )

    client.connect(
        hostname=HOST,
        port=PORT,
        username=USER,
        password=PASSWORD,
        timeout=CONNECT_TIMEOUT,
        banner_timeout=CONNECT_TIMEOUT,
        auth_timeout=CONNECT_TIMEOUT,
        look_for_keys=False,
        allow_agent=False
    )

    channel = client.invoke_shell(
        term="vt100",
        width=200,
        height=1000
    )

    channel.settimeout(2)

    return client, channel


# ==================================================
# READ DATA
# ==================================================

def read_data(channel, wait=1):

    output = ""

    end_time = time.time() + wait

    while time.time() < end_time:

        try:

            if channel.recv_ready():

                data = channel.recv(65535)

                if not data:
                    break

                output += data.decode(
                    "utf-8",
                    errors="ignore"
                )

                end_time = time.time() + 0.3

            else:

                time.sleep(0.05)

        except socket.timeout:
            break

        except Exception:
            break

    return output


# ==================================================
# WAIT FOR CLI PROMPT
# ==================================================

def wait_prompt(channel, timeout=8):

    output = ""

    end_time = time.time() + timeout

    prompt_regex = re.compile(
        r"(?m)(?:"
        r"^[^\r\n#>]+\>\s*$"
        r"|^[^\r\n#>]+_config#\s*$"
        r"|^(?!.*_config#)[^\r\n#>]+#\s*$"
        r")"
    )

    while time.time() < end_time:

        try:

            if channel.recv_ready():

                data = channel.recv(65535)

                if not data:
                    break

                text = data.decode(
                    "utf-8",
                    errors="ignore"
                )

                output += text

                if prompt_regex.search(output):
                    return output

            else:

                time.sleep(0.05)

        except socket.timeout:

            continue

        except Exception:

            break

    return output


# ==================================================
# SEND COMMAND
# ==================================================

def send_command(channel, command, timeout=8):

    channel.send(command + "\n")

    return wait_prompt(
        channel,
        timeout
    )


# ==================================================
# CHECK CLI ERROR
# ==================================================

def has_error(output):

    error_patterns = [

        r"Too many parameters",
        r"Incomplete command",
        r"Unknown command",
        r"Invalid",
        r"Error",
        r"Failed",
        r"Failure",
        r"Unrecognized",
        r"Ambiguous"
    ]

    for pattern in error_patterns:

        if re.search(
            pattern,
            output,
            re.IGNORECASE
        ):

            return True

    return False


# ==================================================
# ENTER CONFIG MODE
# ==================================================

def enter_config(channel):

    output = ""

    output += wait_prompt(
        channel,
        8
    )

    output += send_command(
        channel,
        "enable",
        8
    )

    output += send_command(
        channel,
        "config",
        8
    )

    return output


# ==================================================
# SINGLE CONFIG COMMAND
# ==================================================

def single_config_command(command):

    client = None

    try:

        client, channel = connect_device()

        login_output = enter_config(channel)

        command_output = send_command(
            channel,
            command,
            8
        )

        complete_output = (
            login_output +
            command_output
        )

        if has_error(command_output):

            print_result(
                False,
                complete_output,
                "Device returned an error for: "
                + command
            )

            client.close()

            return 1

        print_result(
            True,
            complete_output,
            ""
        )

        client.close()

        return 0

    except Exception as exc:

        if client:

            try:
                client.close()
            except Exception:
                pass

        print_result(
            False,
            "",
            str(exc)
        )

        return 1


# ==================================================
# CHANGE STP MODE
# ==================================================

def change_protocol():

    client = None

    try:

        client, channel = connect_device()

        enter_config(channel)

        channel.send(
            "spanning-tree mode SSTP\n"
        )

        # The switch may close SSH after changing
        # the spanning-tree protocol.
        time.sleep(2)

        output = read_data(
            channel,
            3
        )

        try:
            client.close()
        except Exception:
            pass

        print_result(
            True,
            output,
            ""
        )

        return 0

    except Exception as exc:

        message = str(exc)

        # SSH disconnect/timeout is expected after
        # changing STP mode.

        if (
            "closed" in message.lower()
            or
            "eof" in message.lower()
            or
            "timeout" in message.lower()
            or
            "socket" in message.lower()
        ):

            print_result(
                True,
                "",
                "SSH disconnected after SSTP mode change."
            )

            return 0

        print_result(
            False,
            "",
            message
        )

        return 1


# ==================================================
# SAVE CONFIGURATION
# ==================================================

def save_configuration():

    client = None

    try:

        client, channel = connect_device()

        wait_prompt(
            channel,
            8
        )

        output = send_command(
            channel,
            "enable",
            8
        )

        output += send_command(
            channel,
            "write",
            10
        )

        client.close()

        if (
            "OK!" not in output
            and
            "Saving current configuration" not in output
        ):

            print_result(
                False,
                output,
                "Configuration save confirmation not found."
            )

            return 1

        print_result(
            True,
            output,
            ""
        )

        return 0

    except Exception as exc:

        if client:

            try:
                client.close()
            except Exception:
                pass

        print_result(
            False,
            "",
            str(exc)
        )

        return 1


# ==================================================
# VERIFY STP
# ==================================================

def verify_stp():

    client = None

    try:

        client, channel = connect_device()

        wait_prompt(
            channel,
            8
        )

        output = send_command(
            channel,
            "enable",
            8
        )

        output += send_command(
            channel,
            "show spanning-tree",
            10
        )

        client.close()

        if has_error(output):

            print_result(
                False,
                output,
                "show spanning-tree returned an error."
            )

            return 1

        print_result(
            True,
            output,
            ""
        )

        return 0

    except Exception as exc:

        if client:

            try:
                client.close()
            except Exception:
                pass

        print_result(
            False,
            "",
            str(exc)
        )

        return 1


# ==================================================
# MAIN
# ==================================================

if ACTION == "protocol":

    sys.exit(
        change_protocol()
    )


elif ACTION == "priority":

    sys.exit(
        single_config_command(
            "spanning-tree sstp priority "
            + PRIORITY
        )
    )


elif ACTION == "hello":

    sys.exit(
        single_config_command(
            "spanning-tree sstp hello-time "
            + HELLO_TIME
        )
    )


elif ACTION == "max-age":

    sys.exit(
        single_config_command(
            "spanning-tree sstp max-age "
            + MAX_AGE
        )
    )


elif ACTION == "forward":

    sys.exit(
        single_config_command(
            "spanning-tree sstp forward-time "
            + FORWARD_DELAY
        )
    )


elif ACTION == "bpdu-enable":

    sys.exit(
        single_config_command(
            "spanning-tree bpdu-terminal"
        )
    )


elif ACTION == "bpdu-disable":

    sys.exit(
        single_config_command(
            "no spanning-tree bpdu-terminal"
        )
    )


# Device CLI requires "default" after loopguard.
elif ACTION == "loop-enable":

    sys.exit(
        single_config_command(
            "spanning-tree loopguard default"
        )
    )


elif ACTION == "loop-disable":

    sys.exit(
        single_config_command(
            "no spanning-tree loopguard default"
        )
    )


elif ACTION == "save":

    sys.exit(
        save_configuration()
    )


elif ACTION == "verify":

    sys.exit(
        verify_stp()
    )


else:

    print_result(
        False,
        "",
        "Unknown action: " + ACTION
    )

    sys.exit(1)

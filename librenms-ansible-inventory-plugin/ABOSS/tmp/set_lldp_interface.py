#!/usr/bin/env python3

import paramiko
import time
import re
import sys

# ------------------------------------------------------------------
# CONNECTION DETAILS
# ------------------------------------------------------------------

HOST = "192.168.2.245"
PORT = 22

USER = "admin"
PASS = "admin"

INTERFACE = "GigaEthernet0/5"
ADMIN_STATUS = "enable"
TLV = "disable"

# ------------------------------------------------------------------
# CONVERT INTERFACE
#
# Input:
#   GigaEthernet0/2
#
# CLI:
#   GigaEthernet 0/2
# ------------------------------------------------------------------

match = re.match(
    r'^GigaEthernet([0-9]+/[0-9]+)$',
    INTERFACE
)

if not match:
    print("Invalid interface format")
    sys.exit(1)

CLI_INTERFACE = "GigaEthernet " + match.group(1)

# ------------------------------------------------------------------
# CLEAN OUTPUT
# ------------------------------------------------------------------

def clean_output(data):

    data = data.replace("\r", "")

    data = re.sub(
        r'\x1b\[[0-9;?]*[A-Za-z]',
        '',
        data
    )

    return data

# ------------------------------------------------------------------
# READ FROM CHANNEL
# ------------------------------------------------------------------

def read_channel(channel, wait=0.3):

    time.sleep(wait)

    output = ""

    while channel.recv_ready():

        try:

            data = channel.recv(65535)

            if not data:
                break

            output += data.decode(
                "utf-8",
                "ignore"
            )

        except Exception:
            break

    return clean_output(output)

# ------------------------------------------------------------------
# WAIT FOR PROMPT
# ------------------------------------------------------------------

def wait_for_prompt(channel, timeout=10):

    end_time = time.time() + timeout
    output = ""

    while time.time() < end_time:

        if channel.recv_ready():

            data = channel.recv(65535)

            if data:

                output += data.decode(
                    "utf-8",
                    "ignore"
                )

                output = clean_output(output)

                lines = [
                    x.strip()
                    for x in output.splitlines()
                    if x.strip()
                ]

                if lines:

                    last_line = lines[-1]

                    # Username prompt
                    if re.search(
                        r'username\s*:',
                        last_line,
                        re.IGNORECASE
                    ):
                        return output

                    # Password prompt
                    if re.search(
                        r'password\s*:',
                        last_line,
                        re.IGNORECASE
                    ):
                        return output

                    # CLI prompt
                    if (
                        last_line.endswith(">") or
                        last_line.endswith("#")
                    ):
                        return output

        time.sleep(0.2)

    raise Exception(
        "Timeout waiting for switch prompt"
    )

# ------------------------------------------------------------------
# SEND COMMAND
# ------------------------------------------------------------------

def send_command(
    channel,
    command,
    wait=0.8
):

    try:

        if channel.closed:
            raise Exception("Socket is closed")

        channel.send(
            command + "\n"
        )

    except Exception as e:

        raise Exception(
            "Failed to send command '" +
            command +
            "': " +
            str(e)
        )

    time.sleep(wait)

    return read_channel(
        channel,
        0.2
    )

# ------------------------------------------------------------------
# FAILURE
# ------------------------------------------------------------------

def fail(message):

    print("FAILURE: " + message)
    sys.exit(1)

ssh = None
channel = None

try:

    # ==============================================================
    # SSH CONNECTION
    # ==============================================================

    ssh = paramiko.SSHClient()

    ssh.set_missing_host_key_policy(
        paramiko.AutoAddPolicy()
    )

    try:

        ssh.connect(
            HOST,
            port=PORT,
            username=USER,
            password=PASS,
            look_for_keys=False,
            allow_agent=False,
            timeout=10,
            banner_timeout=10,
            auth_timeout=10
        )

    except paramiko.AuthenticationException:

        fail(
            "SSH authentication failed"
        )

    except paramiko.SSHException as e:

        fail(
            "SSH connection failed: " + str(e)
        )

    except Exception as e:

        fail(
            "SSH connection failed: " + str(e)
        )

    # ==============================================================
    # OPEN INTERACTIVE SHELL
    # ==============================================================

    channel = ssh.invoke_shell(
        width=200,
        height=50
    )

    time.sleep(1)

    initial_output = read_channel(
        channel,
        1
    )

    # ==============================================================
    # WAIT FOR CLI PROMPT
    # ==============================================================

    try:

        prompt_output = wait_for_prompt(
            channel,
            10
        )

    except Exception:

        prompt_output = initial_output

    # ==============================================================
    # HANDLE USER EXEC MODE
    #
    # Switch>
    #    |
    #    +---- enable
    #    |
    # Switch#
    # ==============================================================

    lines = [
        x.strip()
        for x in prompt_output.splitlines()
        if x.strip()
    ]

    if lines:

        last_line = lines[-1]

        if last_line.endswith(">"):

            enable_output = send_command(
                channel,
                "enable",
                1
            )

            if re.search(
                r'password\s*:',
                enable_output,
                re.IGNORECASE
            ):

                channel.send(
                    PASS + "\n"
                )

                time.sleep(1)

                read_channel(
                    channel,
                    0.5
                )

    # ==============================================================
    # DISABLE PAGINATION
    # ==============================================================

    send_command(
        channel,
        "terminal length 0",
        0.5
    )

    # ==============================================================
    # ENTER CONFIGURATION MODE
    # ==============================================================

    config_output = send_command(
        channel,
        "config",
        1
    )

    if re.search(
        r'unknown command|invalid command|error',
        config_output,
        re.IGNORECASE
    ):

        fail(
            "Unable to enter configuration mode: "
            + config_output.strip()
        )

    # ==============================================================
    # ENTER INTERFACE CONFIGURATION
    # ==============================================================

    interface_output = send_command(
        channel,
        "interface " + CLI_INTERFACE,
        1
    )

    if re.search(
        r'unknown command|invalid command|error',
        interface_output,
        re.IGNORECASE
    ):

        fail(
            "Unable to enter interface "
            + INTERFACE
            + ": "
            + interface_output.strip()
        )

    # ==============================================================
    # LLDP RECEIVE
    # ==============================================================

    if ADMIN_STATUS == "enable":

        receive_command = "lldp receive"

    else:

        receive_command = "no lldp receive"

    receive_output = send_command(
        channel,
        receive_command,
        0.8
    )

    if re.search(
        r'unknown command|invalid command|error',
        receive_output,
        re.IGNORECASE
    ):

        fail(
            "LLDP receive command failed: "
            + receive_output.strip()
        )

    # ==============================================================
    # LLDP TRANSMIT
    # ==============================================================

    if TLV == "enable":

        transmit_command = "lldp transmit"

    else:

        transmit_command = "no lldp transmit"

    transmit_output = send_command(
        channel,
        transmit_command,
        0.8
    )

    if re.search(
        r'unknown command|invalid command|error',
        transmit_output,
        re.IGNORECASE
    ):

        fail(
            "LLDP transmit command failed: "
            + transmit_output.strip()
        )

    # ==============================================================
    # EXIT INTERFACE CONFIGURATION
    # ==============================================================

    send_command(
        channel,
        "exit",
        0.8
    )

    # ==============================================================
    # EXIT CONFIGURATION MODE
    # ==============================================================

    send_command(
        channel,
        "exit",
        0.8
    )

    # ==============================================================
    # VERIFY CONFIGURATION
    # ==============================================================

    verify_output = send_command(
        channel,
        "show lldp interface " + CLI_INTERFACE,
        2
    )

    verify_lower = verify_output.lower()

    # ==============================================================
    # VERIFY RECEIVE
    # ==============================================================

    if ADMIN_STATUS == "enable":

        if not re.search(
            r'rx\s*:\s*enabled',
            verify_lower
        ):

            fail(
                "LLDP receive verification failed"
            )

    else:

        if not re.search(
            r'rx\s*:\s*disabled',
            verify_lower
        ):

            fail(
                "LLDP receive disable verification failed"
            )

    # ==============================================================
    # VERIFY TRANSMIT
    # ==============================================================

    if TLV == "enable":

        if not re.search(
            r'tx\s*:\s*enabled',
            verify_lower
        ):

            fail(
                "LLDP transmit verification failed"
            )

    else:

        if not re.search(
            r'tx\s*:\s*disabled',
            verify_lower
        ):

            fail(
                "LLDP transmit disable verification failed"
            )

    # ==============================================================
    # SUCCESS
    # ==============================================================

    print("SUCCESS")
    sys.exit(0)

except Exception as e:

    fail(
        str(e)
    )

finally:

    try:

        if channel is not None:
            channel.close()

    except Exception:
        pass

    try:

        if ssh is not None:
            ssh.close()

    except Exception:
        pass

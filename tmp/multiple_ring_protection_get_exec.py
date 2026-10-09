#!/usr/bin/env python3

import os
import paramiko
import time
import re
import json
import sys

# ==========================================
# SWITCH CREDENTIALS
# ==========================================

HOST = os.environ["SWITCH_HOST"]
USER = os.environ["SWITCH_USER"]
PASSWORD = os.environ["SWITCH_PASSWORD"]

DOMAIN_FILTER = os.environ.get(
    "DOMAIN_FILTER", ""
)

RING_FILTER = os.environ.get(
    "RING_FILTER", ""
)

# ==========================================
# ANSI CLEANUP
# ==========================================

ansi_re = re.compile(
    r'\x1B(?:[@-Z\\-_]|\[[0-?]*[ -/]*[@-~])'
)

PROMPT_RE = r'(?m)^[^\r\n\s]+[>#]\s*\Z'

# ==========================================
# READ UNTIL SWITCH PROMPT
# ==========================================

def read_until_prompt(
    shell,
    timeout=10
):

    buf = ""
    end_time = time.time() + timeout

    while time.time() < end_time:

        if shell.recv_ready():

            chunk = shell.recv(65535).decode(
                errors="ignore"
            )

            buf += chunk

            clean_buf = ansi_re.sub(
                "",
                buf
            ).replace("\r", "")

            if re.search(
                PROMPT_RE,
                clean_buf
            ):
                return clean_buf

        else:
            time.sleep(0.1)

    raise TimeoutError(
        "Timeout waiting for switch prompt"
    )

# ==========================================
# EXECUTE CLI COMMAND
# ==========================================

def send_cmd(
    shell,
    command,
    timeout=10
):

    shell.send(command + "\n")

    output = read_until_prompt(
        shell,
        timeout=timeout
    )

    if re.search(
        r'(?im)^\s*(?:%err|%invalid|'
        r'invalid input|unknown command|'
        r'error:)',
        output
    ):

        raise Exception(
            "Command failed: " + command
        )

    return output

# ==========================================
# CLI FIELD MAPPING
#
# Only GUI fields are included.
# ==========================================

KEY_MAP = {

    "NodeRingLevel": "ring_type",

    "NodeType": "node_type",

    "ControlVlan": "control_vlan",

    "HelloTime": "hello_time",

    "FailTime": "fail_time",

    "PreforwardTime": "pre_forward_time",

    "PrimaryPort": "primary_port",

    "SecondaryPort": "secondary_port"

}

# ==========================================
# CONVERT NUMBER
# ==========================================

def number_or_none(value):

    if value is None:
        return None

    value = str(value).strip()

    if value.isdigit():
        return int(value)

    return None

# ==========================================
# CONVERT CLI LABEL TO GUI LABEL
#
# Major-Ring -> Major Ring
# Master-node -> Master Node
# ==========================================

def display_label(value):

    if value is None:
        return None

    return value.replace(
        "-",
        " "
    ).title()

# ==========================================
# MAP ONE RING TO GUI FIELDS
# ==========================================

def format_ring(ring):

    return {

        "Domain ID":
            number_or_none(
                ring.get("domain_id")
            ),

        "Ring ID":
            number_or_none(
                ring.get("ring_id")
            ),

        "Ring Type":
            display_label(
                ring.get("ring_type")
            ),

        "Node Type":
            display_label(
                ring.get("node_type")
            ),

        "Control VLAN":
            number_or_none(
                ring.get("control_vlan")
            ),

        "Hello Time":
            number_or_none(
                ring.get("hello_time")
            ),

        "Failed Time":
            number_or_none(
                ring.get("fail_time")
            ),

        "Pre Forward Time":
            number_or_none(
                ring.get("pre_forward_time")
            ),

        "Primary Port":
            ring.get("primary_port"),

        "Primary Port Type":
            "Primary-Port",

        "Secondary Port":
            ring.get("secondary_port"),

        "Secondary Port Type":
            "Secondary-Port"

    }

# ==========================================
# PARSE SHOW MEAPS-DICT OUTPUT
# ==========================================

def parse_meaps_output(output):

    rings = []

    current = None

    for raw_line in output.splitlines():

        line = raw_line.strip()

        if not line:
            continue

        # ==================================
        # DOMAIN ID
        #
        # Ether-Ring Domain 3
        # ==================================

        domain_match = re.match(
            r'^Ether-Ring\s+Domain\s+(\d+)\s*$',
            line,
            re.IGNORECASE
        )

        if domain_match:

            if current is not None:
                rings.append(current)

            current = {
                "domain_id":
                    domain_match.group(1)
            }

            continue

        # ==================================
        # RING ID
        #
        # Ether-Ring Node 2
        # ==================================

        ring_match = re.match(
            r'^Ether-Ring\s+Node\s+(\d+)\s*$',
            line,
            re.IGNORECASE
        )

        if ring_match and current is not None:

            current["ring_id"] = (
                ring_match.group(1)
            )

            continue

        # ==================================
        # OTHER PARAMETERS
        # ==================================

        field_match = re.match(
            r'^(\S+)\s+(.+?)\s*$',
            line
        )

        if field_match and current is not None:

            key = field_match.group(1)

            value = field_match.group(2)

            output_key = KEY_MAP.get(key)

            if output_key:

                current[output_key] = value

    # ==================================
    # ADD LAST RECORD
    # ==================================

    if current is not None:
        rings.append(current)

    # ==================================
    # APPLY DOMAIN FILTER
    # ==================================

    if DOMAIN_FILTER.strip():

        rings = [

            ring for ring in rings

            if ring.get("domain_id")
            == DOMAIN_FILTER.strip()

        ]

    # ==================================
    # APPLY RING FILTER
    # ==================================

    if RING_FILTER.strip():

        rings = [

            ring for ring in rings

            if ring.get("ring_id")
            == RING_FILTER.strip()

        ]

    # ==================================
    # CONVERT TO GUI FORMAT
    # ==================================

    return [

        format_ring(ring)

        for ring in rings

    ]

# ==========================================
# SSH INITIALIZATION
# ==========================================

ssh = None

shell = None

try:

    ssh = paramiko.SSHClient()

    ssh.set_missing_host_key_policy(
        paramiko.AutoAddPolicy()
    )

    # ==================================
    # CONNECT TO SWITCH
    # ==================================

    ssh.connect(
        HOST,
        username=USER,
        password=PASSWORD,
        look_for_keys=False,
        allow_agent=False,
        timeout=10
    )

    shell = ssh.invoke_shell()

    time.sleep(0.5)

    # ==================================
    # INITIAL PROMPT
    # ==================================

    initial_output = read_until_prompt(
        shell,
        timeout=10
    )

    # ==================================
    # ENABLE MODE
    # ==================================

    if initial_output.rstrip().endswith(">"):

        enable_output = send_cmd(
            shell,
            "enable",
            timeout=10
        )

        if not enable_output.rstrip().endswith("#"):

            raise Exception(
                "Failed to enter enable mode"
            )

    elif not initial_output.rstrip().endswith("#"):

        raise Exception(
            "Unrecognized switch prompt"
        )

    # ==================================
    # DISABLE PAGING
    # ==================================

    send_cmd(
        shell,
        "terminal length 0",
        timeout=10
    )

    # ==================================
    # SHOW MEAPS-DICT
    # ==================================

    show_output = send_cmd(
        shell,
        "show meaps-dict",
        timeout=30
    )

    # ==================================
    # PARSE OUTPUT
    # ==================================

    rings = parse_meaps_output(
        show_output
    )

    # ==================================
    # RETURN JSON
    # ==================================

    print(json.dumps({

        "ip": HOST,

        "rings": rings,

        "status": "success"

    }))

# ==========================================
# EXCEPTION HANDLING
# ==========================================

except Exception as e:

    print(json.dumps({

        "ip": HOST,

        "status": "failed",

        "error": str(e)

    }))

    sys.exit(1)

# ==========================================
# CLOSE SSH CONNECTION
# ==========================================

finally:

    if shell:
        shell.close()

    if ssh:
        ssh.close()

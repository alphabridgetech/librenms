#!/usr/bin/env python3

import paramiko
import time
import re
import os
import sys

HOST = "10.10.10.5"
USER = "admin"
PASS = "admin"

OUTPUT_FILE = os.path.join(
    "/opt/librenms/librenms-ansible-inventory-plugin/playbooks/portmirror/../../../output",
    HOST + "_portmirror_get.yml"
)

def clean_output(data):
    data = re.sub(
        r'\x1B(?:[@-Z\\-_]|\[[0-?]*[ -/]*[@-~])',
        '',
        data
    )
    return data.replace('\r', '')

def read_output(shell, timeout=5):
    buffer = ""
    end_time = time.time() + timeout
    last_data = time.time()

    while time.time() < end_time:
        if shell.recv_ready():
            data = shell.recv(65535).decode(errors="ignore")
            buffer += data
            last_data = time.time()

            if "--More--" in data:
                shell.send(" ")
        else:
            if buffer and time.time() - last_data > 1:
                break
            time.sleep(0.1)

    return clean_output(buffer)

def cmd(shell, command, timeout=5):
    while shell.recv_ready():
        shell.recv(65535)

    shell.send(command + "\n")
    return read_output(shell, timeout)

def parse_port_mirror(config):
    result = {
        "mirror_port": "Disable",
        "mirror_type": "port-based",
        "mirrored_port": "",
        "mirror_mode": "",
        "vid": "",
        "tpid": ""
    }

    destination_pattern = re.compile(
        r'^\s*mirror\s+session\s+1\s+destination\s+'
        r'interface\s+(\S+)'
        r'(?:\s+(portbased|flowbased))?'
        r'\s+rspan\s+(\d+)\s+(\S+)',
        re.IGNORECASE | re.MULTILINE
    )

    source_pattern = re.compile(
        r'^\s*mirror\s+session\s+1\s+source\s+'
        r'interface\s+(\S+)\s+(both|rx|tx)\s*$',
        re.IGNORECASE | re.MULTILINE
    )

    destination = destination_pattern.search(config)
    source = source_pattern.search(config)

    if destination:
        result["mirror_port"] = destination.group(1)

        if destination.group(2):
            result["mirror_type"] = (
                "port-based"
                if destination.group(2).lower() == "portbased"
                else "flow-based"
            )

        result["vid"] = destination.group(3)
        result["tpid"] = destination.group(4)

    if source:
        result["mirrored_port"] = source.group(1)
        mode = source.group(2).lower()
        result["mirror_mode"] = {
            "rx": "RX",
            "tx": "TX",
            "both": "RX & TX"
        }[mode]

    return result

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())

try:
    ssh.connect(
        HOST,
        username=USER,
        password=PASS,
        look_for_keys=False,
        allow_agent=False,
        timeout=10,
        banner_timeout=30,
        auth_timeout=20
    )

    shell = ssh.invoke_shell()
    time.sleep(1)

    while shell.recv_ready():
        shell.recv(65535)

    cmd(shell, "enable")
    output = cmd(shell, "show running-config", timeout=10)

    if re.search(
        r'Unknown command|Invalid command|Incomplete command',
        output,
        re.IGNORECASE
    ):
        raise Exception(
            "Failed to retrieve running configuration: " + output
        )

    config = parse_port_mirror(output)

    if config["mirror_port"] == "Disable":
        status = "SUCCESS"
    elif config["vid"] and config["tpid"]:
        status = "SUCCESS"
    else:
        raise Exception(
            "Port mirroring destination configuration not found "
            "in show running-config."
        )

    os.makedirs(os.path.dirname(OUTPUT_FILE), exist_ok=True)

    with open(OUTPUT_FILE, "w", encoding="utf-8") as file:
        file.write(f"{HOST}: {status}\n")
        file.write(f"mirror_port: {config['mirror_port']}\n")
        file.write(f"mirror_type: {config['mirror_type']}\n")
        file.write(f"mirrored_port: {config['mirrored_port']}\n")
        file.write(f"mirror_mode: {config['mirror_mode']}\n")
        file.write(f"vid: {config['vid']}\n")
        file.write(f"tpid: {config['tpid']}\n")

    print("SUCCESS")
    print(config)
    sys.exit(0)

except Exception as e:
    os.makedirs(os.path.dirname(OUTPUT_FILE), exist_ok=True)

    with open(OUTPUT_FILE, "w", encoding="utf-8") as file:
        file.write(f"{HOST}: FAILED\n")

    print("FAILED: " + str(e))
    sys.exit(1)

finally:
    try:
        ssh.close()
    except Exception:
        pass

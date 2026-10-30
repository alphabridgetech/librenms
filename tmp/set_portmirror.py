import sys
import time
import json
import re
import paramiko

HOST = "192.168.200.241"
PORT = int("22")
USERNAME = "admin"
PASSWORD = "admin"

DEST_PORT = "g0/7"
SOURCE_PORT = "g0/1"
MIRROR_TYPE = "port-based"
MIRROR_MODE = "TX"
VID = "45"
TPID = "0x0021"
TYPE_KEYWORD = True

OUTPUT_FILE = "/opt/librenms/librenms-ansible-inventory-plugin/playbooks/portmirror/../../../output/bridge1_portmirror.yml"

def normalize_port(port):
    port = str(port).strip()
    match = re.fullmatch(r"[gG](?:igaEthernet)?\s*0?\s*/\s*(\d+)", port)
    if match:
        return "g0/" + match.group(1)
    return port

def read_output(channel, wait=1):
    time.sleep(wait)
    output = ""
    while channel.recv_ready():
        output += channel.recv(65535).decode("utf-8", errors="ignore")
        time.sleep(0.2)
    return output

def run_command(channel, command, wait=2):
    channel.send(command + "\n")
    return read_output(channel, wait)

def last_prompt(output):
    lines = [line.strip() for line in output.splitlines() if line.strip()]
    if not lines:
        return ""
    return lines[-1].replace("\x1b", "").strip()

def has_cli_error(output):
    patterns = [
        r"Unknown command",
        r"Invalid command",
        r"invalid input",
        r"Incomplete command",
        r"% Error",
        r"% Invalid",
        r"destination port failed",
        r"source port failed"
    ]
    return any(re.search(p, output, re.IGNORECASE) for p in patterns)

def main():
    client = None
    status = "failed"
    results = []
    errors = []

    report = {
        "host": HOST,
        "status": status,
        "mirror_session": 1,
        "mirror_type": MIRROR_TYPE,
        "destination_port": DEST_PORT,
        "source_port": SOURCE_PORT,
        "source_mode": MIRROR_MODE,
        "rspan_vlan": VID,
        "tpid": TPID,
        "type_keyword_enabled": TYPE_KEYWORD,
        "results": results,
        "errors": errors
    }

    try:
        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        client.connect(
            hostname=HOST,
            port=PORT,
            username=USERNAME,
            password=PASSWORD,
            look_for_keys=False,
            allow_agent=False,
            timeout=15,
            banner_timeout=15,
            auth_timeout=15
        )

        channel = client.invoke_shell()
        initial_output = read_output(channel, 2)
        prompt = last_prompt(initial_output)

        results.append({
            "step": "initial_prompt",
            "prompt": prompt,
            "output": initial_output.strip()
        })

        if not prompt:
            raise RuntimeError("Could not read the switch prompt.")

        if "config#" not in prompt.lower():
            if prompt.endswith(">"):
                output = run_command(channel, "enable", 2)
                results.append({
                    "command": "enable",
                    "output": output.strip()
                })

                if has_cli_error(output):
                    raise RuntimeError(
                        "The switch rejected the enable command."
                    )

                prompt = last_prompt(output)
                if not prompt.endswith("#"):
                    raise RuntimeError(
                        "Enable did not reach privileged EXEC mode. "
                        "Prompt: " + prompt
                    )

            output = run_command(channel, "config", 2)
            results.append({
                "command": "config",
                "output": output.strip()
            })

            prompt = last_prompt(output)
            if has_cli_error(output) or "config#" not in prompt.lower():
                raise RuntimeError(
                    "Could not enter configuration mode. "
                    "Current prompt: " + prompt
                )

        dest_port = normalize_port(DEST_PORT)
        source_port = normalize_port(SOURCE_PORT)

        type_keyword = (
            "flowbased" if MIRROR_TYPE == "flow-based"
            else "portbased"
        )

        typed_destination = (
            "mirror session 1 destination interface "
            + dest_port + " " + type_keyword
            + " rspan " + str(VID) + " " + TPID
        )

        untyped_destination = (
            "mirror session 1 destination interface "
            + dest_port + " rspan " + str(VID) + " " + TPID
        )

        if TYPE_KEYWORD:
            destination_commands = [typed_destination]
        else:
            destination_commands = [
                untyped_destination,
                typed_destination
            ]

        destination_success = False

        for command in destination_commands:
            output = run_command(channel, command, 3)
            results.append({
                "command": command,
                "output": output.strip()
            })

            if has_cli_error(output):
                errors.append(
                    "Destination command failed: " + command
                )
                continue

            destination_success = True
            break

        if not destination_success:
            raise RuntimeError(
                "All destination command formats failed."
            )

        mode_map = {
            "RX": "rx",
            "TX": "tx",
            "RX & TX": "both",
            "BOTH": "both"
        }

        source_mode = mode_map[MIRROR_MODE]
        source_command = (
            "mirror session 1 source interface "
            + source_port + " " + source_mode
        )

        source_output = run_command(channel, source_command, 3)
        results.append({
            "command": source_command,
            "output": source_output.strip()
        })

        if has_cli_error(source_output):
            raise RuntimeError("Source command failed.")

        status = "success"

    except Exception as exc:
        errors.append(str(exc))
        status = "failed"

    finally:
        if client:
            client.close()

    report["status"] = status

    with open(OUTPUT_FILE, "w") as report_file:
        json.dump(report, report_file, indent=2)
        report_file.write("\n")

    print(json.dumps(report, indent=2))
    sys.exit(0 if status == "success" else 1)

if __name__ == "__main__":
    main()

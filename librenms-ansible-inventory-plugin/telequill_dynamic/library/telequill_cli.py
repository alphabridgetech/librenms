#!/usr/bin/python
# Runs config lines + show commands + save on the persistent network_cli
# connection, so everything for a device happens in ONE SSH session.

DOCUMENTATION = r"""
module: telequill_cli
short_description: Push config and run show commands over one network_cli session
options:
  config:
    description: Config lines, sent inside one config session (config ... end).
    type: list
    elements: raw
    default: []
  shows:
    description: Show commands to run after the config.
    type: list
    elements: str
    default: []
  save:
    description: Save command (string or {command, prompt, answer}); only sent if config changed.
    type: raw
"""

from ansible.module_utils.basic import AnsibleModule
from ansible.module_utils.connection import Connection, ConnectionError


def main():
    module = AnsibleModule(
        argument_spec=dict(
            config=dict(type="list", elements="raw", default=[]),
            shows=dict(type="list", elements="str", default=[]),
            save=dict(type="raw"),
        ),
        supports_check_mode=True,
    )
    p = module.params
    result = {"changed": False, "config": p["config"], "shows": {}}

    conn = Connection(module._socket_path)
    stage = "connect"
    try:
        if p["config"]:
            stage = "config"
            if not module.check_mode:
                result["config_response"] = conn.edit_config(candidate=p["config"])["response"]
                result["changed"] = True

                if p["save"]:
                    stage = "save"
                    save = p["save"] if isinstance(p["save"], dict) else {"command": p["save"]}
                    result["save_response"] = conn.get(**save)

        for cmd in p["shows"]:
            stage = f"show: {cmd}"
            result["shows"][cmd] = conn.get(command=cmd)

    except ConnectionError as e:
        module.fail_json(msg=f"{stage} failed: {e}", stage=stage, **result)

    module.exit_json(**result)


if __name__ == "__main__":
    main()

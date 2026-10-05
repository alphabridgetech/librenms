# Alphabridge cliconf plugin.
#
# Teaches network_cli how to run commands and push config on Alphabridge:
# "config" -> lines -> "end". Commands themselves come from profiles/,
# never from here.

import json

from ansible.module_utils.common.text.converters import to_text
from ansible.plugins.cliconf import CliconfBase, enable_mode


class Cliconf(CliconfBase):

    config_enter = "config"
    config_exit = "end"

    def get_device_info(self):
        return {"network_os": "alphabridge"}

    @enable_mode
    def get_config(self, source="running", flags=None, format=None):
        return self.send_command("show running-config")

    @enable_mode
    def edit_config(self, candidate=None, commit=True, replace=None, comment=None):
        """Send all lines inside ONE config session; always leave config mode."""
        requests, responses = [], []

        self.send_command(self.config_enter)
        try:
            for line in candidate or []:
                # A line may be a dict to answer device questions:
                # {"command": "...", "prompt": "[y/n]", "answer": "y"}
                cmd = line if isinstance(line, dict) else {"command": line}
                if cmd["command"].strip() in ("", self.config_exit):
                    continue
                responses.append(to_text(self.send_command(**cmd), errors="surrogate_or_strict"))
                requests.append(cmd["command"])
        finally:
            self.send_command(self.config_exit)

        return {"request": requests, "response": responses}

    def get(self, command=None, prompt=None, answer=None, sendonly=False,
            newline=True, output=None, check_all=False):
        if not command:
            raise ValueError("must provide value of command to execute")
        return self.send_command(command=command, prompt=prompt, answer=answer,
                                 sendonly=sendonly, newline=newline, check_all=check_all)

    def get_capabilities(self):
        result = super().get_capabilities()
        result["device_operations"] = {
            "supports_diff_replace": False,
            "supports_commit": False,
            "supports_rollback": False,
            "supports_defaults": False,
            "supports_onbox_diff": False,
            "supports_generate_diff": False,
            "supports_multiline_delimiter": False,
        }
        return json.dumps(result)

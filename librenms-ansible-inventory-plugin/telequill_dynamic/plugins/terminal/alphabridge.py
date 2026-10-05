# Alphabridge terminal plugin.
#
# Teaches ansible.netcommon.network_cli how an Alphabridge CLI looks:
# what a prompt is, what an error is, how to disable paging and how to
# go to enable mode. Login/prompt handling lives ONLY here now instead
# of being copied into every playbook's paramiko script.

import json
import re

from ansible.errors import AnsibleConnectionFailure
from ansible.module_utils.common.text.converters import to_bytes
from ansible.plugins.terminal import TerminalBase


class TerminalModule(TerminalBase):

    # Matches "Switch>", "Switch#", "Switch(config)#", "Switch(config-if)#",
    # "Switch(config_vlan)#" ...
    terminal_stdout_re = [
        re.compile(rb"[\r\n]?[\w+\-.:/\[\]]+(?:\([^)]+\))?[>#] ?$"),
    ]

    # Any of these in a command's output makes the task fail with that output.
    terminal_stderr_re = [
        re.compile(rb"% ?Invalid", re.I),
        re.compile(rb"% ?Unknown command", re.I),
        re.compile(rb"% ?Incomplete command", re.I),
        re.compile(rb"% ?Ambiguous command", re.I),
        re.compile(rb"^\s*Error:", re.I | re.M),
    ]

    # Some firmwares don't page with "terminal length 0", others need "no page".
    paging_commands = (b"terminal length 0", b"no page")

    def _in_enable(self):
        prompt = self._get_prompt()
        return bool(prompt) and prompt.strip().endswith(b"#")

    def on_open_shell(self):
        for cmd in self.paging_commands:
            try:
                self._exec_cli_command(cmd)
            except AnsibleConnectionFailure:
                pass  # command not present on this firmware - try the next one

    def on_become(self, passwd=None):
        if self._in_enable():
            return

        cmd = {"command": "enable"}
        if passwd:
            cmd["prompt"] = r"[\r\n]?[Pp]assword: ?$"
            cmd["answer"] = passwd
            cmd["prompt_retry_check"] = True

        try:
            self._exec_cli_command(to_bytes(json.dumps(cmd), errors="surrogate_or_strict"))
        except AnsibleConnectionFailure:
            raise AnsibleConnectionFailure("unable to enter enable mode")

        if not self._in_enable():
            raise AnsibleConnectionFailure("enable mode not reached, check become password")
        # network_cli calls on_open_shell() right after this, so paging is
        # disabled in enable mode.

    def on_unbecome(self):
        if self._in_enable():
            self._exec_cli_command(b"disable")

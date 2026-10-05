# Dynamic command profiles.
#
#   load_profile  : merges profile files generic -> specific, e.g.
#                   alphabridge/_base.yml -> alphabridge/v3.yml
#                   -> alphabridge/v3_as212xts.yml -> alphabridge/v3_as212xts_2.1.4.yml
#                   Missing files are skipped; a later file overrides only the
#                   actions it defines. "action: null" removes an action
#                   (= not supported on that model).
#
#   build_commands: turns [{"name": "set_access_vlan", "params": {...}}, ...]
#                   into the flat CLI line list for this device's profile.

import os

import yaml
from ansible.errors import AnsibleFilterError


def _deep_merge(base, override):
    for key, value in override.items():
        if isinstance(value, dict) and isinstance(base.get(key), dict):
            _deep_merge(base[key], value)
        else:
            base[key] = value
    return base


def load_profile(profile_dir, vendor, series=None, model=None, firmware=None):
    layers = ["_base"]
    if series:
        layers.append(series)
        if model:
            layers.append(f"{series}_{model}")
            if firmware:
                layers.append(f"{series}_{model}_{firmware}")

    profile, loaded = {}, []
    for layer in layers:
        path = os.path.join(profile_dir, vendor, f"{layer}.yml")
        if os.path.isfile(path):
            with open(path) as fh:
                _deep_merge(profile, yaml.safe_load(fh) or {})
            loaded.append(path)

    if not loaded:
        raise AnsibleFilterError(f"no command profile found for vendor '{vendor}' in {profile_dir}")

    profile = {k: v for k, v in profile.items() if v is not None}
    profile["_loaded_from"] = loaded
    return profile


def _render(template, params, action):
    try:
        return template.format(**params)
    except KeyError as e:
        raise AnsibleFilterError(f"action '{action}' needs parameter {e}")


def build_commands(actions, profile):
    lines = []
    for action in actions or []:
        name = action["name"]
        params = action.get("params") or {}

        if name not in profile:
            raise AnsibleFilterError(f"action '{name}' is not supported on this device "
                                     f"(profile: {profile.get('_loaded_from')})")

        templates = profile[name]
        if isinstance(templates, str):
            templates = [templates]

        for tpl in templates:
            if isinstance(tpl, dict):  # line that answers a device question
                lines.append({**tpl, "command": _render(tpl["command"], params, name)})
            else:
                lines.append(_render(tpl, params, name))
    return lines


class FilterModule:
    def filters(self):
        return {
            "load_profile": load_profile,
            "build_commands": build_commands,
        }

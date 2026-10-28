# Telequill - Device Variants Guide

How Telequill handles switches of different families (V1, V2, V3 ...) and
speeds (1G, 10G, 100G ...) **without hardcoding models in code**.

There are only two things you ever edit:

| I want to ... | Edit this |
|---|---|
| Show or hide a Configuration tab on some switches | `resources/definitions/config_module_support.yaml` |
| Run a different playbook on some switches | Add a playbook in a family/speed sub-folder under `librenms-ansible-inventory-plugin/playbooks/<module>/` |

No PHP changes, no cache clear. The change applies from the next page load or API call.

---

## 1. How Telequill knows what a switch is

Everything comes from discovery, which already runs for every device:

| Value | Where it comes from | Example |
|---|---|---|
| **family** | OS detection (`resources/definitions/os_detection/<os>.yaml`, matched by sysObjectID) | `switchv1alphabridge` |
| **speed** | Fastest physical Ethernet port of the switch (VLAN, LAG and Null0 ports are ignored) | `1g`, `10g`, `100g` |
| **hardware** | Model reported by the switch | `AS200/12/XF` |

**Check what Telequill detected for a switch** in either place:

- Configuration page: the grey line at the top of every Configuration tab:
  `AS200/12/XF · Alphabridge Switch V1 · 10G · 12 ports · firmware 2.2.0F Build 161824`
- API: `GET /api/v0/devicecapabilities/{hostname}` (admin token)

A new switch is classified automatically after its first discovery.

> **Family must be detected correctly.** Each family needs its own OS
> detection file with its own sysObjectID prefix, for example
> `switchv2alphabridge.yaml` for V2. If a V2 switch is detected as
> `switchv1alphabridge`, it gets V1 tabs and V1 playbooks.

---

## 2. Show or hide a Configuration tab

File: **`resources/definitions/config_module_support.yaml`**

### Step 1: find the tab name

The tab name is the last part of the page URL:

```
http://<server>/device/29/configuration/stpconfig
                                        ^^^^^^^^^ tab name
```

All tab names are listed in [section 4](#4-tab-names).

### Step 2: add a rule

```yaml
stpconfig:
  only:
    - speed: 1g
```

That means **STP is shown only on 1G switches**.

Only two keywords are used:

| Keyword | Meaning |
|---|---|
| `only:` | Show the tab **only** on switches matching one of the lines |
| `hide:` | **Never** show the tab on switches matching one of the lines |

Each line can check `family`, `speed` and/or `hardware`:
- If a line has more than one of them, all must match.
- Each one takes one value or a list `[a, b]`.

### Examples

```yaml
# STP only on 1G switches
stpconfig:
  only:
    - speed: 1g

# MLD snooping on 1G and 10G, not on 100G
mldsnoopingconfig:
  only:
    - speed: [1g, 10g]

# DHCP snooping everywhere except V2 switches
dhcpsnoopingconfig:
  hide:
    - family: switchv2alphabridge

# Ring protection hidden on V1 10G and on every V3 switch
ringprotection:
  hide:
    - family: switchv1alphabridge
      speed: 10g
    - family: switchv3alphabridge

# Private VLAN only on two models
privatevlanconfig:
  only:
    - hardware: [AS210T mother card, AS228T mother card]
```

### What happens

- **Menu:** the tab is removed. If every tab in a group is hidden, the group heading disappears too.
- **Direct URL:** opening the tab directly shows *"stpconfig is not available on this switch (AS200/12/XF, 10G)"* instead of the page.
- **No rule:** a tab with no rule is shown on every switch, which is the default.
- **Not yet discovered:** a switch whose family or speed is still unknown is never hidden by a rule.

### Hiding a section inside a page

Some pages have their own sub-tabs, such as the IGMP page's
"IGMP Snooping VLAN List". Write these as `<tab>.<section>`:

```yaml
# IGMP VLAN List not available on V1 switches
igmpconfig.vlanlist:
  hide:
    - family: switchv1alphabridge
```

When a sub-tab is hidden, its link is removed from the page. Its data is not
read from the switch, so no playbook runs for it.

Only these sections can be hidden so far. Each one has to be wired into
its page once (see [section 7](#7-where-the-code-is)):

| Page | Section | Name (use in YAML) |
|---|---|---|
| IGMP Configuration | IGMP Snooping VLAN List | `igmpconfig.vlanlist` |

### Rules for writing the file

- Use 2 spaces for indentation. Do not use tabs. YAML is strict about indentation.
- Spell the tab name exactly as it appears in the URL.
- Field names must be exactly `family`, `speed` or `hardware`. A misspelt field is ignored without any error.
- Write speed as in the info line, in any case: `1g`, `10G`, `100g`.
- Write family as the OS name, such as `switchv1alphabridge`. Do not use the display text "Alphabridge Switch V1".

### Current rules

| Tab | Rule |
|---|---|
| `stpconfig` | only 1G |
| `pdpconfig` | only 1G |

---

## 3. Different playbook per family / speed

Use this when a command on the switch is different for some family or speed.

### Folder layout

```
playbooks/static_route_l3/
├── getstaticroute.yml                        common - used by default
├── setstaticroute.yml                        common
├── switchv1alphabridge/
│   └── 10g/
│       └── getstaticroute.yml                only V1 + 10G
└── switchv2alphabridge/
    ├── getstaticroute.yml                    all V2 switches
    └── 10g/
        └── setstaticroute.yml                only V2 + 10G
```

For each switch Telequill tries these locations in order. The **first file
that exists** is used:

1. `<module>/<family>/<speed>/<file>`, e.g. `static_route_l3/switchv2alphabridge/10g/setstaticroute.yml`
2. `<module>/<family>/<file>`, e.g. `static_route_l3/switchv2alphabridge/getstaticroute.yml`
3. `<module>/<file>`, the common playbook

So:
- Add a file only where the command is really different.
- Get and set are chosen separately. You can have a variant `get` and keep the common `set`.
- A V1 10G variant is never used on a V2 10G switch.

### Writing a variant playbook

1. Copy the common playbook into the new folder and change only the switch commands.
2. **Paths:** do not use `{{ playbook_dir }}/../..`, because the variant is deeper in the folder tree. Use the inventory folder instead. It works at any depth:
   ```yaml
   vars:
     project_root: "{{ inventory_dir }}/.."
   ```
3. **Temporary script:** give it its own name, so the common and variant playbooks never overwrite each other's script:
   ```yaml
   dest: "{{ tmp_dir }}/getstaticroute_v1_10g.py"
   ```
4. **Output:** keep the output file name and format the same as the common playbook (`output/<ip>_staticroute.yml`, same keys). The page and API read that file.
5. **Syntax check:**
   ```bash
   cd librenms-ansible-inventory-plugin && source bin/activate
   ansible-playbook --syntax-check -i hosts/<ip>.yml playbooks/<module>/<family>/<speed>/<file>.yml
   ```

Example: `playbooks/static_route_l3/switchv1alphabridge/10g/getstaticroute.yml`.

### Check which playbook ran

- **Static Route page:** a label next to the heading shows
  **Playbook: switchv1alphabridge / 10g** or **Playbook: common**.
  Hover over it to see the full path.
- **L3 show APIs:** the response has a `playbook` field, for example
  `"playbook": "static_route_l3/switchv1alphabridge/10g/getstaticroute.yml"`.

### Modules that support variants today

| Module | Playbook folder |
|---|---|
| VLAN Interfaces and IP Addresses | `vlan_interface_IP` |
| VLAN Interface IPv6 | `vlan_IPv6_L3` |
| Static Route | `static_route_l3` |
| IPv6 Route | `ipv6routel3` |

Other modules (STP, VLAN, DHCP snooping ...) always run their common playbook
for now. A variant file placed in their folders is **not used** until those
modules are switched to the same lookup.

---

## 4. Tab names

| Group | Tab | Tab name (use in YAML) |
|---|---|---|
| Basic Configuration | Hostname | `hostname` |
| L2 Configuration | VLAN Configuration | `vlansconfig` |
| | MTU configuration | `mtuconfig` |
| | LLDP configuration | `lldpconfig` |
| | ARP configuration | `arpconfig` |
| | BackupLink configuration | `backuplinkconfig` |
| | Port Channel | `portchannelconfig` |
| | PDP configuration | `pdpconfig` |
| | MQTT configuration | `mqttconfig` |
| | GVRP Configuration | `gvrpconfig` |
| | Private VLAN | `privatevlanconfig` |
| | DDM Configuration | `ddm` |
| | IGMP Configuration | `igmpconfig` |
| | IPv6 MLD Snooping | `mldsnoopingconfig` |
| | DHCP Snooping | `dhcpsnoopingconfig` |
| | STP Configuration | `stpconfig` |
| | Ring Protection | `ringprotection` |
| | Multiple Ring Protection | `multipleringprotection` |
| L3 Configuration | VLAN Interfaces and IP Addresses | `vlansinterfaceandipadress` |
| | VLAN Interface IPv6 | `vlaninterfaceipv6config` |
| | Static Route | `staticrouteconfig` |
| | IPv6 Route | `ipv6routeconfig` |
| Port Configuration | Port Configuration | `portconfig` |
| | Rate Limit | `ratelimitconfig` |
| System Management | System Software | `systemsoftware` |
| | NTP | `ntp` |
| | Startup-Config | `startupconfig` |
| | Port Config | `dayfirst` |
| | Reboot | `reboot` |

---

## 5. Adding a new switch family (e.g. V2)

1. Get its sysObjectID: `snmpget -v2c -c <community> <ip> 1.3.6.1.2.1.1.2.0`
2. Create `resources/definitions/os_detection/switchv2alphabridge.yaml` with that sysObjectID prefix. Make sure the prefix does not overlap another family's file.
3. Re-discover the switch. The info line should show **Alphabridge Switch V2** and the right speed.
4. Add `switchv2alphabridge/` playbook folders and tab rules **only where V2 is different**.

---

## 6. Troubleshooting

| Problem | Check |
|---|---|
| Tab still shows | Is the tab name spelt exactly as in the URL? Is the indentation 2 spaces? Does the info line show the family/speed you wrote? |
| Tab hidden on every switch | Is an `only:` value misspelt (e.g. `1gb` instead of `1g`)? |
| Info line shows wrong family | The OS detection file matched the wrong sysObjectID. Fix the os_detection YAML and re-discover. |
| Info line shows wrong speed or none | The switch has no discovered physical ports yet. Run discovery. |
| Variant playbook not used | Is the folder named exactly `<os>/<speed>` in lowercase (`switchv1alphabridge/10g`)? Is the file name the same as the common one? Is the module in the list in section 3? |
| Variant playbook writes nothing | Is `project_root` set to `{{ inventory_dir }}/..`? |

### Known limits

- Tab rules hide the **page only**. The `/api/v0/...` endpoints for a hidden module still work if called directly.
- A misspelt field name in a rule is ignored without any error.

---

## 7. Where the code is

You normally do not need to touch these files:

| File | What it does |
|---|---|
| `app/Services/DeviceCapabilities.php` | Works out family/speed/hardware from discovery. `supports()` applies the YAML rules. |
| `app/Http/Controllers/Device/Tabs/ConfigurationController.php` | `supportedTabs()` filters the menu. It also blocks direct URLs to hidden tabs. |
| `resources/views/device/tabs/config/notsupported.blade.php` | The "not available on this switch" message. |
| `resources/views/device/tabs/config/igmpconfig.blade.php` | Example of a page with a hideable section (`igmpconfig.vlanlist`). To make another section hideable, copy its pattern: `$on = DeviceCapabilities::supports($device, '<tab>.<section>')`, wrap the sub-tab link in `@if($on)`, and give the section's table `ajax: !ON ? null : {...}`. |
| `app/Api/Controllers/KunalApiController.php` | `devicePlaybook()` picks the playbook variant. It also serves `devicecapabilities/{hostname}`. |

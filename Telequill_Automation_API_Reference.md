# Telequill Automation API Reference

Base URL: `http://localhost:8000/api/v0`

Part 1 (sections 1-23): 104 automation endpoints that configure AlphaBridge switches. Almost all of them run an Ansible playbook from
`librenms-ansible-inventory-plugin/playbooks/` against one switch, over SSH (one endpoint uses
Telnet). They are the same calls the device **Configuration** pages in Telequill make. Source:
`app/Api/Controllers/KunalApiController.php`, routes in `routes/api.php`.

Part 2 ([section 24](#24-monitoring-api-librenms-core)): 132 monitoring endpoints (devices, ports,
alerts, logs, graphs, inventory ...) from LibreNMS core. They use a different response format and
access rules, described at the start of that section.

---

## 1. Basics

**Auth.** Every request needs an API token that belongs to an **admin** user (Settings > API
Access). Send it as either header:

```
X-Auth-Token: <token>
Authorization: Bearer <token>
```

A missing or wrong token gets `401`; a non-admin token gets `403`.

**`{hostname}`** in every path is the switch's IP address, exactly as in its inventory file
`librenms-ansible-inventory-plugin/hosts/<ip>.yml`. A switch without that file can't be reached.

**Request bodies** are JSON (`Content-Type: application/json`) or form data. Send
`Accept: application/json` so validation errors come back as JSON instead of a redirect. The one
file upload, `POST /tftpupload/{hostname}`, must be `multipart/form-data`.

**Example:**

```
curl -X POST "http://localhost:8000/api/v0/changemtu/192.168.2.245" \
     -H "X-Auth-Token: TOKEN" -H "Accept: application/json" \
     -H "Content-Type: application/json" \
     -d '{"mtu": 9216}'
```

---

## 2. Responses and errors

**Success** - HTTP `200`, `status` is `success`, plus the endpoint's own fields:

```json
{"status": "success", "message": "MTU changed successfully", "raw": "<ansible-playbook output>"}
```

Most set/add/delete endpoints return `message` and `raw` (the full playbook output, useful for
debugging). Show endpoints return the data fields listed under each endpoint.

**Errors:**

| HTTP | When | Body |
|---|---|---|
| `422` | A parameter fails validation | `{"message": "...", "errors": {"field": ["reason"]}}` |
| `400` | The playbook failed, or its output file is missing or unreadable | `{"status": "error", "message": "...", "raw_output": "..."}` |
| `401` / `403` | Bad token / not an admin | |

A few older endpoints don't check the playbook result, so they return `200` even when the switch
rejected the change. Each one is marked **Unchecked result** below.

**Saved results (`cached`).** Show endpoints marked *cached* below return the result saved from the
previous read immediately with `"cached": true`, and refresh it from the switch in the background.
The first call for a switch (no saved result yet) reads live and returns `"cached": false`. Set
endpoints whose result is checked also refresh the saved result before they return, so a show call
right after one of them sees the change.

**Timing.** Each call logs in to the switch, so expect several seconds per request, and up to a
minute or more for changes that make the switch re-converge (STP/ring changes, reboot).

---

## 3. Index

| Section | Endpoints |
|---|---|
| [System and device](#4-system-and-device) | 7 |
| [Interfaces and ports](#5-interfaces-and-ports) | 10 |
| [VLAN](#6-vlan) | 9 |
| [Voice VLAN](#7-voice-vlan) | 6 |
| [Private VLAN](#8-private-vlan) | 4 |
| [LLDP](#9-lldp) | 4 |
| [PDP](#10-pdp) | 4 |
| [GVRP](#11-gvrp) | 4 |
| [MQTT / Remote NMS](#12-mqtt--remote-nms) | 2 |
| [DDM](#13-ddm) | 2 |
| [Rate limit](#14-rate-limit) | 2 |
| [IGMP snooping](#15-igmp-snooping) | 9 |
| [IPv6 MLD snooping](#16-ipv6-mld-snooping) | 2 |
| [Static ARP](#17-static-arp) | 4 |
| [BackupLink](#18-backuplink) | 2 |
| [Port channel (link aggregation)](#19-port-channel-link-aggregation) | 5 |
| [Ring protection](#20-ring-protection) | 6 |
| [Configuration backup (TFTP)](#21-configuration-backup-tftp) | 3 |
| [Spanning tree (STP)](#22-spanning-tree-stp) | 8 |
| [DHCP snooping](#23-dhcp-snooping) | 11 |
| [Monitoring API (LibreNMS core)](#24-monitoring-api-librenms-core) | 132 |

In the parameter tables, **In** is `path`, `query` or `body`, and **Rules** are the checks the API
applies before running the playbook (ranges are inclusive).

---

## 4. System and device

### GET `/systeminfo/{hostname}`

Device details read from the switch (model, version, serial and so on).

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `data`, `ip`, `raw`

<sub>playbook: `device/devicedetails.yml`</sub>

### GET `/gethostname/{hostname}`

Current hostname of the switch.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `hostname`, `ip`

<sub>playbook: `hostname/gethostname.yml`</sub>

### POST `/changehostname/{hostname}`

Set the switch hostname.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `hostname` | body | yes | string | The **new** hostname (body field - not the path parameter). |

> **Unchecked result:** returns `200` / `success` whenever the playbook runs, even if the switch rejected the change. Check `raw`, or read the setting back afterwards.

Response: `status`, `message`, `raw`

<sub>playbook: `hostname/changehostname.yml`</sub>

### GET `/ntp/{hostname}`

NTP configuration of the switch.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `ip`, `ntp`

<sub>playbook: `ntp/ntp.yml`</sub>

### POST `/devicereboot/{hostname}`

Reboot the switch. No body. The switch drops off the network until it comes back up.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

> **Unchecked result:** returns `200` / `success` whenever the playbook runs, even if the switch rejected the change. Check `raw`, or read the setting back afterwards.

Response: `status`, `message`, `raw`

<sub>playbook: `device/rebootdevice.yml`</sub>

### POST `/testapi`

Connectivity check - returns the text `Test function called.`

No parameters.

### GET `/job-status/{jobId}`

Status of a background job.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `jobId` | path | yes |  |  |

> **Not implemented:** the route exists, but `KunalApiController` has no `jobStatus` method, so this endpoint returns an error.

---

## 5. Interfaces and ports

### GET `/vlan/interface/{hostname}`

Every interface with its status, speed and VLAN settings.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `cached`, `count`, `current_time`, `interfaces`, `ip`, `raw`

<sub>cached · playbook: `interface/getinterface.yml`</sub>

### GET `/portconfig/show/{hostname}`

Port settings (admin status, speed, duplex, flow control, fiber auto) for every interface.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `cached`, `interfaces`, `ip`

<sub>cached · playbook: `port_configuration/getportconfigurationinterfacedata.yml`</sub>

### POST `/portconfig/set/{hostname}`

Change one interface's port settings. Send only the fields to change.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `interface` | body | yes | string |  |
| `status` | body | optional | one of `enable`, `disable` |  |
| `speed` | body | optional | one of `10M`, `100M`, `1000M`, `10G`, `auto` |  |
| `duplex` | body | optional | one of `full`, `half`, `auto` |  |
| `flow_control` | body | optional | one of `on`, `off`, `auto` |  |
| `fiber_auto` | body | optional | one of `on`, `off` |  |

Response: `status`, `message`, `raw`

<sub>playbook: `port_configuration/getportconfigurationinterfacedata.yml`, `port_configuration/setportconfigurationinterfacedata.yml`</sub>

### POST `/cngportstatus/{hostname}`

Change one interface's status. `status` is passed to the switch as given.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `interface` | body | yes | string |  |
| `status` | body | yes | string |  |

> **Unchecked result:** returns `200` / `success` whenever the playbook runs, even if the switch rejected the change. Check `raw`, or read the setting back afterwards.

Response: `status`, `message`, `raw`

<sub>playbook: `interface/cngportstatus.yml`</sub>

### POST `/interface/reset/{hostname}`

Reset one interface to its default configuration.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `interface` | body | yes | string |  |

> **Unchecked result:** returns `200` / `success` whenever the playbook runs, even if the switch rejected the change. Check `raw`, or read the setting back afterwards.

Response: `status`, `message`, `raw`

<sub>playbook: `interface/interfacereset.yml`</sub>

### GET `/getmtu/{hostname}`

Current MTU.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `ip`, `mtu`

<sub>playbook: `mtu/getmtu.yml`</sub>

### POST `/changemtu/{hostname}`

Set the MTU.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `mtu` | body | yes | integer, 1518 - 9216 |  |

> **Unchecked result:** returns `200` / `success` whenever the playbook runs, even if the switch rejected the change. Check `raw`, or read the setting back afterwards.

Response: `status`, `message`, `raw`

<sub>playbook: `mtu/changemtu.yml`</sub>

### POST `/network/interface/show/{hostname}`

Running configuration of one interface, one line per array item in `config`.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `interface` | body | yes |  | Interface name, e.g. `GigaEthernet0/1`. |

Response: `status`, `config`, `interface`, `ip`

<sub>playbook: `interface/network_interface_show.yml`</sub>

### POST `/network/interface/config/{hostname}`

Configure one or more interfaces (description, mode, allowed VLANs, PVID, QinQ, LACP) from a JSON list. Connects over Telnet.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `interfaces` | body | yes | array, at least 1 item(s) | List of objects - see below. |

> **Unchecked result:** returns `200` / `success` whenever the playbook runs, even if the switch rejected the change. Check `raw`, or read the setting back afterwards.

Each item in `interfaces`:

| Field | Default | Description |
|---|---|---|
| `name` | - | Interface name, e.g. `GigaEthernet0/1` |
| `description` | none | Interface description |
| `mode` | `access` | `access`, `trunk`, `dot1q-tunnel-uplink` or `dot1q-translating-tunnel` |
| `allowed_vlans` | none | Allowed VLAN list (trunk / tunnel modes) |
| `pvid` | none | PVID (trunk and translating-tunnel modes) |
| `spanning_tree` | `true` | `false` sends `no spanning-tree` |
| `lacp` | `false` | `true` adds the port to `channel-group 1 mode active` |
| `qinq_rules` | `[]` | Translating tunnel only: list of `{"from": <vlan>, "to": <vlan, default 1>, "cos": <0-7, default 0>}` |

```json
{
  "interfaces": [
    {"name": "GigaEthernet0/1", "description": "uplink", "mode": "trunk", "allowed_vlans": "10,20", "pvid": 10},
    {"name": "GigaEthernet0/2", "mode": "dot1q-translating-tunnel", "allowed_vlans": "100",
     "qinq_rules": [{"from": 200, "to": 100, "cos": 0}]}
  ]
}
```

Response: `status`, `configured`, `message`, `raw`

<sub>playbook: `interface/network_interface_config.yml`</sub>

### POST `/network/cmd/config/{hostname}`

Run raw CLI commands on the switch. `config` is a multi-line string, one command per line; blank lines are dropped.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `config` | body | yes | at least 1 char(s) | Multi-line string, one CLI command per line. |

> **Unchecked result:** returns `200` / `success` whenever the playbook runs, even if the switch rejected the change. Check `raw`, or read the setting back afterwards.

```json
{"config": "interface GigaEthernet0/3\ndescription to-camera\nexit"}
```

Response: `status`, `message`, `raw`

<sub>playbook: `push/network_cmd_config.yml`</sub>

---

## 6. VLAN

### GET `/getvlan/{hostname}`

All VLANs on the switch.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `cached`, `ip`, `vlans`

<sub>cached · playbook: `vlan/getvlan.yml`</sub>

### POST `/addvlan/{hostname}`

Create one VLAN.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `vlan_id` | body | yes | integer |  |
| `vlan_name` | body | yes | string |  |

Response: `status`, `message`, `raw`

<sub>playbook: `vlan/addvlan.yml`, `vlan/getvlan.yml`</sub>

### POST `/vlan/batch/{hostname}`

Create and/or delete several VLANs in one call.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `vlan_add` | body | optional | string | VLAN list, e.g. `10,20-25`. |
| `vlan_delete` | body | optional | string | VLAN list, e.g. `30,40-45`. |

> **Unchecked result:** returns `200` / `success` whenever the playbook runs, even if the switch rejected the change. Check `raw`, or read the setting back afterwards.

Response: `status`, `message`, `raw`

<sub>playbook: `vlan/addvlanbatch.yml`, `vlan/getvlan.yml`</sub>

### POST `/vlandelete/{hostname}`

Delete VLANs.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `vlan_delete` | body | yes |  | VLAN list, e.g. `30,40-45` (same format as `vlan/batch`). |

> **Unchecked result:** returns `200` / `success` whenever the playbook runs, even if the switch rejected the change. Check `raw`, or read the setting back afterwards.

Response: `status`, `message`, `raw`

<sub>playbook: `vlan/addvlanbatch.yml`, `vlan/getvlan.yml`</sub>

### POST `/vlan/edit/{hostname}`

Rename a VLAN and set one interface's PVID, mode and membership for it.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `vlan_id` | body | yes | integer, 1 - 4094 |  |
| `vlan_name` | body | yes | string, max 32 chars |  |
| `interface` | body | yes | string |  |
| `pvid` | body | yes | integer, 1 - 4094 |  |
| `mode` | body | yes | string, one of `Access`, `Trunk` (any case) |  |
| `untagged` | body | if `mode` = trunk | string, one of `Yes`, `No` (any case) |  |
| `allowed` | body | if `mode` = trunk | string, one of `Yes`, `No` (any case) |  |

Response: `status`, `message`, `raw`

<sub>playbook: `vlan/editvlanconfiguration.yml`, `vlan/getinterfacevlanattribute.yml`, `vlan/getvlan.yml`</sub>

### POST `/vlan/configure/{hostname}`

Put an interface in access mode on a VLAN.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `vlan_id` | body | yes | integer |  |
| `interface` | body | yes | string |  |

> **Unchecked result:** returns `200` / `success` whenever the playbook runs, even if the switch rejected the change. Check `raw`, or read the setting back afterwards.

Response: `status`, `message`, `raw`

<sub>playbook: `vlan/vlanconfigure.yml`</sub>

### POST `/vlan/configure-trunk/{hostname}`

Put an interface in trunk mode with a list of allowed VLANs.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `vlan_ids` | body | yes | string |  |
| `interface` | body | yes | string |  |

> **Unchecked result:** returns `200` / `success` whenever the playbook runs, even if the switch rejected the change. Check `raw`, or read the setting back afterwards.

Response: `status`, `message`, `raw`

<sub>playbook: `vlan/vlanconfiguretrunk.yml`</sub>

### GET `/vlan/interface/attribute/show/{hostname}`

Per-interface VLAN attributes (PVID, mode, allowed and untagged VLANs).

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `entries`

<sub>cached · playbook: `vlan/getinterfacevlanattribute.yml`</sub>

### POST `/vlan/interface/attribute/set/{hostname}`

Set one interface's VLAN attributes.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `interface` | body | yes | string |  |
| `pvid` | body | yes | integer, 1 - 4094 |  |
| `mode` | body | yes | string, one of `Access`, `Trunk` (any case) |  |
| `vlan_allowed_range` | body | yes | string |  |
| `vlan_untagged_range` | body | yes | string |  |
| `vlan_allowed_add` | body | optional | string |  |
| `vlan_allowed_remove` | body | optional | string |  |
| `vlan_untagged_add` | body | optional | string |  |
| `vlan_untagged_remove` | body | optional | string |  |

Response: `status`, `message`, `raw`

<sub>playbook: `vlan/getinterfacevlanattribute.yml`, `vlan/setinterfacevlanattribute.yml`</sub>

---

## 7. Voice VLAN

### GET `/vlan/voice/show/{hostname}`

Voice VLAN global MAC address list.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `entries`

<sub>cached · playbook: `vlan/getvoicevlan.yml`</sub>

### POST `/vlan/voice/set/{hostname}`

Add or delete a voice VLAN MAC address entry.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `operation` | body | yes | string, one of `set`, `delete` (any case) |  |
| `mac_address` | body | yes | string, pattern `/^[0-9A-Fa-f]{4}\.[0-9A-Fa-f]{4}\.[0-9A-Fa-f]{4}$/` |  |
| `mac_mask` | body | yes | string, pattern `/^[0-9A-Fa-f]{4}\.[0-9A-Fa-f]{4}\.[0-9A-Fa-f]{4}$/` |  |

Response: `status`, `message`, `raw`

<sub>playbook: `vlan/getvoicevlan.yml`, `vlan/setvoicevlan.yml`</sub>

### GET `/vlan/interface/voice/show/{hostname}`

Per-interface voice VLAN settings.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `entries`

<sub>cached · playbook: `vlan/getinterfacevoicevlan.yml`</sub>

### POST `/vlan/interface/voice/set/{hostname}`

Set or remove voice VLAN on one interface.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `operation` | body | yes | string, one of `set`, `delete` |  |
| `interface` | body | yes | string, pattern `/^(g\|tg)[0-9]+\/[0-9]+$/i` |  |
| `vlan_id` | body | if `operation` = set | integer, 2 - 4094 |  |
| `priority_mode` | body | if `operation` = set | string, one of `cos`, `dscp` |  |
| `priority` | body | if `operation` = set | integer, 0 - 63 |  |
| `mode` | body | if `operation` = set | string, one of `vlan`, `mac-address` |  |

Response: `status`, `message`, `raw`

<sub>playbook: `vlan/getinterfacevoicevlan.yml`, `vlan/setinterfacevoicevlan.yml`</sub>

### GET `/voicevlanshow/{hostname}`

Voice VLAN MAC address list (older endpoint).

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `cached`, `ip`, `mac_addresses`, `raw`

<sub>cached · playbook: `voicevlan/voicevlanshow.yml`</sub>

### POST `/voicevlan/batch/{hostname}`

Delete several voice VLAN MAC entries. `mac` and `mask` are parallel arrays (same length, same order).

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `mac` | body | yes | array, at least 1 item(s) | Array of MAC addresses. |
| `mask` | body | yes | array, at least 1 item(s) | Array of masks, same order as `mac`. |

> **Unchecked result:** returns `200` / `success` whenever the playbook runs, even if the switch rejected the change. Check `raw`, or read the setting back afterwards.

Response: `status`, `message`, `results`

<sub>playbook: `voicevlan/voicevlanbatch.yml`</sub>

---

## 8. Private VLAN

### GET `/privatevlan/show/{hostname}`

Private VLANs and their type.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

<sub>cached · playbook: `private_vlan/getprivatevlan.yml`</sub>

### POST `/privatevlan/set/{hostname}`

Add or delete a private VLAN. **delete removes the whole VLAN** from the switch (`no vlan <id>`).

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `operation` | body | yes | string, one of `add`, `delete` |  |
| `vlan_id` | body | yes | integer, 1 - 4094 |  |
| `vlan_type` | body | yes, only when `operation` = `add` | string, one of `primary`, `community`, `isolated` |  |
| `associated_primary_vlan` | body | unless `vlan_type` = primary, only when `operation` = `add` | integer, 1 - 4094, must differ from `vlan_id` |  |

Response: `status`, `message`, `raw`

<sub>playbook: `private_vlan/getprivatevlan.yml`, `private_vlan/setprivatevlan.yml`</sub>

### GET `/privatevlan/port/show/{hostname}`

Private VLAN settings of every port.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

<sub>cached · playbook: `private_vlan/getprivatevlanport.yml`</sub>

### POST `/privatevlan/port/set/{hostname}`

Set one port's private VLAN mode and VLANs.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `port` | body | yes | string, pattern `/^t?g\d+\/\d+$/i` |  |
| `port_vlan` | body | yes | integer, 1 - 4094 |  |
| `private_vlan_port_mode` | body | yes | string, one of `regular`, `host`, `promiscuous` |  |
| `primary_vlan` | body | unless `private_vlan_port_mode` = regular | integer, 1 - 4094 |  |
| `secondary_vlan` | body | unless `private_vlan_port_mode` = regular | integer, 1 - 4094, must differ from `primary_vlan` |  |
| `outgoing_segment_tag` | body | yes | string, one of `Yes`, `No` |  |
| `tag_pvid` | body | optional | integer, 1 - 4094 |  |
| `tag_priority` | body | optional | integer, 0 - 7 |  |

Response: `status`, `message`, `raw`

<sub>playbook: `private_vlan/getprivatevlanport.yml`, `private_vlan/setprivatevlanport.yml`</sub>

---

## 9. LLDP

### GET `/getlldp/{hostname}`

LLDP global settings.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `ip`, `lldp`

<sub>cached · playbook: `lldp/getlldp.yml`</sub>

### POST `/changelldp/{hostname}`

Change LLDP global settings.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `protocol_state` | body | yes | string, one of `open`, `close` |  |
| `holdtime` | body | optional | integer, ... - 65535 |  |
| `timer` | body | optional | integer, 5 - 65534 |  |
| `reinit` | body | optional | integer, 2 - 5 |  |

> **Unchecked result:** returns `200` / `success` whenever the playbook runs, even if the switch rejected the change. Check `raw`, or read the setting back afterwards.

Response: `status`, `message`, `raw`

<sub>playbook: `lldp/changelldp.yml`, `lldp/getlldp.yml`, `lldp/getlldpinterface.yml`</sub>

### GET `/getlldpinterface/{hostname}`

Per-interface LLDP settings.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `ip`, `lldp_interfaces`

<sub>cached · playbook: `lldp/getlldpinterface.yml`</sub>

### POST `/lldp/interface/set/{hostname}`

Set LLDP on one interface.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `interface` | body | yes | string, pattern `/^GigaEthernet0\/([1-9]\|10)$/i` |  |
| `admin_status` | body | yes | string, one of `enable`, `disable` |  |
| `tlv` | body | yes | string, one of `enable`, `disable` |  |

Response: `status`, `message`, `raw`

<sub>playbook: `lldp/getlldpinterface.yml`, `lldp/setlldpinterfaceconfiguration.yml`</sub>

---

## 10. PDP

### GET `/pdp/show/{hostname}`

PDP global settings.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `peek` | query | optional | boolean | Query param. `1` = return only the saved result, never contact the switch. |

Response: `status`, `cached`, `found`, `pdp`

<sub>cached · playbook: `pdp/getpdp.yml`</sub>

### POST `/pdp/set/{hostname}`

Change PDP global settings.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `pdp_state` | body | yes | string, one of `open`, `close` (any case) |  |
| `holdtime` | body | yes | integer, 10 - 255 |  |
| `tx_interval` | body | yes | integer, 5 - 254 |  |
| `version` | body | yes | string, one of `Version1`, `Version2`, `1`, `2` (any case) |  |

Response: `status`, `message`, `raw`

<sub>playbook: `pdp/getpdp.yml`, `pdp/setpdp.yml`</sub>

### GET `/pdp/interface/show/{hostname}`

Per-interface PDP status.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `cached`, `entries`

<sub>playbook: `pdp/getpdpinterfaceconfi.yml`</sub>

### POST `/pdp/interface/set/{hostname}`

Enable or disable PDP on one interface.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `interface` | body | yes | string |  |
| `status` | body | yes | one of `Enable`, `Disable` |  |

Response: `status`, `message`, `raw`

<sub>playbook: `pdp/getpdpinterfaceconfi.yml`, `pdp/setpdpinterfaceconfi.yml`</sub>

---

## 11. GVRP

### GET `/gvrp/global/show/{hostname}`

GVRP global settings.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `cached`, `dynamic_vlan`, `gvrp_global`

<sub>cached · playbook: `gvrp/gvrp_global_details.yml`</sub>

### POST `/gvrp/global/set/{hostname}`

Change GVRP global settings.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `gvrp_global` | body | yes | one of `enable`, `disable` |  |
| `dynamic_vlan` | body | yes | one of `enable`, `disable` |  |

Response: `status`, `message`, `raw`

<sub>playbook: `gvrp/gvrp_global_config.yml`, `gvrp/gvrp_global_details.yml`</sub>

### GET `/gvrp/show/{hostname}`

Per-interface GVRP status, one row per known port.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `cached`, `entries`, `ports`

<sub>cached · playbook: `gvrp/gvrp_interface_config_show.yml`</sub>

### POST `/gvrp/set/{hostname}`

Enable or disable GVRP on one interface.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `interface` | body | yes | string |  |
| `gvrp_status` | body | yes | one of `Enable`, `Disable` |  |

Response: `status`, `message`, `raw`

<sub>playbook: `gvrp/gvrp_interface_config_set.yml`, `gvrp/gvrp_interface_config_show.yml`</sub>

---

## 12. MQTT / Remote NMS

### GET `/mqtt/show/{hostname}`

MQTT / Remote NMS settings.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `force` | query | optional | boolean | Query param. `1` = skip the saved result and read live from the switch. |

Response: `status`, `cached`, `default_server`, `dns1`, `dns2`, `remote_nms_enable`, `server`

<sub>cached · playbook: `mqtt/getmqtt.yml`</sub>

### POST `/mqtt/set/{hostname}`

Change MQTT / Remote NMS settings.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `remote_nms_enable` | body | yes | one of `enable`, `disable` (any case) |  |
| `default_server` | body | optional | string |  |
| `server` | body | yes | string |  |
| `dns1` | body | optional | string |  |
| `dns2` | body | optional | string |  |

Response: `status`, `message`

<sub>playbook: `mqtt/getmqtt.yml`, `mqtt/setmqtt.yml`</sub>

---

## 13. DDM

### GET `/ddm/show/{hostname}`

DDM (optical module monitoring) global status.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `peek` | query | optional | boolean | Query param. `1` = return only the saved result, never contact the switch. |

Response: `status`, `cached`, `ddm`, `found`

<sub>cached · playbook: `ddm/ddm_show.yml`</sub>

### POST `/ddm/set/{hostname}`

Enable or disable DDM.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `ddm_status` | body | yes | string, one of `enable`, `disable` (any case) |  |

Response: `status`, `message`, `raw`

<sub>playbook: `ddm/ddm_set.yml`, `ddm/ddm_show.yml`</sub>

---

## 14. Rate limit

### GET `/ratelimit/show/{hostname}`

Receive/send rate limits, one row per known port.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `cached`, `entries`, `ports`

<sub>cached · playbook: `rate_limit/rate_limit_details.yml`</sub>

### POST `/ratelimit/set/{hostname}`

Set rate limits on one interface.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `interface` | body | yes | string |  |
| `receive_status` | body | optional | one of `Enable`, `Disable` |  |
| `receive_speed_unit` | body | optional | one of `64kbps`, `Percent` |  |
| `receive_speed` | body | optional | integer, 1 - 15625 |  |
| `send_status` | body | optional | one of `Enable`, `Disable` |  |
| `send_speed_unit` | body | optional | one of `64kbps`, `Percent` |  |
| `send_speed` | body | optional | integer, 1 - 15625 |  |

Response: `status`, `message`, `raw`

<sub>playbook: `rate_limit/rate_limit_details.yml`, `rate_limit/set_rate_limit.yml`</sub>

---

## 15. IGMP snooping

### GET `/igmp/show/{hostname}`

IGMP snooping global settings.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `cached`, `igmp`

<sub>cached · playbook: `igmp/igmp_snooping_config_get.yml`</sub>

### POST `/igmp/set/{hostname}`

Change IGMP snooping global settings. All fields are required.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `igmp_snooping` | body | yes | string, one of `enable`, `disable` |  |
| `dlf_drop` | body | yes | string, one of `discard_unknown`, `transfer_unknown` |  |
| `auto_query` | body | yes | string, one of `enable`, `disable` |  |
| `querier_address` | body | yes | IPv4 address |  |
| `query_interval` | body | yes | integer, 10 - 2147483647 |  |
| `querier_expiry_interval` | body | yes | integer, 10 - 2147483647 |  |

Response: `status`, `message`, `raw`

<sub>playbook: `igmp/igmp_snooping_config_get.yml`, `igmp/igmp_snooping_config_set.yml`</sub>

### GET `/igmp/vlan/show/{hostname}`

IGMP snooping settings per VLAN.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `entries`

<sub>cached · playbook: `igmp/igmp_snooping_vlan_config_get.yml`</sub>

### POST `/igmp/vlan/set/{hostname}`

Apply IGMP snooping settings to one VLAN.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `vlan_id` | body | yes | integer, 1 - 4094 |  |
| `status_snooping_vlan` | body | yes | string, one of `enable`, `disable` |  |
| `immediate_leave` | body | yes | string, one of `enable`, `disable` |  |
| `member_port` | body | yes | string, pattern `/^(g\|tg)[0-9]+\/[0-9]+$/i` |  |

Response: `status`, `message`, `raw`

<sub>playbook: `igmp/igmp_snooping_vlan_config_get.yml`, `igmp/igmp_snooping_vlan_config_set.yml`</sub>

### GET `/igmp/multicast/show/{hostname}`

IGMP multicast groups (static and learned).

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `entries`

<sub>cached · playbook: `igmp/igmp_snooping_multicast_list_info_get.yml`</sub>

### POST `/igmp/multicast/set/{hostname}`

Add a static multicast address on a VLAN.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `vlan_id` | body | yes | integer, 1 - 4094 |  |
| `ip_address` | body | yes | string, pattern `/^(22[4-9]\|23[0-9])\.\d{1,3}\.\d{1,3}\.\d{1,3}$/` |  |
| `port` | body | yes | string, one of `g0/1`, `g0/2`, `g0/3`, `g0/4`, `g0/5`, `g0/6`, `g0/7`, `g0/8`, `g0/9`, `g0/10` |  |
| `action` | body | yes | string, one of `enable`, `disable` |  |

Response: `status`, `message`, `raw`

<sub>playbook: `igmp/igmp_snooping_multicast_address_list_get_set.yml`, `igmp/igmp_snooping_multicast_list_info_get.yml`</sub>

### GET `/igmp/filter/show/{hostname}`

IGMP VLAN filters.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `entries`

<sub>cached · playbook: `igmp/igmp_snooping_vlan_filter_get.yml`</sub>

### POST `/igmp/filter/set/{hostname}`

Add an IGMP VLAN filter.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `vlan_id` | body | yes | integer, 1 - 4094 |  |
| `ip_address` | body | yes | string, pattern `/^(22[4-9]\|23[0-9])\.\d{1,3}\.\d{1,3}\.\d{1,3}$/` |  |

Response: `status`, `message`, `raw`

<sub>playbook: `igmp/igmp_snooping_vlan_filter_get.yml`, `igmp/igmp_snooping_vlan_filter_set.yml`</sub>

### POST `/igmp/filter/delete/{hostname}`

Delete an IGMP VLAN filter.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `vlan_id` | body | yes | integer, 1 - 4094 |  |
| `ip_address` | body | yes | string, pattern `/^(22[4-9]\|23[0-9])\.\d{1,3}\.\d{1,3}\.\d{1,3}$/` |  |

Response: `status`, `message`, `raw`

<sub>playbook: `igmp/igmp_snooping_vlan_filter_delete.yml`, `igmp/igmp_snooping_vlan_filter_get.yml`</sub>

---

## 16. IPv6 MLD snooping

### GET `/mld/show/{hostname}`

IPv6 MLD snooping settings.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `cached`, `mld`

<sub>cached · playbook: `ipv6_mld_snooping/ipv6_mld_snooping_get.yml`</sub>

### POST `/mld/set/{hostname}`

Change IPv6 MLD snooping settings. Both timers are required even when `mld_snooping` is `disable`.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `mld_snooping` | body | yes | string, one of `enable`, `disable` |  |
| `router_age_timer` | body | yes | integer, 10 - 2147483647 |  |
| `response_age_timer` | body | yes | integer, 10 - 2147483647 |  |
| `querier` | body | yes | string, one of `enable`, `disable` |  |
| `querier_address` | body | optional | IPv6 address |  |
| `solicitation` | body | yes | string, one of `enable`, `disable` |  |

Response: `status`, `message`, `raw`

<sub>playbook: `ipv6_mld_snooping/ipv6_mld_snooping_get.yml`, `ipv6_mld_snooping/ipv6_mld_snooping_set.yml`</sub>

---

## 17. Static ARP

### GET `/arp/show/{hostname}`

Static ARP table.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `arp_entries`, `cached`, `ip`

<sub>cached · playbook: `arp/show_basic_arp.yml`</sub>

### POST `/arp/add/{hostname}`

Add a static ARP entry.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `ip_address` | body | yes | IPv4 address |  |
| `mac_address` | body | yes | pattern `/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/` |  |
| `interface_vlan` | body | yes | integer, 1 - 4094 |  |

Response: `status`, `message`, `raw`

<sub>playbook: `arp/add_basic_arp.yml`</sub>

### POST `/arp/edit/{hostname}`

Change a static ARP entry.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `ip_address` | body | yes | IPv4 address |  |
| `mac_address` | body | yes | pattern `/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/` |  |
| `interface_vlan` | body | yes | integer, 1 - 4094 |  |

Response: `status`, `message`, `raw`

<sub>playbook: `arp/edit_basic_arp.yml`</sub>

### POST `/arp/delete/{hostname}`

Delete a static ARP entry.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `ip_address` | body | yes | IPv4 address |  |
| `interface_vlan` | body | yes | integer, 1 - 4094 |  |

Response: `status`, `message`, `raw`

<sub>playbook: `arp/delete_basic_arp.yml`</sub>

---

## 18. BackupLink

### GET `/backuplink/show/{hostname}`

BackupLink groups.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `cached`, `groups`, `ip`

<sub>cached · playbook: `backup_link_configuration/getbackuplink.yml`</sub>

### POST `/backuplink/set/{hostname}`

Add or delete a BackupLink group.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `operation` | body | yes | one of `add`, `delete` |  |
| `group_id` | body | yes | integer, 1 - 8 |  |
| `preemption_mode` | body | if `operation` = add | one of `none`, `forced`, `bandwidth` |  |
| `preemption_delay` | body | optional | integer, 0 - ... |  |

Response: `status`, `message`, `raw`

<sub>playbook: `backup_link_configuration/setbackuplink.yml`</sub>

---

## 19. Port channel (link aggregation)

### GET `/portchannel/show/{hostname}`

Port channel groups and their member ports.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `cached`, `groups`, `ip`

<sub>cached · playbook: `port_channel/port_aggregate_details.yml`</sub>

### POST `/portchannel/add/{hostname}`

Create a port channel group with member ports.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `aggregate_group` | body | yes | one of `P1`, `P2`, `P3`, `P4`, `P5`, `P6`, `P7`, `P8` |  |
| `mode` | body | yes | one of `static`, `lacp active`, `lacp passive` |  |
| `ports` | body | yes | string |  |

Response: `status`, `message`, `raw`

<sub>playbook: `port_channel/port_aggregate_config.yml`, `port_channel/port_aggregate_details.yml`</sub>

### POST `/portchannel/edit/{hostname}`

Change a port channel group's mode and add/remove member ports.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `aggregate_group` | body | yes | one of `p1`, `p2`, `p3`, `p4`, `p5`, `p6`, `p7`, `p8` |  |
| `mode` | body | yes | one of `static`, `lacp active`, `lacp passive` |  |
| `add_ports` | body | optional | string |  |
| `remove_ports` | body | optional | string |  |

Response: `status`, `message`, `raw`

<sub>playbook: `port_channel/port_aggregate_details.yml`, `port_channel/port_aggregate_edit.yml`</sub>

### POST `/portchannel/delete/{hostname}`

Delete a port channel group.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `port_id` | body | yes | one of `p1`, `p2`, `p3`, `p4`, `p5`, `p6`, `p7`, `p8` |  |

Response: `status`, `message`, `raw`

<sub>playbook: `port_channel/port_aggregate_delete.yml`, `port_channel/port_aggregate_details.yml`</sub>

### POST `/portchannel/loadbalance/{hostname}`

Set a port channel's load-balancing mode.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `port` | body | yes | one of `p1`, `p2`, `p3`, `p4`, `p5`, `p6`, `p7`, `p8` |  |
| `mode` | body | yes | one of `SRC MAC`, `DST MAC`, `BOTH MAC`, `SRC IP`, `DST IP`, `BOTH IP` |  |

Response: `status`, `message`, `raw`

<sub>playbook: `port_channel/port_channel_load_balancing.yml`</sub>

---

## 20. Ring protection

### GET `/erps/show/{hostname}`

ERPS rings.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `entries`

<sub>cached · playbook: `ring_protection/geterps.yml`</sub>

### POST `/erps/set/{hostname}`

Add, edit or delete an ERPS ring. Editing never changes ports - delete and re-add the ring for that.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `operation` | body | yes | string, one of `add`, `edit`, `delete` |  |
| `ring_id` | body | yes | integer, 1 - 239 |  |
| `control_vlan` | body | yes, only when `operation` ≠ `delete` | integer, 1 - 4094 |  |
| `wtr_time` | body | yes, only when `operation` ≠ `delete` | integer, 0 - 720 |  |
| `guard_time` | body | yes, only when `operation` ≠ `delete` | integer, 0 - 2000, multiple of 10 |  |
| `send_time` | body | yes, only when `operation` ≠ `delete` | integer, 1 - 10 |  |
| `port1` | body | yes, only when `operation` = `add` | string |  |
| `port2` | body | yes, only when `operation` = `add` | string, must differ from `port1` |  |
| `port1_role` | body | yes, only when `operation` = `add` | string, one of `ring-port`, `rpl` |  |
| `port2_role` | body | yes, only when `operation` = `add` | string, one of `ring-port`, `rpl`, must differ from `port1_role` |  |

Response: `status`, `message`, `raw`

<sub>playbook: `ring_protection/geterps.yml`, `ring_protection/seterps.yml`</sub>

### GET `/etherring/show/{hostname}`

Ethernet rings (EAPS).

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `entries`

<sub>cached · playbook: `ring_protection/getethernet.yml`</sub>

### POST `/etherring/set/{hostname}`

Add, edit or delete an Ethernet ring (EAPS).

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `operation` | body | yes | string, one of `add`, `edit`, `delete` |  |
| `ring_id` | body | yes | integer, 1 - 32 |  |
| `node_type` | body | yes, only when `operation` = `add` | string, one of `Master Node`, `Transit Node` |  |
| `control_vlan` | body | yes, only when `operation` = `add` | integer, 1 - 4094 |  |
| `hello_time` | body | yes, only when `operation` = `add` | integer, 1 - 10 |  |
| `fail_time` | body | yes, only when `operation` = `add` | integer, 3 - 30 |  |
| `pre_forward_time` | body | yes, only when `operation` = `add` | integer, 3 - 30 |  |
| `primary_port` | body | yes, only when `operation` = `add` | string, one of `None`, `GigaEthernet0/1`, `GigaEthernet0/2`, `GigaEthernet0/3`, `GigaEthernet0/6`, `GigaEthernet0/7`, `GigaEthernet0/8`, `TenGigabitEthernet0/1`, `TenGigabitEthernet0/2`, `TenGigabitEthernet0/3`, `TenGigabitEthernet0/4`, `p1` |  |
| `secondary_port` | body | yes, only when `operation` = `add` | string, one of `None`, `GigaEthernet0/1`, `GigaEthernet0/2`, `GigaEthernet0/3`, `GigaEthernet0/6`, `GigaEthernet0/7`, `GigaEthernet0/8`, `TenGigabitEthernet0/1`, `TenGigabitEthernet0/2`, `TenGigabitEthernet0/3`, `TenGigabitEthernet0/4`, `p1` |  |
| `ring_description` | body | optional | string, max 64 chars |  |

Response: `status`, `message`, `raw`

<sub>playbook: `ring_protection/getethernet.yml`, `ring_protection/setethernet.yml`</sub>

### GET `/multiplering/show/{hostname}`

Multiple ring protection (MEAPS) rings.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `entries`

<sub>cached · playbook: `multiple_ring_protection/multiple_ring_protection_get.yml`</sub>

### POST `/multiplering/set/{hostname}`

Add, edit or delete a MEAPS ring. On `edit`, `ring_type` and `node_type` must match the existing ring - the playbook refuses to change them.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `operation` | body | yes | string, one of `add`, `edit`, `delete` |  |
| `domain_id` | body | yes | integer, 0 - 3 |  |
| `ring_id` | body | yes | integer, 0 - 7 |  |
| `ring_type` | body | yes, only when `operation` ≠ `delete` | string, one of `major ring`, `sub ring` |  |
| `node_type` | body | yes, only when `operation` ≠ `delete` | string, one of `master node`, `transit node`, `edge node`, `assistant node` |  |
| `control_vlan` | body | yes, only when `operation` ≠ `delete` | integer, 1 - 4094 |  |
| `hello_time` | body | yes, only when `operation` ≠ `delete` | integer, 1 - 10 |  |
| `fail_time` | body | yes, only when `operation` ≠ `delete` | integer, 3 - 30 |  |
| `pre_forward_time` | body | yes, only when `operation` ≠ `delete` | integer, 3 - 30 |  |
| `primary_port` | body | yes, only when `operation` ≠ `delete` | string, one of `none`, `g0/1`, `g0/2`, `g0/3`, `g0/4`, `g0/5`, `g0/6`, `g0/7`, `g0/8`, `g0/9`, `g0/10` |  |
| `secondary_port` | body | yes, only when `operation` ≠ `delete` | string, one of `none`, `g0/1`, `g0/2`, `g0/3`, `g0/4`, `g0/5`, `g0/6`, `g0/7`, `g0/8`, `g0/9`, `g0/10` |  |

Response: `status`, `message`, `raw`

<sub>playbook: `multiple_ring_protection/multiple_ring_protection_get.yml`</sub>

---

## 21. Configuration backup (TFTP)

### POST `/tftpupload/{hostname}`

Upload a configuration file to the switch via TFTP. Send as `multipart/form-data`.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `tftp_server` | body | yes | string |  |
| `file` | body | yes | file | The configuration file (multipart upload). |
| `filename` | body | yes | string |  |

> **Unchecked result:** returns `200` / `success` whenever the playbook runs, even if the switch rejected the change. Check `raw`, or read the setting back afterwards.

Response: `status`, `filename`, `message`, `path`, `raw`

<sub>playbook: `tftp/tftpupload.yml`</sub>

### POST `/tftpexport/{hostname}`

Export the switch configuration to the TFTP server.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `tftp_server` | body | yes | string |  |
| `filename` | body | yes | string |  |

Response: `status`, `download_url`, `filename`, `message`, `raw`

<sub>playbook: `tftp/tftpexport.yml`</sub>

### POST `/tftp/schedule`

Save the automatic configuration backup schedule (stored in Telequill, no switch access).

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `backup_time` | body | yes | pattern `/^\d{2}:\d{2}$/` |  |
| `tftp_server_ip` | body | optional | string |  |
| `backup_retention_days` | body | yes | integer, 1 - ... |  |

Response: `status`, `backup_retention_days`, `backup_time`, `message`, `tftp_server_ip`

---

## 22. Spanning tree (STP)

The device **Configuration → L2 Configuration → STP Configuration** page uses these. Show endpoints are *cached*; each set endpoint checks the playbook result and refreshes the saved result before it returns. Changing the protocol or timers can briefly stop traffic on ports while spanning tree re-converges, and can take a minute or more.

### GET `/stp/global/show/{hostname}`

Current spanning tree state: protocol, bridge MAC, root bridge priority / MAC / timers, BPDU terminal, loop guard and the port state table.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `cached`, `data` - `protocol` (as the switch reports it, e.g. `RSTP(2004)`, `Rapid`), `mode` (`SSTP`, `RSTP`, `MSTP`, `PVST` or null), `bpdu_terminal`, `loop_guard`, `bridge_mac`, `root` {`priority`, `mac_address`, `hello_time`, `max_age`, `forward_delay`}, `ports` [{`interface`, `role`, `status`, `cost`, `priority`, `type`}]

<sub>cached · playbook: `stp/getpvst.yml`</sub>

### POST `/stp/global/set/{hostname}`

Set the spanning tree protocol and its settings. Each protocol runs its own playbook, and only that protocol's fields are used.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `protocol` | body | yes | string, one of `SSTP`, `RSTP`, `MSTP`, `PVST` |  |
| `bpdu_terminal` | body | yes | string, one of `Enable`, `Disable` |  |
| `loop_guard` | body | yes | string, one of `Enable`, `Disable` |  |
| `priority` | body | yes, only when `protocol` = `SSTP` / `RSTP` | integer, 0 - 61440, multiple of 4096 | Bridge priority |
| `hello_time` | body | yes, unless `protocol` = `PVST` | integer, 1 - 10 | Seconds |
| `max_age` | body | yes, unless `protocol` = `PVST` | integer, 6 - 40 | Seconds |
| `forward_delay` | body | yes, unless `protocol` = `PVST` | integer, 4 - 30 | Seconds |
| `network_diameter` | body | yes, only when `protocol` = `MSTP` | integer, 2 - 7 |  |
| `max_hops` | body | yes, only when `protocol` = `MSTP` | integer, 6 - 40 |  |
| `mst_compatibility` | body | yes, only when `protocol` = `MSTP` | string, one of `Enable`, `Disable` |  |

Timers must satisfy 2 × (forward_delay − 1) ≥ max_age ≥ 2 × (hello_time + 1), or the switch may reject them.

```json
{"protocol": "RSTP", "priority": 32768, "hello_time": 2, "max_age": 20, "forward_delay": 15, "bpdu_terminal": "Enable", "loop_guard": "Disable"}
```

Response: `status`, `message`

<sub>playbook: `stp/setstp.yml` (SSTP), `stp/setrstp.yml` (RSTP), `stp/setmstp.yml` (MSTP), `stp/setpvst.yml` (PVST), then `stp/getpvst.yml`</sub>

### GET `/stp/mst/show/{hostname}`

MST region (name, revision) and the VLANs mapped to each MST instance.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `cached`, `data` - `name`, `revision`, `instances` [{`instance_id`, `vlan_ids`}]

<sub>cached · playbook: `stp/getmstinstance.yml`</sub>

### POST `/stp/mst/set/{hostname}`

Configure the MST region and one MST instance (VLAN mapping, bridge priority, optional per-port cost and priority). Instances only take effect when the protocol is MSTP.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `mst_name` | body | yes | string, max 32 chars, pattern `/^[A-Za-z0-9_.-]+$/` | Region name |
| `mst_revision` | body | yes | integer, 0 - 65535 |  |
| `mst_instance` | body | yes | integer, 0 - 15 |  |
| `mst_vlan` | body | yes | string, pattern `/^[0-9]+(-[0-9]+)?(,[0-9]+(-[0-9]+)?)*$/` | e.g. `10,20-30` |
| `mst_priority` | body | yes | integer, 0 - 61440, multiple of 4096 | Bridge priority for this instance |
| `mst_ports` | body | yes | array (may be empty) | Per-port settings - see below |

Each item in `mst_ports`:

| Field | Required | Rules |
|---|---|---|
| `interface_type` | yes | one of `GigaEthernet`, `TGigaEthernet`, `Port-aggregator` |
| `interface_id` | yes | string, e.g. `0/1` |
| `cost` | yes | integer, 1 - 200000000 |
| `port_priority` | yes | integer, 0 - 240, multiple of 16 |

```json
{"mst_name": "C86BBCA0D0F4", "mst_revision": 5, "mst_instance": 2, "mst_vlan": "12", "mst_priority": 12288,
 "mst_ports": [{"interface_type": "GigaEthernet", "interface_id": "0/1", "cost": 34, "port_priority": 32}]}
```

Response: `status`, `message`

<sub>playbook: `stp/setmstinstance.yml`, then `stp/getmstinstance.yml`</sub>

### GET `/stp/port/show/{hostname}`

STP settings of every port.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `cached`, `data` [{`port` (e.g. `g0/1`), `interface` (e.g. `GigaEthernet0/1`), `protocol_status`, `priority`, `path_cost` (`0` = automatic), `edge_port`, `rstp_ring`, `bpdu_filter`}]

<sub>cached · playbook: `stp/getstpportconfiguration.yml`</sub>

### POST `/stp/port/set/{hostname}`

Change one port's STP settings (`set`, only the fields sent are applied) or remove all of them (`delete`).

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `operation` | body | yes | string, one of `set`, `delete` |  |
| `interface` | body | yes | string | e.g. `GigaEthernet0/1`, `g0/1`, `TGigaEthernet0/1`, `Port-aggregator1` |
| `protocol_status` | body | optional | string, one of `Enable`, `Disable` | Ignored on `delete` |
| `priority` | body | optional | integer, 0 - 240, multiple of 16 | Ignored on `delete` |
| `path_cost` | body | optional | integer, 1 - 65535 | Leave out to keep the current value |
| `edge_port` | body | optional | string, one of `Enable`, `Disable` | Ignored on `delete` |
| `rstp_ring` | body | optional | string, one of `Enable`, `Disable` | Ignored on `delete` |
| `bpdu_filter` | body | optional | string, one of `Enable`, `Disable` | Ignored on `delete` |

Response: `status`, `message`

<sub>playbook: `stp/setstpportconfiguration.yml`, then `stp/getstpportconfiguration.yml`</sub>

### GET `/stp/portguard/show/{hostname}`

BPDU guard and loop / root guard of every port.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `cached`, `data` [{`port`, `interface`, `bpdu_guard` (`Enable` / `Disable`), `guard` (`loop`, `root` or `none`)}]

<sub>cached · playbook: `stp/getportguardconfiguration.yml`</sub>

### POST `/stp/portguard/set/{hostname}`

Set BPDU guard and loop / root guard on one port. GigaEthernet and TGigaEthernet ports only.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `interface` | body | yes | string | e.g. `GigaEthernet0/1`, `g0/1`, `tg0/1` |
| `bpdu_guard` | body | yes | string, one of `Enable`, `Disable` |  |
| `guard` | body | yes | string, one of `loop`, `root`, `none` | Loop guard and root guard are mutually exclusive |

Response: `status`, `message`

<sub>playbook: `stp/setportguardconfiguration.yml`, then `stp/getportguardconfiguration.yml`</sub>

---
## 23. DHCP snooping

### GET `/dhcpsnooping/global/show/{hostname}`

DHCP snooping global settings.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

<sub>cached · playbook: `dhcp_snooping/dhcp_global_config_get.yml`</sub>

### POST `/dhcpsnooping/global/set/{hostname}`

Change DHCP snooping global settings. All four fields are required even when `dhcp_snooping` is `disable`.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `dhcp_snooping` | body | yes | string, one of `enable`, `disable` |  |
| `update_interval` | body | yes | integer, 2 - 1440 |  |
| `filter_upper_interface` | body | yes | string, one of `enable`, `disable` |  |
| `option82_format` | body | yes | string, one of `disable`, `snmp-ifindex`, `hn-type`, `cm-type`, `hw-type`, `manual` |  |

Response: `status`, `message`

<sub>playbook: `dhcp_snooping/dhcp_global_config_get.yml`, `dhcp_snooping/dhcp_global_config_set.yml`</sub>

### GET `/dhcpsnooping/vlan/show/{hostname}`

DHCP snooping, ARP inspection and verify-source VLANs. A field can be a list or range such as `10,20-30`, or empty.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

<sub>cached · playbook: `dhcp_snooping/dhcp_vlan_config_get.yml`</sub>

### POST `/dhcpsnooping/vlan/set/{hostname}`

Replace the three DHCP snooping VLANs. Send the current values (from the show endpoint) as `old_*` so they are removed first.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `new_dhcp_vlan` | body | yes | integer, 1 - 4094 |  |
| `new_arp_vlan` | body | yes | integer, 1 - 4094 |  |
| `new_verify_source_vlan` | body | yes | integer, 1 - 4094 |  |
| `old_dhcp_vlan` | body | optional | string, pattern `/^[0-9]+(-[0-9]+)?(,[0-9]+(-[0-9]+)?)*$/` | Current value from the show endpoint. |
| `old_arp_vlan` | body | optional | string, pattern `/^[0-9]+(-[0-9]+)?(,[0-9]+(-[0-9]+)?)*$/` | Current value from the show endpoint. |
| `old_verify_source_vlan` | body | optional | string, pattern `/^[0-9]+(-[0-9]+)?(,[0-9]+(-[0-9]+)?)*$/` | Current value from the show endpoint. |

Response: `status`, `message`

<sub>playbook: `dhcp_snooping/dhcp_vlan_config_get.yml`, `dhcp_snooping/dhcp_vlan_config_set.yml`</sub>

### GET `/dhcpsnooping/interface/show/{hostname}`

DHCP / ARP inspection / IP source trust of every port.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

<sub>cached · playbook: `dhcp_snooping/dhcp_interface_config_get.yml`</sub>

### POST `/dhcpsnooping/interface/set/{hostname}`

Set one port's three trust settings.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `port` | body | yes | string, pattern `/^t?g\d+\/\d+$/i` |  |
| `dhcp_trust` | body | yes | string, one of `Trust`, `Distrust` |  |
| `arp_trust` | body | yes | string, one of `Trust`, `Distrust` |  |
| `ip_source_trust` | body | yes | string, one of `Trust`, `Distrust` |  |

Response: `status`, `message`

<sub>playbook: `dhcp_snooping/dhcp_interface_config_get.yml`, `dhcp_snooping/dhcp_interface_config_set.yml`</sub>

### GET `/dhcpsnooping/option82/show/{hostname}`

Option82 settings of every port. The switch reports which options are set, not their values.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

<sub>cached · playbook: `dhcp_snooping/dhcp_option82_interface_get.yml`</sub>

### POST `/dhcpsnooping/option82/set/{hostname}`

Configure (`set`) or clear (`delete`) Option82 on one port. Only `g0/1` - `g0/10`.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `operation` | body | yes | string, one of `set`, `delete` |  |
| `port` | body | yes | string, pattern `/^g0\/([1-9]\|10)$/` |  |
| `drop` | body | yes, only when `operation` = `set` | string, one of `enable`, `disable` |  |
| `replace` | body | yes, only when `operation` = `set` | string, one of `enable`, `disable` |  |
| `transmit` | body | yes, only when `operation` = `set` | string, one of `enable`, `disable` |  |
| `circuit_id_type`, `remote_id_type`, `vendor_specific_type` | body | optional, only when `operation` = `set` | string, one of `string`, `hex` | Give each `_type` together with its `_value`, or leave both out. |
| `circuit_id_value`, `remote_id_value`, `vendor_specific_value` | body | optional, only when `operation` = `set` | string, max 64 chars, pattern `/^[A-Za-z0-9._:\/-]+$/` | Give each `_type` together with its `_value`, or leave both out. `hex` values must be byte pairs like `aa-bb-cc-dd`. |

Response: `status`, `message`

<sub>playbook: `dhcp_snooping/dhcp_option82_interface_delete.yml`, `dhcp_snooping/dhcp_option82_interface_get.yml`, `dhcp_snooping/dhcp_option82_interface_set.yml`</sub>

### GET `/dhcpsnooping/binding/show/{hostname}`

Static IP source bindings. Read live from the switch every time (no cache).

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `data`, `ip_address`, `mac_address`, `port`, `vlan_id`

<sub>playbook: `dhcp_snooping/dhcp_binding_port_list_get.yml`</sub>

### POST `/dhcpsnooping/binding/set/{hostname}`

Add or delete a static IP source binding.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |
| `operation` | body | yes | string, one of `add`, `delete` |  |
| `mac_address` | body | yes | string, pattern `/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/` |  |
| `ip_address` | body | yes | IPv4 address |  |
| `vlan_id` | body | yes | integer, 1 - 4094 |  |
| `port` | body | yes, only when `operation` = `add` | string, pattern `/^t?g\d+\/\d+$/i` |  |

Response: `status`, `message`

<sub>playbook: `dhcp_snooping/dhcp_binding_port_list_delete.yml`, `dhcp_snooping/dhcp_binding_port_list_set.yml`</sub>

### GET `/dhcpsnooping/binding/applied/show/{hostname}`

The switch's DHCP snooping binding table (learned and manual). Read-only, read live every time.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | Switch IP as in `hosts/<ip>.yml` |  |

Response: `status`, `data`

<sub>playbook: `dhcp_snooping/dhcp_binding_port_applied_list_get.yml`</sub>

---
## 24. Monitoring API (LibreNMS core)

These 132 endpoints read and manage the monitoring data in Telequill: devices, ports, graphs, alerts,
logs, routing tables, bills and so on. Most of them come from upstream LibreNMS; a few are custom to
Telequill (port SFP details, port descriptions, transceivers, template push, chatbot). They do not run
playbooks; they read and write the Telequill database (graphs are rendered from RRD files). Source:
`app/Api/Controllers/LegacyApiController.php` (which hands every call to the function of the same
name in `includes/html/api_functions.inc.php`), `app/Api/Controllers/TemplatePushApiController.php`
and `app/Http/Controllers/ChatBotController.php`, routes in `routes/api.php`.

**Base URL and auth.** Same as the automation API: `http://localhost:8000/api/v0`, with the token in
`X-Auth-Token: <token>` (or `Authorization: Bearer <token>`). Unlike the automation API, the token
does not have to belong to an admin. What it needs depends on the route group:

| Access | Who can call it | Routes |
|---|---|---|
| `global-read` | A user with global read access (admin or global-read role). Others get `403`. | `can:global-read` group |
| `admin` | An admin user. Others get `403`. | `can:admin` group |
| `per-device` / `per-port` / `per-bill` | Any valid token. The controller checks the device, port or bill: global-read users see everything; other users only what is assigned to them. A single object they can't see returns `403` (`Insufficient permissions to access this device`); lists are filtered. | Routes with no middleware |
| `any token` | Any valid token, no further check. | `/system`, `/`, and a few routes flagged below |

A missing or wrong token gets `401`.

**`{hostname}`** in these paths is the device's hostname as added to Telequill, or its numeric
`device_id` (all digits are read as an ID). `{portid}` is the numeric `port_id`.

**Request bodies** are JSON (`Content-Type: application/json`). Most of these endpoints read the raw
JSON body, so form data is ignored.

**Success** - HTTP `200` (some create calls return `201`), `status` is `ok`, not `success` as in the
automation API. The data comes under a key named for the endpoint (`devices`, `ports`, `alerts` ...),
and `count` is added when that data is an array:

```json
{"status": "ok", "message": "...", "count": 2, "devices": [{...}, {...}]}
```

`message` only appears when the endpoint sets one. Calls that change something without returning
data send just `{"status": "ok", "message": "..."}`.

**Errors** - `{"status": "error", "message": "..."}` with a `4xx` code (`400` bad input, `403` no
permission, `404` not found, `422` validation). Some legacy endpoints use `500` for bad input too
(noted where it matters). Unknown paths under `/api/v0` return `404` with
`This API route doesn't exist.`

A few endpoints don't use the envelope (`/`, `/oxidized`, raw graph images, `/chatbot/message`);
each one says so below.

---

## 24.1 System

### GET `/system`

Telequill / LibreNMS version information.

No parameters.

Response: `status`, `count`, `system` (one object: `local_ver`, `local_sha`, `local_date`, `local_branch`, `db_schema`, `php_ver`, `python_ver`, `database_ver`, `rrdtool_ver`, `netsnmp_ver`)

<sub>access: any token · `server_info`</sub>

### GET `/`

Every named API route with its full URL.

No parameters.

Response: a plain JSON object (no envelope), `{"<route name>": "<url>", ...}`, sorted by name.

<sub>access: any token · (unnamed route)</sub>

---

## 24.2 Devices

### GET `/devices`

List devices, optionally filtered.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `type` | query | optional | one of `all`, `active`, `ignored`, `up`, `down`, `disabled`, `device_id`, `hostname`, `sysName`, `display`, `os`, `type`, `location`, `location_id`, `mac`, `ipv4`, `ipv6`, `serial`, `version`, `hardware`, `features` | Default `all`. An unknown value lists all. |
| `query` | query | with search types | string | Value to search for. `hostname`, `location`, `display`, `serial`, `version`, `hardware`, `features` match with LIKE; the rest are exact. |
| `order` | query | optional | `<column>` or `<column> asc\|desc`, letters and `_` only | Default `hostname ASC`. |

Response: `status`, `count`, `devices` (device rows plus `dependency_parent_id`, `dependency_parent_hostname`, `location`, `lat`, `lng`, and `parent_id` for VMs; the `mac`/`ipv4`/`ipv6` searches add the matching port's columns)

<sub>access: per-device (filtered) · `list_devices`</sub>

### GET `/devices/{hostname}`

One device.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |

Response: `status`, `count`, `devices` (array with one device row, plus `parent_id` for a VM)

<sub>access: per-device · `get_device`</sub>

### POST `/devices`

Add a device. Without SNMP credentials, each configured credential is tried in turn.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | body | yes | string | Hostname or IP. |
| `display` | body | optional | string | Display name; may use `{{ $hostname }}`, `{{ $sysName }}`, `{{ $sysName_fallback }}`, `{{ $ip }}`. |
| `overwrite_ip` | body | optional | IP | IP used to reach the device instead of resolving `hostname`. |
| `snmpver` (or `version`) | body | optional | `v1`, `v2c`, `v3` |  |
| `port` | body | optional | integer | SNMP port. |
| `transport` | body | optional | `udp`, `tcp`, `udp6`, `tcp6` |  |
| `community` | body | optional | string | SNMP v1/v2c. |
| `authlevel`, `authname`, `authpass`, `authalgo`, `cryptopass`, `cryptoalgo` | body | optional | string | SNMP v3. `authlevel`: `noAuthNoPriv`, `authNoPriv`, `authPriv`. |
| `port_association_mode` | body | optional | `ifIndex`, `ifName`, `ifDescr`, `ifAlias` |  |
| `poller_group` | body | optional | integer | Default `0`. |
| `location` / `location_id` | body | optional | string / integer | `location` is created if it doesn't exist. |
| `override_sysLocation` | body | optional | boolean |  |
| `force_add` | body | optional | boolean | Skip reachability and duplicate checks (SNMP credentials then required). |
| `ping_fallback` | body | optional | boolean | Add as ping-only if SNMP fails. |
| `snmp_disable` | body | optional | boolean | ICMP-only device. |
| `os`, `sysName`, `hardware` | body | optional | string | Only with `snmp_disable`. `os` defaults to `ping`. |

```json
{"hostname": "192.168.2.245", "snmpver": "v2c", "community": "public", "location": "Lab"}
```

Response: `status`, `message`, `count`, `devices` (the new device). Failures return `500` with the reason.

<sub>access: admin · `add_device`</sub>

### DELETE `/devices/{hostname}`

Delete a device and its data.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |

Response: `status`, `message`, `count`, `devices` (the deleted device's details)

<sub>access: admin · `del_device`</sub>

### PATCH `/devices/{hostname}`

Update one or more columns of the device row.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |
| `field` | body | yes | string or array of strings | Column name(s). `device_id` and `hostname` are refused; `location` is turned into `location_id`. |
| `data` | body | yes | value or array | New value(s), same count and order as `field`. |

```json
{"field": ["notes", "purpose"], "data": ["Core switch", "Uplink"]}
```

Response: `status`, `message`

<sub>access: admin · `update_device_field`</sub>

### PATCH `/devices/{hostname}/rename/{new_hostname}`

Rename a device. No body.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |
| `new_hostname` | path | yes | string | Must not already exist (`500` otherwise). |

Response: `status`, `message`

<sub>access: admin · `rename_device`</sub>

### GET `/devices/{hostname}/discover`

Queue the device for rediscovery on the next discovery run.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |

> **Not permission-checked:** any valid token can trigger it for any device.

Response: `status`, `count`, `result` (`status`, `message`)

<sub>access: any token · `trigger_device_discovery`</sub>

### GET `/devices/{hostname}/availability`

Availability percentages per period.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |

Response: `status`, `count`, `availability` (`duration` in seconds, `availability_perc`)

<sub>access: per-device · `device_availability`</sub>

### GET `/devices/{hostname}/outages`

Outage history, newest first.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |

Response: `status`, `count`, `outages` (`going_down`, `up_again`, Unix timestamps)

<sub>access: per-device · `device_outages`</sub>

### GET `/devices/{hostname}/maintenance`

Whether the device is in an active maintenance window.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |

Response: `status`, `is_under_maintenance` (boolean)

<sub>access: per-device · `device_under_maintenance`</sub>

### POST `/devices/{hostname}/maintenance`

Put the device into a one-off maintenance window.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |
| `duration` | body | yes | `H:i`, e.g. `2:00` | A value without `:` creates the schedule with no end time. |
| `start` | body | optional | `Y-m-d H:i:s` | Default now. |
| `title` | body | optional | string | Default the device display name. |
| `notes` | body | optional | string | Also appended to the device notes if the user has that preference set. |
| `behavior` | body | optional | integer: `1` skip alerts, `2` mute alerts, `3` run alerts | Default from `alert.scheduled_maintenance_default_behavior`. |

```json
{"title": "Firmware upgrade", "start": "2026-10-23 22:00:00", "duration": "2:00", "behavior": 1}
```

Response: `201`, `status`, `message`

<sub>access: admin · `maintenance_device`</sub>

### POST `/devices/{id}/parents`

Set the device's parent devices (dependencies). Replaces the existing parents.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `id` | path | yes | hostname or device_id |  |
| `parent_ids` | body | yes | string, comma-separated IDs or hostnames | A device can't be its own parent. |

```json
{"parent_ids": "12,core-sw1"}
```

Response: `201`, `status`, `message`

<sub>access: admin · `add_parents_to_host`</sub>

### DELETE `/devices/{id}/parents`

Remove parent devices.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `id` | path | yes | hostname or device_id |  |
| `parent_ids` | body | optional | string, comma-separated IDs or hostnames | Omit to remove all parents. |

Response: `201`, `status`, `message`

<sub>access: admin · `del_parents_from_host`</sub>

### GET `/devices/{hostname}/components`

Components of a device. Every query parameter is applied as an exact-match filter on the component.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |
| `type`, `id`, `status`, `disabled`, `ignore` | query | optional |  | Exact match. |
| `label` | query | optional |  | Matched with LIKE. |

Response: `status`, `count`, `components` (object keyed by component ID)

<sub>access: per-device · `get_components`</sub>

### POST `/devices/{hostname}/components/{type}`

Create an empty component of the given type. No body.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |
| `type` | path | yes | string | Component type. |

Response: `status`, `count`, `components` (the new component keyed by its ID)

<sub>access: admin · `add_components`</sub>

### PUT `/devices/{hostname}/components`

Save component settings. The body is the components object as returned by the GET, keyed by ID.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |
| `<component_id>` | body | yes | object | Component fields to store. |

```json
{"4": {"type": "TESTING", "label": "Comp4", "status": 0, "ignore": 0, "disabled": 0, "error": ""}}
```

Response: `status`

<sub>access: admin · `edit_components`</sub>

### DELETE `/devices/{hostname}/components/{component}`

Delete a component.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id | Not used; the component ID alone identifies it. |
| `component` | path | yes | integer | Component ID. |

Response: `status`

<sub>access: admin · `delete_components`</sub>

---

## 24.3 Device graphs and health

Graph endpoints return the image itself (`Content-Type: image/png` or `image/svg+xml`) unless
`output=base64`. All graph endpoints in this section and in 24.4, 24.5, 24.12 and 24.13 accept these
query parameters:

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `from`, `to` | query | optional | RRDtool time (Unix timestamp or e.g. `-1d`) | Graph start and end. |
| `width`, `height` | query | optional | integer | Default `1075` x `300`. |
| `output` | query | optional | `base64` | Return `{"status":"ok","image":{"image":"<base64>","content-type":"..."}}` instead of the raw image. |
| `legend`, `title`, `graph_title`, `absolute`, `font`, `bg`, `bbg`, `nototal`, `nodetails`, `noagg`, `inverse`, `previous`, `duration` | query | optional |  | Passed to the graph renderer. |

A graph that fails to render returns `500` with the RRDtool error.

### GET `/devices/{hostname}/graphs`

Graph types available for a device.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |

Response: `status`, `count`, `graphs` (`desc`, `name`; use `name` as `{type}` below)

<sub>access: per-device · `get_graphs`</sub>

### GET `/devices/{hostname}/{type}`

A device graph, e.g. `device_uptime`, `device_poller_perf`, `device_icmp_perf`, `device_processor`.
Graph query parameters above apply.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |
| `type` | path | yes | graph name from `/devices/{hostname}/graphs` |  |

Response: image (or base64 envelope)

<sub>access: per-device · `get_graph_generic_by_hostname`</sub>

### GET `/devices/{hostname}/health/{type?}/{sensor_id?}`

Health sensors of a device. With no `type`: the health graph types available. With `type`: the
sensors of that class. With `type` and `sensor_id`: that sensor's full row.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |
| `type` | path | optional | sensor class, e.g. `device_temperature` or `temperature` | `device_` prefix is stripped. |
| `sensor_id` | path | optional | integer |  |

Response: `status`, `count`, `graphs` (`desc`, `name` / `sensor_id`, `desc` / sensor rows)

<sub>access: per-device · `list_available_health_graphs`</sub>

### GET `/devices/{hostname}/wireless/{type?}/{sensor_id?}`

Same as the health listing, for wireless sensors.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |
| `type` | path | optional | `device_wireless_<class>`, e.g. `device_wireless_clients` | The class is the third `_`-separated part. |
| `sensor_id` | path | optional | integer |  |

Response: `status`, `count`, `graphs`

<sub>access: per-device · `list_available_wireless_graphs`</sub>

### GET `/devices/{hostname}/graphs/health/{type}/{sensor_id?}`

A health graph. Without `sensor_id`, all sensors of the class in one graph. Graph query parameters
apply.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |
| `type` | path | yes | `name` from the health listing, e.g. `device_temperature` |  |
| `sensor_id` | path | optional | integer | One sensor only. |

Response: image (or base64 envelope)

<sub>access: per-device · `get_health_graph`</sub>

### GET `/devices/{hostname}/graphs/wireless/{type}/{sensor_id?}`

A wireless sensor graph. Graph query parameters apply.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |
| `type` | path | yes | `name` from the wireless listing, e.g. `device_wireless_clients` |  |
| `sensor_id` | path | optional | integer | One sensor only. |

Response: image (or base64 envelope)

<sub>access: per-device · `get_wireless_graph`</sub>

---

## 24.4 Ports

### GET `/ports`

All ports the user can see.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `columns` | query | optional | comma-separated `ports` columns | Default `port_id,ifName`. Unknown columns are an error. `hostname` is always added. |

Response: `status`, `count`, `ports`

<sub>access: per-port (filtered) · `get_all_ports`</sub>

### GET `/ports/{portid}`

One port with its device and, when an SFP is fitted, the SFP readings. Telequill-specific: pass `all`
as `portid` to get every port in the same format.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `portid` | path | yes | integer, or `all` |  |
| `with` | query | optional | `vlans` or `device` | Also load that relation. |

> **`all` is not permission-checked:** it returns every port to any valid token.

Response: `status`, `port` (or `count`, `ports` for `all`): `hostname`, `device`, all port columns, and
`sfp` (`connected`, `vendor`, `part-number`, `serial`, `wavelength`, `rx_power`, `tx_power`,
`temperature`, `voltage`, `current`, `los_status`) when transceiver sensors exist for the port's
`ifIndex`

<sub>access: per-port · `get_port_info`</sub>

### GET `/ports/{portid}/fdb`

MAC addresses learned on a port.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `portid` | path | yes | integer |  |

Response: `status`, `count`, `macs` (`ports_fdb` rows). `404` when empty.

<sub>access: per-port · `get_port_fdb`</sub>

### GET `/ports/{portid}/ip`

IPv4 and IPv6 addresses on a port.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `portid` | path | yes | integer |  |

Response: `status`, `count`, `addresses`. `404` when none.

<sub>access: per-port · `get_port_ip_info`</sub>

### GET `/ports/{portid}/description`

The port's description (`ifAlias`).

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `portid` | path | yes | integer | A port you can't see returns `400 Invalid port ID.` |

Response: `status`, `port_description`

<sub>access: per-port · `get_port_description`</sub>

### PATCH `/ports/{portid}/description`

Set the port's description in Telequill and stop the poller overwriting it. An empty description
clears it and lets the poller set it again. This changes the database only, not the switch.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `portid` | path | yes | integer | A port you can't see returns `400 Invalid port ID.` |
| `description` | body | yes | string | Empty to clear. |

```json
{"description": "Uplink to core"}
```

> **Not admin-only:** any user who can see the port can change its description.

Response: `status`, `message`

<sub>access: per-port · `update_port_description`</sub>

### GET `/ports/search/{field}/{search?}`

Search ports by text. With only one path segment (`/ports/search/{search}`), it searches
`ifAlias`, `ifDescr` and `ifName`.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `field` | path | yes | comma-separated `ports` columns | Columns to search. |
| `search` | path | optional | string, may contain `/` | Matched with LIKE `%search%`. |
| `columns` | query | optional | comma-separated `ports` columns | Default `device_id,port_id,ifIndex,ifName,ifAlias`. |

Response: `status`, `count`, `ports`. `404` when none.

<sub>access: per-port (filtered) · `search_ports`</sub>

### GET `/ports/mac/{search}`

Ports where a MAC address was learned (FDB), the port with the fewest FDB entries first (most likely
the access port).

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `search` | path | yes | MAC in any common format | `422` if not a valid MAC. |
| `filter` | query | optional | `first` | Return only the most likely port. |

> **Not permission-checked:** results are not filtered by the user's port access.

Response: `status`, `count`, `ports` (with `fdb_entries_count`). `404` when not found.

<sub>access: any token · `search_mac`</sub>

### GET `/devices/{hostname}/ports`

A device's ports, by `ifIndex`.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |
| `columns` | query | optional | comma-separated `ports` columns | Default `port_id,ifName`. |

Response: `status`, `count`, `ports`. `404` when none.

<sub>access: per-port (filtered) · `get_port_graphs`</sub>

### GET `/devices/{hostname}/ports/{ifname}`

Current stats of one port, by `ifName`. URL-encode names with `/` (e.g. `GigaEthernet0%2F1`).

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |
| `ifname` | path | yes | `ifName` |  |
| `columns` | query | optional | comma-separated | Only return these keys. |

Response: `status`, `count`, `port` (port row plus `in_rate`, `out_rate`, `in_perc`, `out_perc`, `in_pps`, `out_pps`)

<sub>access: per-port · `get_port_stats_by_port_hostname`</sub>

### GET `/devices/{hostname}/ports/{ifname}/{type}`

A port graph, e.g. `port_bits`, `port_upkts`, `port_errors`. Graph query parameters apply.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |
| `ifname` | path | yes | `ifName` (URL-encoded) |  |
| `type` | path | yes | port graph type |  |
| `ifDescr` | query | optional | boolean | Look the port up by `ifDescr` instead of `ifName`. |

Response: image (or base64 envelope)

<sub>access: per-port · `get_graph_by_port_hostname`</sub>

### GET `/devices/{hostname}/port_stack`

Port stack mappings (e.g. physical members of a port-channel).

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id | The upstream `valid_mappings` filter is not implemented here. |

Response: `status`, `count`, `mappings`

<sub>access: per-device · `get_port_stack`</sub>

### PATCH `/devices/{hostname}/port/{portid}`

Set the notes of a port.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |
| `portid` | path | yes | integer |  |
| `notes` | body | yes | string |  |

```json
{"notes": "Patch panel A-12"}
```

Response: `status`, `message`

<sub>access: admin · `update_device_port_notes`</sub>

---

## 24.5 Port groups

### GET `/port_groups`

All port groups.

No parameters.

Response: `status`, `message`, `count`, `groups` (`id`, `name`, `desc`). `404` when none.

<sub>access: global-read · `get_port_groups`</sub>

### GET `/port_groups/{name}`

Ports in a port group.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `name` | path | yes | group name or ID |  |
| `full` | query | optional | any value | Return full port rows instead of only `port_id`. |

Response: `status`, `count`, `ports`. `404` when the group or its ports don't exist.

<sub>access: global-read · `get_ports_by_group`</sub>

### POST `/port_groups`

Create a port group.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `name` | body | yes | string, unique |  |
| `desc` | body | optional | string |  |

```json
{"name": "Uplinks", "desc": "Core uplink ports"}
```

Response: `201`, `status`, `message`, `id`

<sub>access: admin · `add_port_group`</sub>

### POST `/port_groups/{port_group_id}/assign`

Add ports to a port group.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `port_group_id` | path | yes | integer |  |
| `port_ids` | body | yes | array of port IDs |  |

```json
{"port_ids": [101, 102]}
```

Response: `status` and a single key whose name is the message (`"Port Ids 101, 102 have been added to Port Group Id 3": 200`)

<sub>access: admin · `assign_port_group`</sub>

### POST `/port_groups/{port_group_id}/remove`

Remove ports from a port group.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `port_group_id` | path | yes | integer |  |
| `port_ids` | body | yes | array of port IDs |  |

```json
{"port_ids": [101]}
```

Response: as for assign, with `have been removed from`.

<sub>access: admin · `remove_port_group`</sub>

### GET `/portgroups/{group}`

Combined traffic graph of all ports with the given port description type (e.g. `transit`,
`peering`; comma-separate several). Graph query parameters apply.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `group` | path | yes | comma-separated port types | Port types from the description parser, not port group names. |

Response: image (or base64 envelope)

<sub>access: global-read · `get_graph_by_portgroup`</sub>

### GET `/portgroups/multiport/bits/{id}`

Combined traffic graph of a list of ports. Graph query parameters apply.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `id` | path | yes | comma-separated port IDs |  |

Response: image (or base64 envelope)

<sub>access: global-read · `get_graph_by_portgroup_multiport_bits`</sub>

---

## 24.6 Device groups

### GET `/devicegroups`

All device groups.

No parameters.

Response: `status`, `message`, `count`, `groups`. `404` when none.

<sub>access: global-read · `get_device_groups`</sub>

### GET `/devicegroups/{name}`

Devices in a device group.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `name` | path | yes | group name or ID |  |
| `full` | query | optional | any value | Return full device rows instead of only `device_id`. |

Response: `status`, `count`, `devices`. `404` when the group or its devices don't exist.

<sub>access: global-read · `get_devices_by_group`</sub>

### GET `/devices/{hostname}/groups`

Device groups a device belongs to.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |

Response: `status`, `message`, `count`, `groups`. `404` when none.

<sub>access: per-device (filtered) · `get_device_groups_device`</sub>

### POST `/devicegroups`

Create a device group.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `name` | body | yes | string, unique |  |
| `type` | body | yes | `static` or `dynamic` |  |
| `desc` | body | optional | string |  |
| `devices` | body | yes, if `static` | array of device IDs (integers) |  |
| `rules` | body | yes, if `dynamic` | JSON **string** in the query-builder format | `500` if it can't be parsed. |

```json
{"name": "Lab switches", "type": "static", "desc": "Bench", "devices": [1, 2, 3]}
```

Response: `201`, `status`, `message`, `id`. Validation errors are `422`.

<sub>access: admin · `add_device_group`</sub>

### PATCH `/devicegroups/{name}`

Update a device group. Send only the fields to change.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `name` | path | yes | group name or ID |  |
| `name` | body | optional | string, unique | New name. |
| `desc` | body | optional | string |  |
| `type` | body | optional | `static` or `dynamic` |  |
| `devices` | body | yes, if `type` = `static` | array of device IDs | Replaces the members. |
| `rules` | body | yes, if `type` = `dynamic` | JSON string |  |

Response: `status`, `message`

<sub>access: admin · `update_device_group`</sub>

### DELETE `/devicegroups/{name}`

Delete a device group.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `name` | path | yes | group name or ID |  |

Response: `status`, `message`

<sub>access: admin · `delete_device_group`</sub>

### POST `/devicegroups/{name}/devices`

Add devices to a static device group.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `name` | path | yes | group name or ID | `422` for a dynamic group. |
| `devices` | body | yes | array of device IDs (integers) |  |

```json
{"devices": [4, 5]}
```

Response: `status`, `message`

<sub>access: admin · `update_device_group_add_devices`</sub>

### DELETE `/devicegroups/{name}/devices`

Remove devices from a static device group.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `name` | path | yes | group name or ID | `422` for a dynamic group. |
| `devices` | body | yes | array of device IDs (integers) |  |

Response: `status`, `message`

<sub>access: admin · `update_device_group_remove_devices`</sub>

### POST `/devicegroups/{name}/maintenance`

Put every device of the group into a one-off maintenance window.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `name` | path | yes | group name or ID |  |
| `duration` | body | yes | `H:i`, e.g. `2:00` | A value without `:` creates the schedule with no end time. |
| `start` | body | optional | `Y-m-d H:i:s` | Default now. |
| `title` | body | optional | string | Default the group name. |
| `notes` | body | optional | string |  |
| `behavior` | body | optional | integer: `1` skip alerts, `2` mute alerts, `3` run alerts |  |

```json
{"title": "Ring maintenance", "duration": "1:30"}
```

Response: `201`, `status`, `message`

<sub>access: admin · `maintenance_devicegroup`</sub>

---

## 24.7 Alerts and alert rules

### GET `/alerts`

Current alerts, newest first.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `state` | query | optional | comma-separated: `0` ok, `1` alert, `2` acknowledged | Default `1`. |
| `severity` | query | optional | `ok`, `warning`, `critical` |  |
| `alert_rule` | query | optional | integer | Rule ID. |
| `order` | query | optional | `<alerts column> asc\|desc` (lowercase) | Default `timestamp desc`. |

Response: `status`, `count`, `alerts` (alert row plus `hostname`, `severity`, `name`, `proc`, `notes` of the rule)

<sub>access: global-read · `list_alerts`</sub>

### GET `/alerts/{id}`

One alert. Takes the same query parameters; note the default `state` filter still applies, so pass
`state=0,1,2` to find an alert in any state.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `id` | path | yes | integer |  |

Response: `status`, `count`, `alerts`

<sub>access: global-read · `get_alert`</sub>

### PUT `/alerts/{id}`

Acknowledge an alert.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `id` | path | yes | integer |  |
| `note` | body | optional | string | Appended to the alert's notes. |
| `until_clear` | body | optional | boolean | `false`: re-alert if the alert worsens or improves. |

```json
{"note": "Working on it", "until_clear": false}
```

Response: `status`, `message` (`No Alert by that ID` is also `200`)

<sub>access: admin · `ack_alert`</sub>

### PUT `/alerts/unmute/{id}`

Unacknowledge (unmute) an alert.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `id` | path | yes | integer |  |
| `note` | body | optional | string | Appended to the alert's notes. |

Response: `status`, `message`

<sub>access: admin · `unmute_alert`</sub>

### GET `/rules`

All alert rules.

No parameters.

Response: `status`, `count`, `rules` (rule row plus `devices`, `groups`, `locations` as ID arrays)

<sub>access: global-read · `list_alert_rules`</sub>

### GET `/rules/{id}`

One alert rule.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `id` | path | yes | integer |  |

Response: `status`, `count`, `rules`

<sub>access: global-read · `get_alert_rule`</sub>

### POST `/rules`

Create an alert rule.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `name` | body | yes | string, unique |  |
| `devices` | body | yes | array of device IDs/hostnames, or `[-1]` for all devices |  |
| `builder` | body | yes (or `rule`) | query-builder JSON, object or string | The rule condition. |
| `severity` | body | yes | `ok`, `warning`, `critical` |  |
| `groups` | body | optional | array of device group IDs |  |
| `locations` | body | optional | array of location IDs |  |
| `disabled` | body | optional | `0` or `1` | Default `0`. |
| `count` | body | optional | integer | Max notifications. |
| `delay` | body | optional | seconds, or `5 m` / `5 h` / `5 d` | Wait before alerting. |
| `interval` | body | optional | same as `delay` | Re-notify interval, `0` = once. |
| `mute` | body | optional | `1` to mute | Show in the UI but don't notify. |
| `override_query` | body | optional | `on` or `true` | Use `adv_query` instead of `builder`. |
| `adv_query` | body | optional | SQL | Only with `override_query`. |
| `notes` | body | optional | string |  |

```json
{"name": "Device down", "devices": [-1], "severity": "critical", "delay": "5 m", "interval": "1 h",
 "builder": {"condition": "AND", "rules": [{"id": "devices.status", "field": "devices.status", "type": "boolean", "input": "radio", "operator": "equal", "value": "0"}], "valid": true}}
```

Response: `status`. A duplicate name or unparsable rule returns `500`.

<sub>access: admin · `add_rule`</sub>

### PUT `/rules`

Update an alert rule. Same body as POST plus `rule_id`; `devices` may then be empty. Every field is
rewritten, so send the full rule.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `rule_id` | body | yes | integer |  |
| (POST fields) | body |  |  | As for POST `/rules`. |

Response: `status`

<sub>access: admin · `edit_rule`</sub>

### DELETE `/rules/{id}`

Delete an alert rule.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `id` | path | yes | integer |  |

Response: `status`, `message` (`No alert rule by that ID` is also `200`)

<sub>access: admin · `delete_rule`</sub>

---

## 24.8 Logs

All four log endpoints take the same query parameters:

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `start` | query | optional | integer | Offset, default `0`. |
| `limit` | query | optional | integer | Default `50`. |
| `from`, `to` | query | optional | log ID (number) or datetime `Y-m-d H:i:s` | Numbers filter on the log ID, anything else on the timestamp. |
| `sortorder` | query | optional | `ASC` or `DESC` | Default `ASC` (oldest first). |

Response: `status`, `count`, `total` (all matching rows), `logs` (each row includes `hostname` and
`sysName` except for the auth log)

### GET `/logs/eventlog/{hostname?}`

Event log, optionally for one device.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | optional | hostname or device_id |  |

<sub>access: global-read · `list_eventlog`</sub>

### GET `/logs/syslog/{hostname?}`

Syslog messages, optionally for one device.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | optional | hostname or device_id |  |

<sub>access: global-read · `list_syslog`</sub>

### GET `/logs/alertlog/{hostname?}`

Alert log, optionally for one device. `details` is returned decoded.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | optional | hostname or device_id |  |

<sub>access: global-read · `list_alertlog`</sub>

### GET `/logs/authlog`

Web login log.

No path parameters.

<sub>access: global-read · `list_authlog`</sub>

---

## 24.9 Inventory and transceivers

### GET `/inventory/{hostname}`

Physical inventory (ENTITY-MIB) entries of a device, top level by default.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |
| `entPhysicalClass` | query | optional | e.g. `chassis`, `port`, `module` |  |
| `entPhysicalContainedIn` | query | optional | integer | Parent index; default `0` (top level). |

Response: `status`, `count`, `inventory` (`entPhysical` rows)

<sub>access: per-device · `get_inventory`</sub>

### GET `/inventory/{hostname}/all`

All inventory entries of a device, unfiltered.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |

Response: `status`, `count`, `inventory`

<sub>access: per-device · `get_inventory_for_device`</sub>

### GET `/devices/{hostname}/transceivers`

Transceivers of a device.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id | An unknown device returns an empty list, not `404`. |

Response: `status`, `count`, `transceivers` (`id`, `device_id`, `port_id`, `index`, `type`, `vendor`, `oui`, `model`, `revision`, `serial`, `date`, `ddm`, `encoding`, `cable`, `distance`, `wavelength`, `connector`, `channels`, timestamps)

<sub>access: per-port (filtered) · `get_transceivers`</sub>

### GET `/ports/{portid}/transceiver`

Transceiver of one port.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `portid` | path | yes | integer |  |

Response: `status`, `count`, `transceivers` (same fields as above)

<sub>access: per-port · `get_port_transceiver`</sub>

### PATCH `/ports/transceiver/metric/{metric}`

Meant to update the thresholds of a transceiver metric.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `metric` | path | yes |  |  |

> **Not implemented:** the route exists, but there is no `update_transceiver_metric_thresholds` function in `api_functions.inc.php`, so this endpoint returns a server error.

<sub>access: per-port · `update_transceiver_metric_thresholds`</sub>

---

## 24.10 Resources (ARP, IP, FDB, NAC, links, VLANs, sensors, locations)

### GET `/resources/ip/arp/{query}/{cidr?}`

ARP table lookup.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `query` | path | yes | IPv4, MAC, network address, or `all` |  |
| `cidr` | path | optional | prefix length, e.g. `24` | With `query` as the network address. |
| `device` | query | optional | hostname or device_id | Only with `query` = `all`: that device's ARP table. |

Response: `status`, `count`, `arp` (`ipv4_mac` rows)

<sub>access: global-read · `list_arp`</sub>

### GET `/resources/ip/addresses/{address_family?}`

All IP addresses.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `address_family` | path | optional | `ipv4` or `ipv6` | Default both. Any other value returns nothing. |

Response: `status`, `count`, `ip_addresses`. `404` when none.

<sub>access: global-read · `list_ip_addresses`</sub>

### GET `/resources/ip/networks/{address_family?}`

All IP networks.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `address_family` | path | optional | `ipv4` or `ipv6` | Default both. |

Response: `status`, `count`, `ip_networks`. `404` when none.

<sub>access: global-read · `list_ip_networks`</sub>

### GET `/resources/ip/networks/{id}/ip`

IP addresses in one network.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `id` | path | yes | network ID | Matches IPv4 and IPv6 network IDs. |

Response: `status`, `count`, `addresses`. `404` when empty.

<sub>access: global-read · `get_network_ip_addresses`</sub>

### GET `/devices/{hostname}/ip`

IPv4 and IPv6 addresses on a device's ports.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |

Response: `status`, `count`, `addresses`. `404` when none.

<sub>access: per-device · `get_ip_addresses`</sub>

### GET `/resources/fdb`

The whole forwarding database (MAC table).

No parameters.

Response: `status`, `count`, `ports_fdb`. `404` when empty.

<sub>access: per-device (filtered) · `list_fdb`</sub>

### GET `/resources/fdb/{mac}`

FDB entries for one MAC.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `mac` | path | yes | 12 hex digits, no separators, lowercase | Compared as stored. |

Response: `status`, `count`, `ports_fdb`. `404` when not found.

<sub>access: per-device (filtered) · `list_fdb_mac`</sub>

### GET `/resources/fdb/{mac}/detail`

Where a MAC was seen, with device and port names, newest first (max 1000).

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `mac` | path | yes | MAC in any common format | `422` if invalid. |

Response: `status`, `count`, `mac`, `mac_oui` (vendor), `ports_fdb` (`hostname`, `sysName`, `ifName`, `ifAlias`, `ifDescr`, `updated_at`, `last_seen`)

<sub>access: per-device (filtered) · `list_fdb_detail`</sub>

### GET `/devices/{hostname}/fdb`

A device's FDB entries.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |

Response: `status`, `count`, `ports_fdb`

<sub>access: per-device · `get_fdb`</sub>

### GET `/resources/nac`

All NAC (802.1X / MAB) sessions.

No parameters.

Response: `status`, `count`, `ports_nac`. `404` when none.

<sub>access: per-device (filtered) · `list_nac`</sub>

### GET `/resources/nac/{mac}`

NAC sessions for one MAC.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `mac` | path | yes | MAC as stored (12 hex digits) |  |

Response: `status`, `count`, `ports_nac`. `404` when not found.

<sub>access: per-device (filtered) · `list_nac_mac`</sub>

### GET `/devices/{hostname}/nac`

A device's NAC sessions.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |

> **Permission bug:** the function checks permission on an undefined device ID, so users without global read get `403` even for their own devices.

Response: `status`, `count`, `ports_nac`

<sub>access: per-device · `get_nac`</sub>

### GET `/resources/links`

All discovered neighbour links (LLDP/CDP etc.).

No parameters.

Response: `status`, `count`, `links`. `404` when none.

<sub>access: per-device (filtered) · `list_links`</sub>

### GET `/resources/links/{id}`

One link.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `id` | path | yes | integer |  |

Response: `status`, `count`, `link`

<sub>access: global-read · `get_link`</sub>

### GET `/devices/{hostname}/links`

Links whose local side is this device.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |

Response: `status`, `count`, `links`. `404` when none.

<sub>access: per-device · `list_links_device`</sub>

### GET `/resources/vlans`

All VLANs known to Telequill.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | query | optional | hostname or device_id | One device only. |

Response: `status`, `count`, `vlans`. `404` when none.

<sub>access: per-device (filtered) · `list_vlans`</sub>

### GET `/devices/{hostname}/vlans`

A device's VLANs.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |

Response: `status`, `count`, `vlans` (`vlan_vlan`, `vlan_domain`, `vlan_name`, `vlan_type`, `vlan_state`)

<sub>access: per-device · `get_vlans`</sub>

### GET `/resources/sensors`

All health sensors.

No parameters.

Response: `status`, `count`, `sensors`. `404` when none.

<sub>access: per-device (filtered) · `list_sensors`</sub>

### GET `/resources/locations`

All locations.

No parameters.

Response: `status`, `count`, `locations`. `404` when none.

<sub>access: global-read · `list_locations`</sub>

### POST `/locations`

Create a location.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `location` | body | yes | string | Name. |
| `lat` | body | yes | number |  |
| `lng` | body | yes | number |  |
| `fixed_coordinates` | body | optional | `0` or `1` | `1` = don't update from devices. Default `1` if the coordinates are valid. |

```json
{"location": "Pune DC", "lat": 18.52, "lng": 73.85}
```

Response: `201`, `status`, `message` (with the new ID)

<sub>access: admin · `add_location`</sub>

### GET `/location/{location_id_or_name}`

One location. Note the singular `location` in the path.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `location_id_or_name` | path | yes | ID or name |  |

Response: `status`, `get_location` (the location object)

<sub>access: admin · `get_location`</sub>

### PATCH `/locations/{location_id_or_name}`

Update a location. The body is written straight to the `locations` row.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `location_id_or_name` | path | yes | ID or name |  |
| `location`, `lat`, `lng`, `fixed_coordinates` | body | optional | `locations` columns | Unknown columns fail with `500`. |

```json
{"lat": 18.53, "lng": 73.86}
```

Response: `201`, `status`, `message`

<sub>access: admin · `edit_location`</sub>

### DELETE `/locations/{location}`

Delete a location. Its devices are left with no location.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `location` | path | yes | ID or name |  |

Response: `201`, `status`, `message`

<sub>access: admin · `del_location`</sub>

---

## 24.11 Routing (BGP, OSPF, VRF, IPsec, MPLS)

### GET `/bgp`

BGP sessions.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | query | optional | hostname or device_id |  |
| `asn` | query | optional | integer | Local AS. |
| `remote_asn` | query | optional | integer |  |
| `local_address`, `remote_address` | query | optional | IP | `400` if invalid. |
| `bgp_descr` | query | optional | string | LIKE match. |
| `bgp_state` | query | optional | e.g. `established`, `idle` |  |
| `bgp_adminstate` | query | optional | e.g. `start`, `stop`, `running` |  |
| `bgp_family` | query | optional | `4` or `6` |  |

Response: `status`, `count`, `bgp_sessions`

<sub>access: global-read · `list_bgp`</sub>

### GET `/bgp/{id}`

One BGP session.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `id` | path | yes | integer | `bgpPeer_id`. |

Response: `status`, `count`, `bgp_session` (array with one row)

<sub>access: global-read · `get_bgp`</sub>

### POST `/bgp/{id}`

Set the description of a BGP session.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `id` | path | yes | integer | `bgpPeer_id`. |
| `bgp_descr` | body | yes | string | `500` if missing. |

```json
{"bgp_descr": "Transit - ISP A"}
```

Response: `status`, `message`

<sub>access: admin · `edit_bgp_descr`</sub>

### GET `/routing/bgp/cbgp`

BGP per-address-family counters (Cisco cBGP).

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | query | optional | hostname or device_id |  |

Response: `status`, `count`, `bgp_counters`. `404` when none.

<sub>access: per-device (filtered) · `list_cbgp`</sub>

### GET `/ospf`

OSPF neighbours.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | query | optional | hostname or device_id |  |

Response: `status`, `count`, `ospf_neighbours`

<sub>access: global-read · `list_ospf`</sub>

### GET `/ospf_ports`

OSPF interfaces.

No parameters.

Response: `status`, `count`, `ospf_ports`. `404` when none.

<sub>access: global-read · `list_ospf_ports`</sub>

### GET `/ospfv3`

OSPFv3 neighbours.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | query | optional | hostname or device_id |  |

Response: `status`, `count`, `ospfv3_neighbours`. Returns `500` (not `404`) when there are none.

<sub>access: global-read · `list_ospfv3`</sub>

### GET `/ospfv3_ports`

OSPFv3 interfaces.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | query | optional | hostname or device_id |  |

Response: `status`, `count`, `ospfv3_ports`. `404` when none.

<sub>access: global-read · `list_ospfv3_ports`</sub>

### GET `/routing/vrf`

VRFs.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | query | optional | hostname or device_id |  |
| `vrfname` | query | optional | string | Replaces the `hostname` filter if both are given. |

Response: `status`, `count`, `vrfs`. `404` when none.

<sub>access: per-device (filtered) · `list_vrf`</sub>

### GET `/routing/vrf/{id}`

One VRF.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `id` | path | yes | integer | `vrf_id`. |

Response: `status`, `count`, `vrf`

<sub>access: global-read · `get_vrf`</sub>

### GET `/routing/ipsec/data/{hostname}`

IPsec tunnels of a device.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |

Response: `status`, `count`, `ipsec` (tunnel rows plus `hostname`)

<sub>access: global-read · `list_ipsec`</sub>

### GET `/routing/mpls/services`

MPLS services.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | query | optional | hostname or device_id |  |

Response: `status`, `count`, `mpls_services`. `404` when none.

<sub>access: per-device (filtered) · `list_mpls_services`</sub>

### GET `/routing/mpls/saps`

MPLS service access points.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | query | optional | hostname or device_id |  |

Response: `status`, `count`, `saps`. `404` when none.

<sub>access: per-device (filtered) · `list_mpls_saps`</sub>

---

## 24.12 Services

### GET `/services`

All service checks.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `state` | query | optional | `0` ok, `1` warning, `2` critical | Also excludes disabled and ignored services. |
| `type` | query | optional | string | LIKE match, e.g. `http`. |

Response: `status`, `count` (always `1`), `services` (an array containing one array of service rows, kept for backwards compatibility)

<sub>access: global-read · `list_services`</sub>

### GET `/services/{hostname}`

Service checks of one device. Same query parameters and response as `/services`.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |

<sub>access: global-read · `list_services_device`</sub>

### POST `/services/{hostname}`

Add a service check to a device.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |
| `type` | body | yes | an installed check, e.g. `http`, `icmp`, `ssh` | The error lists the available types. |
| `ip` | body | optional | string | Target address. |
| `name` | body | optional | string |  |
| `desc` | body | optional | string |  |
| `param` | body | optional | string | Check arguments. |
| `ignore` | body | optional | boolean | Default `false`. |
| `disable` | body | optional | boolean | Default `false`. |

```json
{"type": "http", "ip": "192.168.2.245", "name": "Switch web UI", "param": "-p 80"}
```

Response: `201`, `status`, `message` (with the new service ID)

<sub>access: admin · `add_service_for_host`</sub>

### PATCH `/services/{id}`

Update a service. The body is written straight to the `services` row.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `id` | path | yes | integer | `service_id`. |
| `service_name`, `service_ip`, `service_type`, `service_desc`, `service_param`, `service_ignore`, `service_disabled` | body | optional | `services` columns |  |

```json
{"service_disabled": 1}
```

Response: `201`, `status`, `message`. Returns `500` when nothing changed.

<sub>access: admin · `edit_service_for_host`</sub>

### DELETE `/services/{id}`

Delete a service.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `id` | path | yes | integer | `service_id`. |

Response: `201`, `status`, `message`

<sub>access: admin · `del_service_from_host`</sub>

### GET `/devices/{hostname}/services/{id}/graphs/{datasource}`

Graph of one data source of a service check. Graph query parameters apply.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | yes | hostname or device_id |  |
| `id` | path | yes | integer | `service_id`. |
| `datasource` | path | yes | string | Data source name of the check, e.g. `time`. |

Response: image (or base64 envelope)

<sub>access: per-device · `get_graph_by_service`</sub>

---

## 24.13 Bills

### GET `/bills`

All bills.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `custid` | query | optional | string | Filter by customer ID. |
| `ref` | query | optional | string | Filter by reference (ignored if `custid` is set). |
| `period` | query | optional | `previous` | Figures of the last completed period instead of the current one. |

Response: `status`, `count`, `bills` (bill row plus `allowed`, `used`, `percent`, `overuse`, `ports`)

<sub>access: per-bill (filtered) · `list_bills`</sub>

### GET `/bills/{bill_id}`

One bill. Same query parameters and response as `/bills`.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `bill_id` | path | yes | integer |  |

<sub>access: per-bill (filtered) · `get_bill`</sub>

### GET `/bills/{bill_id}/graphs/{graph_type}`

Bill graph for the current period. Graph query parameters apply.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `bill_id` | path | yes | integer |  |
| `graph_type` | path | yes | `bits` or `monthly` |  |

Response: image (or base64 envelope)

<sub>access: per-bill · `get_bill_graph`</sub>

### GET `/bills/{bill_id}/graphdata/{graph_type}`

Raw data behind a bill graph.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `bill_id` | path | yes | integer |  |
| `graph_type` | path | yes | `bits` or `monthly` | Other values return `400`. |
| `from`, `to` | query | optional | Unix timestamp | `bits` only. Default the last 24 hours. |
| `reducefactor` | query | optional | integer | `bits` only. |

Response: `status`, `graph_data`

<sub>access: per-bill · `get_bill_graphdata`</sub>

### GET `/bills/{bill_id}/history`

The last 24 billing periods.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `bill_id` | path | yes | integer |  |

Response: `status`, `count`, `bill_history`

<sub>access: per-bill · `get_bill_history`</sub>

### GET `/bills/{bill_id}/history/{bill_hist_id}/graphs/{graph_type}`

Graph of a past billing period. Graph query parameters apply.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `bill_id` | path | yes | integer |  |
| `bill_hist_id` | path | yes | integer | From `/history`. |
| `graph_type` | path | yes | `bits`, `day` or `hour` | Other values return `400`. |
| `reducefactor` | query | optional | integer | `bits` only. |

Response: image (or base64 envelope)

<sub>access: per-bill · `get_bill_history_graph`</sub>

### GET `/bills/{bill_id}/history/{bill_hist_id}/graphdata/{graph_type}`

Raw data behind a past period's graph.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `bill_id` | path | yes | integer |  |
| `bill_hist_id` | path | yes | integer |  |
| `graph_type` | path | yes | `bits`, `day` or `hour` |  |
| `reducefactor` | query | optional | integer | `bits` only. |

Response: `status`, `graph_data`

<sub>access: per-bill · `get_bill_history_graphdata`</sub>

### POST `/bills`

Create a bill, or update one when `bill_id` is an existing bill. On update, `ports` (if sent)
replaces the bill's ports, so send the full list.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `bill_id` | body | update only | integer | Not allowed on create. |
| `bill_name` | body | yes on create | string |  |
| `bill_type` | body | yes on create | `quota` or `cdr` |  |
| `bill_cdr` | body | yes for `cdr` | number, bits/s | Set to `0` for `quota`. |
| `bill_quota` | body | yes for `quota` | number, bytes | Set to `0` for `cdr`. |
| `bill_day` | body | yes on create | integer, 1 - 31 | Billing day of the month. |
| `bill_custid`, `bill_ref`, `bill_notes` | body | yes on create | string | May be empty. |
| `dir_95th` | body | yes on create | e.g. `in`, `out`, `agg` |  |
| `ports` | body | optional | array of port IDs | `500` if a port doesn't exist. |

```json
{"bill_name": "Customer A", "bill_type": "cdr", "bill_cdr": 100000000, "bill_day": 1,
 "bill_custid": "CUST-A", "bill_ref": "", "bill_notes": "", "dir_95th": "in", "ports": [101, 102]}
```

Response: `status`, `bill_id`. Missing fields return `500` with `Missing parameters: ...`; invalid values `400`.

<sub>access: admin · `create_bill`</sub>

### DELETE `/bills/{bill_id}`

Delete a bill and its ports, data, history and permissions.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `bill_id` | path | yes | integer, >= 1 |  |

Response: `status`, `message`

<sub>access: admin · `delete_bill`</sub>

---

## 24.14 Oxidized

### GET `/oxidized/{hostname?}`

Device list in the format Oxidized reads as its source: enabled devices not excluded by the Oxidized
settings, with group/model mapping applied.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `hostname` | path | optional | hostname | One device only. |

Response: a plain JSON array (no envelope) of `hostname`, `os`, `ip`, and `ssh_port`, `telnet_port`, `group` and other mapped fields when set

<sub>access: global-read · `list_oxidized`</sub>

### GET `/oxidized/config/search/{searchstring}`

Search the configs stored in Oxidized.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `searchstring` | path | yes | string |  |

Response: `status`, `count`, `nodes` (`node`, `full_name`, `dev_id`). `404` when Oxidized returns nothing.

<sub>access: admin · `search_oxidized`</sub>

### GET `/oxidized/config/{device_name}`

The current config of a device from Oxidized.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `device_name` | path | yes | hostname or IP as known to Oxidized |  |

Response: `status`, `config` (the config text). `404` when Oxidized returns nothing.

<sub>access: admin · `get_oxidized_config`</sub>

---

## 24.15 Poller groups and syslog sink

### GET `/poller_group/{poller_group_id_or_name?}`

Poller groups. Without a parameter, all of them.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `poller_group_id_or_name` | path | optional | ID or `group_name` |  |

Response: `status`, `get_poller_group` (a list, or one object: `id`, `group_name`, `descr`). `404` when not found.

<sub>access: admin · `get_poller_group`</sub>

### POST `/syslogsink`

Receive syslog messages as JSON (for example from a log shipper) and store them like syslog
received by the server.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| (body) | body | yes | one message object, or an array of them | Fields stored: `host`, `facility`, `priority`, `level`, `tag`, `timestamp`, `msg`, `program`. Only messages whose `host` is a known device are stored; `syslog_filter` matches are dropped. |

```json
[{"host": "192.168.2.245", "facility": "local7", "priority": "189", "level": "notice", "tag": "bd", "timestamp": "2026-10-22 10:00:00", "msg": "Interface g0/1 up", "program": "LINK"}]
```

Response: `status`, `message` (`Syslog received: N`)

<sub>access: admin · `post_syslogsink`</sub>

---

## 24.16 Templates (push)

The API behind the Template Push page (`/addhost/template`). Templates are the JSON files under
`resources/templates/` (one sub-folder level allowed). Pushing runs a playbook on each switch, like
the automation API.

### GET `/templates`

All templates, optionally only those that fit a device's hardware.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `device` | query | optional | device_id, hostname, `overwrite_ip` or sysName | `404` if not found. Templates with no `hardware_models` always match. |

Response: `status`, `count`, `templates` (`name`, `folder`, `type`, `hardware_models`)

<sub>access: admin · `templates.list`</sub>

### GET `/templates/{name}`

One template, with what it needs as input.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `name` | path | yes | template name or its slug |  |
| `folder` | query | optional | folder name | Pick the template from this folder when names repeat. |

Response: `status`, `template` (`name`, `folder`, `type`, `hardware_models`, and `fields` for `form` templates (`label`, `type`, `required`, `command`, `options` / `value` / `divisor`) or `pvid` and `commands` for the others). `404` if not found.

<sub>access: admin · `templates.show`</sub>

### POST `/templates/push`

Build the CLI commands from a template (or take raw commands) and push them to one or more switches.

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `device` | body | yes, unless `devices` | device_id, hostname, `overwrite_ip` or sysName |  |
| `devices` | body | optional | array of the same | Push to several switches. |
| `template` | body | yes, unless `commands` | string | Template name or slug. Must match each device's hardware (`422`). |
| `folder` | body | optional | string |  |
| `commands` | body | yes, unless `template` | array of strings | Raw CLI commands; with a `custom` template, replaces its commands. |
| `interfaces` | body | yes for `access`/`trunk` | array, e.g. `["GigaEthernet0/1"]` | Interface blocks are repeated for each one. |
| `values` | body | for `form` templates | object, field label => value | Dropdown: option name; checkbox: boolean; dynamic list: array of row objects. |
| `port_mode` | body | optional | `access`, `trunk`, `custom` | Overrides the template type. |
| `pvid` | body | optional | integer, 1 - 4094 | For `access`/`trunk`. |
| `dry_run` | body | optional | boolean | Return the commands without pushing. |
| `ansible_user`, `ansible_password`, `snmp_community` | body | optional | string | Used only when the device has no stored SSH user/password/community. Default `admin`/`admin`/`public`. |

```json
{"device": "192.168.200.239", "template": "Access port", "interfaces": ["GigaEthernet0/1"], "pvid": 20, "dry_run": true}
```

Response (dry run): `status`, `dry_run`, `devices` (IPs), `commands`

Response: `status` (`ok` / `error`), `success`, `message`, `results` (per switch: `ip`, `hostname`, `status`, `ansible_output` or `error`), `summary` (`total`, `success`, `failed`), `commands`. Input errors are `404` (device/template) or `422`.

<sub>access: admin · `templates.push` · playbook: `push/pushconfigtempuploadip.yml`</sub>

---

## 24.17 Chatbot

### POST `/chatbot/message`

Ask the NMS assistant a question. Telequill collects devices, ports, services, alerts, graphs and
sensors through this API (as the calling user), sends them with the message to the configured LLM
(`services.chatbot.endpoint` / `services.chatbot.key`), and returns its answer. If the LLM replies
with an action, it is carried out: adding a device (`POST /devices`) or deleting one
(`DELETE /devices/{id}`).

| Param | In | Required | Rules | Notes |
|---|---|---|---|---|
| `message` | body | yes | string, max 5000 chars |  |
| `mode` | body | yes | `bubble` or `terminal` | Not otherwise used. |

```json
{"message": "Which devices are down?", "mode": "bubble"}
```

Response: no envelope, always `200`: `reply` (text), `type` (`llm`, `action` or `error`). `error` means the user has no API token. Validation errors are `422`.

> The internal calls use the user's stored `token_hash` as `X-Auth-Token`, and the base URL from `services.librenms.url`. Each message fetches all devices several times plus one call per device, so it is slow on large networks.

<sub>access: admin · (unnamed route)</sub>

---

## 24.18 Index

| Section | Endpoints |
|---|---|
| [System](#241-system) | 2 |
| [Devices](#242-devices) | 17 |
| [Device graphs and health](#243-device-graphs-and-health) | 6 |
| [Ports](#244-ports) | 13 |
| [Port groups](#245-port-groups) | 7 |
| [Device groups](#246-device-groups) | 9 |
| [Alerts and alert rules](#247-alerts-and-alert-rules) | 9 |
| [Logs](#248-logs) | 4 |
| [Inventory and transceivers](#249-inventory-and-transceivers) | 5 |
| [Resources (ARP, IP, FDB, NAC, links, VLANs, sensors, locations)](#2410-resources-arp-ip-fdb-nac-links-vlans-sensors-locations) | 23 |
| [Routing (BGP, OSPF, VRF, IPsec, MPLS)](#2411-routing-bgp-ospf-vrf-ipsec-mpls) | 13 |
| [Services](#2412-services) | 6 |
| [Bills](#2413-bills) | 9 |
| [Oxidized](#2414-oxidized) | 3 |
| [Poller groups and syslog sink](#2415-poller-groups-and-syslog-sink) | 2 |
| [Templates (push)](#2416-templates-push) | 3 |
| [Chatbot](#2417-chatbot) | 1 |
| **Total** | **132** |

---

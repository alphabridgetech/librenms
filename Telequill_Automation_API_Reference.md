# Telequill Automation API Reference

Base URL: `http://localhost:8000/api/v0`

These 104 endpoints configure AlphaBridge switches. Almost all of them run an Ansible playbook from
`librenms-ansible-inventory-plugin/playbooks/` against one switch, over SSH (one endpoint uses
Telnet). They are the same calls the device **Configuration** pages in Telequill make. Source:
`app/Api/Controllers/KunalApiController.php`, routes in `routes/api.php`.

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


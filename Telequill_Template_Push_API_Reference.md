# Telequill Template Push API Reference

Base URL: `http://localhost:8000/api/v0`

Auth: every request needs an `X-Auth-Token: <your-api-token>` header (generate a token under
Settings > API Access). The token must belong to an **admin** user; other users get `403`, a
missing or wrong token gets `401`.

This API does the same job as the Template Push page (`/addhost/template`): pick a template,
fill in its fields, choose interfaces and push the resulting CLI commands to one or more
devices over SSH (Ansible). The commands are built with the same rules the page uses, so a
template produces identical commands from the page and from the API.

Send request bodies as JSON with `Content-Type: application/json`, or as form-data /
x-www-form-urlencoded (see section 5).

---

## 1. Endpoints

| Purpose | Method & URL |
|---|---|
| List templates | `GET /templates` |
| Show one template and the values it needs | `GET /templates/{name}` |
| Build commands and push them to devices | `POST /templates/push` |

Recommended flow: `GET /templates` → `GET /templates/{name}` → `POST /templates/push` with
`"dry_run": true` to check the commands → the same request without `dry_run` to push.

---

## 2. List templates

`GET /templates`

| Query param | Required | Description |
|---|---|---|
| `device` | no | Device IP, hostname, sysName or device_id. Only templates that apply to that device's hardware model are returned. |

Example:
```
curl -H "X-Auth-Token: TOKEN" "http://localhost:8000/api/v0/templates?device=192.168.200.241"
```

Response:
```json
{
    "status": "ok",
    "count": 21,
    "templates": [
        {
            "name": "Creating the LAG with Trunk vlans in AS210T",
            "folder": "",
            "type": "form",
            "hardware_models": ["AS210T mother card"]
        },
        {
            "name": "description",
            "folder": "",
            "type": "form",
            "hardware_models": []
        }
    ]
}
```

- `type` is `form` (fields to fill in), or `access`, `trunk` or `custom` (port-mode templates).
- An empty `hardware_models` list means the template works on every device.

---

## 3. Show a template

`GET /templates/{name}`

| Param | Required | Description |
|---|---|---|
| `{name}` | yes | Template name, as returned by the list. Spaces and letter case don't matter (`L2TP on uplink for AS212XTS` and `l2tp-on-uplink-for-as212xts` both work). URL-encode spaces as `%20`. |
| `folder` (query) | no | Only needed when two templates in different folders share a name. |

Example:
```
curl -H "X-Auth-Token: TOKEN" "http://localhost:8000/api/v0/templates/L2TP%20on%20uplink%20for%20AS212XTS"
```

Response for a `form` template:
```json
{
    "status": "ok",
    "template": {
        "name": "L2TP on uplink for AS212XTS",
        "folder": "",
        "type": "form",
        "hardware_models": ["AS212XTS"],
        "fields": [
            {"label": "Description", "type": "text", "required": false, "command": "description {{value}}"},
            {"label": "L2TP - LLDP", "type": "checkbox", "required": false, "command": "l2protocol-tunnel LLDP", "value": "true or false"},
            {"label": "Flow Control", "type": "dropdown", "required": false, "command": "", "options": ["on", "off", "auto"]},
            {"label": "Allowed Vlans", "type": "text", "required": false, "command": "switchport trunk vlan-allowed {{value}}"}
        ]
    }
}
```

Each entry in `fields` is one input you can send in `values` when pushing. Fixed commands that
need no input (for example `interface {{interface}}`) are not listed but are still part of the
output.

For `access`, `trunk` and `custom` templates the response has `pvid` and `commands` instead of
`fields`.

---

## 4. Push a template

`POST /templates/push`

| Body field | Required | Description |
|---|---|---|
| `device` | yes, or `devices` | Device IP, hostname, sysName or device_id. |
| `devices` | yes, or `device` | List of devices, to push the same commands to several devices. |
| `template` | yes, or `commands` | Template name (same rules as `GET /templates/{name}`). |
| `folder` | no | Template folder, if needed to tell templates apart. |
| `interfaces` | depends | Interfaces the template is applied to, e.g. `["GigaEthernet0/1", "GigaEthernet0/2"]`. Required for `access` and `trunk` templates. |
| `values` | depends | Field values for `form` templates, see the table below. |
| `port_mode` | no | `access`, `trunk` or `custom`, to override a port-mode template's type. |
| `pvid` | no | PVID (1-4094) for `access` / `trunk`. Defaults to the template's PVID, then `1`. |
| `commands` | no | A list of CLI commands. Without `template`, these are pushed as they are. With a `custom` template, they replace the template's commands. |
| `dry_run` | no | `true` returns the generated commands without connecting to any device. Use this first. |

### `values` by field type

Keys are the field `label` from `GET /templates/{name}` (letter case doesn't matter).

| Field type | What to send | Example |
|---|---|---|
| `text` | String | `"Description": "uplink-to-core"` |
| `number` | Number or string. If the field has a `divisor`, the value is divided by it and rounded, like on the page (e.g. kbps → 64 kbps units). | `"Ingress": 1000` |
| `dropdown` | One of the `options` names | `"Flow Control": "off"` |
| `checkbox` | `true` adds the command, `false` or missing leaves it out | `"L2TP - LLDP": true` |
| `dynamic_list` | A list of rows. Each row is an object with the `{{variables}}` from the field's `command` | `"QinQ": [{"from": "100", "to": "200"}]` |

A `required` field that's missing or empty is an error. Optional fields you leave out add no
command.

### Example: check the commands first (dry run)

```
curl -X POST http://localhost:8000/api/v0/templates/push \
  -H "X-Auth-Token: TOKEN" -H "Content-Type: application/json" \
  -d '{
        "device": "192.168.200.240",
        "template": "L2TP on uplink for AS212XTS",
        "interfaces": ["GigaEthernet0/3"],
        "values": {
            "Description": "to-cust",
            "L2TP - LLDP": true,
            "Flow Control": "off",
            "Speed": "100",
            "Allowed Vlans": "10,20"
        },
        "dry_run": true
      }'
```

Response:
```json
{
    "status": "ok",
    "dry_run": true,
    "devices": ["192.168.200.240"],
    "commands": [
        "interface GigaEthernet0/3",
        "description to-cust",
        "l2protocol-tunnel LLDP",
        "flow-control off",
        "speed 100",
        "no spanning-tree",
        "switchport mode dot1q-tunnel-uplink",
        "switchport trunk vlan-allowed 10,20"
    ]
}
```

### Example: push to the device

Send the same body without `"dry_run": true`. The request waits until Ansible has finished on
every device, which can take a while for several devices.

Response:
```json
{
    "success": true,
    "status": "ok",
    "message": "Successfully processed 1 device(s)",
    "results": [
        {
            "ip": "192.168.200.240",
            "hostname": "bridge_192_168_200_240",
            "status": "success",
            "ansible_output": "PLAY [...] ... ok=2 changed=1 unreachable=0 failed=0 ..."
        }
    ],
    "summary": {"total": 1, "success": 1, "failed": 0},
    "commands": ["interface GigaEthernet0/3", "description to-cust", "..."]
}
```

If any device fails, `success` is `false`, `status` is `error`, the failed device has
`"status": "failed"` in `results`, and `ansible_output` shows why (e.g. unreachable, wrong
SSH password).

### Example: access port on several interfaces

```
curl -X POST http://localhost:8000/api/v0/templates/push \
  -H "X-Auth-Token: TOKEN" -H "Content-Type: application/json" \
  -d '{"device": "192.168.200.241", "template": "<your access/trunk/custom template>",
       "port_mode": "access", "pvid": 100, "interfaces": ["GigaEthernet0/1", "GigaEthernet0/2"],
       "dry_run": true}'
```

Gives `interface GigaEthernet0/1`, `switchport mode access`, `switchport pvid 100`, then the
same for `GigaEthernet0/2`.

### Example: plain commands, no template

```
curl -X POST http://localhost:8000/api/v0/templates/push \
  -H "X-Auth-Token: TOKEN" -H "Content-Type: application/json" \
  -d '{"device": "192.168.200.239", "commands": ["interface GigaEthernet0/5", "shutdown"]}'
```

### Example: reboot a switch

The template "Reboot the switch" has one dropdown, `Reboot`, with the options `Yes` and `No`.
`Yes` sends the command `reboot n`; `No` sends nothing (the API answers `No commands to push`).
No `interfaces` are needed.

```
curl -X POST http://localhost:8000/api/v0/templates/push \
  -H "X-Auth-Token: TOKEN" -H "Content-Type: application/json" \
  -d '{"device": "192.168.200.239", "template": "Reboot the switch", "values": {"Reboot": "Yes"}, "dry_run": true}'
```

Response:
```json
{"status": "ok", "dry_run": true, "devices": ["192.168.200.239"], "commands": ["reboot n"]}
```

Without `"dry_run": true` the switch reboots and is offline until it has restarted. Use
`devices` to reboot several switches, one after the other.

---

## 5. Sending as form-data (Postman)

Every push can also be sent as **form-data** or **x-www-form-urlencoded** instead of JSON, e.g.
in Postman under **Body → form-data**. Keep the `X-Auth-Token` header.

| JSON | form-data key | form-data value |
|---|---|---|
| `"device": "192.168.200.240"` | `device` | `192.168.200.240` |
| `"devices": ["A", "B"]` | `devices[]` (one row per device) | `A`, then `B` |
| `"template": "..."` | `template` | template name |
| `"interfaces": ["Gi0/1", "Gi0/2"]` | `interfaces[]` (one row per interface) | `GigaEthernet0/1`, then `GigaEthernet0/2` |
| `"values": {"Description": "x"}` | `values[Description]` | `x` |
| checkbox `true` / `false` | `values[L2CP - LLDP]` | `1` / `0` |
| dynamic_list rows | `values[QinQ][0][from]`, `values[QinQ][0][to]`, `values[QinQ][1][from]`, ... | one row per variable per list row |
| `"dry_run": true` | `dry_run` | `1` (`0` or no row = real push) |

- Field labels go inside the square brackets exactly as shown by `GET /templates/{name}`,
  spaces included (`values[Allowed Vlan]`).
- `dry_run` and checkboxes must be `1` / `0`. The text `true` is rejected with
  `The dry run field must be true or false.`
- Leave out the row of an optional field you don't need; its command is not sent.

### Form-data: Reboot the switch

| Key | Value |
|---|---|
| `device` | `192.168.200.239` |
| `template` | `Reboot the switch` |
| `values[Reboot]` | `Yes` |
| `dry_run` | `1` |

Generated command: `reboot n`. With `values[Reboot]` = `No` nothing is sent (`No commands to
push`). Remove `dry_run` (or set `0`) to really reboot the switch.

### Form-data: Tag traffic to single S-vlan in AS212XTS

Only for devices whose hardware model is **AS212XTS** (see section 7).

| Key | Value | Required |
|---|---|---|
| `device` | `192.168.200.240` | yes |
| `template` | `Tag traffic to single S-vlan in AS212XTS` | yes |
| `interfaces[]` | `GigaEthernet0/1` | yes, one row per port |
| `interfaces[]` | `GigaEthernet0/2` | no, extra port |
| `values[Description]` | `cust-a-uplink` | yes |
| `values[Allowed Vlan]` | `100-200` | yes |
| `values[QinQ][0][from]` | `100` | yes, at least one QinQ row |
| `values[QinQ][0][to]` | `200` | yes |
| `values[QinQ][1][from]` | `101` | no, 2nd QinQ row (`[2]`, `[3]`, ... for more) |
| `values[QinQ][1][to]` | `201` | no |
| `values[L2CP - CDP]` | `1` | no |
| `values[L2CP - LLDP]` | `1` | no |
| `values[L2CP - STP]` | `1` | no |
| `values[L2CP - LACP]` | `1` | no |
| `values[Flow Control]` | `on`, `off` or `auto` | no |
| `values[Duplex]` | `Auto`, `Half` or `Full` | no |
| `values[Speed]` | `10`, `100` or `Auto` | no |
| `dry_run` | `1` | `1` = test only |

Generated commands (with the values above), repeated for each interface:
```
interface GigaEthernet0/1
description cust-a-uplink
l2protocol-tunnel lldp
l2protocol-tunnel stp
flow-control off
duplex full
speed 100
no spanning-tree
switchport mode dot1q-translating-tunnel
switchport trunk vlan-allowed 100-200
switchport dot1q-translating-tunnel mode QinQ translate 100 200
switchport dot1q-translating-tunnel mode QinQ translate 101 201
switchport dot1q-tunnel-sub-global
```

The same request with curl:
```
curl -X POST http://localhost:8000/api/v0/templates/push -H "X-Auth-Token: TOKEN" \
  -F device=192.168.200.240 -F "template=Tag traffic to single S-vlan in AS212XTS" \
  -F "interfaces[]=GigaEthernet0/1" -F "interfaces[]=GigaEthernet0/2" \
  -F "values[Description]=cust-a-uplink" -F "values[L2CP - LLDP]=1" -F "values[L2CP - STP]=1" \
  -F "values[Flow Control]=off" -F "values[Duplex]=Full" -F "values[Speed]=100" \
  -F "values[Allowed Vlan]=100-200" \
  -F "values[QinQ][0][from]=100" -F "values[QinQ][0][to]=200" \
  -F "values[QinQ][1][from]=101" -F "values[QinQ][1][to]=201" \
  -F dry_run=1
```

---

## 6. How interfaces are applied

- A template whose commands contain `{{interface}}` (normally `interface {{interface}}`) has
  that block repeated for every entry in `interfaces`. Commands before it, or after a line
  that is just `!`, run once.
- A template without `{{interface}}` gets `interface <name>` added in front and the whole
  set repeated for every interface.
- With no `interfaces`, `{{interface}}` is removed and the commands run once.

---

## 7. Hardware models

A template with `hardware_models` can only be pushed to devices whose hardware model (the
**Hardware** shown on the device page) matches one of them. The match ignores case, spaces
and symbols but otherwise must be exact: a template for `AS200/12/XTS` does not match a device
reporting `AS200/12/XTS-2AC`. Add the exact model to the template in the Template Builder if
needed. Templates with no hardware models work on every device.

---

## 8. SSH credentials

The push logs in with the device's own SSH user and password (set on the device in Telequill).
If the device has none, it uses `admin` / `admin`. The SNMP community comes from the device the
same way, falling back to `public`.

---

## 9. Errors

Errors return `{"status": "error", "message": "..."}` with these HTTP codes:

| Code | When | Example message |
|---|---|---|
| `401` | Missing or wrong `X-Auth-Token` | |
| `403` | Token user is not an admin | |
| `404` | Device or template not found | `Device not found: 192.168.200.99`, `Template not found` |
| `422` | Missing or invalid input | `"description" is required` |
| | | `"Flow Control" must be one of: on, off, auto` |
| | | `Template "L2CP on downlink for AS210T" is for AS210T mother card, device 192.168.200.239 is AS200/12/XTS-2AC` |
| | | `interfaces are required for access/trunk templates` |
| | | `No commands to push` |

### Differences from the page

- The page fills an empty required field with its label as a placeholder. The API rejects the
  request instead, so no placeholder text is pushed to a device.
- The API needs `interfaces` for `access` and `trunk` templates.

---

## 10. What a push records

Same as a push from the page:

- An eventlog entry per device (type `template_push`), e.g.
  `kunal pushed template "description" to 192.168.200.239 (4 command(s)) - success`.
- The `values`, `interfaces`, `port_mode` and `pvid` used are saved for that device and
  template, so the Template Push page pre-fills them next time.

---

## 11. How it works internally

| Part | File |
|---|---|
| Routes (admin only) | `routes/api.php` |
| Endpoints | `app/Api/Controllers/TemplatePushApiController.php` |
| Template loading and command building | `app/Services/PushTemplates.php` |
| SSH push via Ansible, eventlog, saved values | `app/Http/Controllers/Traits/HandlesPushConfiguration.php` (`processPushNetworkCommand`) |
| Templates | `resources/templates/**/*.json` |

`PushTemplates::buildCommands()` follows `generateCommands()` in
`resources/views/addhostip/template.blade.php`. If the command rules change on the page,
change both.

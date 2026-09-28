# Telequill Graphs API Reference

Base URL: `http://localhost:8000/api/v0`

Auth: every request needs an `X-Auth-Token: <your-api-token>` header (generate a token under
Settings > API Access). Requests also respect normal device permission checks
(`global-read` / per-device access).

Graph images are fetched with `Accept: image/png` (or just opened directly in a browser /
`<img>` tag) — the JSON list endpoints return metadata about which graphs exist.

---

## 1. List endpoints (what graphs exist for a device)

| Purpose | Method & URL |
|---|---|
| List a device's known graph types | `GET /devices/{hostname}/graphs` |
| List available health/sensor graphs | `GET /devices/{hostname}/health/{type?}/{sensor_id?}` |
| List available wireless graphs | `GET /devices/{hostname}/wireless/{type?}/{sensor_id?}` |

`{hostname}` can be the device's hostname/sysName or its numeric device_id.

Note: `GET /devices/{hostname}/graphs` only returns a couple of fixed types
(`device_poller_perf`, `device_icmp_perf`) plus whatever is in the `device_graphs` DB table for
that device (populated by discovery based on installed poller modules). It is **not** a
complete enumeration — the fetch endpoints below accept any valid graph type regardless of
whether it's listed here.

---

## 2. Fetch endpoints (the actual graph image)

| Purpose | Method & URL |
|---|---|
| Generic device graph | `GET /devices/{hostname}/{type}` |
| Port/interface graph | `GET /devices/{hostname}/ports/{ifname}/{type}` |
| Health/sensor graph | `GET /devices/{hostname}/graphs/health/{type}/{sensor_id?}` |
| Wireless graph | `GET /devices/{hostname}/graphs/wireless/{type}/{sensor_id?}` |

Common query params (all fetch endpoints): `from`, `to` (unix timestamps or relative, e.g.
`-1d`, `-7d`, `-30d`, `-1y`), `width`, `height`.

Example:
```
GET /api/v0/devices/as212xts_lab/device_bits
GET /api/v0/devices/as212xts_lab/ports/eth0/device_bits?from=-7d
GET /api/v0/devices/as212xts_lab/graphs/health/sensor_temperature/12
```

---

## 3. Valid `{type}` values

These map 1:1 to the graphs rendered on the device Overview page (`/device/{id}`) — same
underlying template files, so anything visible on that page is fetchable through the API.

### Device-level

| type | Graph |
|---|---|
| `device_bits` | Port traffic (use the ports endpoint per-interface) |
| `device_processor` | CPU (all processors) |
| `processor_usage` | CPU (single processor) |
| `device_mempool` | Memory (all pools) |
| `mempool_usage` | Memory (single pool) |
| `storage_usage` | Disk/storage |
| `toner_usage` | Printer toner |
| `device_icmp_perf` | Ping latency/loss (ping-only devices) |
| `device_poller_perf` | Poller performance |

### Sensor graphs (`sensor_<class>`, used with the health endpoints)

`sensor_temperature`, `sensor_voltage`, `sensor_current`, `sensor_fanspeed`, `sensor_power`,
`sensor_power_consumed`, `sensor_power_factor`, `sensor_frequency`, `sensor_humidity`,
`sensor_dbm`, `sensor_charge`, `sensor_runtime`, `sensor_load`, `sensor_state`, `sensor_count`,
`sensor_percent`, `sensor_signal`, `sensor_bitrate`, `sensor_airflow`, `sensor_snr`,
`sensor_pressure`, `sensor_cooling`, `sensor_delay`, `sensor_quality_factor`,
`sensor_chromatic_dispersion`, `sensor_ber`, `sensor_eer`, `sensor_waterflow`, `sensor_loss`,
`sensor_signal_loss`

---

## 4. How it works internally

`LibreNMS\Util\Graph::get()` splits the `type` string on the first underscore into
`type`/`subtype` and loads `includes/html/graphs/<type>/<subtype>.inc.php`, falling back to
`includes/html/graphs/<type>/generic.inc.php`. This is the same file set the web UI's device
Overview tab uses, so the API is not restricted to a hardcoded whitelist — any graph type with
a matching template file will render. If a device doesn't have data for a given type (e.g. no
temperature sensors), the request will simply return an empty/error graph rather than a route
404.

Key source files: `routes/api.php`, `includes/html/api_functions.inc.php`
(`get_graphs`, `get_graph_generic_by_hostname`, `get_graph_by_port_hostname`,
`list_available_health_graphs`, `list_available_wireless_graphs`, `api_get_graph`),
`LibreNMS/Util/Graph.php`, `includes/html/pages/device/overview/*.inc.php`.

# Telequill – Demo Content (9 Pages)

> This is for demo only. New IPs, names and passwords below are sample values.

**Order to show the pages (about 15 minutes):**
Devices → Bulk Add → Device Page → VLAN → Push by IP → Template Push → Software Upload → Inventory → SFP Inventory

---

## 1. Bulk Add Devices — `/addbulkhost`

**What it does:** Add many devices at one time using a CSV file.

**What to show**
- Only `hostname` is needed. Other columns are optional.
- **Download CSV Template**: get a ready file to fill.
- **Preview only**: check the file first. Nothing is added yet.
- **Upload and Add Devices**: add all devices in one click.

**What to say**
> "Adding switches one by one takes a lot of time. Here we fill one CSV file and upload it. First we check it with Preview. If it is OK, we upload and all devices are added together."

**Sample CSV (`demo_devices.csv`)**
```csv
hostname,snmpver,community,ssh_user,ssh_pass,os,force_add,snmp_disable
192.168.50.11,v2c,demo_ro,admin,admin,,,
192.168.50.12,v2c,demo_ro,admin,admin,,,
192.168.50.13,v2c,demo_ro,admin,admin,,yes,
192.168.50.20,,,,,,yes,yes
```

**Main point:** *"Add 10 or 1,000 devices in one upload."*

---

## 2. Push Config by IP — `/addhost/ip`

**What it does:** Send the same commands to many devices. You just type their IPs.

**What to show**
- Type one IP per line.
- Load a saved template or file, or type the commands.
- Login details are taken from the system automatically.
- **Save as Template**: keep the commands for next time.

**What to say**
> "We want the same NTP server on all switches. We type the IPs, type the commands once, and click **Process Devices**. Telequill sends it to every switch."

**Sample IP list**
```text
# Demo Site A switches
192.168.200.239
192.168.200.241
192.168.200.244
```

**Sample commands**
```text
configure terminal
ntp server 192.168.100.10
banner motd #Authorized access only - Telequill Demo#
exit
write
```

**Template name:** `Demo_NTP_Banner`

**Main point:** *"One change, many devices, one click."*

---

## 3. Template Push — `/addhost/template`

**What it does:** Fill a simple form. Telequill makes the commands for you.

**What to show**
- Pick a device. Only templates for that device model are shown.
- Ready templates, for example:
  - *Tag traffic to single S-vlan in AS210T*
  - *Creating the LAG with Trunk vlans in AS20012XTS*
  - *Rate Limit in AS200 series on Interface*
  - *Reboot the switch*
- **Commands Preview**: see the commands before sending.
- **Template Builder**: make your own form.
- **View Push Logs**: see what was sent and when.

**What to say**
> "The engineer does not need to remember commands. Pick the device, pick the template, fill two or three boxes, and the commands are ready. Click to send. Everything is saved in the logs."

**Sample values (Tag traffic to single S-vlan)**
| Box | Value |
|---|---|
| Device | as228t_test_lab (192.168.200.244) |
| Interface | GigaEthernet0/5 |
| S-VLAN ID | 300 |
| Description | DEMO-CUSTOMER |

**Main point:** *"Easy and correct config, even for new engineers."*

---

## 4. Software Upload — `/syssoftbulk`

**What it does:** Upgrade firmware on many devices at once, model by model.

**What to show**
- Models in the network: **AS200/12/XF, AS200/12/XTS-2AC, AS210T, AS228T, Generic x86**.
- Select models (hold Ctrl), click **Load Upload Fields**, then add the file for each model.

**What to say**
> "Upgrading each switch by hand is slow. Here we pick the models, add the firmware file, and Telequill upgrades all matching switches."

**Sample values**
| Model | File |
|---|---|
| AS210T mother card | `AS210T_2.2.0F.bin` |
| AS228T mother card | `AS228T_2.2.0F.bin` |

> Tip: stop at the upload screen. Do not really upgrade lab switches in the demo.

**Main point:** *"Upgrade the whole network in one go."*

---

## 5. Inventory — `/inventory`

**What it does:** Shows all hardware parts of every device, with serial numbers and versions.

**What to show**
- Filter by Part, Part No or Device, or use Search.
- Examples:
  - `asss (192.168.200.241)`: AS210T, Serial **G20009088521**
  - `as228t_test_lab (192.168.200.244)`: AS228T, Serial **G20008059033**
- 124 items, all collected automatically.

**What to say**
> "Need all serial numbers for an audit? No Excel needed. Telequill reads them from the devices. Just search or filter."

**Try searching:** `AS210T`, `G2000`

**Main point:** *"Always up-to-date list of all hardware."*

---

## 6. SFP Inventory — `/sfp-inventory`

**What it does:** Shows every SFP / optical module: which device, which port, model, serial number and distance.

**What to show**
- Top boxes: **4 SFPs**, **2 vendors**, **2 devices with SFP**.
- Filter by Device, Vendor or Type.
- **Export CSV** to download the list.

**Data on screen**
| Device | Port | Model | Distance |
|---|---|---|---|
| as212xf_lab | TGigaEthernet0/6 | ASFP-1G-LX | 20 km |
| gayatri | GigaEthernet0/9 | ASFP-1G-T-I-CER | 550 m |
| as212xf_lab | TGigaEthernet0/3 | ASFP-1G-LX3155B | 10 km |
| as212xf_lab | TGigaEthernet0/9 | ASFP-1G-LX3155B | 10 km |

**What to say**
> "SFPs are costly and easy to lose track of. Here we see every one: where it is and what type it is. Click Export and the report is ready."

**Main point:** *"Know every SFP without going to the rack."*

---

## 7. Devices — `/devices`

**What it does:** List of all devices with model, software version and uptime.

**What to show**
- 7 devices:

| Name | IP | Model | Uptime |
|---|---|---|---|
| gayatri | 10.10.10.1 | AS210T | 29m |
| as212xf_lab | 192.168.2.248 | AS200/12/XF | 1 month |
| Alpha_Bridge | 192.168.200.239 | AS200/12/XTS-2AC | 2 weeks |
| Switch | 192.168.200.240 | Generic | 1 week |
| asss | 192.168.200.241 | AS210T | 2 months |
| as228t_test_lab | 192.168.200.244 | AS228T | 1 month |
| ISPL | 192.168.90.122 | Linux server | 1 week |

- Graph views at the top: CPU, Memory, Ping, Temperature and more.

**What to say**
> "This is the full network on one screen. We can see every device, its model, version and how long it has been up. Click **CPU** to see the load of all devices together."

**Main point:** *"Whole network in one view."*

---

## 8. Device Page — `/device/29` (gayatri, 10.10.10.1)

**What it does:** Full details and health of one device.

**What to show**
- Tabs: Overview, Graphs, Health, Ports, VLANs, Inventory, Logs, Alerts, Configuration.
- Model **AS210T**, Serial **G20009088521**, Version **2.2.0D**.
- Ports: **15 total, 4 up, 11 down**.
- Health: CPU **7%**, Temp **33 °C**, all fans **Normal**, Power **Normal**.
- SFP on Gi0/9: signal **-40 dBm**, status **LOS lost** (no light).
- **Recent Events**: alerts like *Device Down* and *Ping Latency*.

**What to say**
> "Click one device and see everything: fans, power, temperature, CPU. Look at port Gi0/9: there is no light on the fibre. Telequill already sent an alert, so we know about the problem before the customer calls."

> Tip: this device had alerts today. Check it is up before the demo.

**Main point:** *"Find the problem in one click."*

---

## 9. VLAN Configuration — `/device/29/configuration/vlansconfig`

**What it does:** Add, edit and delete VLANs from the browser. No CLI needed.

**What to show**
- Menu: VLAN Configuration, VLAN Batch Configuration, Interface VLAN Attribute, Voice VLAN.
- **Add**, **Edit**, **Batch Delete**. The default VLAN cannot be deleted.
- Right now the list is empty, a good start for the demo.

**What to say**
> "Click **Add** and create VLAN 100. Use **Batch** to create VLANs 200 to 210 in one step. Then put port Gi0/5 in VLAN 100. All from the browser."

**Sample VLANs**
| VLAN ID | Name |
|---|---|
| 100 | DEMO-DATA |
| 110 | DEMO-VOICE |
| 120 | DEMO-CCTV |
| 200–210 | DEMO-CUST (batch) |
| 999 | DEMO-MGMT |

**Sample port setting**
| Port | Mode | VLAN |
|---|---|---|
| GigaEthernet0/5 | Access | 100 |
| GigaEthernet0/1 | Trunk | 100,110,120,999 |

**Main point:** *"Make VLANs easily from the browser."*

---

### Closing line
> "With Telequill you can **add** devices fast, **see** all devices and parts, **configure** with simple forms, **upgrade** software in bulk, and **find problems** early, all from one place."

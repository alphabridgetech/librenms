#!/usr/bin/env python3
import sys
import os
import subprocess
import csv
from datetime import datetime

def export_rrd(rrd_file, start_time="-7d", output_csv="readable_traffic_report.csv", device_name="Unknown", port_name="Unknown"):
    if not os.path.exists(rrd_file):
        print(f"Error: File '{rrd_file}' not found.")
        sys.exit(1)

    cmd = ['rrdtool', 'fetch', rrd_file, 'AVERAGE', '--start', start_time]
    res = subprocess.run(cmd, capture_output=True, text=True)
    if res.returncode != 0:
        print(f"Error executing rrdtool: {res.stderr}")
        sys.exit(1)

    lines = res.stdout.strip().split('\n')
    if not lines or len(lines) < 3:
        print("No data found in RRD file for the specified time range.")
        sys.exit(1)

    headers = lines[0].split()

    with open(output_csv, 'w', newline='') as f:
        writer = csv.writer(f)
        
        # Meta headers for readability
        writer.writerow(["Device Name / IP", device_name])
        writer.writerow(["Port Name / ID", port_name])
        writer.writerow(["RRD Source File", rrd_file])
        writer.writerow([]) # Blank separator row

        # Table Header
        writer.writerow([
            "Date & Time",
            "In Traffic (Mbps)",
            "Out Traffic (Mbps)",
            "In Traffic (Bytes/sec)",
            "Out Traffic (Bytes/sec)",
            "In Errors",
            "Out Errors"
        ])

        valid_rows = 0
        for line in lines[2:]:
            parts = line.split()
            if not parts:
                continue
            ts_str = parts[0].replace(':', '')
            try:
                ts = int(ts_str)
                dt_str = datetime.fromtimestamp(ts).strftime('%Y-%m-%d %H:%M:%S')
            except ValueError:
                continue

            vals = parts[1:]
            
            # Extract INOCTETS (val[0]) and OUTOCTETS (val[1])
            in_bytes_sec = float(vals[0]) if (len(vals) > 0 and vals[0] not in ['-nan', 'nan']) else 0.0
            out_bytes_sec = float(vals[1]) if (len(vals) > 1 and vals[1] not in ['-nan', 'nan']) else 0.0
            in_errors = float(vals[2]) if (len(vals) > 2 and vals[2] not in ['-nan', 'nan']) else 0.0
            out_errors = float(vals[3]) if (len(vals) > 3 and vals[3] not in ['-nan', 'nan']) else 0.0

            # Convert Bytes/sec to Mbps
            in_mbps = round((in_bytes_sec * 8) / 1000000.0, 4)
            out_mbps = round((out_bytes_sec * 8) / 1000000.0, 4)

            # Skip rows where all metrics are 0 / nan (optional, keep if valid timestamp)
            if vals[0] not in ['-nan', 'nan'] or vals[1] not in ['-nan', 'nan']:
                writer.writerow([
                    dt_str,
                    in_mbps,
                    out_mbps,
                    round(in_bytes_sec, 2),
                    round(out_bytes_sec, 2),
                    int(in_errors),
                    int(out_errors)
                ])
                valid_rows += 1

    print(f"Successfully generated '{output_csv}' with {valid_rows} data points.")

if __name__ == "__main__":
    rrd_path = sys.argv[1] if len(sys.argv) > 1 else "/opt/librenms/rrd/192.168.200.244/port-id4505.rrd"
    start = sys.argv[2] if len(sys.argv) > 2 else "-7d"
    output = sys.argv[3] if len(sys.argv) > 3 else "readable_traffic_report.csv"
    dev = sys.argv[4] if len(sys.argv) > 4 else "192.168.200.244"
    port = sys.argv[5] if len(sys.argv) > 5 else "port-id4505"

    export_rrd(rrd_path, start, output, dev, port)

#!/usr/bin/env python3
from flask import Flask, request, jsonify
from flask_cors import CORS
import requests

LIBRENMS_URL   = "http://172.18.0.5:8000"
LIBRENMS_TOKEN = "96c999c679ca673c0b3b5910c0e1b581"
GEMINI_API_KEY = "AIzaSyDMYopkI7e4B1zHuSgTRu7wWcxaQjWKTko"

app = Flask(__name__)
CORS(app)

HEADERS = {"X-Auth-Token": LIBRENMS_TOKEN}

# ---------------- Helper Functions ----------------
def fetch_librenms_data(endpoint):
    """Fetch data from LibreNMS API for given endpoint."""
    try:
        r = requests.get(f"{LIBRENMS_URL}/api/v0/{endpoint}", headers=HEADERS, timeout=10)
        if r.ok:
            return r.json()
        return {"error": f"LibreNMS API returned {r.status_code}"}
    except Exception as e:
        return {"error": str(e)}

# ---------------- Gemini API ----------------
def query_gemini(prompt):
    url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent"
    payload = {"contents": [{"parts": [{"text": prompt}]}]}
    headers = {
        "Content-Type": "application/json",
        "X-goog-api-key": GEMINI_API_KEY
    }
    try:
        r = requests.post(url, headers=headers, json=payload, timeout=15)
        if r.ok:
            resp_json = r.json()
            text = resp_json.get("candidates", [{}])[0].get("content", "")
            return text if text else "No response from Gemini."
        return f"Gemini API error: {r.status_code} {r.text}"
    except Exception as e:
        return f"Exception contacting Gemini API: {e}"

# ---------------- Flask Routes ----------------
@app.route("/ping")
def ping():
    return "pong"

@app.route("/ask", methods=["POST"])
def ask():
    data = request.json or {}
    question = data.get("question", "")
    devices_data = fetch_librenms_data("devices")
    
    devices_text = ""
    if "devices" in devices_data:
        devices_text = "\n".join(
            f"{d.get('hostname', 'Unknown')} ({d.get('ip', 'Unknown')}) - status: {d.get('status', 'Unknown')}"
            for d in devices_data["devices"]
        )
    
    prompt = f"LibreNMS Device Data:\n{devices_text}\n\nUser question: {question}\nAnswer concisely and clearly."
    answer = query_gemini(prompt)
    return jsonify({"answer": answer})

# ---------------- LibreNMS Endpoints ----------------
@app.route("/devices")
def get_devices():
    return jsonify(fetch_librenms_data("devices"))

@app.route("/devicegroups")
def get_devicegroups():
    return jsonify(fetch_librenms_data("devicegroups"))

@app.route("/ports")
def get_ports():
    return jsonify(fetch_librenms_data("ports"))

@app.route("/portgroups")
def get_portgroups():
    return jsonify(fetch_librenms_data("portgroups"))

@app.route("/alerts")
def get_alerts():
    return jsonify(fetch_librenms_data("alerts"))

@app.route("/routing")
def get_routing():
    return jsonify(fetch_librenms_data("routing"))

@app.route("/switching")
def get_switching():
    return jsonify(fetch_librenms_data("switching"))

@app.route("/inventory")
def get_inventory():
    return jsonify(fetch_librenms_data("inventory"))

@app.route("/bills")
def get_bills():
    return jsonify(fetch_librenms_data("bills"))

@app.route("/arp")
def get_arp():
    return jsonify(fetch_librenms_data("arp"))

@app.route("/services")
def get_services():
    return jsonify(fetch_librenms_data("services"))

@app.route("/logs")
def get_logs():
    return jsonify(fetch_librenms_data("logs"))

@app.route("/system")
def get_system():
    return jsonify(fetch_librenms_data("system"))

@app.route("/locations")
def get_locations():
    return jsonify(fetch_librenms_data("locations"))

# ---------------- Run Server ----------------
if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000)

#!/usr/bin/env python3
from flask import Flask, request, jsonify
from flask_cors import CORS
import requests, json, re

# -------- CONFIGURATION --------
TELEQUILL_URL = "http://127.0.0.1:8000"
GEMINI_API_KEY = "AIzaSyDMYopkI7e4B1zHuSgTRu7wWcxaQjWKTko"

# -------- FLASK APP --------
app = Flask(__name__)
CORS(app)

# -------- HELPER FUNCTIONS --------
def fetch_telequill_data(endpoint, token):
    headers = {"X-Auth-Token": token}
    try:
        r = requests.get(f"{TELEQUILL_URL}/api/v0/{endpoint}", headers=headers, timeout=10)
        if r.ok:
            return r.json()
        return {"error": f"Telequill API returned {r.status_code}: {r.text}"}
    except Exception as e:
        return {"error": str(e)}

def add_device(device_info, token):
    headers = {"X-Auth-Token": token, "Content-Type": "application/json"}
    payload = {"hostname": device_info}
    try:
        r = requests.post(f"{TELEQUILL_URL}/api/v0/devices", headers=headers, json=payload, timeout=10)
        if r.ok:
            return f"Device '{device_info}' added successfully."
        return f"Failed to add device '{device_info}': {r.status_code} {r.text}"
    except Exception as e:
        return f"Exception while adding device: {e}"

def delete_device(device_info, token):
    headers = {"X-Auth-Token": token}
    try:
        r = requests.delete(f"{TELEQUILL_URL}/api/v0/devices/{device_info}", headers=headers, timeout=10)
        if r.ok:
            return f"Device '{device_info}' deleted successfully."
        return f"Failed to delete device '{device_info}': {r.status_code} {r.text}"
    except Exception as e:
        return f"Exception while deleting device: {e}"

def query_gemini(prompt):
    url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent"
    payload = {"contents": [{"parts": [{"text": prompt}]}]}
    headers = {"Content-Type": "application/json", "X-goog-api-key": GEMINI_API_KEY}
    try:
        r = requests.post(url, headers=headers, json=payload, timeout=15)
        if r.ok:
            resp_json = r.json()
            if "candidates" in resp_json and resp_json["candidates"]:
                parts = resp_json["candidates"][0].get("content", {}).get("parts", [])
                return "\n".join(p.get("text", "") for p in parts)
            return "No response from Gemini."
        return f"Gemini API error: {r.status_code} {r.text}"
    except Exception as e:
        return f"Exception contacting Gemini API: {e}"

# -------- NATURAL LANGUAGE DETECTION --------
def detect_action(question):
    """
    Detect add/delete commands anywhere in the sentence and extract IP/hostname.
    Returns action and the device info.
    """
    q = question.lower()
    
    # Match patterns like "add device 192.168.200.244" anywhere
    add_match = re.search(r'add\s+device\s+([^\s]+)', q)
    del_match = re.search(r'(delete|remove)\s+device\s+([^\s]+)', q)

    if add_match:
        return "add_device", add_match.group(1).strip()
    if del_match:
        return "delete_device", del_match.group(2).strip()
    return "info", ""

# -------- FLASK ROUTES --------
@app.route("/ping")
def ping():
    return "pong"

@app.route("/ask", methods=["POST"])
def ask():
    user_token = request.headers.get("Authorization", "").replace("Bearer ", "")
    if not user_token:
        return jsonify({"answer": "No API token provided."}), 401

    data = request.json or {}
    question = data.get("question", "").strip()
    if not question:
        return jsonify({"answer": "Please type a question."}), 400

    action, device_info = detect_action(question)

    # Handle add/delete device commands
    if action == "add_device" and device_info:
        return jsonify({"answer": add_device(device_info, user_token)})
    if action == "delete_device" and device_info:
        return jsonify({"answer": delete_device(device_info, user_token)})

    # Default: ask Gemini LLM with Telequill context
    devices_data = fetch_telequill_data("devices", user_token)
    devices_text = json.dumps(devices_data, indent=2)
    prompt = f"Answer the user question: \"{question}\" concisely.\n\nTelequill Device Data:\n{devices_text}"
    llm_answer = query_gemini(prompt)
    return jsonify({"answer": llm_answer})

@app.route("/devices")
def get_devices():
    token = request.headers.get("Authorization", "").replace("Bearer ", "")
    return jsonify(fetch_telequill_data("devices", token))

@app.route("/ports")
def get_ports():
    token = request.headers.get("Authorization", "").replace("Bearer ", "")
    return jsonify(fetch_telequill_data("ports", token))

# -------- RUN SERVER --------
if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000)

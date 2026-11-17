import time
import json
import urllib.request
import urllib.error
import subprocess

BASE_URL = "http://localhost/NETGUARD/"
CHECK_INTERVAL = 5
FAILURE_LIMIT = 2

# Tracks the condition of each registered device.
device_states = {}


def send_request(endpoint, data=None):
    url = BASE_URL + endpoint

    payload = None

    if data is not None:
        payload = json.dumps(data).encode("utf-8")

    request = urllib.request.Request(
        url,
        data=payload,
        headers={"Content-Type": "application/json"},
        method="POST" if payload is not None else "GET"
    )

    with urllib.request.urlopen(request, timeout=10) as response:
        return json.loads(response.read().decode("utf-8"))


def get_devices():
    result = send_request("get_devices.php")

    if not result.get("success"):
        raise Exception("Could not retrieve registered devices")

    return result.get("devices", [])


def device_available(ip_address):
    try:
        result = subprocess.run(
            ["ping", "-n", "1", "-w", "1500", ip_address],
            capture_output=True,
            text=True,
            timeout=3
        )

        return result.returncode == 0

    except Exception:
        return False


def record_fault(device):
    result = send_request("receive_fault.php", {
        "device_id": int(device["id"]),
        "fault_type": "Internet Connection Failure",
        "description": (
            "NETGUARD detected an internet connection failure "
            "on " + device["device_name"]
        )
    })

    print("FAULT RESULT:", result)

    return result.get("success", False)


def recover_device(device):
    result = send_request("recover_fault.php", {
        "device_id": int(device["id"])
    })

    print("RECOVERY RESULT:", result)

    return result.get("success", False)


print("NETGUARD automatic device monitoring started...")

while True:
    try:
        devices = get_devices()

        if not devices:
            print("No registered devices found.")

        for device in devices:
            device_id = int(device["id"])
            device_name = device["device_name"]
            ip_address = device["ip_address"]

            if device_id not in device_states:
                device_states[device_id] = {
                    "failed_checks": 0,
                    "offline": False
                }

            state = device_states[device_id]

            if device_available(ip_address):

                state["failed_checks"] = 0

                if state["offline"]:
                    print(device_name, "is ONLINE again.")

                    if recover_device(device):
                        state["offline"] = False
                        print(device_name, "RECOVERED")

                else:
                    print(device_name, "ONLINE")

            else:

                state["failed_checks"] += 1

                print(
                    device_name,
                    "OFFLINE:",
                    state["failed_checks"]
                )

                if (
                    state["failed_checks"] >= FAILURE_LIMIT
                    and not state["offline"]
                ):

                    if record_fault(device):
                        state["offline"] = True
                        print("FAULT RECORDED:", device_name)

    except urllib.error.URLError as error:
        print("CONNECTION ERROR:", error)

    except Exception as error:
        print("MONITORING ERROR:", error)

    time.sleep(CHECK_INTERVAL)
import csv
import os
from datetime import datetime

ATTENDANCE_FILE = "attendance.csv"


def ensure_attendance_file():
    if not os.path.exists(ATTENDANCE_FILE):
        with open(ATTENDANCE_FILE, "w", newline="") as file:
            writer = csv.writer(file)
            writer.writerow([
                "employee_id",
                "action",
                "timestamp"
            ])


def log_attendance(employee_id, action):
    ensure_attendance_file()

    timestamp = datetime.now().isoformat(timespec="seconds")

    with open(ATTENDANCE_FILE, "a", newline="") as file:
        writer = csv.writer(file)

        writer.writerow([
            employee_id,
            action,
            timestamp
        ])

    return timestamp
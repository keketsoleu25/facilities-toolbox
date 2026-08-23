import csv
import os
from datetime import datetime


class AttendanceService:
    def __init__(self, path="attendance.csv"):
        self.path = path
        self.ensure_file()

    def ensure_file(self):
        if not os.path.exists(self.path):
            with open(
                self.path,
                "w",
                newline=""
            ) as file:
                writer = csv.writer(file)

                writer.writerow([
                    "employee_id",
                    "action",
                    "timestamp"
                ])

    def get_last_action(self, employee_id):
        last_action = None

        with open(
            self.path,
            "r",
            newline=""
        ) as file:
            reader = csv.DictReader(file)

            for row in reader:
                if row["employee_id"] == employee_id:
                    last_action = row["action"]

        return last_action

    def log(self, employee_id, action):
        last_action = self.get_last_action(
            employee_id
        )

        if last_action == action:
            return None

        timestamp = datetime.now().isoformat(
            timespec="seconds"
        )

        with open(
            self.path,
            "a",
            newline=""
        ) as file:
            writer = csv.writer(file)

            writer.writerow([
                employee_id,
                action,
                timestamp
            ])

        return timestamp
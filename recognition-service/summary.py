import csv
import json
import os
from collections import defaultdict
from datetime import datetime


# --------------------------------------------------
# File locations
# --------------------------------------------------

ATTENDANCE_FILE = "attendance.csv"
EMPLOYEES_FILE = "employees.json"


# --------------------------------------------------
# Validate required files
# --------------------------------------------------

if not os.path.exists(ATTENDANCE_FILE):
    print("attendance.csv not found.")
    raise SystemExit

if not os.path.exists(EMPLOYEES_FILE):
    print("employees.json not found.")
    raise SystemExit


# --------------------------------------------------
# Load employee profiles
# --------------------------------------------------

with open(
    EMPLOYEES_FILE,
    "r",
    encoding="utf-8"
) as file:
    employees = json.load(file)


# --------------------------------------------------
# Group attendance events by employee and date
# --------------------------------------------------

daily_records = defaultdict(list)

with open(
    ATTENDANCE_FILE,
    "r",
    newline="",
    encoding="utf-8"
) as file:

    reader = csv.DictReader(file)

    for row in reader:

        # Convert stored timestamp into a Python datetime.
        timestamp = datetime.fromisoformat(
            row["timestamp"]
        )

        # Create a date key such as 2026-08-22.
        date_key = timestamp.strftime(
            "%Y-%m-%d"
        )

        employee_id = row["employee_id"]

        # Store the parsed event for this employee/date.
        daily_records[
            (employee_id, date_key)
        ].append({
            "action": row["action"],
            "timestamp": timestamp
        })


# --------------------------------------------------
# Display summary heading
# --------------------------------------------------

print()
print("=" * 100)
print("FACILITIES TOOLBOX - DAILY ATTENDANCE SUMMARY")
print("=" * 100)

print(
    f"{'EMPLOYEE':<22}"
    f"{'ID':<12}"
    f"{'DATE':<14}"
    f"{'FIRST IN':<14}"
    f"{'LAST OUT':<14}"
    f"{'STATUS':<14}"
    f"{'EVENTS':<8}"
)

print("-" * 100)


# --------------------------------------------------
# Calculate one summary row per employee/day
# --------------------------------------------------

for (
    employee_id,
    date_key
), events in sorted(
    daily_records.items()
):

    # Load employee profile details.
    profile = employees.get(
        employee_id,
        {}
    )

    employee_name = profile.get(
        "name",
        "Unknown"
    )


    # --------------------------------------------------
    # Find all IN and OUT events separately
    # --------------------------------------------------

    clock_ins = [
        event["timestamp"]
        for event in events
        if event["action"] == "IN"
    ]

    clock_outs = [
        event["timestamp"]
        for event in events
        if event["action"] == "OUT"
    ]


    # --------------------------------------------------
    # Find first clock-in
    # --------------------------------------------------

    if clock_ins:
        first_in = min(
            clock_ins
        ).strftime("%H:%M:%S")
    else:
        first_in = "-"


    # --------------------------------------------------
    # Find latest clock-out
    # --------------------------------------------------

    if clock_outs:
        last_out = max(
            clock_outs
        ).strftime("%H:%M:%S")
    else:
        last_out = "-"


    # --------------------------------------------------
    # Determine current attendance state
    # --------------------------------------------------

    # Sort all events by timestamp so the latest event
    # determines whether the employee is currently IN
    # or OUT.
    events.sort(
        key=lambda event: event["timestamp"]
    )

    latest_action = events[-1]["action"]

    if latest_action == "IN":
        status = "CLOCKED IN"
    else:
        status = "CLOCKED OUT"


    # --------------------------------------------------
    # Display summary row
    # --------------------------------------------------

    print(
        f"{employee_name:<22}"
        f"{employee_id:<12}"
        f"{date_key:<14}"
        f"{first_in:<14}"
        f"{last_out:<14}"
        f"{status:<14}"
        f"{len(events):<8}"
    )


# --------------------------------------------------
# Footer
# --------------------------------------------------

print("-" * 100)

print(
    f"Employee/day records: "
    f"{len(daily_records)}"
)

print("=" * 100)
print()
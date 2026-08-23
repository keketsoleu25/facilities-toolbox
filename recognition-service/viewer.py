import csv
import json
import os
from datetime import datetime


# --------------------------------------------------
# File paths used by the attendance viewer
# --------------------------------------------------

ATTENDANCE_FILE = "attendance.csv"
EMPLOYEES_FILE = "employees.json"


# --------------------------------------------------
# Validate required files
# --------------------------------------------------

# The viewer needs attendance.csv to show attendance history.
if not os.path.exists(ATTENDANCE_FILE):
    print("attendance.csv not found.")
    print("Clock an employee IN or OUT first.")
    raise SystemExit


# The viewer needs employees.json so employee IDs
# can be translated into readable names and roles.
if not os.path.exists(EMPLOYEES_FILE):
    print("employees.json not found.")
    raise SystemExit


# --------------------------------------------------
# Load employee profile data
# --------------------------------------------------

with open(
    EMPLOYEES_FILE,
    "r",
    encoding="utf-8"
) as file:
    employees = json.load(file)


# --------------------------------------------------
# Load attendance records
# --------------------------------------------------

attendance_records = []

with open(
    ATTENDANCE_FILE,
    "r",
    newline="",
    encoding="utf-8"
) as file:

    reader = csv.DictReader(file)

    for row in reader:
        attendance_records.append(row)


# --------------------------------------------------
# Handle empty attendance history
# --------------------------------------------------

if not attendance_records:
    print("No attendance records found.")
    raise SystemExit


# --------------------------------------------------
# Display viewer heading
# --------------------------------------------------

print()
print("=" * 90)
print("FACILITIES TOOLBOX - ATTENDANCE VIEWER")
print("=" * 90)

print(
    f"{'EMPLOYEE':<22}"
    f"{'ID':<12}"
    f"{'ACTION':<10}"
    f"{'DATE':<14}"
    f"{'TIME':<12}"
    f"{'ROLE':<20}"
)

print("-" * 90)


# --------------------------------------------------
# Display each attendance record
# --------------------------------------------------

for record in attendance_records:

    # Read the employee ID stored in attendance.csv.
    employee_id = record["employee_id"]


    # Look up the corresponding employee profile.
    profile = employees.get(
        employee_id,
        {}
    )


    # Use readable profile values where possible.
    employee_name = profile.get(
        "name",
        "Unknown"
    )

    role = profile.get(
        "role",
        "Unknown"
    )


    # Read attendance action.
    action = record["action"]


    # Convert the ISO timestamp stored in the CSV
    # into separate date and time values.
    timestamp = datetime.fromisoformat(
        record["timestamp"]
    )

    attendance_date = timestamp.strftime(
        "%Y-%m-%d"
    )

    attendance_time = timestamp.strftime(
        "%H:%M:%S"
    )


    # Print one formatted attendance row.
    print(
        f"{employee_name:<22}"
        f"{employee_id:<12}"
        f"{action:<10}"
        f"{attendance_date:<14}"
        f"{attendance_time:<12}"
        f"{role:<20}"
    )


# --------------------------------------------------
# Display summary
# --------------------------------------------------

print("-" * 90)

print(
    f"Total attendance events: "
    f"{len(attendance_records)}"
)

print("=" * 90)
print()
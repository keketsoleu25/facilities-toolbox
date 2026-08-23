import json
import os


class EmployeeRepository:
    def __init__(self, path="employees.json"):
        if not os.path.exists(path):
            raise FileNotFoundError(
                "employees.json not found."
            )

        with open(path, "r") as file:
            self.employees = json.load(file)

    def get(self, employee_id):
        return self.employees.get(employee_id)

    def get_name(self, employee_id):
        employee = self.get(employee_id)

        if not employee:
            return "Unknown"

        return employee.get("name", "Unknown")
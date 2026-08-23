import requests


# --------------------------------------------------
# Attendance API configuration
# --------------------------------------------------

# Base URL of the ASP.NET Core Facilities API.
#
# Later this should move into an environment variable
# instead of being hard-coded.
API_BASE_URL = "http://localhost:5209"

# Full attendance endpoint used by the recognition service.
ATTENDANCE_ENDPOINT = f"{API_BASE_URL}/api/attendance"


# --------------------------------------------------
# Attendance API client
# --------------------------------------------------

class AttendanceApiClient:
    """
    Sends attendance events from the Python recognition
    service to the C# Facilities API.

    Python is responsible for identifying the employee.

    C# is responsible for validating and storing
    attendance business events.
    """

    def log(self, employee_id, action):
        """
        Send one attendance event to the C# API.

        Example:

            employee_id = EMP001
            action = IN

        The API decides whether the event is valid.
        """

        # Build the JSON body expected by the C# endpoint.
        payload = {
            "employeeId": employee_id,
            "action": action
        }

        try:
            # Send the attendance event to ASP.NET Core.
            response = requests.post(
                ATTENDANCE_ENDPOINT,
                json=payload,
                timeout=5
            )

        except requests.ConnectionError:
            # This normally means the C# API is not running
            # or localhost:5209 cannot be reached.
            return {
                "success": False,
                "message": "Facilities API is unavailable."
            }

        except requests.RequestException as exception:
            # Catch other networking failures without
            # crashing the camera application.
            return {
                "success": False,
                "message": f"API request failed: {exception}"
            }


        # --------------------------------------------------
        # Successful attendance event
        # --------------------------------------------------

        if response.status_code == 200:

            data = response.json()

            return {
                "success": True,
                "record": data,
                "message": (
                    f"{employee_id} clocked {action} successfully."
                )
            }


        # --------------------------------------------------
        # Business-rule conflict
        # --------------------------------------------------

        if response.status_code == 409:

            try:
                data = response.json()

                message = data.get(
                    "error",
                    "Attendance conflict."
                )

            except ValueError:
                message = "Attendance conflict."

            return {
                "success": False,
                "message": message
            }


        # --------------------------------------------------
        # Invalid input
        # --------------------------------------------------

        if response.status_code == 400:

            try:
                data = response.json()

                message = data.get(
                    "error",
                    "Invalid attendance request."
                )

            except ValueError:
                message = "Invalid attendance request."

            return {
                "success": False,
                "message": message
            }


        # --------------------------------------------------
        # Unexpected API response
        # --------------------------------------------------

        return {
            "success": False,
            "message": (
                f"Unexpected API response: "
                f"{response.status_code}"
            )
        }
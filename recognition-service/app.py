import cv2

from recognition.detector import FaceDetector
from recognition.recognizer import FaceRecognizer
from employees.repository import EmployeeRepository
from attendance.api_client import AttendanceApiClient


# --------------------------------------------------
# Initialise application services
# --------------------------------------------------

# Handles face detection from camera frames.
detector = FaceDetector()

# Handles matching detected faces to trained employees.
recognizer = FaceRecognizer()

# Loads employee profile information.
employees = EmployeeRepository()

# Handles attendance logging and duplicate prevention.
attendance = AttendanceApiClient()


# --------------------------------------------------
# Open webcam
# --------------------------------------------------

camera = cv2.VideoCapture(0)

if not camera.isOpened():
    print("Could not open camera.")
    raise SystemExit


# Stores the employee currently recognised on screen.
current_employee = None


# --------------------------------------------------
# Application instructions
# --------------------------------------------------

print("Facilities Toolbox")
print("I = Clock IN")
print("O = Clock OUT")
print("Q = Quit")


# --------------------------------------------------
# Main application loop
# --------------------------------------------------

while True:

    # Read one frame from the webcam.
    success, frame = camera.read()

    if not success:
        print("Could not read camera.")
        break


    # Convert the frame to grayscale.
    # Haar cascades and LBPH recognition work with grayscale images.
    gray = cv2.cvtColor(
        frame,
        cv2.COLOR_BGR2GRAY
    )


    # Detect all faces visible in the current frame.
    faces = detector.detect(gray)


    # Reset the currently recognised employee on each frame.
    current_employee = None


    # --------------------------------------------------
    # Process each detected face
    # --------------------------------------------------

    for x, y, width, height in faces:

        # Crop only the detected face from the grayscale frame.
        face_image = gray[
            y:y + height,
            x:x + width
        ]


        # Ask the recognition service to identify the face.
        employee_id, distance = recognizer.recognize(
            face_image
        )


        # --------------------------------------------------
        # Recognised employee
        # --------------------------------------------------

        if employee_id:

            current_employee = employee_id


            # Load employee profile information.
            profile = employees.get(
                employee_id
            ) or {}


            name = profile.get(
                "name",
                "Unknown"
            )

            department = profile.get(
                "department",
                ""
            )

            role = profile.get(
                "role",
                ""
            )


            # Text displayed above the face.
            display_text = (
                f"{name} | {employee_id}"
            )


            # Green rectangle = recognised employee.
            color = (0, 255, 0)


        # --------------------------------------------------
        # Unknown face
        # --------------------------------------------------

        else:

            department = ""
            role = ""

            display_text = "UNKNOWN"

            # Red rectangle = unknown person.
            color = (0, 0, 255)


        # --------------------------------------------------
        # Draw face information
        # --------------------------------------------------

        # Draw rectangle around detected face.
        cv2.rectangle(
            frame,
            (x, y),
            (x + width, y + height),
            color,
            2
        )


        # Show employee name and ID above the face.
        cv2.putText(
            frame,
            display_text,
            (x, y - 10),
            cv2.FONT_HERSHEY_SIMPLEX,
            0.8,
            color,
            2
        )


        # Show employee department and role below the face.
        if employee_id:

            cv2.putText(
                frame,
                f"{department} - {role}",
                (x, y + height + 25),
                cv2.FONT_HERSHEY_SIMPLEX,
                0.6,
                color,
                2
            )


    # --------------------------------------------------
    # Draw keyboard instructions
    # --------------------------------------------------

    cv2.putText(
        frame,
        "I = IN | O = OUT | Q = QUIT",
        (20, 35),
        cv2.FONT_HERSHEY_SIMPLEX,
        0.7,
        (255, 255, 255),
        2
    )


    # Show the final camera frame.
    cv2.imshow(
        "Facilities Toolbox",
        frame
    )


    # --------------------------------------------------
    # Handle keyboard controls
    # --------------------------------------------------

    key = cv2.waitKey(1) & 0xFF

    # --------------------------------------------------
    # Clock IN
    # --------------------------------------------------

    if key in (ord("i"), ord("I")):

        if current_employee:

            result = attendance.log(
                current_employee,
                "IN"
            )

            print(result["message"])

        else:

            print(
                "No recognized employee. "
                "Cannot clock IN."
            )

        

    # --------------------------------------------------
    # Clock OUT
    # --------------------------------------------------

    elif key in (ord("o"), ord("O")):

        if current_employee:

            result = attendance.log(
                current_employee,
                "OUT"
            )

            print(result["message"])

        else:

            print(
                "No recognised employee. "
                "Cannot clock OUT."
            )

    # --------------------------------------------------
    # Quit application
    # --------------------------------------------------

    elif key in (ord("q"), ord("Q")):
        break


# --------------------------------------------------
# Cleanup
# --------------------------------------------------

camera.release()
cv2.destroyAllWindows()

print("Facilities Toolbox closed.")
from attendance import log_attendance
import cv2
import json
import os

EMPLOYEES_PATH = "employees.json"
MODEL_PATH = os.path.join("models", "face_model.yml")
LABELS_PATH = os.path.join("models", "labels.json")


# -----------------------------
# Validate required files
# -----------------------------

if not os.path.exists(EMPLOYEES_PATH):
    print("employees.json not found.")
    raise SystemExit

if not os.path.exists(MODEL_PATH):
    print("Model not found. Run train.py first.")
    raise SystemExit

if not os.path.exists(LABELS_PATH):
    print("Labels file not found. Run train.py first.")
    raise SystemExit


# -----------------------------
# Load employee profiles
# -----------------------------

with open(EMPLOYEES_PATH, "r") as file:
    employees = json.load(file)


# -----------------------------
# Load recognition labels
# -----------------------------

with open(LABELS_PATH, "r") as file:
    raw_labels = json.load(file)

label_map = {
    int(label): employee_id
    for label, employee_id in raw_labels.items()
}


# -----------------------------
# Load recognition model
# -----------------------------

recognizer = cv2.face.LBPHFaceRecognizer_create()
recognizer.read(MODEL_PATH)

face_detector = cv2.CascadeClassifier(
    cv2.data.haarcascades + "haarcascade_frontalface_default.xml"
)


# -----------------------------
# Open camera
# -----------------------------

camera = cv2.VideoCapture(0)

if not camera.isOpened():
    print("Could not open camera.")
    raise SystemExit


current_employee = None

print("Recognition running.")
print("I = Clock IN")
print("O = Clock OUT")
print("Q = Quit")


# -----------------------------
# Main recognition loop
# -----------------------------

while True:
    success, frame = camera.read()

    if not success:
        print("Could not read frame.")
        break

    gray = cv2.cvtColor(
        frame,
        cv2.COLOR_BGR2GRAY
    )

    faces = face_detector.detectMultiScale(
        gray,
        scaleFactor=1.1,
        minNeighbors=5,
        minSize=(80, 80)
    )

    current_employee = None

    for x, y, width, height in faces:
        face_image = gray[
            y:y + height,
            x:x + width
        ]

        label, distance = recognizer.predict(
            face_image
        )

        employee_id = label_map.get(
            label,
            "UNKNOWN"
        )

        if distance < 80:
            current_employee = employee_id

            profile = employees.get(
                employee_id,
                {}
            )

            employee_name = profile.get(
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

            display_text = (
                f"{employee_name} | {employee_id}"
            )

        else:
            employee_name = ""
            department = ""
            role = ""
            display_text = "UNKNOWN"

        # Draw face box
        cv2.rectangle(
            frame,
            (x, y),
            (x + width, y + height),
            (0, 255, 0),
            2
        )

        # Draw employee name / ID
        cv2.putText(
            frame,
            display_text,
            (x, y - 10),
            cv2.FONT_HERSHEY_SIMPLEX,
            0.8,
            (0, 255, 0),
            2
        )

        # Draw employee department / role
        if current_employee:
            cv2.putText(
                frame,
                f"{department} - {role}",
                (x, y + height + 25),
                cv2.FONT_HERSHEY_SIMPLEX,
                0.6,
                (0, 255, 0),
                2
            )


    # Instructions
    cv2.putText(
        frame,
        "I = IN | O = OUT | Q = QUIT",
        (20, 35),
        cv2.FONT_HERSHEY_SIMPLEX,
        0.7,
        (255, 255, 255),
        2
    )


    # Show camera window
    cv2.imshow(
        "Facilities Toolbox - Attendance",
        frame
    )


    # -----------------------------
    # Keyboard controls
    # -----------------------------

    key = cv2.waitKey(1) & 0xFF

    if key in (ord("i"), ord("I")):
        if current_employee:
            timestamp = log_attendance(
                current_employee,
                "IN"
            )

            print(
                f"{current_employee} "
                f"clocked IN at {timestamp}"
            )

        else:
            print(
                "No recognized employee. "
                "Cannot clock IN."
            )

    elif key in (ord("o"), ord("O")):
        if current_employee:
            timestamp = log_attendance(
                current_employee,
                "OUT"
            )

            print(
                f"{current_employee} "
                f"clocked OUT at {timestamp}"
            )

        else:
            print(
                "No recognized employee. "
                "Cannot clock OUT."
            )

    elif key in (ord("q"), ord("Q")):
        break


# -----------------------------
# Cleanup
# -----------------------------

camera.release()
cv2.destroyAllWindows()
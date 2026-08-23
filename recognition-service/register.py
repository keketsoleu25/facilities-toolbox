import cv2
import os

employee_id = input("Employee ID: ").strip()
employee_name = input("Employee name: ").strip()

if not employee_id or not employee_name:
    print("Employee ID and name are required.")
    raise SystemExit

employee_folder = os.path.join("data", "employees", employee_id)
os.makedirs(employee_folder, exist_ok=True)

camera = cv2.VideoCapture(0)

if not camera.isOpened():
    print("Could not open camera.")
    raise SystemExit

face_detector = cv2.CascadeClassifier(
    cv2.data.haarcascades + "haarcascade_frontalface_default.xml"
)

captured = 0
target_images = 20

print(f"Registering {employee_name} ({employee_id})")
print("Look at the camera and move your head slightly.")
print("Press Q to cancel.")

while captured < target_images:
    success, frame = camera.read()

    if not success:
        break

    gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)

    faces = face_detector.detectMultiScale(
        gray,
        scaleFactor=1.1,
        minNeighbors=5,
        minSize=(80, 80)
    )

    for x, y, width, height in faces:
        cv2.rectangle(
            frame,
            (x, y),
            (x + width, y + height),
            (0, 255, 0),
            2
        )

        face_image = gray[y:y + height, x:x + width]

        captured += 1

        filename = os.path.join(
            employee_folder,
            f"{captured:03}.jpg"
        )

        cv2.imwrite(filename, face_image)

        break

    cv2.putText(
        frame,
        f"Captured: {captured}/{target_images}",
        (20, 35),
        cv2.FONT_HERSHEY_SIMPLEX,
        0.8,
        (255, 255, 255),
        2
    )

    cv2.imshow(
        "Facilities Toolbox - Employee Registration",
        frame
    )

    if cv2.waitKey(150) & 0xFF == ord("q"):
        break

camera.release()
cv2.destroyAllWindows()

print(f"Capture complete: {captured} images saved.")
print(f"Location: {employee_folder}")
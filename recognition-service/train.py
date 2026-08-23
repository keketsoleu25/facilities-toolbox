import cv2
import os
import json
import numpy as np

DATA_DIR = os.path.join("data", "employees")
MODEL_DIR = "models"

os.makedirs(MODEL_DIR, exist_ok=True)

recognizer = cv2.face.LBPHFaceRecognizer_create()

faces = []
labels = []

label_map = {}
current_label = 0

if not os.path.exists(DATA_DIR):
    print("No employee data found.")
    raise SystemExit

for employee_id in os.listdir(DATA_DIR):
    employee_folder = os.path.join(DATA_DIR, employee_id)

    if not os.path.isdir(employee_folder):
        continue

    label_map[current_label] = employee_id

    for filename in os.listdir(employee_folder):
        if not filename.lower().endswith((".jpg", ".jpeg", ".png")):
            continue

        image_path = os.path.join(employee_folder, filename)

        image = cv2.imread(image_path, cv2.IMREAD_GRAYSCALE)

        if image is None:
            continue

        faces.append(image)
        labels.append(current_label)

    print(f"Loaded employee: {employee_id}")

    current_label += 1

if not faces:
    print("No valid face images found.")
    raise SystemExit

recognizer.train(
    faces,
    np.array(labels)
)

model_path = os.path.join(
    MODEL_DIR,
    "face_model.yml"
)

recognizer.save(model_path)

labels_path = os.path.join(
    MODEL_DIR,
    "labels.json"
)

with open(labels_path, "w") as file:
    json.dump(label_map, file, indent=4)

print()
print("Training complete.")
print(f"Faces used: {len(faces)}")
print(f"Employees: {len(label_map)}")
print(f"Model saved: {model_path}")
import cv2
import json
import os


class FaceRecognizer:
    def __init__(
        self,
        model_path="models/face_model.yml",
        labels_path="models/labels.json",
        threshold=80
    ):
        if not os.path.exists(model_path):
            raise FileNotFoundError(
                "Recognition model not found. Run train.py first."
            )

        if not os.path.exists(labels_path):
            raise FileNotFoundError(
                "Labels file not found. Run train.py first."
            )

        with open(labels_path, "r") as file:
            raw_labels = json.load(file)

        self.label_map = {
            int(label): employee_id
            for label, employee_id in raw_labels.items()
        }

        self.recognizer = cv2.face.LBPHFaceRecognizer_create()
        self.recognizer.read(model_path)

        self.threshold = threshold

    def recognize(self, face_image):
        label, distance = self.recognizer.predict(face_image)

        employee_id = self.label_map.get(
            label,
            "UNKNOWN"
        )

        if distance < self.threshold:
            return employee_id, distance

        return None, distance
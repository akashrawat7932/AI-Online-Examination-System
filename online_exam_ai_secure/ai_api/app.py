import os
import cv2
import numpy as np
import base64
from flask import Flask, request, jsonify

app = Flask(__name__)
API_TOKEN = os.getenv("AI_API_TOKEN", "CHANGE_THIS_RANDOM_LONG_TOKEN")

# OpenCV Haar Cascade / MediaPipe Fallback setup
face_cascade = cv2.CascadeClassifier(cv2.data.haarcascades + 'haarcascade_frontalface_default.xml')

def authenticate(req):
    auth_header = req.headers.get("Authorization", "")
    return auth_header == f"Bearer {API_TOKEN}"

@app.route("/proctor/analyze-frame", methods=["POST"])
def analyze_frame():
    if not authenticate(request):
        return jsonify({"error": "Unauthorized"}), 401

    data = request.get_json() or {}
    image_data = data.get("image", "")

    if not image_data:
        return jsonify({"error": "No image data provided"}), 400

    try:
        # Decode base64 image frame
        header, encoded = image_data.split(",", 1) if "," in image_data else ("", image_data)
        img_bytes = base64.b64decode(encoded)
        np_arr = np.frombuffer(img_bytes, np.uint8)
        frame = cv2.imdecode(np_arr, cv2.IMREAD_COLOR)

        gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
        faces = face_cascade.detectMultiScale(gray, scaleFactor=1.1, minNeighbors=5, minSize=(30, 30))
        face_count = len(faces)

        risk_score = 0
        events = []

        if face_count == 0:
            events.append("FACE_MISSING")
            risk_score += 5
        elif face_count > 1:
            events.append("MULTIPLE_FACE")
            risk_score += 15

        return jsonify({
            "face_count": face_count,
            "events": events,
            "risk_score": risk_score
        })
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route("/evaluate", methods=["POST"])
def evaluate_descriptive():
    if not authenticate(request):
        return jsonify({"error": "Unauthorized"}), 401

    data = request.get_json() or {}
    question = data.get("question", "")
    student_answer = data.get("answer", "")
    model_answer = data.get("model_answer", "")
    max_marks = float(data.get("max_marks", 10.0))

    if not student_answer.strip():
        return jsonify({"score": 0.0, "feedback": "No answer provided by the student."})

    # Heuristic Similarity & Key Term Coverage Scoring
    answer_words = set(student_answer.lower().split())
    model_words = set(model_answer.lower().split()) if model_answer else set()

    if model_words:
        intersection = answer_words.intersection(model_words)
        match_ratio = len(intersection) / float(len(model_words))
        score = min(max_marks, round(match_ratio * max_marks, 2))
        feedback = f"Matched {len(intersection)} key concepts from the model answer."
    else:
        word_count = len(student_answer.split())
        score = min(max_marks, round((word_count / 40.0) * max_marks, 2))
        feedback = f"Evaluated based on completeness ({word_count} words)."

    return jsonify({
        "score": score,
        "feedback": feedback
    })

if __name__ == "__main__":
    
    app.run(host="127.0.0.1", port=5000, debug=True)
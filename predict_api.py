from datetime import datetime
from pathlib import Path

import joblib
from flask import Flask, jsonify, request
from flask_cors import CORS

app = Flask(__name__)
CORS(app)

MODEL_PATH = Path("organ_priority_model.pkl")
FEATURES_PATH = Path("model_features.pkl")

model = joblib.load(MODEL_PATH) if MODEL_PATH.exists() else None
model_features = joblib.load(FEATURES_PATH) if FEATURES_PATH.exists() else []


def blood_compatible(donor, recipient):
    allowed = {
        "O-": {"O-", "O+", "A-", "A+", "B-", "B+", "AB-", "AB+"},
        "O+": {"O+", "A+", "B+", "AB+"},
        "A-": {"A-", "A+", "AB-", "AB+"},
        "A+": {"A+", "AB+"},
        "B-": {"B-", "B+", "AB-", "AB+"},
        "B+": {"B+", "AB+"},
        "AB-": {"AB-", "AB+"},
        "AB+": {"AB+"},
    }
    return int(str(recipient).upper() in allowed.get(str(donor).upper(), set()))


def city_distance_km(a, b):
    return 10 if str(a).strip().lower() == str(b).strip().lower() else 250


def viability_hours(value):
    if not value:
        return 24
    try:
        return max(0, (datetime.fromisoformat(str(value).replace("T", " ")) - datetime.now()).total_seconds() / 3600)
    except ValueError:
        return 24


def clamp(value, low=0, high=1):
    return max(low, min(high, value))


def score_pair(organ, recipient):
    organ_name = organ.get("organ_type", "")
    needed = recipient.get("organ_needed") or recipient.get("organ_required", "")
    if str(organ_name).strip().lower() != str(needed).strip().lower():
        return None

    blood_ok = blood_compatible(organ.get("blood_group"), recipient.get("blood_group"))
    if not blood_ok:
        return None

    distance = city_distance_km(organ.get("city") or organ.get("donor_city"), recipient.get("city"))
    urgency = clamp(float(recipient.get("urgency_level") or 1) / 5)
    organ_function = clamp(float(organ.get("organ_function_pct") or 85) / 100)
    hla = clamp(float(recipient.get("hla_match_score") or recipient.get("gfr_score") or 70) / 100)
    distance_score = clamp(1 - (distance / 500))
    waiting = clamp(float(recipient.get("waiting_days") or 0) / 365)
    systolic = int(recipient.get("systolic_bp") or 120)
    diastolic = int(recipient.get("diastolic_bp") or 80)
    bp_stable = systolic <= 140 and diastolic <= 90
    bp_severity = 1 if bp_stable else 0.4

    infection = int(recipient.get("infection") or recipient.get("infection_present") or 0)
    previous = int(recipient.get("prev_transplants") or recipient.get("previous_transplants") or 0)
    cardiac_stable = int(recipient.get("cardiac_stable", 1))
    penalty = (0.12 if infection else 0) + (0.08 if previous >= 2 else 0) + (0.06 if not cardiac_stable else 0)

    score = (
        0.30
        + urgency * 0.22
        + organ_function * 0.15
        + hla * 0.12
        + distance_score * 0.10
        + waiting * 0.07
        + bp_severity * 0.04
        - penalty
    ) * 100
    score = round(clamp(score, 0, 100), 2)

    factors = {
        "blood_compatible": True,
        "viability_hours": round(viability_hours(organ.get("viable_until")), 1),
        "urgency_component": round(urgency * 22, 2),
        "organ_function_component": round(organ_function * 15, 2),
        "hla_component": round(hla * 12, 2),
        "distance_component": round(distance_score * 10, 2),
        "waiting_component": round(waiting * 7, 2),
        "bp_severity_component": round(bp_severity * 4, 2),
        "penalty_pct": round(penalty * 100, 2),
    }

    return {
        "recipient_id": int(recipient.get("id") or 0),
        "request_id": int(recipient.get("id") or 0),
        "score": score,
        "distance_km": distance,
        "blood_compatible": 1,
        "city_match": 1 if distance <= 25 else 0,
        "factors": factors,
        "recommendation": "Strong match" if score >= 75 else ("Review match" if score >= 50 else "Low priority"),
    }


@app.route("/match", methods=["POST"])
def match():
    data = request.get_json(force=True)
    organ = data.get("organ", {})
    recipients = data.get("recipients") or data.get("requests") or []
    ranked = [score_pair(organ, recipient) for recipient in recipients]
    ranked = [item for item in ranked if item is not None]
    ranked.sort(key=lambda item: item["score"], reverse=True)
    return jsonify({"matches": ranked[:3], "model_loaded": model is not None})


@app.route("/predict", methods=["POST"])
def predict():
    data = request.get_json(force=True)
    organ = {
        "organ_type": data.get("organ_type"),
        "blood_group": data.get("donor_blood_group"),
        "donor_age": data.get("donor_age"),
        "city": data.get("donor_city"),
    }
    recipient = {
        "id": 0,
        "organ_needed": data.get("organ_type"),
        "blood_group": data.get("recipient_blood_group"),
        "age": data.get("recipient_age"),
        "city": data.get("recipient_city"),
        "urgency_level": data.get("urgency_level", 3),
        "waiting_days": data.get("waiting_days", 0),
    }
    scored = score_pair(organ, recipient)
    return jsonify({"match_score": scored["score"] if scored else 0})


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=5000, debug=True)

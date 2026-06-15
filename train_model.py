from pathlib import Path

import joblib
import pandas as pd
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import accuracy_score
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import LabelEncoder

try:
    from xgboost import XGBClassifier
except Exception:
    XGBClassifier = None

DATA_CANDIDATES = [Path("organ_train.csv"), Path("organ_transplant_dataset.csv")]
FEATURES = [
    "blood_compatible",
    "urgency_level",
    "organ_function_pct",
    "hla_match_score",
    "distance_km",
    "waiting_days",
    "systolic_bp",
    "diastolic_bp",
    "bmi",
    "gfr_score",
    "creatinine_level",
    "dialysis_months",
    "diabetes",
    "hypertension",
    "cardiac_stable",
    "infection_present",
    "previous_transplants",
    "age_difference",
    "organ_encoded",
    "gender_encoded",
]


def find_target(df):
    for name in ["match_label", "priority", "label", "suitable", "approved", "target"]:
        if name in df.columns:
            return name
    for name in ["match_score", "score", "suitability_score"]:
        if name in df.columns:
            df["match_label"] = (pd.to_numeric(df[name], errors="coerce").fillna(0) >= 75).astype(int)
            return "match_label"
    raise ValueError("Training data needs a target column or score column.")


def prepare_dataframe(df):
    df = df.copy()
    encoders = {}

    if "organ_encoded" not in df.columns:
        source = "organ_type" if "organ_type" in df.columns else None
        df["organ_encoded"] = df[source].astype(str) if source else "Unknown"
    if "gender_encoded" not in df.columns:
        source = "gender" if "gender" in df.columns else None
        df["gender_encoded"] = df[source].astype(str) if source else "Unknown"

    for col in ["organ_encoded", "gender_encoded"]:
        if not pd.api.types.is_numeric_dtype(df[col]):
            encoder = LabelEncoder()
            df[col] = encoder.fit_transform(df[col].astype(str))
            encoders[col] = encoder

    defaults = {
        "blood_compatible": 1,
        "urgency_level": 3,
        "organ_function_pct": 85,
        "hla_match_score": 70,
        "distance_km": 250,
        "waiting_days": 0,
        "systolic_bp": 120,
        "diastolic_bp": 80,
        "bmi": 24,
        "gfr_score": 70,
        "creatinine_level": 1.0,
        "dialysis_months": 0,
        "diabetes": 0,
        "hypertension": 0,
        "cardiac_stable": 1,
        "infection_present": 0,
        "previous_transplants": 0,
        "age_difference": 20,
    }
    for col, default in defaults.items():
        if col not in df.columns:
            df[col] = default
        df[col] = pd.to_numeric(df[col], errors="coerce").fillna(default)

    target = find_target(df)
    y = df[target]
    if not pd.api.types.is_numeric_dtype(y):
        target_encoder = LabelEncoder()
        y = target_encoder.fit_transform(y.astype(str))
        encoders["target"] = target_encoder
    return df[FEATURES].astype(float), pd.Series(y).astype(int), encoders


def main():
    data_path = next((path for path in DATA_CANDIDATES if path.exists()), None)
    if data_path is None:
        raise FileNotFoundError("organ_train.csv not found.")

    print(f"Loaded {data_path}")
    df = pd.read_csv(data_path)
    x, y, encoders = prepare_dataframe(df)
    stratify = y if y.nunique() > 1 and y.value_counts().min() >= 2 else None
    x_train, x_test, y_train, y_test = train_test_split(x, y, test_size=0.2, random_state=42, stratify=stratify)

    if XGBClassifier is not None:
        model = XGBClassifier(
            n_estimators=220,
            max_depth=4,
            learning_rate=0.05,
            subsample=0.9,
            colsample_bytree=0.9,
            eval_metric="logloss",
            random_state=42,
        )
    else:
        model = RandomForestClassifier(n_estimators=220, random_state=42)

    model.fit(x_train, y_train)
    predictions = model.predict(x_test)
    print(f"Model: {type(model).__name__}")
    print(f"Accuracy: {accuracy_score(y_test, predictions) * 100:.2f}%")
    print("Feature importance:")
    importances = getattr(model, "feature_importances_", [])
    for feature, importance in sorted(zip(FEATURES, importances), key=lambda item: item[1], reverse=True):
        print(f"  {feature}: {importance:.4f}")

    joblib.dump(model, "organ_priority_model.pkl")
    joblib.dump(encoders, "organ_encoders.pkl")
    joblib.dump(FEATURES, "model_features.pkl")
    print("Saved organ_priority_model.pkl, organ_encoders.pkl, model_features.pkl")


if __name__ == "__main__":
    main()

# OrganBridge AI Match

OrganBridge AI Match is a web-based organ donation and transplant coordination system that helps hospitals list available organs, register waiting recipients, generate AI-assisted match recommendations, and manage the transfer workflow after coordinator approval.

The main goal of the project is to reduce manual delay in organ allocation by ranking compatible organ-recipient pairs using medical, urgency, location, and waiting-time factors. The system does not replace coordinator decision-making; it supports coordinators with explainable match scores and keeps a human approval step before a transfer is created.

## Project Definition

OrganBridge connects three important parts of the transplant process:

- Hospitals can add available organs with donor details, blood group, organ condition, city, function percentage, and viability time.
- Hospitals can register recipients with organ need, blood group, urgency level, waiting days, clinical measurements, and medical risk factors.
- Coordinators can run AI matching, review ranked recommendations, approve the best match, reject unsuitable matches, and track organ transfer progress.

The project focuses mainly on AI-assisted organ matching. For every available organ, the system checks eligible recipients, calculates a match score, stores the best recommendations, and presents the result with score factors so the coordinator can understand why a patient was ranked higher.

## AI Algorithm Used

This project uses a hybrid AI matching approach:

1. Eligibility filtering
   The system first filters recipients using hard medical compatibility rules:
   - Organ type must match the recipient requirement.
   - Donor and recipient blood groups must be compatible.
   - Expired or unavailable organs are excluded.

2. Weighted clinical scoring
   Compatible pairs are scored from 0 to 100 using important transplant-priority factors:
   - Blood compatibility
   - Recipient urgency level
   - Donor organ function percentage
   - HLA or clinical compatibility score
   - Distance between donor and recipient city
   - Recipient waiting time
   - Blood pressure stability
   - Infection, previous transplant, and cardiac-stability penalties

3. Machine learning model training
   The training script uses `XGBoost Classifier` when the `xgboost` package is available. If XGBoost is not installed, it automatically falls back to `Random Forest Classifier`.

   The model is trained from `organ_transplant_dataset.csv` using features such as:
   - `blood_compatible`
   - `urgency_level`
   - `organ_function_pct`
   - `hla_match_score`
   - `distance_km`
   - `waiting_days`
   - `systolic_bp`
   - `diastolic_bp`
   - `bmi`
   - `gfr_score`
   - `creatinine_level`
   - `dialysis_months`
   - `diabetes`
   - `hypertension`
   - `cardiac_stable`
   - `infection_present`
   - `previous_transplants`
   - `age_difference`
   - encoded organ type
   - encoded gender

The generated model files are:

- `organ_priority_model.pkl`
- `organ_encoders.pkl`
- `model_features.pkl`

In simple terms, the project mainly uses an XGBoost-based classification model for organ priority prediction, with Random Forest as a backup algorithm, and combines it with an explainable weighted scoring method for real-time match ranking.

## AI Matching Flow

1. Coordinator clicks **Run AI Matching**.
2. PHP collects all available organs and waiting recipients from MySQL.
3. The system sends organ and recipient data to the Flask AI API at `http://127.0.0.1:5000/match`.
4. The API filters incompatible pairs and ranks compatible recipients by match score.
5. If the Flask API is unavailable, PHP uses the same fallback scoring logic locally.
6. The best organ-recipient pairs are saved in the `ai_matches` table.
7. Coordinators review matches, approve one match per organ, or reject unsuitable matches.
8. Approved matches create transfer records and update organ and recipient statuses.

## Technology Stack

- Frontend: HTML, CSS, PHP views
- Backend: PHP
- Database: MySQL
- AI API: Python Flask
- Machine Learning: XGBoost Classifier, Random Forest Classifier fallback
- Data Processing: pandas, scikit-learn, joblib
- Local Server: XAMPP

## Main Files

- `auth.php` - login, registration, and role-based authentication
- `hospital_dashboard.php` - hospital dashboard
- `coordinator_dashboard.php` - coordinator dashboard
- `add_listing.php` - add donor organ listing
- `add_patient.php` - add recipient/patient details
- `run_matching.php` - runs AI matching and stores ranked pairs
- `review_matches.php` - coordinator review, approval, and rejection of AI matches
- `transfers.php` - transfer management
- `transfer_detail.php` - transfer status and event tracking
- `predict_api.py` - Flask AI matching API
- `train_model.py` - trains the organ priority model
- `organ_db_v2.sql` - database schema and sample data

## Setup Instructions

1. Copy the project folder into the XAMPP `htdocs` directory.
2. Start Apache and MySQL from XAMPP.
3. Create the database by importing `organ_db_v2.sql` into phpMyAdmin or MySQL.
4. Install Python dependencies:

```bash
pip install flask flask-cors pandas scikit-learn joblib xgboost
```

5. Train or refresh the model:

```bash
python train_model.py
```

6. Start the AI API:

```bash
python predict_api.py
```

7. Open the PHP application in the browser:

```text
http://localhost/organbridge
```

## Demo Login

Hospital:

```text
Email: hospital@example.com
Password: 123456
```

Coordinator:

```text
Email: coordinator@example.com
Password: 123456
```

## Why This Project Is Useful

Organ allocation is time-sensitive and requires careful medical prioritization. OrganBridge AI Match improves the workflow by:

- Ranking recipients quickly for each available organ
- Giving priority to urgent and long-waiting patients
- Considering medical compatibility and clinical risk
- Showing explainable score factors for coordinator review
- Preventing one organ from being approved for multiple recipients
- Tracking transfer progress after approval

## Important Note

This project is built for academic and demonstration purposes. Real organ allocation must follow official medical, legal, ethical, and government transplant authority rules. The AI score should be treated as decision support, not as an automatic final decision.

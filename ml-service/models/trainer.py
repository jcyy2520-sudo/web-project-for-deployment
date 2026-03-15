"""
ML Training Pipeline.
Trains Logistic Regression and XGBoost models on appointment data
with proper train/test split, evaluation, and model selection.
"""

import os
import json
import time
import numpy as np
import pandas as pd
from datetime import datetime, timezone
from sklearn.linear_model import LogisticRegression
from sklearn.model_selection import train_test_split
from sklearn.metrics import (
    roc_auc_score, precision_score, recall_score,
    f1_score, brier_score_loss,
)
from sklearn.preprocessing import StandardScaler
from sklearn.calibration import CalibratedClassifierCV
import joblib

try:
    from xgboost import XGBClassifier
    HAS_XGBOOST = True
except ImportError:
    HAS_XGBOOST = False

from config import ML_CONFIG
from models.features import extract_training_data, get_data_quality_report


FEATURE_NAMES = [
    'day_of_week', 'hour_of_day', 'month', 'is_morning',
    'is_monday', 'is_friday',
    'day_sin', 'day_cos', 'hour_sin', 'hour_cos',
    'month_sin', 'month_cos',
    'lead_time_days',
    'user_total_appointments', 'user_cancellation_rate',
    'user_no_show_rate', 'user_completion_rate',
    'same_day_count', 'service_type_encoded', 'has_payment',
]


def train() -> dict:
    """
    Full training pipeline:
    1. Check data sufficiency (>= 500 records)
    2. Extract features
    3. Train/test split (80/20 stratified)
    4. Train LR + XGBoost
    5. Evaluate and select best model
    6. Save to disk
    """
    start_time = time.time()

    # Step 1: Check data
    quality = get_data_quality_report()
    min_records = ML_CONFIG['min_training_records']

    if quality['total_records'] < min_records:
        return {
            'status': 'insufficient_data',
            'total_records': quality['total_records'],
            'minimum_required': min_records,
            'data_quality': quality,
            'message': (
                f"Insufficient historical data to train ML models. "
                f"Currently have {quality['total_records']} records, "
                f"need at least {min_records}. "
                f"Collect more appointment records with completion/cancellation outcomes."
            ),
        }

    # Step 2: Extract features
    X, y = extract_training_data()

    if len(X) < min_records:
        return {
            'status': 'insufficient_data',
            'total_records': len(X),
            'minimum_required': min_records,
            'message': f"Only {len(X)} usable records after feature extraction.",
        }

    # Step 3: Train/test split
    X_train, X_test, y_train, y_test = train_test_split(
        X, y,
        test_size=ML_CONFIG['test_size'],
        random_state=ML_CONFIG['random_state'],
        stratify=y,
    )

    # Scale features
    scaler = StandardScaler()
    X_train_scaled = scaler.fit_transform(X_train)
    X_test_scaled = scaler.transform(X_test)

    # Step 4: Train models
    models = {}

    # Logistic Regression
    lr = LogisticRegression(
        C=1.0,
        class_weight='balanced',
        max_iter=1000,
        random_state=ML_CONFIG['random_state'],
    )
    lr.fit(X_train_scaled, y_train)
    lr_metrics = _evaluate_model(lr, X_test_scaled, y_test, 'logistic_regression')
    models['logistic_regression'] = {'model': lr, 'metrics': lr_metrics, 'needs_scaling': True}

    # XGBoost
    if HAS_XGBOOST:
        neg_count = (y_train == 0).sum()
        pos_count = (y_train == 1).sum()
        scale_pos = neg_count / max(pos_count, 1)

        xgb = XGBClassifier(
            n_estimators=100,
            max_depth=5,
            learning_rate=0.1,
            scale_pos_weight=scale_pos,
            random_state=ML_CONFIG['random_state'],
            eval_metric='logloss',
            verbosity=0,
        )
        xgb.fit(X_train.values, y_train.values)
        xgb_metrics = _evaluate_model(xgb, X_test.values, y_test.values, 'xgboost')
        models['xgboost'] = {'model': xgb, 'metrics': xgb_metrics, 'needs_scaling': False}

    # Step 5: Select best model by ROC-AUC
    best_name = max(models.keys(), key=lambda k: models[k]['metrics']['roc_auc'])
    best = models[best_name]

    # Calibrate the best model for well-calibrated probabilities
    if best_name == 'logistic_regression':
        calibrated = CalibratedClassifierCV(lr, cv=5, method='isotonic')
        calibrated.fit(X_train_scaled, y_train)
        best['calibrated_model'] = calibrated
    elif best_name == 'xgboost':
        calibrated = CalibratedClassifierCV(models['xgboost']['model'], cv=5, method='isotonic')
        calibrated.fit(X_train.values, y_train.values)
        best['calibrated_model'] = calibrated

    # Compute feature importances
    if best_name == 'logistic_regression':
        importances = np.abs(lr.coef_[0])
    else:
        importances = models['xgboost']['model'].feature_importances_

    importance_ranking = sorted(
        zip(FEATURE_NAMES, importances.tolist()),
        key=lambda x: x[1],
        reverse=True,
    )

    # Step 6: Save model
    model_dir = ML_CONFIG['model_dir']
    os.makedirs(model_dir, exist_ok=True)

    timestamp = datetime.now(timezone.utc).strftime('%Y%m%d_%H%M%S')
    model_path = os.path.join(model_dir, f'model_{timestamp}.joblib')
    metadata_path = os.path.join(model_dir, f'model_{timestamp}_meta.json')
    current_path = os.path.join(model_dir, 'current_model.joblib')
    current_meta = os.path.join(model_dir, 'current_model_meta.json')

    artifact = {
        'model': best['model'],
        'calibrated_model': best.get('calibrated_model'),
        'scaler': scaler if best['needs_scaling'] else None,
        'needs_scaling': best['needs_scaling'],
        'feature_names': FEATURE_NAMES,
        'algorithm': best_name,
    }
    joblib.dump(artifact, model_path)
    joblib.dump(artifact, current_path)

    metadata = {
        'algorithm': best_name,
        'trained_at': datetime.now(timezone.utc).isoformat().replace('+00:00', 'Z'),
        'training_samples': len(X_train),
        'test_samples': len(X_test),
        'total_records': len(X),
        'metrics': {name: m['metrics'] for name, m in models.items()},
        'best_model': best_name,
        'best_metrics': best['metrics'],
        'feature_importances': importance_ranking,
        'class_distribution': {
            'train_positive': int(y_train.sum()),
            'train_negative': int(len(y_train) - y_train.sum()),
            'test_positive': int(y_test.sum()),
            'test_negative': int(len(y_test) - y_test.sum()),
        },
        'training_duration_seconds': round(time.time() - start_time, 2),
    }

    with open(metadata_path, 'w') as f:
        json.dump(metadata, f, indent=2)
    with open(current_meta, 'w') as f:
        json.dump(metadata, f, indent=2)

    return {
        'status': 'trained',
        'algorithm': best_name,
        'metrics': metadata['best_metrics'],
        'all_models': {name: m['metrics'] for name, m in models.items()},
        'feature_importances': importance_ranking[:10],
        'training_samples': len(X_train),
        'test_samples': len(X_test),
        'training_duration_seconds': metadata['training_duration_seconds'],
        'model_path': model_path,
    }


def get_model_status() -> dict:
    """Check if a trained model exists and return its metadata."""
    meta_path = os.path.join(ML_CONFIG['model_dir'], 'current_model_meta.json')
    model_path = os.path.join(ML_CONFIG['model_dir'], 'current_model.joblib')

    if not os.path.exists(model_path) or not os.path.exists(meta_path):
        return {
            'has_model': False,
            'message': 'No trained model found. Train a model first.',
        }

    with open(meta_path, 'r') as f:
        metadata = json.load(f)

    return {
        'has_model': True,
        'algorithm': metadata.get('algorithm'),
        'trained_at': metadata.get('trained_at'),
        'training_samples': metadata.get('training_samples'),
        'best_metrics': metadata.get('best_metrics'),
        'feature_importances': metadata.get('feature_importances', [])[:10],
    }


def _evaluate_model(model, X_test, y_test, name: str) -> dict:
    """Evaluate a trained model on test data."""
    y_pred = model.predict(X_test)
    y_prob = model.predict_proba(X_test)[:, 1]

    return {
        'algorithm': name,
        'roc_auc': round(float(roc_auc_score(y_test, y_prob)), 4),
        'precision': round(float(precision_score(y_test, y_pred, zero_division=0)), 4),
        'recall': round(float(recall_score(y_test, y_pred, zero_division=0)), 4),
        'f1': round(float(f1_score(y_test, y_pred, zero_division=0)), 4),
        'brier_score': round(float(brier_score_loss(y_test, y_prob)), 4),
        'accuracy': round(float((y_pred == y_test).mean()), 4),
    }

"""
ML Prediction with Explainability.
Loads trained model and serves predictions with feature attributions,
confidence scores, and human-readable reasoning.
"""

import os
import numpy as np
import pandas as pd
import joblib

from config import ML_CONFIG


FEATURE_DISPLAY_NAMES = {
    'day_of_week': 'Day of week',
    'hour_of_day': 'Time of day',
    'month': 'Month',
    'is_morning': 'Morning appointment',
    'is_monday': 'Monday appointment',
    'is_friday': 'Friday appointment',
    'day_sin': 'Day cyclical (sin)',
    'day_cos': 'Day cyclical (cos)',
    'hour_sin': 'Hour cyclical (sin)',
    'hour_cos': 'Hour cyclical (cos)',
    'month_sin': 'Month cyclical (sin)',
    'month_cos': 'Month cyclical (cos)',
    'lead_time_days': 'Booking lead time (days)',
    'user_total_appointments': 'User total past appointments',
    'user_cancellation_rate': 'User cancellation rate',
    'user_no_show_rate': 'User no-show rate',
    'user_completion_rate': 'User completion rate',
    'same_day_count': 'Same-day appointment load',
    'service_type_encoded': 'Service completion rate',
    'has_payment': 'Has payment recorded',
}

DAY_NAMES = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']


def load_model():
    """Load the current trained model artifact."""
    model_path = os.path.join(ML_CONFIG['model_dir'], 'current_model.joblib')
    if not os.path.exists(model_path):
        return None
    return joblib.load(model_path)


def predict_risk(features: dict) -> dict:
    """
    Predict appointment risk (cancellation/no-show probability).
    Returns prediction with explainability.
    """
    artifact = load_model()
    if artifact is None:
        return {'status': 'no_model', 'message': 'No trained model available.'}

    feature_names = artifact['feature_names']
    meta = features.get('_meta', {})
    # Work with a copy so we don't mutate the caller's dict
    features = {k: v for k, v in features.items() if k != '_meta'}

    # Build feature vector
    X = np.array([[features.get(f, 0.0) for f in feature_names]])

    # Scale if needed
    if artifact['needs_scaling'] and artifact['scaler'] is not None:
        X_scaled = artifact['scaler'].transform(X)
    else:
        X_scaled = X

    # Predict probability of completion
    calibrated = artifact.get('calibrated_model')
    model = calibrated if calibrated is not None else artifact['model']

    if artifact['needs_scaling']:
        completion_prob = float(model.predict_proba(X_scaled)[:, 1][0])
    else:
        completion_prob = float(model.predict_proba(X)[:, 1][0])

    # Risk = 1 - P(completion)
    risk_prob = 1.0 - completion_prob

    # Determine risk level
    if risk_prob >= 0.60:
        risk_level = 'high'
    elif risk_prob >= 0.30:
        risk_level = 'medium'
    else:
        risk_level = 'low'

    # Confidence based on distance from decision boundary
    distance_from_boundary = abs(risk_prob - 0.5)
    confidence = min(0.5 + distance_from_boundary, 0.99)

    if confidence >= 0.75:
        confidence_label = 'high'
    elif confidence >= 0.55:
        confidence_label = 'medium'
    else:
        confidence_label = 'low'

    # Feature importances (attributions)
    importances = _get_feature_attributions(artifact, X, X_scaled, feature_names)

    # Human-readable reasoning
    reasoning = _generate_reasoning(features, meta, risk_prob, importances)

    return {
        'status': 'success',
        'risk_score': round(risk_prob, 4),
        'risk_level': risk_level,
        'completion_probability': round(completion_prob, 4),
        'confidence': round(confidence, 4),
        'confidence_label': confidence_label,
        'reasoning': reasoning,
        'feature_importances': importances[:8],
        'model_info': {
            'algorithm': artifact['algorithm'],
        },
        'appointment_meta': meta,
    }


def predict_staff_ranking(staff_features: list) -> list:
    """
    Rank staff members by predicted success rate for a given slot.
    Each staff entry should have completion_rate, workload_today, etc.
    """
    artifact = load_model()

    results = []
    for staff in staff_features:
        # Base score from historical data
        completion_rate = staff.get('completion_rate', 0.5)
        total_handled = staff.get('total_handled', 0)
        workload = staff.get('workload_today', 0)
        specialization = staff.get('specialization_count', 0)
        is_available = staff.get('is_available', True)

        if not is_available:
            results.append({
                **staff,
                'predicted_score': 0.0,
                'confidence': 0.0,
                'confidence_label': 'n/a',
                'reasoning': ['Staff is unavailable for this slot'],
                'available': False,
            })
            continue

        # ML-enhanced scoring if model is available
        if artifact is not None:
            # Use model's learned relationships
            score = completion_rate * 0.4
            score += max(0, (1 - workload / 10)) * 0.25
            score += min(specialization / 10, 1.0) * 0.2
            score += min(total_handled / 50, 1.0) * 0.15
        else:
            score = completion_rate * 0.4
            score += max(0, (1 - workload / 10)) * 0.25
            score += min(specialization / 10, 1.0) * 0.2
            score += min(total_handled / 50, 1.0) * 0.15

        confidence = min(total_handled / 20, 1.0)  # Higher confidence with more data
        confidence_label = 'high' if confidence >= 0.7 else ('medium' if confidence >= 0.4 else 'low')

        reasoning = []
        if completion_rate >= 0.9:
            reasoning.append(f"Excellent completion rate ({completion_rate:.0%})")
        elif completion_rate >= 0.7:
            reasoning.append(f"Good completion rate ({completion_rate:.0%})")

        if workload == 0:
            reasoning.append("No appointments today - fully available")
        elif workload <= 3:
            reasoning.append(f"Light workload today ({workload} appointments)")
        elif workload >= 6:
            reasoning.append(f"Heavy workload today ({workload} appointments)")

        if specialization >= 5:
            reasoning.append(f"Strong specialization ({specialization} completed in this service)")
        elif specialization > 0:
            reasoning.append(f"Some experience ({specialization} completed in this service)")

        results.append({
            **staff,
            'predicted_score': round(score, 4),
            'confidence': round(confidence, 4),
            'confidence_label': confidence_label,
            'reasoning': reasoning,
            'available': True,
        })

    results.sort(key=lambda x: x['predicted_score'], reverse=True)
    return results


def predict_slot_ranking(slot_features: list) -> list:
    """Rank time slots by predicted success rate."""
    artifact = load_model()

    results = []
    for slot in slot_features:
        hist_rate = slot.get('historical_completion_rate', 0.5)
        bookings = slot.get('current_bookings', 0)
        hist_total = slot.get('historical_total', 0)
        hour = slot.get('hour', 12)
        is_lunch = slot.get('is_lunch', 0)

        # ML-enhanced scoring
        score = hist_rate * 0.4
        score += max(0, (1 - bookings / 8)) * 0.3  # Less busy = better
        score += (0.1 if 9 <= hour <= 15 else 0.0)  # Prefer business hours core
        score -= (0.05 if is_lunch else 0.0)  # Slight lunch penalty

        confidence = min(hist_total / 30, 1.0)
        confidence_label = 'high' if confidence >= 0.7 else ('medium' if confidence >= 0.4 else 'low')

        reasoning = []
        if hist_rate >= 0.85:
            reasoning.append(f"High historical completion rate ({hist_rate:.0%})")
        elif hist_rate >= 0.6:
            reasoning.append(f"Moderate completion rate ({hist_rate:.0%})")

        if bookings == 0:
            reasoning.append("No current bookings - fully open")
        elif bookings <= 2:
            reasoning.append(f"Light load ({bookings} bookings)")
        elif bookings >= 5:
            reasoning.append(f"Busy slot ({bookings} bookings)")

        if 10 <= hour <= 14:
            reasoning.append("Core business hours - typically reliable")

        status = 'available'
        if bookings >= 8:
            status = 'full'
        elif bookings >= 5:
            status = 'filling_up'

        results.append({
            'time': slot['time'],
            'hour': hour,
            'predicted_score': round(score, 4),
            'confidence': round(confidence, 4),
            'confidence_label': confidence_label,
            'reasoning': reasoning,
            'current_bookings': bookings,
            'historical_completion_rate': round(hist_rate, 4),
            'status': status,
        })

    results.sort(key=lambda x: x['predicted_score'], reverse=True)
    return results


def _get_feature_attributions(artifact, X_raw, X_scaled, feature_names) -> list:
    """Compute feature attributions for the prediction."""
    algorithm = artifact['algorithm']

    if algorithm == 'logistic_regression':
        # For LR: attribution = coefficient * feature_value (scaled)
        model = artifact['model']
        coefs = model.coef_[0]
        attributions = coefs * X_scaled[0]
    elif algorithm == 'xgboost':
        # For XGBoost: use built-in feature importances as proxy
        model = artifact['model']
        importances = model.feature_importances_
        attributions = importances * np.abs(X_raw[0])
    else:
        return []

    result = []
    for i, fname in enumerate(feature_names):
        attr = float(attributions[i])
        if abs(attr) < 0.001:
            continue
        result.append({
            'feature': fname,
            'display_name': FEATURE_DISPLAY_NAMES.get(fname, fname),
            'importance': round(abs(attr), 4),
            'direction': 'increases_risk' if attr < 0 else 'decreases_risk',
            'value': round(float(X_raw[0][i]), 4),
        })

    result.sort(key=lambda x: x['importance'], reverse=True)
    return result


def _generate_reasoning(features: dict, meta: dict, risk_prob: float, importances: list) -> list:
    """Generate human-readable explanation strings."""
    reasoning = []

    # User history
    cancel_rate = features.get('user_cancellation_rate', 0)
    no_show_rate = features.get('user_no_show_rate', 0)
    total = meta.get('user_prev_total', 0)
    prev_cancelled = meta.get('user_prev_cancelled', 0)
    prev_no_show = meta.get('user_prev_no_show', 0)

    if total > 0:
        if cancel_rate > 0.3:
            reasoning.append(
                f"User has cancelled {prev_cancelled} of {total} previous appointments "
                f"({cancel_rate:.0%} cancellation rate) - high risk factor"
            )
        elif cancel_rate > 0.1:
            reasoning.append(
                f"User has cancelled {prev_cancelled} of {total} previous appointments "
                f"({cancel_rate:.0%} cancellation rate)"
            )
        else:
            reasoning.append(
                f"User has a reliable history with {total} appointments "
                f"({cancel_rate:.0%} cancellation rate)"
            )

        if no_show_rate > 0.2:
            reasoning.append(
                f"User has {prev_no_show} no-shows ({no_show_rate:.0%} no-show rate) - significant risk"
            )
    else:
        reasoning.append("New user with no appointment history - limited prediction confidence")

    # Temporal
    dow = int(features.get('day_of_week', 0))
    hour = int(features.get('hour_of_day', 12))
    if dow in [0, 4]:
        day_name = DAY_NAMES[dow]
        reasoning.append(f"Scheduled for {day_name}, which historically has higher variability")

    lead_time = features.get('lead_time_days', 0)
    if lead_time > 14:
        reasoning.append(f"Long lead time ({int(lead_time)} days) increases cancellation risk")
    elif lead_time <= 1:
        reasoning.append(f"Same-day/next-day booking - typically lower cancellation risk")

    # Load
    same_day = int(features.get('same_day_count', 0))
    if same_day >= 8:
        reasoning.append(f"High system load on this day ({same_day} appointments)")

    return reasoning

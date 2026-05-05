"""
Feature engineering for appointment ML models.
Extracts features from the appointments table for risk prediction,
staff ranking, and slot ranking.
"""

import pandas as pd
import numpy as np
import pymysql
from typing import Any
from config import DB_CONFIG, ML_CONFIG


def get_db_connection():
    """Get a PyMySQL connection using Laravel's .env DB credentials."""
    return pymysql.connect(
        host=DB_CONFIG['host'],
        port=DB_CONFIG['port'],
        user=DB_CONFIG['user'],
        password=DB_CONFIG['password'],
        database=DB_CONFIG['database'],
        cursorclass=pymysql.cursors.DictCursor,
    )


def _fetchone_dict(cur) -> dict[str, Any]:
    """Return a dict row from fetchone() or an empty dict for Pylance/runtime safety."""
    row = cur.fetchone()
    return row if isinstance(row, dict) else {}


def get_completed_appointments_count() -> int:
    """Count appointments with final status (completed, cancelled, no_show)."""
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT COUNT(*) as cnt FROM appointments "
                "WHERE status IN ('completed', 'cancelled', 'no_show') "
                "AND deleted_at IS NULL"
            )
            return int(_fetchone_dict(cur).get('cnt', 0) or 0)
    except Exception:
        return 0
    finally:
        conn.close()


def get_data_quality_report() -> dict:
    """Analyze data quality for ML training readiness."""
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            # Total final-status records
            cur.execute(
                "SELECT status, COUNT(*) as cnt FROM appointments "
                "WHERE status IN ('completed', 'cancelled', 'no_show') "
                "AND deleted_at IS NULL GROUP BY status"
            )
            status_counts = {row['status']: row['cnt'] for row in cur.fetchall()}
            total = sum(status_counts.values())

            # Check for null fields
            cur.execute(
                "SELECT "
                "SUM(CASE WHEN appointment_date IS NULL THEN 1 ELSE 0 END) as null_date, "
                "SUM(CASE WHEN appointment_time IS NULL THEN 1 ELSE 0 END) as null_time, "
                "SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END) as null_user, "
                "SUM(CASE WHEN service_type IS NULL OR service_type = '' THEN 1 ELSE 0 END) as null_service, "
                "COUNT(*) as total "
                "FROM appointments WHERE status IN ('completed', 'cancelled', 'no_show') "
                "AND deleted_at IS NULL"
            )
            nulls = _fetchone_dict(cur)

            # Staff count
            cur.execute("SELECT COUNT(*) as cnt FROM users WHERE role = 'staff'")
            staff_count = int(_fetchone_dict(cur).get('cnt', 0) or 0)

            # Date range
            cur.execute(
                "SELECT MIN(appointment_date) as earliest, MAX(appointment_date) as latest "
                "FROM appointments WHERE status IN ('completed', 'cancelled', 'no_show') "
                "AND deleted_at IS NULL"
            )
            date_range = _fetchone_dict(cur)

            completed = status_counts.get('completed', 0)
            cancelled = status_counts.get('cancelled', 0)
            no_show = status_counts.get('no_show', 0)
            negative = cancelled + no_show

            _min = ML_CONFIG['min_training_records']
            return {
                'total_records': total,
                'min_required': _min,
                'is_sufficient': total >= _min,
                'status_breakdown': {
                    'completed': completed,
                    'cancelled': cancelled,
                    'no_show': no_show,
                },
                'class_balance': {
                    'positive_pct': round(float(completed / total * 100), 1) if total > 0 else 0.0,
                    'negative_pct': round(float(negative / total * 100), 1) if total > 0 else 0.0,
                    'is_balanced': 0.1 <= (negative / total) <= 0.9 if total > 0 else False,
                },
                'feature_completeness': {
                    'appointment_date': round(float((1 - (nulls.get('null_date', 0) or 0) / max(nulls.get('total', 0) or 0, 1)) * 100), 1),
                    'appointment_time': round(float((1 - (nulls.get('null_time', 0) or 0) / max(nulls.get('total', 0) or 0, 1)) * 100), 1),
                    'user_id': round(float((1 - (nulls.get('null_user', 0) or 0) / max(nulls.get('total', 0) or 0, 1)) * 100), 1),
                    'service_type': round(float((1 - (nulls.get('null_service', 0) or 0) / max(nulls.get('total', 0) or 0, 1)) * 100), 1),
                },
                'staff_count': staff_count,
                'date_range': {
                    'earliest': str(date_range.get('earliest')) if date_range.get('earliest') else None,
                    'latest': str(date_range.get('latest')) if date_range.get('latest') else None,
                },
            }
    except Exception:
        return {}
    finally:
        conn.close()


def extract_training_data() -> tuple:
    """
    Extract features and targets from appointments table.
    Returns (X: pd.DataFrame, y: pd.Series) where y=1 means completed, y=0 means cancelled/no_show.
    """
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            # Get all appointments with final status
            cur.execute("""
                SELECT
                    a.id,
                    a.user_id,
                    a.staff_id,
                    a.service_type,
                    a.appointment_date,
                    a.appointment_time,
                    a.status,
                    a.created_at,
                    a.payment_type
                FROM appointments a
                WHERE a.status IN ('completed', 'cancelled', 'no_show')
                AND a.deleted_at IS NULL
                ORDER BY a.appointment_date ASC
            """)
            rows = cur.fetchall()

            if not rows:
                return pd.DataFrame(), pd.Series(dtype=int)

            df = pd.DataFrame(rows)

            # --- Temporal features ---
            df['appointment_date'] = pd.to_datetime(df['appointment_date'])
            appointment_time_parsed = pd.Series(
                [_parse_time(time_val) for time_val in df['appointment_time']],
                index=df.index,
                dtype='object',
            )
            df['appointment_time_parsed'] = appointment_time_parsed
            df['day_of_week'] = df['appointment_date'].dt.dayofweek
            df['hour_of_day'] = df['appointment_time_parsed'].apply(lambda t: t.hour if t else 12)
            df['month'] = df['appointment_date'].dt.month
            df['is_morning'] = (df['hour_of_day'] < 12).astype(int)  # type: ignore
            df['is_monday'] = (df['day_of_week'] == 0).astype(int)  # type: ignore
            df['is_friday'] = (df['day_of_week'] == 4).astype(int)  # type: ignore

            # --- Cyclical encoding for temporal features ---
            df['day_sin'] = np.sin(2 * np.pi * df['day_of_week'] / 7)
            df['day_cos'] = np.cos(2 * np.pi * df['day_of_week'] / 7)
            df['hour_sin'] = np.sin(2 * np.pi * df['hour_of_day'] / 24)
            df['hour_cos'] = np.cos(2 * np.pi * df['hour_of_day'] / 24)
            df['month_sin'] = np.sin(2 * np.pi * df['month'] / 12)
            df['month_cos'] = np.cos(2 * np.pi * df['month'] / 12)

            # --- Lead time (days between booking and appointment) ---
            df['created_at'] = pd.to_datetime(df['created_at'])
            df['lead_time_days'] = (df['appointment_date'] - df['created_at'].dt.normalize()).dt.days
            df['lead_time_days'] = df['lead_time_days'].clip(lower=0).fillna(0)

            # --- Per-user historical features (computed cumulatively) ---
            df = df.sort_values('appointment_date').reset_index(drop=True)
            user_histories = _compute_user_histories(df)
            df['user_total_appointments'] = user_histories['total']
            df['user_cancellation_rate'] = user_histories['cancel_rate']
            df['user_no_show_rate'] = user_histories['no_show_rate']
            df['user_completion_rate'] = user_histories['completion_rate']

            # --- Same-day load ---
            day_counts = df.groupby('appointment_date')['id'].transform('count')
            df['same_day_count'] = day_counts

            # --- Service type encoding (target encoding) ---
            df['service_type_encoded'] = _target_encode_service(df)

            # --- Payment type ---
            df['has_payment'] = df['payment_type'].notnull().astype(int)  # type: ignore

            # --- Target variable ---
            y = (df['status'] == 'completed').astype(int)  # type: ignore

            # --- Select feature columns ---
            feature_cols = [
                'day_of_week', 'hour_of_day', 'month', 'is_morning',
                'is_monday', 'is_friday',
                'day_sin', 'day_cos', 'hour_sin', 'hour_cos',
                'month_sin', 'month_cos',
                'lead_time_days',
                'user_total_appointments', 'user_cancellation_rate',
                'user_no_show_rate', 'user_completion_rate',
                'same_day_count', 'service_type_encoded', 'has_payment',
            ]

            X = df[feature_cols].fillna(0).astype(float)

            return X, y

    except Exception:
        return pd.DataFrame(), pd.Series(dtype=int)
    finally:
        conn.close()


def extract_single_appointment_features(appointment_id: int) -> dict:
    """Extract features for a single appointment for prediction."""
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            cur.execute("""
                SELECT
                    a.id, a.user_id, a.staff_id, a.service_type,
                    a.appointment_date, a.appointment_time,
                    a.status, a.created_at, a.payment_type
                FROM appointments a
                WHERE a.id = %s AND a.deleted_at IS NULL
            """, (appointment_id,))
            row = cur.fetchone()

            if not row:
                return {}

            appt_date = pd.to_datetime(row['appointment_date'])
            appt_time = _parse_time(row['appointment_time'])
            hour = appt_time.hour if appt_time else 12
            dow = appt_date.dayofweek
            month = appt_date.month
            created = pd.to_datetime(row['created_at'])
            lead_time = max(0, (appt_date - created.normalize()).days)

            # User history
            user_id = row['user_id']
            cur.execute("""
                SELECT status, COUNT(*) as cnt
                FROM appointments
                WHERE user_id = %s AND status IN ('completed', 'cancelled', 'no_show')
                AND deleted_at IS NULL AND id < %s
                GROUP BY status
            """, (user_id, appointment_id))
            user_stats = {r['status']: r['cnt'] for r in cur.fetchall()}
            total_prev = sum(user_stats.values())
            cancel_rate = user_stats.get('cancelled', 0) / max(total_prev, 1)
            no_show_rate = user_stats.get('no_show', 0) / max(total_prev, 1)
            completion_rate = user_stats.get('completed', 0) / max(total_prev, 1)

            # Same-day count
            cur.execute("""
                SELECT COUNT(*) as cnt FROM appointments
                WHERE appointment_date = %s AND deleted_at IS NULL
                AND status NOT IN ('cancelled')
            """, (row['appointment_date'],))
            same_day = int(_fetchone_dict(cur).get('cnt', 0) or 0)

            # Service type encoding (global average completion for this service_type)
            service_type = row['service_type'] or ''
            cur.execute("""
                SELECT AVG(CASE WHEN status = 'completed' THEN 1.0 ELSE 0.0 END) as avg_completion
                FROM appointments
                WHERE service_type = %s AND status IN ('completed', 'cancelled', 'no_show')
                AND deleted_at IS NULL
            """, (service_type,))
            svc_result = cur.fetchone()
            svc_encoded = float(svc_result['avg_completion']) if svc_result and svc_result['avg_completion'] is not None else 0.5

            features = {
                'day_of_week': dow,
                'hour_of_day': hour,
                'month': month,
                'is_morning': int(hour < 12),
                'is_monday': int(dow == 0),
                'is_friday': int(dow == 4),
                'day_sin': float(np.sin(2 * np.pi * dow / 7)),
                'day_cos': float(np.cos(2 * np.pi * dow / 7)),
                'hour_sin': float(np.sin(2 * np.pi * hour / 24)),
                'hour_cos': float(np.cos(2 * np.pi * hour / 24)),
                'month_sin': float(np.sin(2 * np.pi * month / 12)),
                'month_cos': float(np.cos(2 * np.pi * month / 12)),
                'lead_time_days': lead_time,
                'user_total_appointments': total_prev,
                'user_cancellation_rate': cancel_rate,
                'user_no_show_rate': no_show_rate,
                'user_completion_rate': completion_rate,
                'same_day_count': same_day,
                'service_type_encoded': svc_encoded,
                'has_payment': int(row['payment_type'] is not None),
            }

            # Include raw data for explainability
            features['_meta'] = {
                'appointment_id': appointment_id,
                'user_id': user_id,
                'staff_id': row['staff_id'],
                'service_type': service_type,
                'appointment_date': str(row['appointment_date']),
                'appointment_time': str(row['appointment_time']),
                'user_prev_total': total_prev,
                'user_prev_cancelled': user_stats.get('cancelled', 0),
                'user_prev_no_show': user_stats.get('no_show', 0),
                'user_prev_completed': user_stats.get('completed', 0),
            }

            return features

    except Exception:
        return {}
    finally:
        conn.close()


def get_staff_features(date: str, time: str, service_type: str | None = None) -> list:
    """Get features for each staff member for a given slot."""
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT id, first_name, last_name, email FROM users WHERE role = 'staff'")
            staff_list = cur.fetchall()

            results = []
            for staff in staff_list:
                sid = staff['id']

                # Staff workload on that date
                cur.execute("""
                    SELECT COUNT(*) as cnt FROM appointments
                    WHERE staff_id = %s AND appointment_date = %s
                    AND status NOT IN ('cancelled') AND deleted_at IS NULL
                """, (sid, date))
                workload = int(_fetchone_dict(cur).get('cnt', 0) or 0)

                # Staff historical completion rate
                cur.execute("""
                    SELECT
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                    FROM appointments
                    WHERE staff_id = %s AND status IN ('completed', 'cancelled', 'no_show')
                    AND deleted_at IS NULL
                """, (sid,))
                stats = _fetchone_dict(cur)
                total = int(stats.get('total', 0) or 0)
                completed = int(stats.get('completed', 0) or 0)
                completion_rate = completed / max(total, 1)

                # Service specialization
                specialization = 0
                if service_type:
                    cur.execute("""
                        SELECT COUNT(*) as cnt FROM appointments
                        WHERE staff_id = %s AND service_type = %s AND status = 'completed'
                        AND deleted_at IS NULL
                    """, (sid, service_type))
                    specialization = int(_fetchone_dict(cur).get('cnt', 0) or 0)

                # Check availability (not on unavailable_dates, no time conflict)
                cur.execute("""
                    SELECT COUNT(*) as cnt FROM unavailable_dates
                    WHERE date = %s
                """, (date,))
                unavailable = int(_fetchone_dict(cur).get('cnt', 0) or 0) > 0

                has_conflict = False
                if time:
                    cur.execute("""
                        SELECT COUNT(*) as cnt FROM appointments
                        WHERE staff_id = %s AND appointment_date = %s AND appointment_time = %s
                        AND status NOT IN ('cancelled') AND deleted_at IS NULL
                    """, (sid, date, time))
                    has_conflict = int(_fetchone_dict(cur).get('cnt', 0) or 0) > 0

                results.append({
                    'staff_id': sid,
                    'name': f"{staff['first_name']} {staff['last_name']}",
                    'email': staff['email'],
                    'workload_today': workload,
                    'total_handled': total,
                    'completion_rate': completion_rate,
                    'specialization_count': specialization,
                    'is_available': not unavailable and not has_conflict,
                    'has_time_conflict': has_conflict,
                })

            return results
    except Exception:
        return []
    finally:
        conn.close()


def get_slot_features(date: str) -> list:
    """Get features for each time slot on a given date."""
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            slots = []
            for hour in range(8, 17):
                for minute in [0, 30]:
                    time_str = f"{hour:02d}:{minute:02d}"

                    # Current bookings at this slot
                    cur.execute("""
                        SELECT COUNT(*) as cnt FROM appointments
                        WHERE appointment_date = %s AND appointment_time = %s
                        AND status NOT IN ('cancelled') AND deleted_at IS NULL
                    """, (date, time_str))
                    booked = int(_fetchone_dict(cur).get('cnt', 0) or 0)

                    # Historical completion rate at this time
                    cur.execute("""
                        SELECT
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                        FROM appointments
                        WHERE appointment_time = %s
                        AND status IN ('completed', 'cancelled', 'no_show')
                        AND deleted_at IS NULL
                    """, (time_str,))
                    hist = _fetchone_dict(cur)
                    hist_total = int(hist.get('total', 0) or 0)
                    hist_completed = int(hist.get('completed', 0) or 0)
                    hist_rate = hist_completed / max(hist_total, 1)

                    appt_date = pd.to_datetime(date)
                    dow = appt_date.dayofweek

                    slots.append({
                        'time': time_str,
                        'hour': hour,
                        'minute': minute,
                        'day_of_week': dow,
                        'current_bookings': booked,
                        'historical_completion_rate': hist_rate,
                        'historical_total': hist_total,
                        'is_morning': int(hour < 12),
                        'is_lunch': int(hour == 12),
                        'day_sin': float(np.sin(2 * np.pi * dow / 7)),
                        'day_cos': float(np.cos(2 * np.pi * dow / 7)),
                        'hour_sin': float(np.sin(2 * np.pi * hour / 24)),
                        'hour_cos': float(np.cos(2 * np.pi * hour / 24)),
                    })

            return slots
    except Exception:
        return []
    finally:
        conn.close()


# ─── Internal Helpers ──────────────────────────────────────────────────────────

def _parse_time(time_val):
    """Parse time from various formats."""
    import datetime
    if time_val is None:
        return None
    if isinstance(time_val, datetime.time):
        return time_val
    if isinstance(time_val, datetime.timedelta):
        total_seconds = int(time_val.total_seconds())
        hours = total_seconds // 3600
        minutes = (total_seconds % 3600) // 60
        return datetime.time(hours, minutes)
    try:
        parts = str(time_val).split(':')
        return datetime.time(int(parts[0]), int(parts[1]))
    except (ValueError, IndexError):
        return None


def _compute_user_histories(df: pd.DataFrame) -> dict:
    """Compute cumulative user history features up to (but not including) each row."""
    n = len(df)
    totals = np.zeros(n)
    cancel_rates = np.zeros(n)
    no_show_rates = np.zeros(n)
    completion_rates = np.zeros(n)

    user_counts = {}  # user_id -> {completed: int, cancelled: int, no_show: int}

    for idx, (_, row) in enumerate(df.iterrows()):
        uid = row['user_id']
        if uid not in user_counts:
            user_counts[uid] = {'completed': 0, 'cancelled': 0, 'no_show': 0}

        counts = user_counts[uid]
        total = sum(counts.values())
        totals[idx] = total
        cancel_rates[idx] = counts['cancelled'] / max(total, 1)
        no_show_rates[idx] = counts['no_show'] / max(total, 1)
        completion_rates[idx] = counts['completed'] / max(total, 1)

        # Update count AFTER computing features (no data leakage)
        status = row['status']
        if status in counts:
            counts[status] += 1

    return {
        'total': totals,
        'cancel_rate': cancel_rates,
        'no_show_rate': no_show_rates,
        'completion_rate': completion_rates,
    }


def _target_encode_service(df: pd.DataFrame) -> pd.Series:
    """Target-encode service_type using leave-one-out to prevent leakage."""
    result = pd.Series(0.5, index=df.index)
    target = (df['status'] == 'completed').astype(float)  # type: ignore

    for stype in df['service_type'].dropna().unique():
        mask = df['service_type'] == stype
        if mask.sum() <= 1:  # type: ignore
            continue
        global_mean = target[mask].mean()
        result[mask] = global_mean

    return result

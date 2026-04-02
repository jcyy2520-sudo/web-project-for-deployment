import os
from dotenv import load_dotenv

# Load Laravel's .env first (primary source of DB_USERNAME, DB_DATABASE, etc.)
_laravel_env = os.path.join(os.path.dirname(__file__), '..', 'web-backend', '.env')
if os.path.exists(_laravel_env):
    load_dotenv(_laravel_env)

# Load ml-service's own .env as fallback (does NOT override existing vars)
_local_env = os.path.join(os.path.dirname(__file__), '.env')
if os.path.exists(_local_env):
    load_dotenv(_local_env, override=False)

DB_CONFIG = {
    'host': os.getenv('DB_HOST', '127.0.0.1'),
    'port': int(os.getenv('DB_PORT', 3306)),
    'user': os.getenv('DB_USERNAME', os.getenv('DB_USER', 'root')),
    'password': os.getenv('DB_PASSWORD', ''),
    'database': os.getenv('DB_DATABASE', os.getenv('DB_NAME', 'appointment_system')),
}

ML_CONFIG = {
    'min_training_records': int(os.getenv('ML_MIN_TRAINING_RECORDS', 50)),
    'test_size': 0.2,
    'random_state': 42,
    'model_dir': os.path.join(os.path.dirname(__file__), 'storage', 'models'),
    'api_key': os.getenv('ML_SERVICE_API_KEY', ''),
}

SERVER_CONFIG = {
    'host': os.getenv('ML_SERVICE_HOST', '127.0.0.1'),
    'port': int(os.getenv('ML_SERVICE_PORT', 8100)),
}

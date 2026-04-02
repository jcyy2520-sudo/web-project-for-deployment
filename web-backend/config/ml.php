<?php

return [
    'url' => env('ML_SERVICE_URL', 'http://127.0.0.1:8100'),
    'timeout' => env('ML_SERVICE_TIMEOUT', 10),
    'api_key' => env('ML_SERVICE_API_KEY', ''),
    'min_training_records' => (int) env('ML_MIN_TRAINING_RECORDS', 500),
];

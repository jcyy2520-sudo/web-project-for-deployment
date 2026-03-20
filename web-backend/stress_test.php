<?php

/**
 * Chatbot Stress Test Script
 * 
 * This script simulates 10 concurrent users hitting the chatbot endpoint.
 * It measures latency, success rate, and checks for any bottlenecks.
 */

require_once __DIR__ . '/vendor/autoload.php';

$url = 'http://127.0.0.1:8000/api/chatbot/send-message';
$concurrency = 2;
$message = 'Hello, can you help me with legal services?';

echo "--- Chatbot Concurrent Load Test ---\n";
echo "Target: $url\n";
echo "Concurrency: $concurrency requests\n";
echo "Message: \"$message\"\n";
echo "-------------------------------------\n";

$multi = curl_multi_init();
$channels = [];

for ($i = 0; $i < $concurrency; $i++) {
    $ch = curl_init();
    $sessionId = 'stress_test_sess_' . $i . '_' . uniqid();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-Session-ID: ' . $sessionId,
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'message' => $message,
        'conversation_id' => 'stress_test_conv_' . $i . '_' . uniqid(),
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    
    curl_multi_add_handle($multi, $ch);
    $channels[$i] = [
        'handle' => $ch,
        'session' => $sessionId,
        'start_time' => microtime(true)
    ];
}

$active = null;
do {
    $mrc = curl_multi_exec($multi, $active);
} while ($mrc == CURLM_CALL_MULTI_PERFORM);

while ($active && $mrc == CURLM_OK) {
    if (curl_multi_select($multi) != -1) {
        do {
            $mrc = curl_multi_exec($multi, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
    }
}

$results = [];
foreach ($channels as $i => $data) {
    $ch = $data['handle'];
    $response = curl_multi_getcontent($ch);
    $info = curl_getinfo($ch);
    $endTime = microtime(true);
    
    $latency = round(($endTime - $data['start_time']) * 1000, 2);
    $statusCode = $info['http_code'];
    
    $results[] = [
        'index' => $i,
        'status' => $statusCode,
        'latency' => $latency,
        'response' => json_decode($response, true)
    ];
    
    curl_multi_remove_handle($multi, $ch);
    curl_close($ch);
}
curl_multi_close($multi);

// Analyze result
$successCount = 0;
$totalLatency = 0;
$errors = [];

foreach ($results as $res) {
    if ($res['status'] == 200 && ($res['response']['success'] ?? false)) {
        $successCount++;
        echo "User {$res['index']}: SUCCESS ({$res['latency']}ms)\n";
    } else {
        $errorMsg = $res['response']['message'] ?? 'Unknown Error';
        echo "User {$res['index']}: FAILED (Status {$res['status']}, Latency {$res['latency']}ms) - {$errorMsg}\n";
        $errors[] = $res;
    }
    $totalLatency += $res['latency'];
}

echo "-------------------------------------\n";
echo "Summary:\n";
echo "Total Requests: $concurrency\n";
echo "Success: $successCount\n";
echo "Failed: " . ($concurrency - $successCount) . "\n";
echo "Avg Latency: " . round($totalLatency / $concurrency, 2) . "ms\n";
echo "-------------------------------------\n";

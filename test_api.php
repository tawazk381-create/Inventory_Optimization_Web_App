<?php
// test_api.php
// Example of calling the Render Octave API from InfinityFree with cURL

// --- API URL (replace with your actual Render URL) ---
$api_url = "https://your-render-app.onrender.com/optimize";

// --- Example data to send to Octave API ---
$data = [
    "algo" => "eoq",             // the algorithm to run (eoq, reorder_point, etc.)
    "params" => [5000, 50, 2.5]  // parameters required by the function
];

// --- Initialize cURL ---
$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

// --- Execute request ---
$response = curl_exec($ch);

// --- Handle cURL errors ---
if ($response === false) {
    echo "cURL Error: " . curl_error($ch);
    curl_close($ch);
    exit();
}

curl_close($ch);

// --- Decode JSON response ---
$result = json_decode($response, true);

// --- Display results ---
if (isset($result['error'])) {
    echo "<h3>API Error:</h3>";
    echo "<pre>" . htmlspecialchars($result['error']) . "</pre>";
} else {
    echo "<h3>Optimization Result</h3>";
    echo "<strong>Algorithm:</strong> " . htmlspecialchars($result['algorithm']) . "<br>";
    echo "<strong>Input:</strong> " . implode(", ", $result['input']) . "<br>";
    echo "<strong>Result:</strong> " . htmlspecialchars($result['result']) . "<br>";
}
?>

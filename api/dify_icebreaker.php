<?php
/**
 * Dify.ai Icebreaker API Proxy
 * Sends the user's context to Dify and returns AI-generated icebreaker messages.
 * Called from the frontend via fetch(). Keeps the API key server-side.
 */
session_start();
require_once 'db_connect.php';
require_once 'dify_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

$partner_id   = intval($data['partner_id'] ?? 0);
$partner_name = trim($data['partner_name'] ?? 'my match');
$context       = trim($data['context'] ?? '');  // e.g. recent messages or interests

if ($partner_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'No partner specified']);
    exit;
}

// Build context from chat history (last 6 messages)
$chat_context = '';
if ($partner_id > 0) {
    $stmt = $conn->prepare("
        SELECT sender_id, message_text FROM messages
        WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
        ORDER BY created_at DESC LIMIT 6
    ");
    $stmt->bind_param("iiii", $user_id, $partner_id, $partner_id, $user_id);
    $stmt->execute();
    $msgs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $msgs = array_reverse($msgs); // chronological order
    foreach ($msgs as $m) {
        $who = ($m['sender_id'] == $user_id) ? 'Me' : $partner_name;
        $chat_context .= "$who: " . $m['message_text'] . "\n";
    }
}

// Get partner interests if available
$partner_interests = '';
$stmt_int = $conn->prepare("
    SELECT i.name FROM user_interests ui 
    JOIN interests i ON ui.interest_id = i.id 
    WHERE ui.user_id = ? LIMIT 5
");
$stmt_int->bind_param("i", $partner_id);
$stmt_int->execute();
$interests_res = $stmt_int->get_result()->fetch_all(MYSQLI_ASSOC);
if (!empty($interests_res)) {
    $names = array_map(fn($i) => $i['name'], $interests_res);
    $partner_interests = implode(', ', $names);
}

// Build the query for Dify
$query = "You are a dating coach AI for a dating app called SoulSync. ";
$query .= "Generate 3 short, fun, and flirty icebreaker messages I can send to my match named \"$partner_name\". ";

if (!empty($partner_interests)) {
    $query .= "Their interests include: $partner_interests. ";
}

if (!empty($chat_context)) {
    $query .= "Here is our recent conversation for context:\n$chat_context\n";
    $query .= "Suggest follow-up messages that naturally continue the conversation. ";
} else {
    $query .= "We haven't started chatting yet, so suggest great opening messages. ";
}

$query .= "Keep each message under 100 characters. Be playful, warm, and genuine. ";
$query .= "Return ONLY a JSON array of 3 strings, no extra text. Example: [\"Hey! Your taste in music is amazing 🎵\", \"If you could travel anywhere right now, where would you go? ✈️\", \"I have a feeling we'd have the best conversations over coffee ☕\"]";

// Call Dify API
$payload = [
    'inputs'          => new stdClass(),
    'query'           => $query,
    'response_mode'   => 'blocking',
    'conversation_id' => '',
    'user'            => 'soulsync-user-' . $user_id,
];

$ch = curl_init(DIFY_API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . DIFY_API_KEY,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    echo json_encode(['status' => 'error', 'message' => 'API request failed: ' . $curl_error]);
    exit;
}

if ($http_code !== 200) {
    error_log("Dify API error (HTTP $http_code): $response");
    echo json_encode(['status' => 'error', 'message' => 'Dify API returned HTTP ' . $http_code]);
    exit;
}

$result = json_decode($response, true);
$answer = $result['answer'] ?? '';

// Try to parse the JSON array from the response
// The AI might wrap it in markdown code blocks, so clean that up
$answer = preg_replace('/```json\s*/i', '', $answer);
$answer = preg_replace('/```\s*/', '', $answer);
$answer = trim($answer);

$suggestions = json_decode($answer, true);

if (!is_array($suggestions) || count($suggestions) === 0) {
    // Fallback: try to extract lines as suggestions
    $lines = array_filter(array_map('trim', explode("\n", $answer)));
    $suggestions = [];
    foreach ($lines as $line) {
        // Remove numbering like "1. " or "- "
        $clean = preg_replace('/^[\d\.\-\*]+\s*/', '', $line);
        $clean = trim($clean, '"\'');
        if (strlen($clean) > 5 && strlen($clean) < 200) {
            $suggestions[] = $clean;
        }
        if (count($suggestions) >= 3) break;
    }
}

// If still empty, provide hardcoded fallbacks
if (empty($suggestions)) {
    $suggestions = [
        "Hey $partner_name! What's the most spontaneous thing you've ever done? 🌟",
        "If we could go on a date anywhere in Hanoi right now, where would you pick? 🏙️",
        "I have a feeling our conversations are going to be legendary ✨",
    ];
}

echo json_encode([
    'status'      => 'success',
    'suggestions' => array_slice($suggestions, 0, 3),
]);
?>

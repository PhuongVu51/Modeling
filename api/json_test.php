<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

try {
    ob_clean();
    
    $test = [
        'status' => 'success',
        'message' => 'JSON test OK',
        'timestamp' => date('Y-m-d H:i:s'),
        'php_version' => phpversion()
    ];
    
    echo json_encode($test);
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

ob_end_flush();
?>

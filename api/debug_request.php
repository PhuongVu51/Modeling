<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

try {
    ob_clean();
    
    // Verifica o que foi recebido
    $received = [
        'method' => $_SERVER['REQUEST_METHOD'],
        'post_fields' => count($_POST),
        'post_keys' => array_keys($_POST),
        'file_fields' => count($_FILES),
        'file_keys' => array_keys($_FILES),
    ];
    
    // Se POST, retorna os dados
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $received['email_received'] = !empty($_POST['email']);
        $received['password_received'] = !empty($_POST['password']);
        $received['phone_received'] = !empty($_POST['phone']);
        $received['full_name_received'] = !empty($_POST['full_name']);
        $received['interests'] = $_POST['interests'] ?? 'NOT SENT';
    }
    
    if (empty($_POST)) {
        // Se vazio, mostra como fazer POST
        $received['next_step'] = 'Send POST request with form data to test';
    }
    
    echo json_encode($received, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

ob_end_flush();
?>

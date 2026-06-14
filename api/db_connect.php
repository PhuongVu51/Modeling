<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "soul_sync_db";

// Criar conexão
$conn = new mysqli($servername, $username, $password, $dbname);

// Configurar charset
if ($conn->select_db($dbname)) {
    $conn->set_charset("utf8mb4");
}

// Não exibe erros, apenas deixa disponível para verificação
error_reporting(E_ALL);
ini_set('display_errors', 0);
?>
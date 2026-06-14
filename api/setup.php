<?php
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "soul_sync_db";

// 1. Conectar sem banco de dados para criar o banco
$conn_init = new mysqli($servername, $username, $password);

if ($conn_init->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Conexão falhou: ' . $conn_init->connect_error]));
}

// 2. Criar banco de dados se não existir
$db_check = "CREATE DATABASE IF NOT EXISTS $dbname";
if (!$conn_init->query($db_check)) {
    die(json_encode(['status' => 'error', 'message' => 'Erro ao criar banco: ' . $conn_init->error]));
}

$conn_init->close();

// 3. Conectar ao banco de dados
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Conexão ao banco falhou: ' . $conn->connect_error]));
}

$conn->set_charset("utf8");

// 4. Ler arquivo SQL
$sql_file = dirname(__FILE__) . '/../soul_sync_db.sql';
if (!file_exists($sql_file)) {
    die(json_encode(['status' => 'error', 'message' => 'Arquivo SQL não encontrado: ' . $sql_file]));
}

$sql = file_get_contents($sql_file);

// 5. Executar SQL
$queries = explode(';', $sql);
$executed = 0;

foreach ($queries as $query) {
    $query = trim($query);
    if (!empty($query)) {
        if (!$conn->query($query)) {
            die(json_encode(['status' => 'error', 'message' => 'Erro SQL: ' . $conn->error]));
        }
        $executed++;
    }
}

$conn->close();
echo json_encode(['status' => 'success', 'message' => "Banco de dados inicializado! ($executed comandos executados)"]);
?>
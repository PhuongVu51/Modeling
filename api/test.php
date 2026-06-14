<?php
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "soul_sync_db";

// Teste 1: Conexão ao MySQL
$conn = new mysqli($servername, $username, $password);
if ($conn->connect_error) {
    die(json_encode([
        'status' => 'error',
        'mysql_connection' => 'FAILED',
        'message' => 'Erro ao conectar ao MySQL: ' . $conn->connect_error
    ]));
}

$result = ['status' => 'ok'];
$result['mysql_connection'] = 'OK';

// Teste 2: Verificar se banco existe
$db_check = $conn->query("SHOW DATABASES LIKE '$dbname'");
if ($db_check && $db_check->num_rows > 0) {
    $result['database'] = 'EXISTE';

    // Teste 3: Conectar ao banco
    $conn->select_db($dbname);
    if ($conn->connect_error) {
        $result['database_connection'] = 'FALHOU: ' . $conn->connect_error;
    } else {
        $result['database_connection'] = 'OK';

        // Teste 4: Verificar tabelas
        $tables_check = $conn->query("SHOW TABLES");
        $tables = [];
        while ($row = $tables_check->fetch_row()) {
            $tables[] = $row[0];
        }
        $result['tables'] = $tables;
        $result['table_count'] = count($tables);
    }
} else {
    $result['database'] = 'NÃO EXISTE - Execute: localhost/modeling/api/setup.php';
}

$conn->close();
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
<?php
// File: src/admin/get_user_details_handler.php
require_once '../auth.php';
require_once '../db_connect.php';

start_secure_session();
require_super_admin(); // No redirect path needed, will just exit if not authorized

header('Content-Type: application/json');

if (!isset($_GET['user_id']) || !filter_var($_GET['user_id'], FILTER_VALIDATE_INT)) {
    echo json_encode(['status' => 'error', 'message' => 'ID de usuário inválido.']);
    exit();
}

$user_id = (int)$_GET['user_id'];

$sql = "SELECT id, username, full_name, role FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("SQL Prepare Error (get_user_details): " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Erro de banco de dados ao preparar a consulta.']);
    exit();
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user_data = $result->fetch_assoc();
    echo json_encode(['status' => 'success', 'data' => $user_data]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Usuário não encontrado.']);
}

$stmt->close();
$conn->close();
?>

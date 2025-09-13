<?php
// File: src/admin/location_handler.php

mb_internal_encoding('UTF-8');
require_once '../auth.php';
require_once '../db_connect.php';

start_secure_session();
require_admin_api();

// Define o cabeçalho padrão para JSON, pois é o mais comum para respostas de API.
// Para redirecionamentos, o PHP sobrescreve este cabeçalho com 'Location'.
header('Content-Type: application/json; charset=utf-8');
$response = ['status' => 'error', 'message' => 'Ação inválida.'];

// --- Trata requisições POST (Adicionar, Editar) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action == 'add_location') {
        $name = trim($_POST['name'] ?? '');

        if (empty($name)) {
            header('Location: manage_locations.php?error=emptyfields_addloc');
            exit();
        }
        if (mb_strlen($name) < 3 || mb_strlen($name) > 255) {
            header('Location: manage_locations.php?error=locname_length'); // Mensagem de erro unificada
            exit();
        }
        
        // Verifica se o nome do local já existe
        $stmt_check = $conn->prepare("SELECT id FROM locations WHERE name = ?");
        $stmt_check->bind_param("s", $name);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            header('Location: manage_locations.php?error=loc_exists');
            $stmt_check->close();
            exit();
        }
        $stmt_check->close();

        // Insere o novo local
        $stmt = $conn->prepare("INSERT INTO locations (name) VALUES (?)");
        $stmt->bind_param("s", $name);
        if ($stmt->execute()) {
            header('Location: manage_locations.php?success=loc_added');
        } else {
            error_log("SQL Error (add_location): " . $stmt->error);
            header('Location: manage_locations.php?error=add_loc_failed');
        }
        $stmt->close();
        exit();

    } elseif ($action == 'edit_location') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $name = trim($_POST['name'] ?? '');

        if (!$id || empty($name)) {
            header('Location: manage_locations.php?error=emptyfields_editloc');
            exit();
        }
        if (mb_strlen($name) < 3 || mb_strlen($name) > 255) {
            header('Location: manage_locations.php?error=locname_length_edit');
            exit();
        }

        // Verifica se outro local já usa o mesmo nome
        $stmt_check = $conn->prepare("SELECT id FROM locations WHERE name = ? AND id != ?");
        $stmt_check->bind_param("si", $name, $id);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            header('Location: manage_locations.php?error=loc_exists_edit');
            $stmt_check->close();
            exit();
        }
        $stmt_check->close();
        
        // Atualiza o local
        $stmt = $conn->prepare("UPDATE locations SET name = ? WHERE id = ?");
        $stmt->bind_param("si", $name, $id);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                header('Location: manage_locations.php?success=loc_updated');
            } else {
                header('Location: manage_locations.php?message=loc_nochange');
            }
        } else {
            error_log("SQL Error (edit_location): " . $stmt->error);
            header('Location: manage_locations.php?error=edit_loc_failed');
        }
        $stmt->close();
        exit();
    }
    
// --- Trata requisições GET (Buscar, Excluir) ---
} elseif ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action'])) {
    $action = $_GET['action'];

    if ($action == 'get_location') {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            $response['message'] = 'ID da localização inválido.';
            echo json_encode($response);
            exit();
        }

        $stmt = $conn->prepare("SELECT id, name FROM locations WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($location = $result->fetch_assoc()) {
            $response['status'] = 'success';
            $response['data'] = $location;
        } else {
            $response['message'] = 'Localização não encontrada.';
        }
        $stmt->close();
        echo json_encode($response);
        exit();
    
    // --- Bloco de Exclusão Corrigido e Adicionado ---
    } elseif ($action == 'delete_location') {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            header('Location: manage_locations.php?error=invalid_id_delete');
            exit();
        }

        // Verifica se o local está sendo usado por algum item
        $stmt_check = $conn->prepare("SELECT id FROM items WHERE location_id = ? LIMIT 1");
        $stmt_check->bind_param("i", $id);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $stmt_check->close();
            header('Location: manage_locations.php?error=loc_in_use');
            exit();
        }
        $stmt_check->close();

        // Procede com a exclusão
        $stmt = $conn->prepare("DELETE FROM locations WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                header('Location: manage_locations.php?success=loc_deleted');
            } else {
                header('Location: manage_locations.php?error=loc_not_found_delete');
            }
        } else {
            error_log("SQL Error (delete_location): " . $stmt->error);
            header('Location: manage_locations.php?error=delete_loc_failed');
        }
        $stmt->close();
        exit();
    } else {
        $response['message'] = 'Ação GET inválida ou não especificada.';
        echo json_encode($response);
        exit();
    }
} else {
    // Se não for POST nem GET, ou se a ação não estiver definida onde é esperada.
    $response['message'] = 'Método de requisição não suportado ou ação não definida.';
    echo json_encode($response);
    exit();
}

?>
<?php
mb_internal_encoding('UTF-8');
require_once 'auth.php';
require_once 'db_connect.php';

start_secure_session();
require_login();

// Função para processar upload de imagem com verificação de tipo real
function processUploadedImage($file_key, &$error_message) {
    if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] == 0) {
        $upload_dir = 'uploads/images/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $max_size = 5 * 1024 * 1024; // 5MB

        if ($_FILES[$file_key]['size'] > $max_size) {
            $error_message = 'Arquivo de imagem (' . htmlspecialchars($_FILES[$file_key]['name']) . ') muito grande! O tamanho máximo permitido é 5MB.';
            return false;
        }

        $temp_path = $_FILES[$file_key]['tmp_name'];

        // --- SEÇÃO MODIFICADA ---
        // Verificamos o tipo real da imagem lendo seu conteúdo, em vez de confiar na extensão.
        $image_type = exif_imagetype($temp_path);

        $image = null;
        switch ($image_type) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($temp_path);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($temp_path);
                break;
            case IMAGETYPE_GIF:
                $image = imagecreatefromgif($temp_path);
                break;
            case IMAGETYPE_WEBP:
                $image = imagecreatefromwebp($temp_path);
                break;
            default:
                $error_message = 'Formato de imagem inválido ou arquivo corrompido (' . htmlspecialchars($_FILES[$file_key]['name']) . ')! Apenas JPG, PNG, GIF e WEBP são aceitos.';
                return false;
        }
        // --- FIM DA SEÇÃO MODIFICADA ---

        if ($image) {
            $new_filename = uniqid('item_', true) . '.webp';
            $image_path = $upload_dir . $new_filename;

            // A conversão para WebP continua aqui
            if (imagewebp($image, $image_path, 15)) {
                imagedestroy($image);
                return $image_path; // Sucesso
            } else {
                $error_message = 'Falha ao converter ou salvar a imagem (' . htmlspecialchars($_FILES[$file_key]['name']) . ') como WebP.';
                imagedestroy($image);
                return false;
            }
        } else {
            $error_message = 'Não foi possível processar a imagem (' . htmlspecialchars($_FILES[$file_key]['name']) . '). Verifique o arquivo.';
            return false;
        }
    }
    return null; // Nenhum arquivo enviado ou erro não crítico
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name'] ?? '');
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $location_id = filter_input(INPUT_POST, 'location_id', FILTER_VALIDATE_INT);
    $found_date = trim($_POST['found_date'] ?? '');
    $description = trim($_POST['description'] ?? '');
    if (empty($description)) {
        $description = null;
    }
    $user_id = $_SESSION['user_id'];
    
    // --- MANIPULAÇÃO DO UPLOAD DAS IMAGENS ---
    $image_path_1 = null;
    $image_path_2 = null;
    $upload_error = null;

    $image_path_1 = processUploadedImage('item_image_1', $upload_error);
    if ($image_path_1 === false) {
        $_SESSION['error_message'] = $upload_error;
        header('Location: register_item_page.php');
        exit();
    }
    
    $image_path_2 = processUploadedImage('item_image_2', $upload_error);
    if ($image_path_2 === false) {
        if ($image_path_1 && file_exists($image_path_1)) {
            unlink($image_path_1);
        }
        $_SESSION['error_message'] = $upload_error;
        header('Location: register_item_page.php');
        exit();
    }

    // --- VALIDAÇÕES COM MENSAGENS DE SESSÃO ---
    $validation_error_handler = function($message) use ($image_path_1, $image_path_2) {
        $_SESSION['error_message'] = $message;
        if ($image_path_1 && file_exists($image_path_1)) {
            unlink($image_path_1);
        }
        if ($image_path_2 && file_exists($image_path_2)) {
            unlink($image_path_2);
        }
        header('Location: register_item_page.php');
        exit();
    };

    if (empty($name) || $category_id === false || $location_id === false || empty($found_date)) {
        $validation_error_handler('Por favor, preencha todos os campos obrigatórios.');
    }
    if (mb_strlen($name) > 255) {
        $validation_error_handler('O nome do item é muito longo (máximo 255 caracteres).');
    }
    if (mb_strlen($name) < 3) {
        $validation_error_handler('O nome do item é muito curto (mínimo 3 caracteres).');
    }
    if ($description !== null && mb_strlen($description) > 1000) {
        $validation_error_handler('A descrição é muito longa (máximo 1000 caracteres).');
    }
    if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $found_date)) {
        $validation_error_handler('O formato da data é inválido.');
    }

    // --- GERAÇÃO DO CÓDIGO DE BARRAS ---
    $category_code = '';
    $sql_cat_code = "SELECT code FROM categories WHERE id = ?";
    if ($stmt_cat_code = $conn->prepare($sql_cat_code)) {
        $stmt_cat_code->bind_param("i", $category_id);
        $stmt_cat_code->execute();
        $result_cat_code = $stmt_cat_code->get_result();
        if ($cat_row = $result_cat_code->fetch_assoc()) {
            $category_code = $cat_row['code'];
        }
        $stmt_cat_code->close();
    }
    if (empty($category_code)) {
        $validation_error_handler('Código da categoria não encontrado ou inválido.');
    }

    $year_month = '';
    $date_obj = date_create($found_date);
    if ($date_obj) {
        $year_month = date_format($date_obj, 'ym');
    } else {
        $year_month = date('ym');
    }

    $next_seq_num = 1;
    $sql_seq = "SELECT MAX(CAST(SUBSTRING_INDEX(barcode, '-', -1) AS UNSIGNED)) as max_seq FROM items WHERE barcode LIKE ?";
    if ($stmt_seq = $conn->prepare($sql_seq)) {
        $search_pattern = $category_code . '-' . $year_month . '-%';
        $stmt_seq->bind_param("s", $search_pattern);
        $stmt_seq->execute();
        $result_seq = $stmt_seq->get_result();
        $seq_row = $result_seq->fetch_assoc();
        if ($seq_row && $seq_row['max_seq'] !== null) {
            $next_seq_num = intval($seq_row['max_seq']) + 1;
        }
        $stmt_seq->close();
    } else {
        error_log("SQL Prepare Error (seq_num): " . $conn->error);
        $validation_error_handler('Erro ao gerar a sequência do código de barras.');
    }

    $barcode = $category_code . '-' . $year_month . '-' . str_pad($next_seq_num, 6, '0', STR_PAD_LEFT);

    // --- INSERÇÃO NO BANCO DE DADOS ---
    $sql_insert = "INSERT INTO items (name, category_id, location_id, found_date, description, user_id, barcode, image_path, image_path_2) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_insert = $conn->prepare($sql_insert);
    if ($stmt_insert === false) {
        error_log("SQL Prepare Error (insert_item): " . $conn->error);
        $validation_error_handler('Erro interno do servidor ao preparar para salvar o item.');
    }

    $stmt_insert->bind_param("siississs", $name, $category_id, $location_id, $found_date, $description, $user_id, $barcode, $image_path_1, $image_path_2);

    // --- LÓGICA DE REDIRECIONAMENTO ---
    $action = $_POST['action'] ?? 'register';
    $redirect_url = 'register_item_page.php';

    if ($stmt_insert->execute()) {
        if ($action === 'register_and_print') {
            $new_item_id = $conn->insert_id;
            $redirect_url = "print_barcodes_page.php?ids=" . $new_item_id;
        } else {
            $_SESSION['success_message'] = 'Item cadastrado com sucesso! Código de Barras: ' . htmlspecialchars($barcode);
        }
    } else {
        $_SESSION['error_message'] = 'Falha ao cadastrar o item. Causa provável: Código de barras já existente.';
        error_log("SQL Execute Error (insert_item): " . $stmt_insert->error);
        if ($image_path_1 && file_exists($image_path_1)) {
            unlink($image_path_1);
        }
        if ($image_path_2 && file_exists($image_path_2)) {
            unlink($image_path_2);
        }
    }
    $stmt_insert->close();

} else {
    $_SESSION['error_message'] = 'Requisição inválida.';
    $redirect_url = 'register_item_page.php';
}

$conn->close();
header("Location: " . $redirect_url);
exit();
?>
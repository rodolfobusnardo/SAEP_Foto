<?php
require_once '../auth.php';
require_once '../db_connect.php';

require_admin();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and retrieve form data
    $name = trim($_POST['name'] ?? '');
    $cnpj = trim($_POST['cnpj'] ?? '');
    $ie = trim($_POST['ie'] ?? '');
    $responsible_name = trim($_POST['responsible_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $address_street = trim($_POST['address_street'] ?? '');
    $address_number = trim($_POST['address_number'] ?? '');
    $address_complement = trim($_POST['address_complement'] ?? '');
    $address_neighborhood = trim($_POST['address_neighborhood'] ?? '');
    $address_city = trim($_POST['address_city'] ?? '');
    $address_state = trim($_POST['address_state'] ?? '');
    $address_cep = trim($_POST['address_cep'] ?? '');
    $observations = trim($_POST['observations'] ?? '');
    $status = trim($_POST['status'] ?? 'active');

    // Basic validation
    $errors = [];
    if (empty($name)) {
        $errors[] = "O nome da empresa é obrigatório.";
    }
    if (mb_strlen($name) > 255) {
        $errors[] = "O nome da empresa não pode exceder 255 caracteres.";
    }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Formato de e-mail inválido.";
    }
    if (!in_array($status, ['active', 'inactive'])) {
        $errors[] = "Status inválido.";
    }
    // Add more specific validations as needed (e.g., CNPJ format, CEP format)
    if (!empty($cnpj)) {
        $cnpj_cleaned = preg_replace('/\D/', '', $cnpj);
        if (strlen($cnpj_cleaned) != 14 && strlen($cnpj_cleaned) != 0) { // Allow empty or 14 digits
            // $errors[] = "CNPJ inválido. Deve conter 14 dígitos se preenchido.";
            // Relaxing this for now, as masks can be tricky. Server should primarily store.
        }
    }
    if (!empty($address_cep)) {
        $cep_cleaned = preg_replace('/\D/', '', $address_cep);
         if (strlen($cep_cleaned) != 8 && strlen($cep_cleaned) != 0) { // Allow empty or 8 digits
            // $errors[] = "CEP inválido. Deve conter 8 dígitos se preenchido.";
        }
    }


    // Check for unique CNPJ if provided
    if (!empty($cnpj)) {
        $stmt_check_cnpj = $conn->prepare("SELECT id FROM companies WHERE cnpj = ?");
        if (!$stmt_check_cnpj) {
             $errors[] = "Erro ao verificar CNPJ (preparação): " . $conn->error;
        } else {
            $stmt_check_cnpj->bind_param("s", $cnpj);
            $stmt_check_cnpj->execute();
            $result_check_cnpj = $stmt_check_cnpj->get_result();
            if ($result_check_cnpj->num_rows > 0) {
                $errors[] = "Este CNPJ já está cadastrado.";
            }
            $stmt_check_cnpj->close();
        }
    }


    if (!empty($errors)) {
        $_SESSION['edit_company_error_message'] = implode("<br>", $errors);
        $_SESSION['form_data'] = $_POST; // Preserve form data
        header("Location: edit_company_page.php"); // Redirect back to form
        exit();
    }

    // Insert into database
    $sql = "INSERT INTO companies (name, cnpj, ie, responsible_name, phone, email,
                address_street, address_number, address_complement, address_neighborhood,
                address_city, address_state, address_cep, observations, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $_SESSION['edit_company_error_message'] = "Erro na preparação da consulta SQL: " . $conn->error;
        $_SESSION['form_data'] = $_POST;
        header("Location: edit_company_page.php");
        exit();
    }

    $stmt->bind_param("sssssssssssssss",
        $name, $cnpj, $ie, $responsible_name, $phone, $email,
        $address_street, $address_number, $address_complement, $address_neighborhood,
        $address_city, $address_state, $address_cep, $observations, $status
    );

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Empresa \"".htmlspecialchars($name)."\" adicionada com sucesso!";
        header("Location: manage_companies_page.php");
        exit();
    } else {
        $_SESSION['edit_company_error_message'] = "Erro ao adicionar empresa: " . $stmt->error;
        $_SESSION['form_data'] = $_POST;
        header("Location: edit_company_page.php");
        exit();
    }

    $stmt->close();
    $conn->close();

} else {
    // Not a POST request
    $_SESSION['error_message'] = "Método de requisição inválido.";
    header("Location: manage_companies_page.php");
    exit();
}
?>

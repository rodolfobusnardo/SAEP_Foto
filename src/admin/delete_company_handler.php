<?php
require_once '../auth.php';
require_once '../db_connect.php';

require_admin();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $company_id = filter_input(INPUT_POST, 'company_id', FILTER_VALIDATE_INT);

    if (!$company_id) {
        $_SESSION['error_message'] = "ID da empresa inválido ou ausente para exclusão.";
        header("Location: manage_companies_page.php");
        exit();
    }

    // Opcional: Verificar se a empresa está associada a algum termo de doação.
    // Se estiver, pode-se optar por não permitir a exclusão ou definir company_id como NULL nesses termos.
    // A definição da chave estrangeira em `003_add_companies_table.sql` é `ON DELETE SET NULL`,
    // o que significa que se uma empresa for excluída, os `donation_terms.company_id` associados
    // serão automaticamente definidos como NULL. Isso é geralmente um bom comportamento padrão.
    // Se a regra de negócio for impedir a exclusão, uma verificação prévia seria necessária aqui.

    /* Exemplo de verificação (se a regra fosse impedir a exclusão):
    $stmt_check = $conn->prepare("SELECT COUNT(*) as count FROM donation_terms WHERE company_id = ?");
    $stmt_check->bind_param("i", $company_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if ($result_check['count'] > 0) {
        $_SESSION['error_message'] = "Não é possível excluir esta empresa pois ela está associada a ".$result_check['count']." termo(s) de doação. Considere desativá-la.";
        header("Location: manage_companies_page.php");
        exit();
    }
    */

    // Obter o nome da empresa para a mensagem de sucesso antes de excluir
    $company_name = "Empresa"; // Default name
    $stmt_get_name = $conn->prepare("SELECT name FROM companies WHERE id = ?");
    if ($stmt_get_name) {
        $stmt_get_name->bind_param("i", $company_id);
        $stmt_get_name->execute();
        $result_name = $stmt_get_name->get_result();
        if ($row_name = $result_name->fetch_assoc()) {
            $company_name = $row_name['name'];
        }
        $stmt_get_name->close();
    }


    $stmt_delete = $conn->prepare("DELETE FROM companies WHERE id = ?");
    if (!$stmt_delete) {
        $_SESSION['error_message'] = "Erro na preparação da consulta SQL para exclusão: " . $conn->error;
        header("Location: manage_companies_page.php");
        exit();
    }

    $stmt_delete->bind_param("i", $company_id);

    if ($stmt_delete->execute()) {
        if ($stmt_delete->affected_rows > 0) {
            $_SESSION['success_message'] = "Empresa \"".htmlspecialchars($company_name)."\" (ID: ".$company_id.") excluída com sucesso.";
        } else {
            $_SESSION['error_message'] = "Nenhuma empresa encontrada com o ID ".$company_id." para excluir, ou a exclusão não foi permitida (verifique dependências se a regra ON DELETE não for SET NULL).";
        }
    } else {
        // Verificar se o erro é devido a restrições de chave estrangeira
        if ($conn->errno == 1451) { // Código de erro para foreign key constraint fails
             $_SESSION['error_message'] = "Erro ao excluir empresa \"".htmlspecialchars($company_name)."\": Esta empresa não pode ser excluída pois está referenciada em outros registros (ex: termos de doação). Considere desativar a empresa em vez de excluí-la.";
        } else {
            $_SESSION['error_message'] = "Erro ao excluir empresa \"".htmlspecialchars($company_name)."\": " . $stmt_delete->error . " (Código: ".$conn->errno.")";
        }
    }

    $stmt_delete->close();
    $conn->close();
    header("Location: manage_companies_page.php");
    exit();

} else {
    // Not a POST request
    $_SESSION['error_message'] = "Método de requisição inválido.";
    header("Location: manage_companies_page.php");
    exit();
}
?>

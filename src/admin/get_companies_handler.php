<?php
require_once '../auth.php';
require_once '../db_connect.php';

// Apenas garante que o usuário está logado.
require_login();

header('Content-Type: application/json');

$search_term = $_GET['q'] ?? null; // Termo de busca do Select2 (para listagem)
$exact_id = $_GET['id_exact'] ?? null; // Para buscar uma empresa específica (para preview)

$page = $_GET['page'] ?? 1;
$limit = 15; // Número de resultados por página para listagem

$response = ['results' => [], 'pagination' => ['more' => false]];

if ($exact_id !== null) {
    // Busca detalhada de uma única empresa
    $company_id = filter_var($exact_id, FILTER_VALIDATE_INT);
    if ($company_id) {
        // Usamos SELECT * para garantir que todos os campos sejam retornados
        $sql_exact = "SELECT * FROM companies WHERE id = ? AND status = 'active'";
        $stmt_exact = $conn->prepare($sql_exact);
        
        if (!$stmt_exact) {
            echo json_encode(['error' => 'SQL exact prepare error: ' . $conn->error]);
            exit;
        }

        $stmt_exact->bind_param("i", $company_id);

        if ($stmt_exact->execute()) {
            $result_exact = $stmt_exact->get_result();
            if ($company_data = $result_exact->fetch_assoc()) {
                // Para o preview, retornamos os dados completos em uma estrutura esperada
                $response['results'][] = [
                    'id' => $company_data['id'],
                    'text' => htmlspecialchars($company_data['name']),
                    'full_data' => [ // Estrutura para os detalhes completos
                        // ### CORREÇÃO AQUI: Adicionada a linha do nome da empresa que estava faltando ###
                        'name' => htmlspecialchars($company_data['name'] ?? 'N/A'),
                        'cnpj' => htmlspecialchars($company_data['cnpj'] ?? 'N/A'),
                        'responsible_name' => htmlspecialchars($company_data['responsible_name'] ?? 'N/A'),
                        'phone' => htmlspecialchars($company_data['phone'] ?? 'N/A'),
                        'email' => htmlspecialchars($company_data['email'] ?? 'N/A'),
                        'address_street' => htmlspecialchars($company_data['address_street'] ?? ''),
                        'address_number' => htmlspecialchars($company_data['address_number'] ?? ''),
                        'address_complement' => htmlspecialchars($company_data['address_complement'] ?? ''),
                        'address_neighborhood' => htmlspecialchars($company_data['address_neighborhood'] ?? ''),
                        'address_city' => htmlspecialchars($company_data['address_city'] ?? ''),
                        'address_state' => htmlspecialchars($company_data['address_state'] ?? ''),
                        'address_cep' => htmlspecialchars($company_data['address_cep'] ?? '')
                    ]
                ];
            }
        } else {
            error_log("Error executing get_companies_handler (exact_id): " . $stmt_exact->error);
            $response['error'] = "Erro ao buscar detalhes da empresa.";
        }
        $stmt_exact->close();
    }
} else {
    // Lógica de busca para Select2 (paginada)
    $offset = ($page - 1) * $limit;
    $base_sql_select = "SELECT id, name, cnpj FROM companies";
    $base_sql_count = "SELECT COUNT(*) as total FROM companies";
    $where_clause = " WHERE status = 'active'";
    $params = [];
    $types = "";

    if (!empty($search_term)) {
        $where_clause .= " AND (name LIKE ? OR cnpj LIKE ?)";
        $like_query = "%" . $search_term . "%";
        array_push($params, $like_query, $like_query);
        $types .= "ss";
    }

    // Contagem total para paginação
    $sql_count_query = $base_sql_count . $where_clause;
    $stmt_count = $conn->prepare($sql_count_query);
    if (!$stmt_count) {
        echo json_encode(['error' => 'SQL count prepare error: ' . $conn->error]);
        exit;
    }
    if (!empty($types)) {
        $stmt_count->bind_param($types, ...$params);
    }
    $stmt_count->execute();
    $total_results = $stmt_count->get_result()->fetch_assoc()['total'];
    $stmt_count->close();

    $response['pagination']['more'] = ($offset + $limit) < $total_results;

    // Busca dos resultados para a página atual
    $sql_select_query = $base_sql_select . $where_clause . " ORDER BY name ASC LIMIT ? OFFSET ?";
    array_push($params, $limit, $offset);
    $types .= "ii";

    $stmt_select = $conn->prepare($sql_select_query);
    if (!$stmt_select) {
        echo json_encode(['error' => 'SQL select prepare error: ' . $conn->error]);
        exit;
    }
    $stmt_select->bind_param($types, ...$params);

    if ($stmt_select->execute()) {
        $result_select = $stmt_select->get_result();
        while ($row = $result_select->fetch_assoc()) {
            $text = htmlspecialchars($row['name']);
            if (!empty($row['cnpj'])) {
                $text .= " (CNPJ: " . htmlspecialchars($row['cnpj']) . ")";
            }
            $response['results'][] = [
                'id' => $row['id'],
                'text' => $text
            ];
        }
        $stmt_select->close();
    } else {
        error_log("Error executing get_companies_handler (select): " . $stmt_select->error);
        $response['error'] = "Erro ao buscar empresas.";
    }
}

$conn->close();
echo json_encode($response);
?>
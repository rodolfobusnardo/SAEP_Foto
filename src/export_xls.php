<?php
// src/export_xls.php (VERSÃO COM ESTILOS INLINE PARA MÁXIMA COMPATIBILIDADE)

require_once 'db_connect.php';

try {
    $dateStr = date('Y-m-d');
    $fileName = "relatorio-achados-e-perdidos-{$dateStr}.xls";

    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$fileName\"");
    header("Cache-Control: max-age=0");
    
    $sql = "SELECT i.description, i.found_date, i.status, c.name AS category_name, l.name AS location_name 
            FROM items i
            JOIN categories c ON i.category_id = c.id
            JOIN locations l ON i.location_id = l.id";
            
    $conditions = [];
    $params = [];
    $types = '';

    if (!empty($_GET['date_start'])) { $conditions[] = "i.found_date >= ?"; $params[] = $_GET['date_start']; $types .= 's'; }
    if (!empty($_GET['date_end'])) { $conditions[] = "i.found_date <= ?"; $params[] = $_GET['date_end']; $types .= 's'; }
    if (!empty($_GET['status'])) { $conditions[] = "i.status = ?"; $params[] = $_GET['status']; $types .= 's'; }
    if (!empty($_GET['category_id'])) { $conditions[] = "i.category_id = ?"; $params[] = $_GET['category_id']; $types .= 'i'; }
    if (!empty($_GET['location_id'])) { $conditions[] = "i.location_id = ?"; $params[] = $_GET['location_id']; $types .= 'i'; }
    
    if (count($conditions) > 0) { $sql .= " WHERE " . implode(' AND ', $conditions); }
    $sql .= " ORDER BY i.id DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) { throw new Exception("Erro na preparação da query: " . $conn->error); }
    if ($types) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $result = $stmt->get_result();

    echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8"><title>Relatório</title>';
    echo '<style>
        body { font-family: Arial, sans-serif; }
        table { border-collapse: collapse; width: 100%; }
        td { 
            border: 1px solid #dddddd; 
            text-align: left; 
            padding: 8px; 
        }
        tr:nth-child(even) { 
            background-color: #f2f2f2; 
        }
    </style>';
    echo '</head><body><table>';
    
    // ==================================================================
    // ESTILOS APLICADOS DIRETAMENTE NAS CÉLULAS (INLINE)
    // ==================================================================
    $header_style = "background-color: #007bff; color: #ffffff; font-weight: bold; padding: 12px 8px; text-align: left; border-bottom: 2px solid #0056b3;";
    
    echo "<thead><tr>";
    echo "<th style=\"{$header_style}\">Descrição</th>";
    echo "<th style=\"{$header_style}\">Data do Achado</th>";
    echo "<th style=\"{$header_style}\">Status</th>";
    echo "<th style=\"{$header_style}\">Categoria</th>";
    echo "<th style=\"{$header_style}\">Local</th>";
    echo "</tr></thead>";
    
    echo "<tbody>";

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['description']) . "</td>";
            echo "<td>" . date('d/m/Y', strtotime($row['found_date'])) . "</td>";
            echo "<td>" . htmlspecialchars($row['status']) . "</td>";
            echo "<td>" . htmlspecialchars($row['category_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['location_name']) . "</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='5'>Nenhum dado encontrado para os filtros selecionados.</td></tr>";
    }

    echo "</tbody></table></body></html>";

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    error_log($e->getMessage());
    echo "<table><tr><td>Ocorreu um erro ao gerar o relatório.</td></tr></table>";
}

exit();
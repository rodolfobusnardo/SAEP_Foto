<?php
require_once '../auth.php'; // Includes start_secure_session()
require_once '../db_connect.php';

// Access Control: Only 'admin-aprovador' or 'superAdmin'
if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] !== 'admin-aprovador' && $_SESSION['user_role'] !== 'superAdmin')) {
    $_SESSION['home_page_error_message'] = 'Acesso negado. Requer função de Admin Aprovador ou SuperAdmin.';
    header('Location: /home.php');
    exit();
}

$pending_terms = [];
$error_message = '';

// Fetch pending donation terms
// Modificado para buscar o nome da empresa da tabela `companies` usando `company_id`
// e usar `dt.institution_name` como fallback para dados legados.
$sql_terms = "SELECT
                dt.term_id,
                dt.created_at,
                dt.responsible_donation,
                u.username AS registered_by_username,
                COALESCE(cmp.name, dt.institution_name) AS company_or_institution_name
                -- COALESCE(cmp.responsible_name, dt.institution_responsible_name) AS responsible_name_display -- Se necessário
              FROM donation_terms dt
              LEFT JOIN users u ON dt.user_id = u.id
              LEFT JOIN companies cmp ON dt.company_id = cmp.id
              WHERE dt.status = 'Aguardando Aprovação'
              ORDER BY dt.created_at ASC";

$result_terms = $conn->query($sql_terms);
if (!$result_terms) {
    error_log("Error fetching pending donation terms: " . $conn->error);
    $error_message = "Erro ao carregar os termos de doação pendentes: " . $conn->error;
} else {
    while ($term = $result_terms->fetch_assoc()) {
        // For each term, fetch item summary
        $item_summary_parts = [];
        $sql_summary = "SELECT c.name AS category_name, COUNT(dti.item_id) AS item_count
                        FROM donation_term_items dti
                        JOIN items i ON dti.item_id = i.id
                        JOIN categories c ON i.category_id = c.id
                        WHERE dti.term_id = ?
                        GROUP BY c.name
                        ORDER BY c.name ASC";

        $stmt_summary = $conn->prepare($sql_summary);
        if ($stmt_summary) {
            $stmt_summary->bind_param("i", $term['term_id']);
            if ($stmt_summary->execute()) {
                $result_summary_items = $stmt_summary->get_result();
                while ($summary_item = $result_summary_items->fetch_assoc()) {
                    $item_summary_parts[] = htmlspecialchars($summary_item['category_name']) . ": " . htmlspecialchars($summary_item['item_count']);
                }
                $term['item_summary_text'] = empty($item_summary_parts) ? 'Nenhum item encontrado.' : implode(', ', $item_summary_parts);
            } else {
                error_log("Error executing item summary statement for term ID " . $term['term_id'] . ": " . $stmt_summary->error);
                $term['item_summary_text'] = 'Erro ao carregar resumo.';
            }
            $stmt_summary->close();
        } else {
            error_log("Error preparing item summary statement for term ID " . $term['term_id'] . ": " . $conn->error);
            $term['item_summary_text'] = 'Erro ao preparar resumo.';
        }
        $pending_terms[] = $term;
    }
    // AQUI TERMINA O BLOCO ELSE DO PRIMEIRO IF.
    // Não é necessário outro 'else' aqui. O erro original estava aqui.
}
// O bloco 'else' que causava o erro foi removido,
// pois a lógica de erro para '$result_terms' já é tratada acima.

require_once '../templates/header.php';
?>

<div class="container">
    <h2>Aprovar Doações Pendentes</h2>

    <?php if (!empty($error_message)): ?>
        <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
    <?php endif; ?>

    <?php if (isset($_SESSION['approval_action_message'])): ?>
        <p class="<?php echo (isset($_SESSION['approval_action_success']) && $_SESSION['approval_action_success']) ? 'success-message' : 'error-message'; ?>">
            <?php echo htmlspecialchars($_SESSION['approval_action_message']); ?>
        </p>
        <?php
        unset($_SESSION['approval_action_message']);
        unset($_SESSION['approval_action_success']);
        ?>
    <?php endif; ?>

    <?php if (empty($pending_terms) && empty($error_message)): ?>
        <p>Nenhum termo de doação aguardando aprovação no momento.</p>
    <?php elseif (!empty($pending_terms)): ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID do Termo</th>
                    <th>Data de Criação</th>
                    <th>Responsável (Sistema)</th>
                    <th>Registrado por</th>
                    <th>Instituição</th>
                    <th>Resumo dos Itens</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_terms as $term): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($term['term_id']); ?></td>
                        <td><?php echo htmlspecialchars(date('d/m/Y H:i:s', strtotime($term['created_at']))); ?></td>
                        <td><?php echo htmlspecialchars($term['responsible_donation']); ?></td>
                        <td><?php echo htmlspecialchars($term['registered_by_username'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($term['company_or_institution_name']); // Campo atualizado ?></td>
                        <td><?php echo $term['item_summary_text']; // Already htmlspecialchars'd during creation ?></td>
                        <td class="actions-cell">
                            <a href="../view_donation_term_page.php?term_id=<?php echo htmlspecialchars($term['term_id']); ?>&context=approval"
                               class="button-secondary">Analisar Doação</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php
require_once '../templates/footer.php';
?>
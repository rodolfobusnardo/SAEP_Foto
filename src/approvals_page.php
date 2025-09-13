<?php
// Inclui os arquivos essenciais da raiz do projeto
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth.php';

// Exige que o usuário seja um aprovador para ver esta página
require_approver();

// Prepara a consulta ao banco de dados para buscar termos com status 'Em aprovação'
$sql = "SELECT 
            dt.term_id, 
            dt.responsible_donation, 
            dt.created_at,
            u.full_name AS created_by_name
        FROM 
            donation_terms dt
        LEFT JOIN 
            users u ON dt.user_id = u.id
        WHERE 
            dt.status = 'Em aprovação'
        ORDER BY 
            dt.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();

$pageTitle = "Aprovações de Termos Pendentes";
// Apontando para a pasta 'templates'
include 'templates/header.php';
?>

<div class="container admin-container">
    <header class="admin-header">
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
    </header>

    <?php
    // Exibe uma mensagem de erro ou sucesso vinda de um redirecionamento
    if (isset($_GET['message'])) {
        echo '<div class="success-message">' . htmlspecialchars($_GET['message']) . '</div>';
    }
    if (isset($_GET['error'])) {
        echo '<div class="error-message">' . htmlspecialchars($_GET['error']) . '</div>';
    }
    ?>

    <table class="admin-table">
        <thead>
            <tr>
                <th style="width:10%;">Termo Nº</th>
                <th style="width:20%;">Data da Solicitação</th>
                <th style="width:25%;">Solicitado Por</th>
                <th style="width:25%;">Responsável pela Doação</th>
                <th style="width:20%;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($term = $result->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo htmlspecialchars($term['term_id']); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($term['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($term['created_by_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($term['responsible_donation']); ?></td>
                        <td class="actions-cell">
                            <a href="review_donation_term.php?id=<?php echo $term['term_id']; ?>" class="button button-secondary">
                                <i class="fa-solid fa-magnifying-glass"></i> Analisar Pendência
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center;">Nenhum termo de doação aguardando aprovação no momento.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$stmt->close();
$conn->close();
// Apontando para a pasta 'templates'
include 'templates/footer.php'; 
?>
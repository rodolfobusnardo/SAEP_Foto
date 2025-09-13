<?php
require_once 'auth.php'; // For session and access control
require_once 'db_connect.php'; 

start_secure_session();
require_admin('home.php'); // Redirect non-admins to home.php

require_once 'templates/header.php';

// Display success or error messages from session
if (isset($_SESSION['success_message'])) {
    echo '<p class="success-message">' . htmlspecialchars($_SESSION['success_message']) . '</p>';
    unset($_SESSION['success_message']); 
}
if (isset($_SESSION['error_message'])) {
    echo '<p class="error-message">' . htmlspecialchars($_SESSION['error_message']) . '</p>';
    unset($_SESSION['error_message']);
}
// Note: Consider adding a general page error message display like in manage_categories if needed.
?>

<div class="container admin-container">
    <h2>Gerenciar Termos de Doação Pendentes</h2>

    <?php
    // Fetch pending donation terms
    $sql = "SELECT dt.term_id, dt.responsible_donation, 
                   DATE_FORMAT(dt.donation_date, '%d/%m/%Y') as formatted_donation_date, 
                   dt.institution_name, dt.status, u.username as processed_by_username
            FROM donation_terms dt
            JOIN users u ON dt.user_id = u.id 
            WHERE dt.status = 'Aguardando Aprovação' 
            ORDER BY dt.created_at DESC";
    
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        echo '<table class="admin-table">'; // Changed class here
        echo '<thead><tr>';
        echo '<th>ID Termo</th>';
        echo '<th>Responsável (Sistema)</th>';
        echo '<th>Data Doação</th>';
        echo '<th>Instituição</th>';
        echo '<th>Status</th>';
        echo '<th>Processado Por</th>';
        echo '<th>Ação</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        while ($row = $result->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($row['term_id']) . '</td>';
            echo '<td>' . htmlspecialchars($row['responsible_donation']) . '</td>';
            echo '<td>' . htmlspecialchars($row['formatted_donation_date']) . '</td>';
            echo '<td>' . htmlspecialchars($row['institution_name']) . '</td>';
            echo '<td><span class="badge bg-warning text-dark">' . htmlspecialchars(ucfirst($row['status'])) . '</span></td>';
            echo '<td>' . htmlspecialchars($row['processed_by_username']) . '</td>';
            echo '<td class="actions-cell">'; // Added class for potential styling
            // Link to view details (context=approval for view_donation_term_page.php)
            echo '<a href="view_donation_term_page.php?term_id=' . htmlspecialchars($row['term_id']) . '&context=approval" class="button-secondary" style="margin-right: 5px;">Ver Detalhes</a>';
            // Form to approve the donation term
            echo '<form action="admin/process_donation_approval_handler.php" method="POST" style="display:inline;">';
            echo '<input type="hidden" name="term_id" value="' . htmlspecialchars($row['term_id']) . '">';
            echo '<button type="submit" class="button-primary">Aprovar Termo</button>'; // Removed btn-sm
            echo '</form>';
            // TODO: Add a "Reject Term" button/form here later if needed
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    } else {
        echo '<p class="info-message">Nenhum termo de doação pendente de aprovação encontrado.</p>';
    }

    $conn->close();
    ?>

    <p style="margin-top: 20px;">
        <a href="home.php" class="button-secondary">Voltar para Home</a>
    </p>
</div>

<?php
require_once 'templates/footer.php';
?>

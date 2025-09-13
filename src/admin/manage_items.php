<?php
// File: src/admin/manage_items.php
require_once '../auth.php';
require_once '../db_connect.php'; 

start_secure_session();
require_admin('../index.php'); 

require_once '../get_items_handler.php'; 

require_once '../templates/header.php';
?>

<div class="container admin-container">
    <h2>Gerenciar Itens Cadastrados</h2>

    <?php
    if (isset($_GET['success'])) {
        $success_messages = [
            'itemdeleted' => 'Item excluído com sucesso!',
            'itemupdated' => 'Item atualizado com sucesso!', 
        ];
        if (isset($success_messages[$_GET['success']])) {
            echo '<p class="success-message">' . htmlspecialchars($success_messages[$_GET['success']]) . '</p>';
        }
    }
    if (isset($_GET['error'])) {
        $error_messages = [
            'invaliditemid_delete' => 'ID de item inválido para exclusão.',
            'sqlerror_deleteitem' => 'Erro de banco de dados ao excluir item.',
            'itemnotfound_delete' => 'Item não encontrado para exclusão.',
            'itemdeletefailed' => 'Falha ao excluir o item.',
            'invalidrequest_deleteitem' => 'Requisição inválida para exclusão.',
            'invaliditemid_edit' => 'ID de item inválido para edição.',
            'sqlerror_edititem' => 'Erro de banco de dados ao editar item.',
            'deletefailed_fkey' => 'Este item não pode ser excluído devido a dependências no banco.',
        ];
        $error_key = $_GET['error'];
        $display_message = $error_messages[$error_key] ?? 'Ocorreu um erro desconhecido (' . htmlspecialchars($error_key) . ').';
        echo '<p class="error-message">' . htmlspecialchars($display_message) . '</p>';
    }
    if (isset($_GET['message']) && $_GET['message'] == 'itemnochange_edit') {
        echo '<p class="info-message">Nenhuma alteração detectada no item.</p>';
    }
    if (isset($_SESSION['admin_page_error_message'])) { 
        echo '<p class="error-message">' . htmlspecialchars($_SESSION['admin_page_error_message']) . '</p>';
        unset($_SESSION['admin_page_error_message']);
    }
    ?>

    <?php if (!empty($items)): ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Cód. Barras</th>
                <th>Imagem C.B.</th>
                <th>Categoria</th>
                <th>Local</th>
                <th>Data Achado</th>
                <th>Dias Aguardando</th>
                <th>Registrado por</th>
                <th>Detalhes</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['id']); ?></td>
                <td><?php echo htmlspecialchars($item['name']); ?></td>
                <td><?php echo htmlspecialchars($item['barcode'] ?? 'N/A'); ?></td>
                <td>
                    <?php if (!empty($item['barcode'])): ?>
                        <svg id="barcode-<?php echo htmlspecialchars($item['id']); ?>" class="barcode-image"></svg>
                    <?php else: ?>
                        N/A
                    <?php endif; ?>
                </td>
                <td>
                    <?php echo htmlspecialchars($item['category_name'] ?? 'N/A'); ?>
                    (<?php echo htmlspecialchars($item['category_code'] ?? 'N/A'); ?>)
                </td>
                <td><?php echo htmlspecialchars($item['location_name'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars(date("d/m/Y", strtotime($item['found_date']))); ?></td>
                <td><?php echo htmlspecialchars($item['days_waiting'] ?? '0'); ?> dias</td>
                <td>
                    <?php echo htmlspecialchars($item['registered_by_username'] ?? 'Usuário Removido'); ?>
                    <?php if (isset($item['registered_at'])): ?>
                        <br><small>em <?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($item['registered_at']))); ?></small>
                    <?php endif; ?>
                </td>
                <td> <!-- This is the new cell for "Ver Detalhes" button -->
                    <button type="button" class="button-details" data-description="<?php echo htmlspecialchars($item['description'] ?? ''); ?>" title="Ver Detalhes">Ver Detalhes</button>
                </td>
                <td class="actions-cell"> {/* Ensure Ações cell has this class if desired, or style normally */}
                    <a href="edit_item_page.php?id=<?php echo htmlspecialchars($item['id']); ?>" class="button-edit" title="Editar">Editar</a>
                    <form action="delete_item_handler.php" method="POST" style="display:inline-block; margin-top: 5px;" onsubmit="return confirm('Tem certeza que deseja excluir este item?');">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($item['id']); ?>">
                        <button type="submit" class="button-delete" title="Excluir">Excluir</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Item Details Modal -->
    <div id="itemDetailsModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="modal-close-button">&times;</span>
            <h3>Detalhes do Item</h3>
            <p id="modalDescriptionText"></p> {/* Ensure this ID is used by JS */}
        </div>
    </div>
    <?php else: ?>
    <p class="info-message">Nenhum item encontrado ou cadastrado.</p>
    <?php endif; ?>

    <!-- Modal HTML is already correctly placed before this closing div -->
</div> <!-- This is the closing div for container admin-container -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modal JavaScript Logic (as verified and specified in the prompt)
    const modal = document.getElementById('itemDetailsModal');
    const modalTextElement = document.getElementById('modalDescriptionText');
    const closeButton = modal.querySelector('.modal-close-button');

    if (!modal || !modalTextElement || !closeButton) {
        console.error('Modal elements not found!');
        return;
    }

    document.querySelectorAll('.button-details').forEach(button => {
        button.addEventListener('click', function() {
            const description = this.dataset.description;

            if (description && description.trim() !== '') {
                modalTextElement.textContent = description;
            } else {
                modalTextElement.textContent = 'Sem Detalhes';
            }
            modal.style.display = 'block';
        });
    });

    closeButton.onclick = function() {
        modal.style.display = 'none';
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }

    // JsBarcode logic (consolidated here)
    <?php if (!empty($items)): ?>
        <?php foreach ($items as $item): ?>
            <?php if (!empty($item['barcode'])): ?>
                try {
                    const barcodeElement = document.getElementById("barcode-<?php echo htmlspecialchars($item['id']); ?>");
                    if (barcodeElement) {
                        JsBarcode(barcodeElement, "<?php echo htmlspecialchars($item['barcode']); ?>", {
                            format: "CODE128",
                            lineColor: "#000",
                            width: 1.5,
                            height: 40,
                            displayValue: true,
                            fontSize: 10,
                            margin: 2
                        });
                    }
                } catch (e) {
                    console.error("Error generating barcode for #barcode-<?php echo htmlspecialchars($item['id']); ?>: ", e);
                }
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
});
</script>

<?php
require_once '../templates/footer.php';
?>
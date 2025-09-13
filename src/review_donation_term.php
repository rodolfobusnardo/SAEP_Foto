<?php
// ... (código PHP inicial permanece exatamente o mesmo) ...
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth.php';

require_admin();

$term_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$term_id) {
    header('Location: manage_terms.php?error=ID de termo inválido');
    exit();
}

$sql_term = "SELECT dt.*, dt.status as term_status, u.full_name as creator_name, c.*, approver.full_name as approver_name, denier.full_name as denier_name
             FROM donation_terms dt
             LEFT JOIN users u ON dt.user_id = u.id
             LEFT JOIN companies c ON dt.company_id = c.id
             LEFT JOIN users approver ON dt.approved_by_user_id = approver.id
             LEFT JOIN users denier ON dt.reproved_by_user_id = denier.id
             WHERE dt.term_id = ?";
$stmt_term = $conn->prepare($sql_term);
$stmt_term->bind_param("i", $term_id);
$stmt_term->execute();
$term_result = $stmt_term->get_result();
$term = $term_result->fetch_assoc();

if (!$term) {
    header('Location: manage_terms.php?error=Termo não encontrado.');
    exit();
}

$sql_items = "SELECT i.barcode, i.name as item_name, i.description, c.name as category_name, i.image_path, i.image_path_2
              FROM donation_term_items dti
              JOIN items i ON dti.item_id = i.id
              JOIN categories c ON i.category_id = c.id
              WHERE dti.term_id = ?";
$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param("i", $term_id);
$stmt_items->execute();
$items_result = $stmt_items->get_result();

$pageTitle = "Detalhes do Termo de Doação #" . $term['term_id'];
include 'templates/header.php';
?>

<div class="container view-term-container admin-container">

<style>
/* ... (todo o CSS permanece o mesmo) ... */
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); padding-top: 60px; }
.modal-content { background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 600px; border-radius: 8px; position: relative; box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2),0 6px 20px 0 rgba(0,0,0,0.19); }
.modal-close-button { color: #aaa; float: right; font-size: 28px; font-weight: bold; position: absolute; top: 10px; right: 20px; }
.modal-close-button:hover, .modal-close-button:focus { color: black; text-decoration: none; cursor: pointer; }
.modal-content .button-secondary, .modal-content .button-delete { padding: 8px 15px; font-size: 14px; border-radius: 4px; cursor: pointer; text-decoration: none; text-align: center; border: 1px solid transparent; font-weight: bold; }
.modal-content .button-secondary { background-color: #6c757d; color: white; border-color: #5a6268; }
.modal-content .button-secondary:hover { background-color: #5a6268; }
.modal-content .button-delete { background-color: #dc3545; color: white; border-color: #c82333; }
.modal-content .button-delete:hover { background-color: #c82333; }
#modal_reproval_reason { width: 100%; box-sizing: border-box; }
.image-thumbnail-container { display: flex; gap: 5px; justify-content: center; align-items: center; }
.image-thumbnail { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; cursor: pointer; transition: transform 0.2s; }
.image-thumbnail:hover { transform: scale(1.05); }
#imageModal {
    position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8);
    display: flex; justify-content: center; align-items: center;
    visibility: hidden; opacity: 0; transition: opacity 0.3s, visibility 0.3s; padding: 0;
}
#imageModal.is-visible { visibility: visible; opacity: 1; }
#imageModal .modal-content-image { display: block; max-width: 60%; max-height: 60%; border-radius: 5px; }
#imageModal .close-modal { position: absolute; top: 15px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; transition: 0.3s; cursor: pointer; }
.action-card {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 1.5rem;
    text-align: center;
    margin-top: 2rem;
}
.action-card h3 {
    margin-top: 0;
    margin-bottom: 1rem;
    padding-bottom: 0;
    border-bottom: none;
}
.action-card .approval-actions {
    justify-content: center;
    padding-top: 0.5rem;
}
.action-card .approval-actions button {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 1rem;
    font-weight: bold;
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 8px;
    border: 1px solid transparent;
    cursor: pointer;
    transition: background-color 0.2s, border-color 0.2s, box-shadow 0.2s;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
#approve_button.button-primary {
    background-color: #007bff;
    border-color: #007bff;
}
#approve_button.button-primary:hover {
    background-color: #0056b3;
    border-color: #0056b3;
    box-shadow: 0 4px 8px rgba(0,0,0,0.3);
}
#deny_button.button-delete {
    background-color: #8d2428;
    border-color: #8d2428;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    padding: 8px 20px;
}
#deny_button.button-delete:hover {
    background-color: #d97979;
    border-color: #d97979;
    box-shadow: 0 4px 8px rgba(0,0,0,0.3);
}
</style>

<div id="denialModal" class="modal">
  <div class="modal-content"> <span class="modal-close-button">&times;</span> <h3>Negar Termo de Doação</h3> <p>Por favor, forneça o motivo pelo qual este termo de doação está sendo negado. Esta informação é obrigatória.</p> <div class="form-group"> <textarea id="modal_reproval_reason" class="form-control" rows="5" placeholder="Digite o motivo da negação aqui..."></textarea> </div> <div class="approval-actions" style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 20px;"> <button type="button" class="button-secondary modal-cancel-button">Cancelar</button> <button type="button" id="confirmDenyButton" class="button-delete">Confirmar Negação</button> </div>
  </div>
</div>
<div id="imageModal"> <span class="close-modal">&times;</span> <img class="modal-content-image" id="modalImageContent"></div>
<h2><?php echo $pageTitle; ?></h2>
<div class="term-section"> <h3>Dados da Doação</h3> <p><strong>Status Atual:</strong> <span class="status-tag status-<?php echo strtolower(str_replace(' ', '-', $term['term_status'])); ?>"><?php echo htmlspecialchars($term['term_status']); ?></span></p> <?php if($term['term_status'] === 'Aprovado'): ?> <p><strong>Aprovado por:</strong> <?php echo htmlspecialchars($term['approver_name'] ?? 'N/A'); ?> em <?php echo date('d/m/Y H:i', strtotime($term['approved_at'])); ?></p> <?php elseif($term['term_status'] === 'Negado'): ?> <p><strong>Negado por:</strong> <?php echo htmlspecialchars($term['denier_name'] ?? 'N/A'); ?> em <?php echo date('d/m/Y H:i', strtotime($term['reproved_at'])); ?></p> <p><strong>Motivo:</strong> <?php echo htmlspecialchars($term['reproval_reason']); ?></p> <?php endif; ?>
</div>
<div class="term-section"> <h3>Instituição Recebedora</h3> <p><strong>Nome:</strong> <?php echo htmlspecialchars($term['name']); ?></p> <p><strong>CNPJ:</strong> <?php echo htmlspecialchars($term['cnpj'] ?? 'Não informado'); ?></p> <?php if (!empty($term['ie'])): ?> <p><strong>Inscrição Estadual:</strong> <?php echo htmlspecialchars($term['ie']); ?></p> <?php endif; ?> <?php if (!empty($term['responsible_name'])): ?> <p><strong>Nome do Responsável:</strong> <?php echo htmlspecialchars($term['responsible_name']); ?></p> <?php endif; ?> <?php $address_parts = [ $term['address_street'], $term['address_number'], $term['address_complement'], $term['address_neighborhood'], $term['address_city'], $term['address_state'], $term['address_cep'] ]; $full_address = implode(', ', array_filter($address_parts)); ?> <?php if (!empty($full_address)): ?> <p><strong>Endereço:</strong> <?php echo htmlspecialchars($full_address); ?></p> <?php endif; ?> <?php if (!empty($term['phone'])): ?> <p><strong>Telefone:</strong> <?php echo htmlspecialchars($term['phone']); ?></p> <?php endif; ?> <?php if (!empty($term['email'])): ?> <p><strong>Email:</strong> <?php echo htmlspecialchars($term['email']); ?></p> <?php endif; ?> <?php if (!empty($term['observations'])): ?> <p><strong>Observações:</strong> <?php echo nl2br(htmlspecialchars($term['observations'])); ?></p> <?php endif; ?>
</div>

    <div class="term-section">
        <h3>Itens Doados</h3>
        <table class="admin-table" id="items-table">
            <thead>
                <tr>
                    <th style="width: 20%;">Imagens</th>
                    <th style="width: 20%;">Código de Barras</th>
                    <th style="width: 25%;">Nome do Item</th>
                    <th style="width: 15%;">Categoria</th>
                    <th style="width: 20%;">Descrição</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($item = $items_result->fetch_assoc()): ?>
                <tr>
                    <td>
                        <div class="image-thumbnail-container">
                            <?php
                            $has_image = false;
                            if (!empty($item['image_path'])) {
                                echo '<img class="image-thumbnail" src="/' . htmlspecialchars($item['image_path']) . '" data-fullsrc="/' . htmlspecialchars($item['image_path']) . '">';
                                $has_image = true;
                            }
                            if (!empty($item['image_path_2'])) {
                                echo '<img class="image-thumbnail" src="/' . htmlspecialchars($item['image_path_2']) . '" data-fullsrc="/' . htmlspecialchars($item['image_path_2']) . '">';
                                $has_image = true;
                            }
                            if (!$has_image) {
                                echo '<span>-</span>';
                            }
                            ?>
                        </div>
                    </td>
                    <td><?php echo htmlspecialchars($item['barcode'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($item['item_name'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($item['category_name'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($item['description'] ?? ''); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <?php if ($term['term_status'] === 'Em aprovação' && is_approver()): ?>
        <div class="action-card">
            <h3>Ação Requerida</h3>
            <form action="process_approval_handler.php" method="POST" id="approvalForm">
                <input type="hidden" name="term_id" value="<?php echo $term['term_id']; ?>">
                <input type="hidden" name="action" id="actionInput" value="">
                <div class="form-group" style="display: none;">
                    <textarea name="reproval_reason" id="reproval_reason" class="form-control" rows="3"></textarea>
                </div>
                <div class="approval-actions" style="display: flex; gap: 15px;">
                    <button type="submit" id="approve_button" class="button-primary">
                        <i class="fas fa-check"></i> Aprovar Termo de Doação
                    </button>
                    <button type="button" id="deny_button" class="button-delete">
                        <i class="fas fa-times"></i> Negar Termo de Doação
                    </button>
                </div>
            </form>
        </div>

    <?php elseif ($term['term_status'] === 'Aprovado' && is_admin()): ?>
        <div class="term-section">
            <h3>Assinatura e Finalização</h3>
            <form action="finalize_donation_handler.php" method="POST" id="finalizationForm" class="data-section-rounded">
                <input type="hidden" name="term_id" value="<?php echo $term['term_id']; ?>">
                <input type="hidden" name="signature_data" id="signatureDataInput">
                <fieldset>
                    <legend>Assinatura do Responsável da Instituição</legend>
                    <p>Peça para que o responsável da instituição assine no quadro abaixo para confirmar a retirada dos itens.</p>
                    <div id="signaturePadContainer" class="signature-pad-container">
                        <canvas id="signatureCanvas"></canvas>
                    </div>
                    <button type="button" id="clearSignatureButton" class="button-secondary">Limpar Assinatura</button>
                </fieldset>
                <div class="form-action-buttons-group" style="margin-top: 20px;">
                    <a href="/manage_terms.php" class="button-secondary">Voltar</a>
                    <button type="submit" class="button-primary">Finalizar Doação</button>
                </div>
            </form>
        </div>

    <?php elseif ($term['term_status'] === 'Doado'): ?>
        <div class="term-section">
            <h3>Assinatura Coletada</h3>
            <?php if (!empty($term['signature_image_path'])): ?>
                <img src="/<?php echo htmlspecialchars($term['signature_image_path']); ?>" alt="Assinatura do Responsável" class="signature-image">
            <?php else: ?>
                <p>Nenhuma imagem de assinatura foi encontrada para este termo.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<script>
// ... (JavaScript permanece o mesmo) ...
document.addEventListener('DOMContentLoaded', function() {
    const imageModal = document.getElementById("imageModal");
    if (imageModal) {
        const modalImg = document.getElementById("modalImageContent");
        const itemsTable = document.getElementById("items-table");
        const closeModalSpan = imageModal.querySelector(".close-modal");

        itemsTable.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('image-thumbnail') && e.target.dataset.fullsrc) {
                imageModal.classList.add('is-visible');
                modalImg.src = e.target.dataset.fullsrc;
            }
        });

        function closeImageModal() {
            imageModal.classList.remove('is-visible');
        }

        closeModalSpan.onclick = closeImageModal;
        imageModal.onclick = function(e) {
            if (e.target === imageModal) {
                closeImageModal();
            }
        }
    }

    <?php if ($term['term_status'] === 'Em aprovação' && is_approver()): ?>
    const approvalForm = document.getElementById('approvalForm');
    const actionInput = document.getElementById('actionInput');
    const approveButton = document.getElementById('approve_button');
    const denyButton = document.getElementById('deny_button');
    const originalReasonTextarea = document.getElementById('reproval_reason');
    const denialModal = document.getElementById('denialModal');
    const modalReasonTextarea = document.getElementById('modal_reproval_reason');
    const confirmDenyButton = document.getElementById('confirmDenyButton');
    const closeModalButton = document.querySelector('.modal-close-button');
    const cancelModalButton = document.querySelector('.modal-cancel-button');

    approveButton.addEventListener('click', function(event) {
        event.preventDefault();
        if (confirm('Tem certeza que deseja APROVAR este termo de doação?')) {
            actionInput.value = 'approve';
            originalReasonTextarea.required = false;
            approvalForm.submit();
        }
    });

    denyButton.addEventListener('click', function(event) {
        event.preventDefault();
        denialModal.style.display = 'block';
    });

    confirmDenyButton.addEventListener('click', function() {
        const reason = modalReasonTextarea.value.trim();
        if (reason === '') {
            alert('O motivo da negação é obrigatório.');
            modalReasonTextarea.focus();
            return;
        }

        if (confirm('Tem certeza que deseja NEGAR este termo de doação?')) {
            actionInput.value = 'deny';
            originalReasonTextarea.value = reason;
            approvalForm.submit();
        }
    });

    function closeModal() {
        denialModal.style.display = 'none';
        modalReasonTextarea.value = '';
    }

    closeModalButton.addEventListener('click', closeModal);
    cancelModalButton.addEventListener('click', closeModal);

    window.addEventListener('click', function(event) {
        if (event.target == denialModal) {
            closeModal();
        }
    });
    <?php endif; ?>
});
</script>
<?php if ($term['term_status'] === 'Aprovado' && is_admin()): ?>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('signatureCanvas');
    const signaturePad = new SignaturePad(canvas, { backgroundColor: 'rgb(255, 255, 255)' });
    document.getElementById('clearSignatureButton').addEventListener('click', function () { signaturePad.clear(); });
    document.getElementById('finalizationForm').addEventListener('submit', function (event) {
        if (signaturePad.isEmpty()) {
            alert("Por favor, forneça a assinatura do responsável.");
            event.preventDefault();
            return;
        }
        document.getElementById('signatureDataInput').value = signaturePad.toDataURL('image/png');
    });
});
</script>
<?php endif; ?>
<?php
$stmt_term->close();
$stmt_items->close();
$conn->close();
include 'templates/footer.php';
?>
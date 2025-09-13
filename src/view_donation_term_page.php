<?php
require_once 'auth.php';
require_once 'db_connect.php';

require_admin();

$term_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$term_id) {
    header('Location: manage_terms.php?error=ID do termo inválido.');
    exit();
}

// Fetch term details
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

// Consulta para itens detalhados
$sql_items = "SELECT i.name, i.description, cat.name as category_name, i.image_path, i.image_path_2 
              FROM donation_term_items dti
              JOIN items i ON dti.item_id = i.id
              JOIN categories cat ON i.category_id = cat.id
              WHERE dti.term_id = ?";
$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param("i", $term_id);
$stmt_items->execute();
$items_result = $stmt_items->get_result();

// Consulta para o resumo de itens por categoria
$sql_summary = "SELECT cat.name as category_name, COUNT(i.id) as item_count
                  FROM donation_term_items dti
                  JOIN items i ON dti.item_id = i.id
                  JOIN categories cat ON i.category_id = cat.id
                  WHERE dti.term_id = ?
                  GROUP BY cat.name
                  ORDER BY cat.name ASC";
$stmt_summary = $conn->prepare($sql_summary);
$stmt_summary->bind_param("i", $term_id);
$stmt_summary->execute();
$summary_result = $stmt_summary->get_result();
$summary_items = [];
while ($row = $summary_result->fetch_assoc()) {
    $summary_items[] = $row;
}

$pageTitle = "Detalhes do Termo de Doação #" . $term['term_id'];
include 'templates/header.php';
?>

<style>
    .term-section {
        margin-bottom: 45px;
    }
    
    @media print {
        .term-section, .admin-table tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }
        h3 {
            page-break-after: avoid;
            break-after: avoid;
        }
        @page {
            size: A4;
            margin: 2cm;
            @top-right {
                content: "Página " counter(page) " de " counter(pages);
                font-size: 9pt;
                color: #777;
            }
        }
        body, .container {
            margin: 0 !important; padding: 0 !important; width: 100%;
            max-width: 100%; box-shadow: none; background-color: #fff !important;
        }
        header, footer, .no-print, .form-action-buttons-group, .term-actions, #imageModal {
            display: none !important;
        }
        .item-image-thumbnail { max-width: 50px; height: auto; border: 1px solid #eee; }
        .view-term-container h3 { text-align: center; color: #000; }
        .view-term-container { border: none !important; }
        .signature-image { display: block; margin: 20px auto; max-width: 300px; }
        .admin-table th, .admin-table td { padding: 4px 8px; font-size: 9pt; line-height: 1.2; }
        .admin-table th { background-color: #f2f2f2 !important; color: #000 !important; }
        #print-header {
            display: block;
            text-align: center;
            background-color: #fff;
            padding-bottom: 10px;
            border-bottom: 2px solid #ccc;
            margin-bottom: 25px;
        }
        .view-term-container h2 { display: none; }
        .container.view-term-container { 
            padding-top: 0 !important;
        }
    }
    
    #print-header { display: none; }

    .image-thumbnail-container { display: flex; flex-wrap: wrap; gap: 5px; justify-content: center; align-items: center; }
    .item-image-thumbnail { width: 60px; height: 60px; object-fit: cover; border-radius: 4px;
        border: 1px solid #ddd; cursor: pointer; transition: transform 0.2s; }
    .item-image-thumbnail:hover { transform: scale(1.05); }
    #imageModal { position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%;
        background-color: rgba(0,0,0,0.8); display: flex; justify-content: center;
        align-items: center; visibility: hidden; opacity: 0;
        transition: opacity 0.3s, visibility 0.3s; padding: 0; }
    #imageModal.is-visible { visibility: visible; opacity: 1; }
    #imageModal .modal-content-image { display: block; max-width: 80%; max-height: 80%; border-radius: 5px; object-fit: contain; }
    #imageModal .close-modal { position: absolute; top: 15px; right: 35px; color: #f1f1f1;
        font-size: 40px; font-weight: bold; transition: 0.3s; cursor: pointer; }
    #imageModal .close-modal:hover, #imageModal .close-modal:focus { color: #bbb; text-decoration: none; cursor: pointer; }

    #summaryItemsSection { display: none; }
    #items-table td { font-weight: normal !important; }

    .action-button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 24px;
        font-size: 1rem; font-weight: 500; color: #ffffff; border-radius: 8px; cursor: pointer;
        text-decoration: none; transition: opacity 0.2s ease-in-out; border: none; font-family: inherit; }
    .action-button:hover { opacity: 0.85; }
    #printDetailedButton { background-color: #007bff; }
    #printSummaryButton { background-color: #007bff; }
    #backToTermsButton { background-color: #6c757d; }

    .view-term-container .finalization-card { background-color: #f8f9fa !important; border: 1px solid #dee2e6 !important; border-radius: 8px !important; padding: 30px !important; margin-top: 20px !important; box-shadow: 0 4px 8px rgba(0,0,0,0.05) !important; }
    .finalization-card h3 { text-align: center !important; margin-bottom: 25px !important; color: #007bff !important; border-bottom: none !important; padding-bottom: 0 !important; }
    .form-content-wrapper { border: none !important; padding: 0 !important; margin: 0 !important; display: flex !important; flex-direction: column !important; align-items: center !important; gap: 20px !important; width: 100%; }
    .form-content-wrapper p { text-align: center !important; max-width: 450px; margin-bottom: 0 !important; line-height: 1.5 !important; }
    .signature-wrapper { display: flex; flex-direction: column; width: 350px !important; }
    .signature-wrapper #signaturePadContainer { border-bottom-left-radius: 0 !important; border-bottom-right-radius: 0 !important; border: 2px solid #ccc !important; border-bottom: none !important; box-sizing: border-box !important; width: 100%; height: 180px; }
    #signatureCanvas { width: 100%; height: 100%; }
    .signature-wrapper #clearSignatureButton { width: 100% !important; margin-top: 0 !important; border-top-left-radius: 0 !important; border-top-right-radius: 0 !important; border: 2px solid #ccc !important; border-top: 1px solid #ddd !important; background-color: #000000 !important; color: #ffffff !important; padding: 10px 15px !important; font-size: 0.9rem !important; box-sizing: border-box !important; }
    .finalization-card .form-action-buttons-group { display: flex !important; justify-content: center !important; gap: 15px !important; margin-top: 30px !important; }
    #backToManageTermsButton { text-align: center !important; display: flex !important; justify-content: center !important; align-items: center !important; padding: 15px 25px !important; background-color: #6c757d !important; color: #ffffff !important; border-radius: 8px !important; text-decoration: none !important; border: none !important; }
    #finalizeDonationButton { text-align: center !important; display: flex !important; justify-content: center !important; align-items: center !important; padding: 15px 25px !important; background-color: #007bff !important; color: #ffffff !important; border-radius: 8px !important; border: none !important; }

    /* [NOVO] Estilos para o botão flutuante de rolagem */
    #scrollToBottomBtn {
        position: fixed; /* Mantém o botão fixo na tela */
        bottom: 20px;
        right: 20px;
        z-index: 1000; /* Garante que ele fique sobre outros elementos */
        background-color: #5a6268; /* Uma cor um pouco diferente para destacar */
        padding: 10px 15px;
        gap: 10px; /* Espaçamento entre o ícone e o texto */
    }

    #scrollToBottomBtn span {
        font-size: 0.9rem;
    }
</style>

<div id="print-header">
    <h3>Detalhes do Termo de Doação #<?php echo htmlspecialchars($term['term_id']); ?></h3>
</div>

<div class="container view-term-container admin-container">
    <h2><?php echo $pageTitle; ?></h2>

    <div class="term-section">
        <h3>Dados do Termo</h3>
        <p><strong>Status:</strong> <span class="status-tag"><?php echo htmlspecialchars($term['term_status']); ?></span></p>
        <?php if($term['term_status'] === 'Aprovado' && $term['approved_at']): ?>
            <p><strong>Aprovado por:</strong> <?php echo htmlspecialchars($term['approver_name'] ?? 'N/A'); ?> em <?php echo date('d/m/Y H:i', strtotime($term['approved_at'])); ?></p>
        <?php elseif($term['term_status'] === 'Negado' && $term['reproved_at']): ?>
            <p><strong>Negado por:</strong> <?php echo htmlspecialchars($term['denier_name'] ?? 'N/A'); ?> em <?php echo date('d/m/Y H:i', strtotime($term['reproved_at'])); ?></p>
            <p><strong>Motivo:</strong> <?php echo nl2br(htmlspecialchars($term['reproval_reason'])); ?></p>
        <?php endif; ?>
    </div>
    <div class="term-section">
        <h3>Instituição Recebedora</h3>
        <p><strong>Nome:</strong> <?php echo htmlspecialchars($term['name']); ?></p>
        <p><strong>CNPJ:</strong> <?php echo htmlspecialchars($term['cnpj'] ?? 'Não informado'); ?></p>
        <?php if (!empty($term['ie'])): ?><p><strong>Inscrição Estadual:</strong> <?php echo htmlspecialchars($term['ie']); ?></p><?php endif; ?>
        <?php if (!empty($term['responsible_name'])): ?><p><strong>Nome do Responsável:</strong> <?php echo htmlspecialchars($term['responsible_name']); ?></p><?php endif; ?>
        <?php
            $address_parts = [$term['address_street'], $term['address_number'], $term['address_complement'], $term['address_neighborhood'], $term['address_city'], $term['address_state'], $term['address_cep']];
            $full_address = implode(', ', array_filter($address_parts));
        ?>
        <?php if (!empty($full_address)): ?><p><strong>Endereço:</strong> <?php echo htmlspecialchars($full_address); ?></p><?php endif; ?>
        <?php if (!empty($term['phone'])): ?><p><strong>Telefone:</strong> <?php echo htmlspecialchars($term['phone']); ?></p><?php endif; ?>
        <?php if (!empty($term['email'])): ?><p><strong>Email:</strong> <?php echo htmlspecialchars($term['email']); ?></p><?php endif; ?>
        <?php if (!empty($term['observations'])): ?><p><strong>Observações:</strong> <?php echo nl2br(htmlspecialchars($term['observations'])); ?></p><?php endif; ?>
    </div>
    <div class="term-section" id="detailedItemsSection">
        <h3>Itens</h3>
        <table class="admin-table" id="items-table">
            <thead><tr><th>Imagens</th><th>Item</th><th>Categoria</th><th>Descrição</th></tr></thead>
            <tbody>
                <?php while ($item = $items_result->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <div class="image-thumbnail-container">
                                <?php 
                                $has_image = false;
                                if (!empty($item['image_path'])) {
                                    echo '<img src="/' . htmlspecialchars($item['image_path']) . '" alt="' . htmlspecialchars($item['name']) . '" class="item-image-thumbnail" data-fullsrc="/' . htmlspecialchars($item['image_path']) . '">';
                                    $has_image = true;
                                }
                                if (!empty($item['image_path_2'])) {
                                    echo '<img src="/' . htmlspecialchars($item['image_path_2']) . '" alt="' . htmlspecialchars($item['name']) . ' - foto 2" class="item-image-thumbnail" data-fullsrc="/' . htmlspecialchars($item['image_path_2']) . '">';
                                    $has_image = true;
                                }
                                if (!$has_image) {
                                    echo '<span>-</span>';
                                }
                                ?>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                        <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                        <td><?php echo htmlspecialchars($item['description'] ?? ''); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <div class="term-section" id="summaryItemsSection">
        <h3>Resumo de Itens por Categoria</h3>
        <div style="line-height: 1.5; font-size: 11pt;">
            <?php
            foreach ($summary_items as $summary) {
                echo '&bull; (' . htmlspecialchars($summary['item_count']) . ') ' . htmlspecialchars($summary['category_name']) . '<br>';
            }
            ?>
        </div>
    </div>

    <?php if ($term['term_status'] === 'Aprovado' && is_admin()): ?>
        <div class="term-section">
            <div class="finalization-card">
                <h3>Finalizar Doação</h3>
                <form action="finalize_donation_handler.php" method="POST" id="finalizationForm">
                    <input type="hidden" name="term_id" value="<?php echo $term['term_id']; ?>">
                    <input type="hidden" name="signature_data" id="signatureDataInput">
                    <div class="form-content-wrapper">
                        <p>Peça para que o responsável da instituição assine no quadro abaixo para confirmar a retirada dos itens.</p>
                        <div class="signature-wrapper">
                            <div id="signaturePadContainer">
                                <canvas id="signatureCanvas"></canvas>
                            </div>
                            <button type="button" id="clearSignatureButton" class="button-secondary">Limpar Assinatura</button>
                        </div>
                    </div>
                    <div class="form-action-buttons-group">
                        <a href="manage_terms.php" id="backToManageTermsButton" class="button-secondary">Voltar</a>
                        <button type="submit" id="finalizeDonationButton" class="button-primary">Finalizar Doação</button>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <?php if($term['term_status'] === 'Doado' && !empty($term['signature_image_path'])): ?>
            <div class="term-section">
                <h3>Assinatura Coletada</h3>
                <img src="/<?php echo htmlspecialchars($term['signature_image_path']); ?>" alt="Assinatura do Responsável" class="signature-image">
            </div>
        <?php endif; ?>
        
        <div class="term-actions no-print form-action-buttons-group" style="margin-top: 30px; justify-content: center; gap: 10px;">
             <button id="printDetailedButton" class="action-button"><i class="fas fa-print"></i> Imprimir Termo Detalhado</button>
             <button id="printSummaryButton" class="action-button"><i class="fas fa-print"></i> Imprimir Termo Resumido</button>
             <button id="backToTermsButton" class="action-button" onclick="window.location.href='manage_terms.php'"><i class="fas fa-arrow-left"></i> Voltar para Termos</button>
        </div>
    <?php endif; ?>
</div>

<div id="imageModal">
    <span class="close-modal">&times;</span>
    <img class="modal-content-image" id="modalImageContent">
</div>

<button id="scrollToBottomBtn" class="action-button no-print" title="Rolar tudo para baixo">
    <i class="fas fa-arrow-down"></i>
    <span>Rolar tudo para baixo</span>
</button>

<?php if ($term['term_status'] === 'Aprovado' && is_admin()): ?>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('signatureCanvas');
    if (canvas) {
        const signaturePad = new SignaturePad(canvas, { backgroundColor: 'rgb(255, 255, 255)' });
        function resizeCanvas() {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext("2d").scale(ratio, ratio);
            signaturePad.clear();
        }
        window.addEventListener("resize", resizeCanvas);
        resizeCanvas();
        document.getElementById('clearSignatureButton').addEventListener('click', function() { signaturePad.clear(); });
        document.getElementById('finalizationForm').addEventListener('submit', function(event) {
            if (signaturePad.isEmpty()) {
                alert("Por favor, forneça a assinatura do responsável.");
                event.preventDefault();
                return;
            }
            if (!confirm('Tem certeza que deseja finalizar esta doação? Esta ação não pode ser desfeita.')) {
                event.preventDefault();
                return;
            }
            document.getElementById('signatureDataInput').value = signaturePad.toDataURL('image/png');
        });
    }
});
</script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const imageModal = document.getElementById("imageModal");
    if (imageModal) {
        const modalImg = document.getElementById("modalImageContent");
        const itemsTable = document.getElementById("items-table");
        const closeModalSpan = imageModal.querySelector(".close-modal");
        itemsTable.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('item-image-thumbnail') && e.target.dataset.fullsrc) {
                imageModal.classList.add('is-visible');
                modalImg.src = e.target.dataset.fullsrc;
            }
        });
        function closeImageModal() { imageModal.classList.remove('is-visible'); }
        closeModalSpan.onclick = closeImageModal;
        imageModal.onclick = function(e) { if (e.target === imageModal) { closeImageModal(); } };
    }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const scrollToBottomButton = document.getElementById('scrollToBottomBtn');

    if (scrollToBottomButton) {
        // Função que verifica a posição da rolagem e exibe/oculta o botão
        function toggleScrollButtonVisibility() {
            // Calcula se o usuário está no final da página (com uma pequena margem de 10px)
            const isAtBottom = window.innerHeight + window.scrollY >= document.body.offsetHeight - 10;

            if (isAtBottom) {
                scrollToBottomButton.style.display = 'none'; // Oculta o botão
            } else {
                // Usa 'inline-flex' pois é o display padrão da classe .action-button
                scrollToBottomButton.style.display = 'inline-flex'; // Exibe o botão
            }
        }

        // Adiciona o evento de clique para a ação de rolar
        scrollToBottomButton.addEventListener('click', function() {
            window.scrollTo({
                top: document.body.scrollHeight,
                behavior: 'smooth'
            });
        });

        // Adiciona o "ouvinte" ao evento de rolagem da página
        window.addEventListener('scroll', toggleScrollButtonVisibility);

        // Executa a função uma vez no carregamento para definir o estado inicial do botão
        toggleScrollButtonVisibility();
    }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const scrollToBottomButton = document.getElementById('scrollToBottomBtn');

    if (scrollToBottomButton) {
        scrollToBottomButton.addEventListener('click', function() {
            window.scrollTo({
                top: document.body.scrollHeight, // Rola para a altura total do corpo do documento
                behavior: 'smooth' // Animação suave de rolagem
            });
        });
    }
});
</script>

<?php
$stmt_term->close();
$stmt_items->close();
$stmt_summary->close();
$conn->close();
include 'templates/footer.php';
?>
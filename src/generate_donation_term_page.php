<?php
// ... (código PHP inicial permanece exatamente o mesmo) ...
require_once 'auth.php';
require_once 'db_connect.php';

require_admin('../index.php?error=pleaselogin');

$item_ids_str = $_GET['item_ids'] ?? '';

$page_error_message = $_SESSION['generate_donation_page_error_message'] ?? null;
unset($_SESSION['generate_donation_page_error_message']);

$item_ids = [];
$items_for_donation = [];
$valid_item_ids_for_donation = [];

if (empty($item_ids_str)) {
    $_SESSION['home_page_error_message'] = "Nenhum item selecionado para doação.";
    header('Location: /home.php');
    exit();
}

$item_ids_array = explode(',', $item_ids_str);
foreach ($item_ids_array as $id_str_loop) {
    $id_int = filter_var(trim($id_str_loop), FILTER_VALIDATE_INT);
    if ($id_int !== false && $id_int > 0) {
        $item_ids[] = $id_int;
    }
}

if (empty($item_ids)) {
    $_SESSION['home_page_error_message'] = "IDs de itens inválidos fornecidos.";
    header('Location: /home.php');
    exit();
}

if ($conn && !empty($item_ids)) {
    $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
    
    $sql_items = "SELECT i.id, i.name, i.image_path, i.image_path_2, i.barcode
                  FROM items i
                  WHERE i.id IN ($placeholders) AND i.status = 'Pendente'";

    $stmt_items = $conn->prepare($sql_items);
    if ($stmt_items) {
        $types = str_repeat('i', count($item_ids));
        $stmt_items->bind_param($types, ...$item_ids);
        $stmt_items->execute();
        $result_items = $stmt_items->get_result();

        while ($row = $result_items->fetch_assoc()) {
            $items_for_donation[] = $row;
            $valid_item_ids_for_donation[] = $row['id'];
        }
        $stmt_items->close();
    } else {
        error_log("DB Prepare Error (fetch items for donation): " . $conn->error);
        $page_error_message = "Erro ao buscar detalhes dos itens. Tente novamente.";
    }
} else if (!$conn) {
     error_log("DB Connection failed on generate_donation_term_page.");
     $page_error_message = "Erro de conexão com o banco de dados.";
}

$total_items_for_donation = count($items_for_donation);

if ($total_items_for_donation === 0 && empty($page_error_message)) {
    if (count($item_ids) > 0) {
        $_SESSION['home_page_error_message'] = "Nenhum dos itens selecionados está disponível para doação (podem não estar 'Pendentes' ou IDs são inválidos).";
    } else {
         $_SESSION['home_page_error_message'] = "Nenhum item válido para doação.";
    }
    header('Location: /home.php');
    exit();
}

$current_user_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'N/A';
$current_date = date('Y-m-d');
$current_time = date('H:i');

require_once 'templates/header.php';
?>
<style>
    .remove-item-btn {
        background-color: #dc3545;
        color: white;
        border: none;
        padding: 5px 10px;
        border-radius: 4px;
        cursor: pointer;
    }
    .remove-item-btn:hover {
        background-color: #c82333;
    }

    /* Estilos das Imagens e Modal (sem alterações) */
    .image-thumbnail-container { display: flex; gap: 8px; justify-content: center; align-items: center; height: 100%; }
    .image-thumbnail { width: 70px; height: 70px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; cursor: pointer; transition: transform 0.2s; }
    .image-thumbnail:hover { transform: scale(1.05); }
    #donation-items-table td:first-child { text-align: center; vertical-align: middle; }
    .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.8); justify-content: center; align-items: center; }
    .modal.is-visible { display: flex; }
    .modal-content { margin: auto; display: block; max-width: 60%; max-height: 60%; border-radius: 5px; }
    .close-modal { position: absolute; top: 15px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; transition: 0.3s; cursor: pointer; }
    
    /* ## ALTERAÇÕES: Espaçamento da seção e estilo do título 'Dados da Doação' ## */
    .form-modern fieldset.data-section-rounded {
        margin-top: 30px; /* Espaço adicionado acima da seção */
    }

    .form-modern fieldset.data-section-rounded > legend {
        color: #007bff;
        font-weight: bold;
        font-size: 1.2rem;   /* Tamanho um pouco maior para destaque */
        padding: 0 10px;    /* Adiciona um respiro nas laterais */
        margin-left: 15px;  /* Alinha com o conteúdo do fieldset */
    }
</style>
<div class="container register-item-container">
    <h2>Registrar Termo de Doação</h2>

    <?php if ($page_error_message): ?>
        <p class="error-message"><?php echo htmlspecialchars($page_error_message); ?></p>
    <?php endif; ?>

    <?php if ($total_items_for_donation > 0): ?>
        <form action="submit_donation_handler.php" method="POST" id="donationForm" class="form-modern">
            <div class="data-section-rounded">
                <h4>Itens Selecionados para Doação</h4>
                <p><strong>Total de itens: <span id="total-items-count"><?php echo $total_items_for_donation; ?></span></strong></p>
                <table class="admin-table" id="donation-items-table">
                    <thead>
                        <tr>
                            <th style="width: 20%;">Imagens</th>
                            <th style="width: 50%;">Nome do Item</th>
                            <th style="width: 20%;">Cód. de Barras</th>
                            <th style="width: 10%;">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items_for_donation as $item): ?>
                            <tr data-item-id="<?php echo htmlspecialchars($item['id']); ?>">
                                <td>
                                    <div class="image-thumbnail-container">
                                        <?php 
                                        $has_image_displayed = false;
                                        if (!empty($item['image_path'])) {
                                            echo '<img class="image-thumbnail" src="/' . htmlspecialchars($item['image_path']) . '" alt="' . htmlspecialchars($item['name']) . '" data-fullsrc="/' . htmlspecialchars($item['image_path']) . '">';
                                            $has_image_displayed = true;
                                        }
                                        if (!empty($item['image_path_2'])) {
                                            echo '<img class="image-thumbnail" src="/' . htmlspecialchars($item['image_path_2']) . '" alt="' . htmlspecialchars($item['name']) . ' - foto 2" data-fullsrc="/' . htmlspecialchars($item['image_path_2']) . '">';
                                            $has_image_displayed = true;
                                        }
                                        if (!$has_image_displayed) {
                                            echo '<img class="image-thumbnail" src="/src/favicon.png" alt="Sem imagem">';
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo htmlspecialchars($item['barcode']); ?></td>
                                <td>
                                    <button type="button" class="remove-item-btn">Remover</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <fieldset class="data-section-rounded">
                <legend>Dados da Doação</legend>
                <div class="form-group">
                    <label for="responsible_donation">Responsável pela Doação (Sistema):</label>
                    <input type="text" id="responsible_donation" name="responsible_donation" value="<?php echo htmlspecialchars($current_user_name); ?>" required readonly class="form-control-readonly">
                </div>
                <div class="form-row">
                    <div class="form-group_col">
                        <label for="donation_date">Data da Doação:</label>
                        <input type="date" id="donation_date" name="donation_date" value="<?php echo $current_date; ?>" required class="form-control">
                    </div>
                    <div class="form-group_col">
                        <label for="donation_time">Hora da Doação:</label>
                        <input type="time" id="donation_time" name="donation_time" value="<?php echo $current_time; ?>" required class="form-control">
                    </div>
                </div>
                 <div class="form-row">
                    <div class="form-group_col_full">
                        <label for="company_id">Empresa/Instituição Recebedora: <span class="required-asterisk">*</span></label>
                        <select id="company_id" name="company_id" class="form-control" required style="width: 100%;">
                            <option></option> <?php // Select2 vai preencher ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                     <div class="form-group_col_full" id="company_details_preview" style="display:none; background-color: #f0f0f0; padding:15px; border-radius:4px; margin-top:10px; line-height: 1.6;">
                        <strong id="preview_name" style="font-size: 1.1em; color: #333;"></strong><br>
                        <span id="preview_address"></span><br>
                        <span id="preview_cnpj"></span><br>
                        <span id="preview_phone"></span><br>
                        <span id="preview_responsible"></span>
                    </div>
                </div>
            </fieldset>

            <input type="hidden" name="item_ids_for_donation" value="<?php echo htmlspecialchars(implode(',', $valid_item_ids_for_donation)); ?>">

            <div class="form-action-buttons-group" style="margin-top: 20px;">
                <a href="/home.php" class="button-secondary">Cancelar</a>
                <button type="submit" class="button-primary" id="submitDonationButton">Enviar para Aprovação</button>
            </div>
        </form>
    <?php else: ?>
        <p>Não há itens válidos para este termo de doação. Por favor, <a href="/home.php">volte</a> e selecione itens pendentes.</p>
    <?php endif; ?>
</div>

<div id="imageModal" class="modal">
    <span class="close-modal">&times;</span>
    <img class="modal-content" id="img01">
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- Lógica para o Modal da Imagem ---
    const modal = document.getElementById("imageModal");
    const modalImg = document.getElementById("img01");
    const table = document.getElementById('donation-items-table');
    const span = document.getElementsByClassName("close-modal")[0];

    if (table && modal) {
        table.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('image-thumbnail') && e.target.dataset.fullsrc) {
                modal.classList.add('is-visible');
                modalImg.src = e.target.dataset.fullsrc;
            }
        });

        const closeModal = function() {
            modal.classList.remove('is-visible');
        }

        span.onclick = closeModal;
        modal.onclick = function(e) {
            if (e.target === modal) {
                closeModal();
            }
        }
    }

    // --- Lógica do Select2 para Empresas ---
    $('#company_id').select2({
        placeholder: "Pesquise ou selecione uma empresa",
        allowClear: true,
        ajax: {
            url: '/admin/get_companies_handler.php',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term,
                    page: params.page || 1
                };
            },
            processResults: function (data, params) {
                params.page = params.page || 1;
                return {
                    results: data.results,
                    pagination: {
                        more: data.pagination.more
                    }
                };
            },
            cache: true
        },
        minimumInputLength: 0,
        language: {
            inputTooShort: function(args) {
                var remainingChars = args.minimum - args.input.length;
                return "Por favor, insira " + remainingChars + " ou mais caracteres";
            },
            noResults: function() { return "Nenhuma empresa encontrada"; },
            searching: function() { return "Buscando..."; },
            errorLoading: function() { return "Não foi possível carregar os resultados."; }
        }
    }).on('select2:select', function (e) {
        var data = e.params.data;
        if(data && data.id) {
             $.ajax({
                 url: '/admin/get_companies_handler.php',
                 data: { id_exact: data.id },
                 dataType: 'json',
                 success: function(companyDetails) {
                     let details = companyDetails.results && companyDetails.results.length > 0 ? companyDetails.results[0] : null;
                     if(details && details.full_data) {
                         $('#preview_name').text(details.full_data.name || 'Nome não disponível');
                        
                         let address = [
                             details.full_data.address_street,
                             details.full_data.address_number,
                             details.full_data.address_complement,
                             details.full_data.address_neighborhood,
                             details.full_data.address_city,
                             details.full_data.address_state,
                             details.full_data.address_cep
                         ].filter(Boolean).join(', ');
                        
                         $('#preview_address').text('Endereço: ' + (address || 'N/A'));
                         $('#preview_cnpj').text('CNPJ: ' + (details.full_data.cnpj || 'N/A'));
                         $('#preview_phone').text('Telefone: ' + (details.full_data.phone || 'N/A'));
                         $('#preview_responsible').text('Responsável: ' + (details.full_data.responsible_name || 'N/A'));
                        
                         $('#company_details_preview').show();
                     }
                 },
                 error: function() {
                      $('#preview_name').text('Erro ao buscar detalhes.');
                      $('#company_details_preview').show();
                 }
             });
        } else {
            $('#company_details_preview').hide();
        }
    }).on('select2:unselect', function (e) {
        $('#company_details_preview').hide();
    });

    const donationForm = document.getElementById('donationForm');
    const submitButton = document.getElementById('submitDonationButton');
    if(donationForm && submitButton) {
        donationForm.addEventListener('submit', function(event) {
            if (!event.defaultPrevented) { 
                 submitButton.disabled = true;
                 submitButton.textContent = 'Processando...';
            }
        });
    }

    const donationItemsTable = document.getElementById('donation-items-table');
    if (donationItemsTable) {
        donationItemsTable.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('remove-item-btn')) {
                const row = e.target.closest('tr');
                const itemIdToRemove = row.dataset.itemId;

                const hiddenInput = document.querySelector('input[name="item_ids_for_donation"]');
                let currentIds = hiddenInput.value.split(',');
                currentIds = currentIds.filter(id => id.trim() !== itemIdToRemove);
                hiddenInput.value = currentIds.join(',');

                row.remove();

                const totalCountSpan = document.getElementById('total-items-count');
                totalCountSpan.textContent = currentIds.length;

                if (currentIds.length === 0) {
                    submitButton.disabled = true;
                    const tableBody = donationItemsTable.querySelector('tbody');
                    if (tableBody) {
                        tableBody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding: 20px;">Nenhum item selecionado. Cancele para voltar.</td></tr>';
                    }
                }
            }
        });
    }
});
</script>

<?php require_once 'templates/footer.php'; ?>
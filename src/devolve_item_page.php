<?php
require_once 'auth.php';
require_once 'db_connect.php'; // Provides $conn

// Fetch Unidade Name from settings
$unidade_nome_setting = 'N/A'; // Default value
$stmt_settings = $conn->prepare("SELECT unidade_nome FROM settings WHERE config_id = 1");
if ($stmt_settings) {
    $stmt_settings->execute();
    $result_settings = $stmt_settings->get_result();
    if ($result_settings->num_rows > 0) {
        $setting_row = $result_settings->fetch_assoc();
        if (!empty($setting_row['unidade_nome'])) {
            $unidade_nome_setting = $setting_row['unidade_nome'];
        }
    }
    $stmt_settings->close();
} else {
    error_log("Failed to prepare statement to fetch unidade_nome from settings: " . $conn->error);
}

start_secure_session();
require_login();

$item_ids_str = $_GET['ids'] ?? '';
$item_ids = [];
$items_to_devolve = [];
$page_error = '';

if (!empty($item_ids_str)) {
    $ids_array = explode(',', $item_ids_str);
    foreach ($ids_array as $id_str) {
        $id = filter_var(trim($id_str), FILTER_VALIDATE_INT);
        if ($id) {
            $item_ids[] = $id;
        }
    }
}

if (empty($item_ids)) {
    // Redirect if no valid IDs are provided
    header('Location: home.php?error=noitemids_devpage');
    exit();
}

// Fetch item details from the database
// Ensure we only fetch items that are 'Pendente' to avoid issues later
$placeholders = implode(',', array_fill(0, count($item_ids), '?'));
$sql_items = "SELECT id, name, status, barcode,
              (SELECT name FROM categories WHERE id = items.category_id) as category_name,
              (SELECT name FROM locations WHERE id = items.location_id) as location_name
              FROM items WHERE id IN ($placeholders) AND status = 'Pendente'";
$stmt_items = $conn->prepare($sql_items);

if ($stmt_items) {
    // Dynamically bind parameters
    $types = str_repeat('i', count($item_ids));
    $stmt_items->bind_param($types, ...$item_ids);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();
    while ($row = $result_items->fetch_assoc()) {
        $items_to_devolve[] = $row;
    }
    $stmt_items->close();

    if (count($items_to_devolve) !== count($item_ids)) {
        // This means some items were not found or were not 'Pendente'
        // For simplicity now, we proceed with what we found, but a real app might show a more detailed error or warning.
        if(empty($items_to_devolve)) {
            $_SESSION['home_page_message'] = 'Nenhum dos itens selecionados está disponível para devolução (podem já ter sido processados ou não existem).';
            header('Location: home.php?warning=itemsnotavailablefordevolution');
            exit();
        }
         // Store a message to inform user that some items were filtered out.
        $_SESSION['devolution_page_warning'] = 'Alguns itens foram removidos da lista pois não estão mais pendentes.';
    }

} else {
    error_log("SQL Prepare Error (fetch_items_for_devolution): " . $conn->error);
    $page_error = "Erro ao buscar detalhes dos itens. Tente novamente.";
    // In a real scenario, you might not want to proceed if item details can't be fetched.
}

$current_user_name = $_SESSION['username'] ?? 'Usuário Desconhecido';
$current_date_time = date("d/m/Y H:i:s");

require_once 'templates/header.php';
?>

<div class="container register-item-container"> <!-- Using existing class for some styling -->
    <h2>Registrar Devolução de Itens</h2>

    <?php if ($page_error): ?>
        <p class="error-message"><?php echo htmlspecialchars($page_error); ?></p>
    <?php endif; ?>
    <?php
    if (isset($_SESSION['devolution_page_warning'])) {
        echo '<p class="warning-message">' . htmlspecialchars($_SESSION['devolution_page_warning']) . '</p>';
        unset($_SESSION['devolution_page_warning']);
    }
    if (isset($_SESSION['devolution_form_error'])) {
        echo '<p class="error-message">' . htmlspecialchars($_SESSION['devolution_form_error']) . '</p>';
        unset($_SESSION['devolution_form_error']);
    }
    ?>

    <?php if (!empty($items_to_devolve)): ?>
    <form id="devolutionForm" method="POST" action="confirm_devolution_handler.php">

        <h3>Itens Selecionados para Devolução:</h3>
        <ul class="item-list">
            <?php foreach ($items_to_devolve as $item): ?>
                <li>
                    <strong>Nome:</strong> <?php echo htmlspecialchars($item['name']); ?><br>
                    <strong>Status Atual:</strong> <span class="item-status status-<?php echo strtolower(htmlspecialchars($item['status'])); ?>"><?php echo htmlspecialchars($item['status']); ?></span><br>
                    <strong>Cód. Barras:</strong> <?php echo htmlspecialchars($item['barcode'] ?? 'N/A'); ?><br>
                    <strong>Categoria:</strong> <?php echo htmlspecialchars($item['category_name'] ?? 'N/A'); ?><br>
                    <strong>Local Encontrado:</strong> <?php echo htmlspecialchars($item['location_name'] ?? 'N/A'); ?><br>
                    <input type="hidden" name="item_ids[]" value="<?php echo htmlspecialchars($item['id']); ?>">
                </li>
            <?php endforeach; ?>
        </ul>

        <h3>Dados da Devolução (Automático)</h3>
        <p><strong>Responsável pela Devolução:</strong> <?php echo htmlspecialchars($current_user_name); ?></p>
        <p><strong>Data e Hora da Devolução:</strong> <?php echo htmlspecialchars($current_date_time); ?></p>
        <input type="hidden" name="devolution_timestamp_value" value="<?php echo date("Y-m-d H:i:s"); ?>">


        <h3>Dados do Proprietário (Preenchimento Manual)</h3>
        <div>
            <label for="owner_name">Nome Completo do Proprietário:</label>
            <input type="text" id="owner_name" name="owner_name" required maxlength="255">
        </div>
        <div>
            <label for="owner_address">Endereço:</label>
            <textarea id="owner_address" name="owner_address" rows="3" maxlength="1000"></textarea>
        </div>
        <div>
            <label for="owner_phone">Telefone:</label>
            <input type="text" id="owner_phone" name="owner_phone" maxlength="50">
        </div>
        <div>
            <label for="owner_credential_number">Nº da Credencial/Documento:</label>
            <input type="text" id="owner_credential_number" name="owner_credential_number" maxlength="100">
        </div>

        <h3>Assinatura do Proprietário</h3>
        <div class="devolution-declaration" style="margin-top: 15px; margin-bottom: 15px; padding: 10px; border: 1px solid #eee; background-color: #f9f9f9; text-align: justify; font-size: 0.9em;">
            <h5 style="text-align: center; font-weight: bold; margin-bottom: 8px; font-size: 1.1em;">Declaração de Reconhecimento de Propriedade</h5>
            <p style="line-height: 1.4; margin-bottom: 8px;">
                Declaro, para os devidos fins, que reconheço o item descrito neste termo como de minha propriedade e que o mesmo me foi devolvido pelo setor de Achados e Perdidos - Sesc <?php echo htmlspecialchars($unidade_nome_setting); ?>, após conferência e identificação.
            </p>
        </div>
        <div class="signature-pad-container" style="border: 2px solid #000; padding: 5px; display: inline-block;">
            <canvas id="signatureCanvas" class="signature-canvas" width="400" height="200"></canvas>
        </div>
        <br>
        <button type="button" id="clearSignatureButton" class="button-secondary">Limpar Assinatura</button>
        <input type="hidden" name="signature_data_url" id="signatureDataInput">
        <p><small>Por favor, assine no quadro acima.</small></p>

        <hr>
        <div class="form-action-buttons-group">
            <a href="home.php" class="button-secondary button-large">Cancelar</a>
            <button type="submit" class="button-primary button-large">Confirmar Devolução</button>
        </div>
    </form>
    <?php else: ?>
        <p class="info-message">Não há itens válidos para processar a devolução. Por favor, retorne à <a href="home.php">página inicial</a> e selecione itens pendentes.</p>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('signatureCanvas');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        let drawing = false;
        ctx.lineWidth = 2;
        ctx.lineJoin = 'round';
        ctx.lineCap = 'round';

        function getMousePos(canvasDom, mouseEvent) {
            var rect = canvasDom.getBoundingClientRect();
            return {
                x: mouseEvent.clientX - rect.left,
                y: mouseEvent.clientY - rect.top
            };
        }
        function getTouchPos(canvasDom, touchEvent) {
            var rect = canvasDom.getBoundingClientRect();
            return {
                x: touchEvent.touches[0].clientX - rect.left,
                y: touchEvent.touches[0].clientY - rect.top
            };
        }

        function startDrawing(e) {
            drawing = true;
            const pos = e.type.includes('touch') ? getTouchPos(canvas, e) : getMousePos(canvas, e);
            ctx.beginPath();
            ctx.moveTo(pos.x, pos.y);
            e.preventDefault(); // Prevent scrolling when touching canvas
        }

        function draw(e) {
            if (!drawing) return;
            const pos = e.type.includes('touch') ? getTouchPos(canvas, e) : getMousePos(canvas, e);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
            e.preventDefault(); // Prevent scrolling when touching canvas
        }

        function stopDrawing() {
            if (drawing) {
                ctx.closePath();
                drawing = false;
            }
        }

        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseout', stopDrawing); // Stop drawing if mouse leaves canvas

        canvas.addEventListener('touchstart', startDrawing, { passive: false });
        canvas.addEventListener('touchmove', draw, { passive: false });
        canvas.addEventListener('touchend', stopDrawing);
        canvas.addEventListener('touchcancel', stopDrawing);


        const clearButton = document.getElementById('clearSignatureButton');
        if (clearButton) {
            clearButton.addEventListener('click', function() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                document.getElementById('signatureDataInput').value = ''; // Clear hidden input
            });
        }

        const devolutionForm = document.getElementById('devolutionForm');
        if (devolutionForm) {
            devolutionForm.addEventListener('submit', function(event) {
                // Add confirmation dialog here
                if (!confirm("Deseja realmente confirmar a devolução dos itens selecionados?")) {
                    event.preventDefault(); // Stop submission if user clicks Cancel
                    return false;
                }

                // Check if signature is empty (very basic check: is it all white?)
                // A more robust check would analyze pixel data more thoroughly.
                const blankCanvas = document.createElement('canvas');
                blankCanvas.width = canvas.width;
                blankCanvas.height = canvas.height;
                const isCanvasBlank = canvas.toDataURL() === blankCanvas.toDataURL();

                if (isCanvasBlank) {
                    alert('Por favor, forneça a assinatura do proprietário.');
                    event.preventDefault(); // Stop form submission
                    return false;
                }
                document.getElementById('signatureDataInput').value = canvas.toDataURL('image/png');
            });
        }
    }

    const phoneInput = document.getElementById('owner_phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, ''); // Remove all non-digits
            let formattedValue = '';

            if (value.length > 0) {
                formattedValue = '(' + value.substring(0, 2);
            }
            if (value.length > 2) {
                formattedValue += ') ' + value.substring(2, 7);
            }
            if (value.length > 7) {
                formattedValue += '-' + value.substring(7, 11);
            }

            // If the user deletes characters, the logic above might leave trailing characters like "(" or ") "
            // This part ensures that if the input is cleared or partially cleared, the formatting is adjusted.
            if (value.length <= 2 && value.length > 0) {
                // Only show "(XX"
                formattedValue = '(' + value;
            } else if (value.length === 0) {
                formattedValue = '';
            }

            e.target.value = formattedValue;
        });

        // Set maxlength considering the mask: (XX) YYYYY-YYYY is 15 characters
        // ( D D ) <space> N N N N N - N N N N
        // 1 2 3 4    5    6 7 8 9 10 11 12 13 14 15
        phoneInput.maxLength = 15;
    }
});
</script>

<?php require_once 'templates/footer.php'; ?>

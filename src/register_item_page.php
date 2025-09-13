<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'auth.php';
require_once 'db_connect.php';
require_login('index.php?error=pleaselogin');

$pageTitle = "Cadastrar Novo Item Encontrado";

// Busca de categorias e locais (lógica original mantida)
$categories = [];
$sql_cats = "SELECT id, name FROM categories ORDER BY name ASC";
if ($conn) {
    $result_cats = $conn->query($sql_cats);
    if ($result_cats) {
        while ($row = $result_cats->fetch_assoc()) {
            $categories[] = $row;
        }
    }
}
$locations = [];
$sql_locs = "SELECT id, name FROM locations ORDER BY name ASC";
if ($conn) {
    $result_locs = $conn->query($sql_locs);
    if ($result_locs) {
        while ($row = $result_locs->fetch_assoc()) {
            $locations[] = $row;
        }
    }
}

require_once 'templates/header.php';
?>

<style>
    /* Estilos gerais do formulário (mantidos) */
    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 22px;
        align-items: start;
    }
    .form-grid .full-width {
        grid-column: 1 / -1;
    }
    .form-field {
        display: flex;
        flex-direction: column;
    }
    .form-field label {
        margin-bottom: 5px;
        font-weight: bold;
    }
    .form-field input, .form-field textarea, .form-field select {
        width: 100%;
        box-sizing: border-box;
    }
    .select2-container .select2-selection--single { height: 38px !important; padding: 5px 0; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 28px !important; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }
    .form-buttons {
        display: flex;
        gap: 15px;
        grid-column: 1 / -1;
    }
    .form-buttons .button-primary { height: 38px; display: inline-flex; align-items: center; justify-content: center; padding: 0 15px; }

    /* --- NOVOS ESTILOS PARA A ÁREA DE UPLOAD --- */
    #drop-area {
        border: 2px dashed #ccc;
        border-radius: 8px;
        padding: 30px;
        text-align: center;
        font-family: Arial, sans-serif;
        color: #555;
        cursor: pointer;
        transition: border-color 0.3s, background-color 0.3s;
    }
    #drop-area.drag-over {
        border-color: #007bff;
        background-color: #f0f8ff;
    }
    #drop-area p {
        margin: 0;
        font-size: 16px;
    }
    #drop-area small {
        display: block;
        margin-top: 10px;
        font-size: 13px;
        color: #888;
    }
    #preview-container {
        margin-top: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
    }
    .preview-item {
        position: relative;
        width: 150px;
        height: 150px;
    }
    .preview-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 5px;
        border: 1px solid #ddd;
    }
    .remove-btn {
        position: absolute;
        top: 5px;
        right: 5px;
        background: rgba(0,0,0,0.6);
        color: white;
        border: none;
        border-radius: 50%;
        width: 25px;
        height: 25px;
        cursor: pointer;
        font-weight: bold;
        line-height: 25px;
        text-align: center;
        padding: 0;
        font-size: 16px;
    }
</style>

<div class="container register-item-container">
    <header class="admin-header">
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
    </header>

    <?php
    // Bloco para disparar toasts (mantido)
    if (isset($_SESSION['success_message'])) {
        echo "<script>document.addEventListener('DOMContentLoaded', function() { showToast('" . addslashes($_SESSION['success_message']) . "', 'success'); });</script>";
        unset($_SESSION['success_message']);
    }
    if (isset($_SESSION['error_message'])) {
        echo "<script>document.addEventListener('DOMContentLoaded', function() { showToast('" . addslashes($_SESSION['error_message']) . "', 'error'); });</script>";
        unset($_SESSION['error_message']);
    }
    ?>

    <form id="item-form" action="add_item_handler.php" method="POST" enctype="multipart/form-data">
        <div class="form-grid">
            <div class="form-field">
                <label for="name">Nome do item:</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-field">
                <label for="found_date">Data do achado:</label>
                <input type="date" id="found_date" name="found_date" required value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-field">
                <label for="category_id">Categoria:</label>
                <select id="category_id" name="category_id" required style="width: 100%;"></select>
            </div>
            <div class="form-field">
                <label for="location_id">Local onde foi encontrado:</label>
                <select id="location_id" name="location_id" required style="width: 100%;"></select>
            </div>
            <div class="form-field full-width">
                <label for="description">Descrição (opcional):</label>
                <textarea id="description" name="description" rows="4"></textarea>
            </div>

            <div class="form-field full-width">
                <label>Fotos do Item (máximo 2):</label>
                <input type="file" id="file-input" accept="image/*" multiple style="display: none;">
                
                <input type="file" name="item_image_1" id="item_image_1" style="display: none;">
                <input type="file" name="item_image_2" id="item_image_2" style="display: none;">

                <div id="drop-area">
                    <p>Arraste e solte as imagens aqui, ou clique para selecionar.</p>
                    <small>Formatos aceitos: JPG, PNG, GIF, WebP. Tamanho máximo: 5MB.</small>
                </div>
                <div id="preview-container"></div>
            </div>
            
            <div class="form-buttons">
                <button type="submit" name="action" value="register" class="button-primary">Cadastrar Item</button>
                <button type="submit" name="action" value="register_and_print" class="button-primary">Cadastrar Item e Imprimir</button>
            </div>
        </div>
    </form>
</div>

<?php
require_once 'templates/footer.php';
?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
// Lógica do Select2 para Categoria e Local (adaptada para preencher com PHP)
$(document).ready(function() {
    $('#category_id').select2({
        placeholder: "Pesquise ou selecione uma categoria",
        allowClear: true,
        data: [
            { id: '', text: '' }, // Adiciona a opção vazia para o placeholder
            <?php foreach ($categories as $category): ?>
            { id: '<?php echo $category['id']; ?>', text: '<?php echo addslashes(htmlspecialchars($category['name'])); ?>' },
            <?php endforeach; ?>
        ]
    });

    $('#location_id').select2({
        placeholder: "Pesquise ou selecione um local",
        allowClear: true,
        data: [
            { id: '', text: '' }, // Adiciona a opção vazia para o placeholder
            <?php foreach ($locations as $location): ?>
            { id: '<?php echo $location['id']; ?>', text: '<?php echo addslashes(htmlspecialchars($location['name'])); ?>' },
            <?php endforeach; ?>
        ]
    });
});
</script>

<script>
// --- NOVA LÓGICA JAVASCRIPT PARA UPLOAD DRAG-AND-DROP ---
document.addEventListener('DOMContentLoaded', () => {
    const dropArea = document.getElementById('drop-area');
    const fileInput = document.getElementById('file-input');
    const previewContainer = document.getElementById('preview-container');
    const itemForm = document.getElementById('item-form');
    
    // Armazena os arquivos selecionados (objetos File)
    let selectedFiles = [];
    const MAX_FILES = 2;

    // Abrir o seletor de arquivos ao clicar na área de drop
    dropArea.addEventListener('click', () => fileInput.click());

    // Adicionar classe visual ao arrastar arquivos sobre a área
    dropArea.addEventListener('dragover', (event) => {
        event.preventDefault();
        dropArea.classList.add('drag-over');
    });

    // Remover classe visual ao sair da área
    dropArea.addEventListener('dragleave', () => {
        dropArea.classList.remove('drag-over');
    });

    // Lidar com os arquivos soltos na área
    dropArea.addEventListener('drop', (event) => {
        event.preventDefault();
        dropArea.classList.remove('drag-over');
        const files = event.dataTransfer.files;
        handleFiles(files);
    });

    // Lidar com os arquivos selecionados pelo seletor de arquivos
    fileInput.addEventListener('change', () => {
        handleFiles(fileInput.files);
        // Limpa o valor para permitir selecionar o mesmo arquivo novamente
        fileInput.value = '';
    });

    // Função central para processar os arquivos selecionados/soltos
    function handleFiles(files) {
        for (const file of files) {
            if (selectedFiles.length < MAX_FILES && file.type.startsWith('image/')) {
                selectedFiles.push(file);
            }
        }
        updatePreview();
    }

    // Função para atualizar a pré-visualização das imagens
    function updatePreview() {
        previewContainer.innerHTML = ''; // Limpa a pré-visualização
        selectedFiles.forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const previewItem = document.createElement('div');
                previewItem.className = 'preview-item';
                
                const img = document.createElement('img');
                img.src = e.target.result;
                
                const removeBtn = document.createElement('button');
                removeBtn.className = 'remove-btn';
                removeBtn.innerHTML = '&times;';
                removeBtn.type = 'button'; // Para não submeter o formulário
                removeBtn.onclick = () => {
                    selectedFiles.splice(index, 1); // Remove o arquivo do array
                    updatePreview(); // Atualiza a UI
                };

                previewItem.appendChild(img);
                previewItem.appendChild(removeBtn);
                previewContainer.appendChild(previewItem);
            };
            reader.readAsDataURL(file);
        });
    }
    
    // --- PASSO CRUCIAL: Preparar os arquivos para o envio do formulário ---
    itemForm.addEventListener('submit', (event) => {
        // Pega os inputs de arquivo escondidos que serão enviados para o PHP
        const imageInput1 = document.getElementById('item_image_1');
        const imageInput2 = document.getElementById('item_image_2');
        
        // Cria um objeto DataTransfer para manipular a lista de arquivos dos inputs
        const dataTransfer1 = new DataTransfer();
        const dataTransfer2 = new DataTransfer();

        // Adiciona os arquivos selecionados aos objetos DataTransfer
        if (selectedFiles.length > 0) {
            dataTransfer1.items.add(selectedFiles[0]);
        }
        if (selectedFiles.length > 1) {
            dataTransfer2.items.add(selectedFiles[1]);
        }
        
        // Atribui os arquivos aos inputs escondidos
        imageInput1.files = dataTransfer1.files;
        imageInput2.files = dataTransfer2.files;
    });
});
</script>
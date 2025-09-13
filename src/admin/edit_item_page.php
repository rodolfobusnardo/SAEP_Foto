<?php
require_once '../auth.php';
require_once '../db_connect.php';

require_admin('../index.php?error=unauthorized');

$item_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$item = null;
$categories = [];
$locations = [];
$error_message = '';
$success_message = '';

if (isset($_SESSION['admin_page_error_message'])) {
    $error_message = $_SESSION['admin_page_error_message'];
    unset($_SESSION['admin_page_error_message']);
}
if (isset($_SESSION['admin_page_success_message'])) {
    $success_message = $_SESSION['admin_page_success_message'];
    unset($_SESSION['admin_page_success_message']);
}

if (!$item_id) {
    $_SESSION['admin_page_error_message'] = "ID do item inválido ou não fornecido para edição.";
    header('Location: /home.php?error=invaliditemid_editpage');
    exit();
}

// 1. ALTERAÇÃO: Atualizada a consulta SQL para buscar as duas imagens
$sql_item = "SELECT name, category_id, location_id, found_date, description, status, image_path, image_path_2 FROM items WHERE id = ?";
$stmt_item = $conn->prepare($sql_item);

if ($stmt_item) {
    $stmt_item->bind_param("i", $item_id);
    if ($stmt_item->execute()) {
        $result_item = $stmt_item->get_result();
        if ($result_item->num_rows === 1) {
            $item = $result_item->fetch_assoc();
        } else {
            $_SESSION['admin_page_error_message'] = "Item não encontrado com o ID: " . htmlspecialchars($item_id);
            header('Location: /home.php?error=itemnotfound_editpage');
            exit();
        }
    } else {
        error_log("SQL Execute Error (fetch_item_for_edit): " . $stmt_item->error);
        $error_message = "Erro ao executar busca do item.";
    }
    $stmt_item->close();
} else {
    error_log("SQL Prepare Error (fetch_item_for_edit): " . $conn->error);
    $error_message = "Erro ao preparar busca do item para edição.";
}

// Buscar categorias e locais (lógica mantida)
if (empty($error_message)) {
    $sql_cats = "SELECT id, name FROM categories ORDER BY name ASC";
    $result_cats = $conn->query($sql_cats);
    if ($result_cats) {
        $categories = $result_cats->fetch_all(MYSQLI_ASSOC);
    }

    $sql_locs = "SELECT id, name FROM locations ORDER BY name ASC";
    $result_locs = $conn->query($sql_locs);
    if ($result_locs) {
        $locations = $result_locs->fetch_all(MYSQLI_ASSOC);
    }
}

require_once '../templates/header.php';
?>

<style>
    .form-admin { max-width: 800px; margin: auto; }
    #drop-area { border: 2px dashed #ccc; border-radius: 8px; padding: 30px; text-align: center; color: #555; cursor: pointer; transition: border-color 0.3s, background-color 0.3s; margin-top: 10px; }
    #drop-area.drag-over { border-color: #007bff; background-color: #f0f8ff; }
    #drop-area p { margin: 0; font-size: 16px; }
    #drop-area small { display: block; margin-top: 10px; font-size: 13px; color: #888; }
    #preview-container { margin-top: 20px; display: flex; flex-wrap: wrap; gap: 15px; }
    .preview-item { position: relative; width: 150px; height: 150px; }
    .preview-item img { width: 100%; height: 100%; object-fit: cover; border-radius: 5px; border: 1px solid #ddd; }
    .remove-btn { position: absolute; top: 5px; right: 5px; background: rgba(0,0,0,0.6); color: white; border: none; border-radius: 50%; width: 25px; height: 25px; cursor: pointer; font-weight: bold; line-height: 25px; text-align: center; padding: 0; font-size: 16px; }
</style>

<div class="container admin-container">
    <h2>Editar Item ID: <?php echo htmlspecialchars($item_id); ?></h2>

    <?php if (!empty($success_message)): ?>
        <p class="success-message"><?php echo htmlspecialchars($success_message); ?></p>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
    <?php endif; ?>

    <?php if ($item && empty($error_message)): ?>
    <form id="edit-item-form" action="edit_item_handler.php" method="POST" class="form-admin" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($item_id); ?>">

        <div>
            <label for="name">Nome do item:</label>
            <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($item['name'] ?? ''); ?>">
        </div>
        <div>
            <label for="category_id">Categoria:</label>
            <select id="category_id" name="category_id" required>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo htmlspecialchars($category['id']); ?>" <?php echo ($item['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="location_id">Local onde foi encontrado:</label>
            <select id="location_id" name="location_id" required>
                 <?php foreach ($locations as $location): ?>
                    <option value="<?php echo htmlspecialchars($location['id']); ?>" <?php echo ($item['location_id'] == $location['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($location['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="found_date">Data do achado:</label>
            <input type="date" id="found_date" name="found_date" required value="<?php echo htmlspecialchars($item['found_date'] ?? ''); ?>">
        </div>
        <div>
            <label for="description">Descrição (opcional):</label>
            <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
        </div>
        
        <div>
            <label>Fotos do Item (máximo 2):</label>
            <small>Arraste novos arquivos para substituir as imagens existentes. Para remover uma imagem, clique no 'X'.</small>
            
            <input type="file" name="item_image_1" id="item_image_1" style="display: none;">
            <input type="file" name="item_image_2" id="item_image_2" style="display: none;">
            
            <input type="checkbox" name="delete_image_1" id="delete_image_1" value="1" style="display:none;">
            <input type="checkbox" name="delete_image_2" id="delete_image_2" value="1" style="display:none;">

            <input type="file" id="file-input" accept="image/*" multiple style="display: none;">
            
            <div id="drop-area">
                <p>Arraste e solte as imagens aqui, ou clique para selecionar.</p>
                <small>Novas imagens substituirão as existentes.</small>
            </div>
            
            <div id="preview-container"></div>
        </div>
        
        <?php if (is_admin()): ?>
        <div>
            <label for="status">Status do Item:</label>
            <select id="status" name="status" required>
                <option value="Pendente" <?php echo ($item['status'] == 'Pendente') ? 'selected' : ''; ?>>Pendente</option>
                <option value="Aguardando Aprovação" <?php echo ($item['status'] == 'Aguardando Aprovação') ? 'selected' : ''; ?>>Aguardando Aprovação</option>
                <option value="Devolvido" <?php echo ($item['status'] == 'Devolvido') ? 'selected' : ''; ?>>Devolvido</option>
                <option value="Doado" <?php echo ($item['status'] == 'Doado') ? 'selected' : ''; ?>>Doado</option>
            </select>
        </div>
        <?php endif; ?>

        <div class="form-action-buttons-group">
            <a href="/home.php" class="button-secondary">Cancelar</a>
            <button type="submit" class="button-primary">Salvar Alterações</button>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const dropArea = document.getElementById('drop-area');
    const fileInput = document.getElementById('file-input');
    const previewContainer = document.getElementById('preview-container');
    const editForm = document.getElementById('edit-item-form');
    
    // Array para gerenciar os arquivos (pode ser uma mistura de URLs existentes e novos objetos File)
    let imageSlots = [null, null];
    const MAX_FILES = 2;
    
    // Carrega as imagens existentes do PHP
    const existingImage1 = "<?php echo !empty($item['image_path']) ? '/' . htmlspecialchars($item['image_path']) : ''; ?>";
    const existingImage2 = "<?php echo !empty($item['image_path_2']) ? '/' . htmlspecialchars($item['image_path_2']) : ''; ?>";

    if (existingImage1) imageSlots[0] = { type: 'existing', src: existingImage1, id: 1 };
    if (existingImage2) imageSlots[1] = { type: 'existing', src: existingImage2, id: 2 };

    function updatePreview() {
        previewContainer.innerHTML = '';
        imageSlots.forEach((slot, index) => {
            if (slot === null) return;

            const previewItem = document.createElement('div');
            previewItem.className = 'preview-item';
            
            const img = document.createElement('img');
            
            if (slot.type === 'existing') {
                img.src = slot.src;
            } else { // É um novo arquivo (objeto File)
                img.src = URL.createObjectURL(slot.file);
            }
            
            const removeBtn = document.createElement('button');
            removeBtn.className = 'remove-btn';
            removeBtn.innerHTML = '&times;';
            removeBtn.type = 'button';
            removeBtn.onclick = () => {
                // Se for uma imagem existente, marca para deleção
                if (slot.type === 'existing') {
                    document.getElementById(`delete_image_${slot.id}`).checked = true;
                }
                // Limpa o slot e atualiza
                imageSlots[index] = null;
                updatePreview();
            };

            previewItem.appendChild(img);
            previewItem.appendChild(removeBtn);
            previewContainer.appendChild(previewItem);
        });
    }

    // Eventos do drag-and-drop
    dropArea.addEventListener('click', () => fileInput.click());
    dropArea.addEventListener('dragover', e => { e.preventDefault(); dropArea.classList.add('drag-over'); });
    dropArea.addEventListener('dragleave', () => dropArea.classList.remove('drag-over'));
    dropArea.addEventListener('drop', e => {
        e.preventDefault();
        dropArea.classList.remove('drag-over');
        handleFiles(e.dataTransfer.files);
    });
    fileInput.addEventListener('change', () => {
        handleFiles(fileInput.files);
        fileInput.value = '';
    });

    function handleFiles(files) {
        for (const file of files) {
            if (!file.type.startsWith('image/')) continue;
            
            // Encontra o primeiro slot vazio para inserir a nova imagem
            let emptySlotIndex = imageSlots.indexOf(null);
            if (emptySlotIndex !== -1) {
                imageSlots[emptySlotIndex] = { type: 'new', file: file };
            } else {
                // Se não há slots vazios, substitui o primeiro
                imageSlots[0] = { type: 'new', file: file };
            }
        }
        updatePreview();
    }
    
    // Antes de submeter o formulário, atribui os novos arquivos aos inputs corretos
    editForm.addEventListener('submit', () => {
        const imageInput1 = document.getElementById('item_image_1');
        const imageInput2 = document.getElementById('item_image_2');
        
        // Limpa os inputs antes de atribuir para evitar enviar arquivos antigos
        imageInput1.value = '';
        imageInput2.value = '';
        
        const dataTransfer1 = new DataTransfer();
        const dataTransfer2 = new DataTransfer();

        if (imageSlots[0] && imageSlots[0].type === 'new') {
            dataTransfer1.items.add(imageSlots[0].file);
            imageInput1.files = dataTransfer1.files;
        }
        if (imageSlots[1] && imageSlots[1].type === 'new') {
            dataTransfer2.items.add(imageSlots[1].file);
            imageInput2.files = dataTransfer2.files;
        }
    });

    // Inicia a visualização com as imagens existentes
    updatePreview();
});
</script>

<?php require_once '../templates/footer.php'; ?>
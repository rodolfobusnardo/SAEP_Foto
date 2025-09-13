<?php
require_once 'auth.php';
require_once 'db_connect.php'; // Conexão com o banco de dados
require_once 'config.php';     // Para JSBARCODE_PATH

require_login(); // Garante que o usuário está logado

$item_ids_str = $_GET['ids'] ?? '';
$item_ids = [];
if (!empty($item_ids_str)) {
    $item_ids = explode(',', $item_ids_str);
    $item_ids = array_map('intval', $item_ids); // Sanitiza para inteiros
    $item_ids = array_filter($item_ids, function($id) { return $id > 0; }); // Remove IDs inválidos
}

$items_to_print = [];
if (!empty($item_ids)) {
    $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
    $types = str_repeat('i', count($item_ids));

    $sql = "SELECT id, name, barcode FROM items WHERE id IN ($placeholders) AND barcode IS NOT NULL AND barcode != ''";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param($types, ...$item_ids);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $items_to_print[] = $row;
        }
        $stmt->close();
    } else {
        // Tratar erro na preparação da query, se necessário
        error_log("Erro ao preparar a query para buscar itens para impressão: " . $conn->error);
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir Códigos de Barras</title>
    <link rel="stylesheet" href="style.css">
    <script src="<?php echo JSBARCODE_PATH; ?>"></script>
    <style>
        body {
            background-color: #fff; /* Fundo branco para impressão */
            margin: 0;
            padding: 0;
        }
        .print-page-container {
            width: 100%;
            margin: 20px auto;
            padding: 20px;
            box-sizing: border-box;
        }
        .controls {
            text-align: center;
            margin-bottom: 30px;
            padding: 10px;
            background-color: #f0f0f0;
            border-radius: 5px;
        }
        .controls button, .controls a {
            padding: 10px 20px;
            font-size: 1em;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            margin: 0 10px;
        }
        .controls button {
            background-color: #007bff;
            color: white;
            border: 1px solid #0069d9;
        }
        .controls button:hover {
            background-color: #0056b3;
        }
        .controls a {
            background-color: #6c757d;
            color: white;
            border: 1px solid #5a6268;
        }
        .controls a:hover {
            background-color: #5a6268;
        }

        .barcode-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-start; /* Alinha as etiquetas à esquerda */
            gap: 0mm; /* Sem espaço entre as etiquetas, o tamanho da etiqueta controlará */
        }

        .barcode-label {
            width: 60mm;
            height: 40mm;
            border: 1px dotted #ccc; /* Borda pontilhada para visualização, removida na impressão */
            box-sizing: border-box;
            padding: 2mm; /* Pequeno padding interno */
            display: flex;
            flex-direction: column;
            justify-content: center; /* Centraliza verticalmente o conteúdo */
            align-items: center; /* Centraliza horizontalmente o conteúdo */
            overflow: hidden; /* Evita que o conteúdo exceda a etiqueta */
            page-break-inside: avoid !important; /* Tenta evitar que a etiqueta seja dividida entre páginas */
        }

        .barcode-label .item-name {
            font-size: 8pt; /* Tamanho pequeno para o nome do item */
            text-align: center;
            margin-bottom: 2mm; /* Espaço entre o nome e o código de barras */
            word-break: break-word;
            max-height: 20%; /* Limita a altura do nome */
            overflow: hidden;
        }

        .barcode-label svg.barcode-image {
            display: block; /* Garante que o SVG se comporte como bloco */
            max-width: 100%;
            height: auto; /* Mantém a proporção */
            max-height: 60%; /* Limita a altura do código de barras para caber na etiqueta */
        }

        /* Estilos de Impressão */
        @media print {
            body {
                margin: 0;
                padding: 0;
                background-color: #fff; /* Garante fundo branco */
                -webkit-print-color-adjust: exact !important; /* Chrome, Safari */
                print-color-adjust: exact !important; /* Standard */
            }
            .controls {
                display: none !important; /* Oculta os controles na impressão */
            }
            .print-page-container {
                margin: 0;
                padding: 0;
                width: auto; /* Permite que o conteúdo flua naturalmente */
            }
            .barcode-grid {
                margin: 0;
                padding: 0;
                gap: 0;
            }
            .barcode-label {
                border: none; /* Remove a borda pontilhada na impressão */
                padding: 1mm; /* Ajuste fino do padding para impressão se necessário */
                /* O tamanho 60mm x 40mm já está definido */
                 margin: 0; /* Remove margens externas da etiqueta */
                 page-break-after: auto; /* Comportamento padrão */
                 page-break-inside: avoid !important; /* Tenta fortemente evitar quebrar DENTRO da etiqueta */
            }
             /* Forçar quebras de página para layout de etiquetas, se necessário.
                Ex: se você tem 2 etiquetas por linha em A4 (210mm de largura):
                .barcode-label:nth-child(3n+1) { clear: left; }
                Se a impressora de etiquetas lida com cada etiqueta individualmente, isso não é tão crítico.
                A configuração da impressora para o tamanho da etiqueta (6cm x 4cm) é crucial.
             */
        }
    </style>
</head>
<body>
    <div class="print-page-container">
        <div class="controls">
            <button onclick="window.print();">Imprimir Etiquetas</button>
            <a href="register_item_page.php">Voltar para Cadastro</a>
            <a href="home.php">Voltar para a Home</a>
        </div>

        <?php if (!empty($items_to_print)): ?>
            <div class="barcode-grid">
                <?php foreach ($items_to_print as $item): ?>
                    <div class="barcode-label">
                        <?php if (!empty($item['name'])): ?>
                            <div class="item-name"><?php echo htmlspecialchars(substr($item['name'], 0, 50)); // Limita o nome do item ?></div>
                        <?php endif; ?>
                        <svg class="barcode-image" id="barcode-<?php echo htmlspecialchars($item['id']); ?>"></svg>
                    </div>
                <?php endforeach; ?>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    <?php foreach ($items_to_print as $item): ?>
                        try {
                            const barcodeElement = document.getElementById("barcode-<?php echo htmlspecialchars($item['id']); ?>");
                            if (barcodeElement && "<?php echo htmlspecialchars($item['barcode']); ?>") {
                                JsBarcode(barcodeElement, "<?php echo htmlspecialchars($item['barcode']); ?>", {
                                    format: "CODE128",
                                    lineColor: "#000",
                                    width: 1.8, /* Ajustar a largura da barra conforme necessário */
                                    height: 35, /* Ajustar a altura do código de barras */
                                    displayValue: true, /* Exibe o valor abaixo do código */
                                    fontSize: 10, /* Tamanho da fonte do valor */
                                    margin: 0 /* Remove margens internas do JsBarcode */
                                });
                            }
                        } catch (e) {
                            console.error("Erro ao gerar barcode para item ID <?php echo htmlspecialchars($item['id']); ?>: ", e);
                            if(barcodeElement) {
                                barcodeElement.outerHTML = "<p style='color:red; font-size:8pt;'>Erro ao gerar cód. barras</p>";
                            }
                        }
                    <?php endforeach; ?>
                });
            </script>
        <?php elseif (empty($item_ids_str)): ?>
            <p style="text-align:center; color:orange;">Nenhum ID de item foi fornecido.</p>
        <?php else: ?>
            <p style="text-align:center; color:red;">Nenhum item com código de barras válido encontrado para os IDs fornecidos, ou os IDs são inválidos.</p>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
// Script para ser executado via CRON para limpar imagens antigas de itens doados/devolvidos.
echo "Iniciando script de limpeza de imagens...\n";

// Aumenta o tempo máximo de execução para evitar que o script pare no meio do caminho.
set_time_limit(300); // 5 minutos

// Define o diretório raiz do projeto para poder incluir os arquivos necessários.
// __DIR__ é o diretório do arquivo atual (cron), então subimos um nível para a raiz 'src'.
$project_root = dirname(__DIR__);

require_once $project_root . '/db_connect.php'; // Conexão com o banco de dados.

if (!$conn) {
    die("Falha ao conectar ao banco de dados.\n");
}

// Define o período de retenção das imagens (30 dias).
$retention_days = 30;
$cutoff_date = date('Y-m-d H:i:s', strtotime("-$retention_days days"));

echo "Data de corte para exclusão: $cutoff_date\n";

// Busca por itens que foram 'Doados' ou 'Devolvidos' há mais de 30 dias e que ainda possuem uma imagem.
$sql_select = "
    SELECT id, image_path
    FROM items
    WHERE
        (status = 'Doado' OR status = 'Devolvido')
        AND status_changed_at IS NOT NULL
        AND status_changed_at < ?
        AND image_path IS NOT NULL
        AND image_path != ''
";

$stmt_select = $conn->prepare($sql_select);
if (!$stmt_select) {
    die("Erro ao preparar a consulta de seleção: " . $conn->error . "\n");
}

$stmt_select->bind_param("s", $cutoff_date);
$stmt_select->execute();
$result = $stmt_select->get_result();

if ($result->num_rows > 0) {
    echo "Encontrados " . $result->num_rows . " itens para processar.\n";

    $sql_update = "UPDATE items SET image_path = NULL WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    if (!$stmt_update) {
        die("Erro ao preparar a consulta de atualização: " . $conn->error . "\n");
    }

    while ($item = $result->fetch_assoc()) {
        $item_id = $item['id'];
        $image_path = $item['image_path'];
        $full_image_path = $project_root . '/' . $image_path;

        echo "Processando item ID: $item_id, Imagem: $full_image_path\n";

        // 1. Deleta o arquivo de imagem do servidor.
        if (file_exists($full_image_path)) {
            if (unlink($full_image_path)) {
                echo "  -> Imagem deletada com sucesso.\n";

                // 2. Atualiza o banco de dados para remover a referência à imagem.
                $stmt_update->bind_param("i", $item_id);
                if ($stmt_update->execute()) {
                    if ($stmt_update->affected_rows > 0) {
                        echo "  -> Referência no banco de dados removida.\n";
                    } else {
                        echo "  -> AVISO: Nenhuma linha atualizada no banco de dados para o item ID $item_id.\n";
                    }
                } else {
                    echo "  -> ERRO: Falha ao atualizar o banco de dados para o item ID $item_id: " . $stmt_update->error . "\n";
                }
            } else {
                echo "  -> ERRO: Falha ao deletar o arquivo de imagem: $full_image_path.\n";
            }
        } else {
            echo "  -> AVISO: Arquivo de imagem não encontrado no caminho especificado. Apenas a referência no banco de dados será removida.\n";
            // Mesmo que o arquivo não exista, limpamos a referência no banco de dados.
            $stmt_update->bind_param("i", $item_id);
            if ($stmt_update->execute() && $stmt_update->affected_rows > 0) {
                echo "  -> Referência no banco de dados removida.\n";
            }
        }
    }
    $stmt_update->close();

} else {
    echo "Nenhum item com imagem para limpar.\n";
}

$stmt_select->close();
$conn->close();

echo "Script de limpeza de imagens concluído.\n";
?>

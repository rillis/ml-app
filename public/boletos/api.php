<?php
require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';
$pdo = ml_db('ml_boletos');

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);
$currentUser = $_SESSION['user'] ?? 'system';

require __DIR__ . '/../../src/boletos/vencimento.php';

try {
    switch ($action) {
        case 'listar':
            $stmt = $pdo->query("SELECT * FROM boletos WHERE deleted_at IS NULL");
            $boletos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Busca os feriados uma única vez
            $stmtFeriados = $pdo->query("SELECT data FROM feriados");
            $feriados = $stmtFeriados->fetchAll(PDO::FETCH_COLUMN);

            // Calcula dinamicamente antes de enviar o JSON
            foreach ($boletos as &$boleto) {
                $boleto['data_vencimento_real'] = calcularVencimentoReal($boleto['data_vencimento'], $feriados);
            }
            
            echo json_encode($boletos);
            break;

        case 'adicionar':
            // Recebe um ou mais boletos e grava tudo ou nada, para o usuário poder corrigir e reenviar.
            // Com placeholder_id, atualiza esse placeholder em vez de criar um registro novo.
            $boletos = $input['boletos'] ?? [];
            if (!is_array($boletos) || count($boletos) === 0) {
                echo json_encode(['erro' => 'Nenhum boleto informado.']);
                exit;
            }

            $chk = $pdo->prepare("SELECT id FROM boletos WHERE codigo_boleto = ? AND deleted_at IS NULL");
            $erros = [];
            $codigosDoEnvio = [];
            $placeholdersDoEnvio = [];
            $chkPlaceholder = $pdo->prepare("SELECT id FROM boletos WHERE id = ? AND deleted_at IS NULL AND pago = 0 AND valor = 0 AND (codigo_boleto IS NULL OR codigo_boleto = '')");
            foreach ($boletos as $indice => $boleto) {
                $placeholderId = $boleto['placeholder_id'] ?? null;
                if ($placeholderId) {
                    if (isset($placeholdersDoEnvio[$placeholderId])) {
                        $erros[] = ['indice' => $indice, 'erro' => 'Este boleto existente já está sendo atualizado por outro item da lista.'];
                        continue;
                    }
                    $chkPlaceholder->execute([$placeholderId]);
                    if (!$chkPlaceholder->fetch()) {
                        $erros[] = ['indice' => $indice, 'erro' => 'O boleto existente mudou ou já foi preenchido. Recarregue a página e tente novamente.'];
                        continue;
                    }
                    $placeholdersDoEnvio[$placeholderId] = true;
                }

                $codigo = $boleto['codigo_boleto'] ?? '';
                if ($codigo === '') {
                    continue;
                }
                if (isset($codigosDoEnvio[$codigo])) {
                    $erros[] = ['indice' => $indice, 'erro' => 'Código repetido em outro boleto desta lista.'];
                    continue;
                }
                $codigosDoEnvio[$codigo] = true;

                $chk->execute([$codigo]);
                if ($chk->fetch()) {
                    $erros[] = ['indice' => $indice, 'erro' => 'Código de boleto já cadastrado em outro registro ativo.'];
                }
            }
            if ($erros) {
                echo json_encode(['erro' => 'Corrija os boletos indicados. Nada foi salvo.', 'erros' => $erros]);
                exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO boletos (data_vencimento, descricao, codigo_boleto, valor, created_by)
                VALUES (:data_vencimento, :descricao, :codigo_boleto, :valor, :created_by)
            ");

            $stmtPlaceholder = $pdo->prepare("
                UPDATE boletos
                SET data_vencimento = :data_vencimento,
                    descricao = :descricao,
                    codigo_boleto = :codigo_boleto,
                    valor = :valor,
                    updated_by = :updated_by
                WHERE id = :id AND deleted_at IS NULL
            ");

            $pdo->beginTransaction();
            foreach ($boletos as $boleto) {
                if (!empty($boleto['placeholder_id'])) {
                    $stmtPlaceholder->execute([
                        ':id' => $boleto['placeholder_id'],
                        ':data_vencimento' => $boleto['data_vencimento'],
                        ':descricao' => $boleto['descricao'],
                        ':codigo_boleto' => ($boleto['codigo_boleto'] ?? '') ?: null,
                        ':valor' => $boleto['valor'],
                        ':updated_by' => $currentUser
                    ]);
                    continue;
                }

                $stmt->execute([
                    ':data_vencimento' => $boleto['data_vencimento'],
                    ':descricao' => $boleto['descricao'],
                    ':codigo_boleto' => ($boleto['codigo_boleto'] ?? '') ?: null,
                    ':valor' => $boleto['valor'],
                    ':created_by' => $currentUser
                ]);
            }
            $pdo->commit();

            echo json_encode(['sucesso' => true, 'quantidade' => count($boletos)]);
            break;

        case 'editar':
            if (!empty($input['codigo_boleto'])) {
                $chk = $pdo->prepare("SELECT id FROM boletos WHERE codigo_boleto = ? AND id != ? AND deleted_at IS NULL");
                $chk->execute([$input['codigo_boleto'], $input['id']]);
                if ($chk->fetch()) {
                    echo json_encode(['erro' => 'Código de boleto já cadastrado em outro registro ativo.']);
                    exit;
                }
            }

            $stmt = $pdo->prepare("
                UPDATE boletos 
                SET data_vencimento = :data_vencimento, 
                    descricao = :descricao, 
                    codigo_boleto = :codigo_boleto, 
                    valor = :valor,
                    updated_by = :updated_by
                WHERE id = :id AND deleted_at IS NULL
            ");
            
            $stmt->execute([
                ':id' => $input['id'],
                ':data_vencimento' => $input['data_vencimento'],
                ':descricao' => $input['descricao'],
                ':codigo_boleto' => $input['codigo_boleto'] ?: null,
                ':valor' => $input['valor'],
                ':updated_by' => $currentUser
            ]);
            
            echo json_encode(['sucesso' => true]);
            break;

        case 'status':
            $campo = $input['campo'] === 'pago' ? 'pago' : 'agendado';
            $valor = $input['valor'];
            
            if ($campo === 'pago' && $valor == 1) {
                $stmt = $pdo->prepare("
                    UPDATE boletos 
                    SET pago = 1, agendado = 1, updated_by = :updated_by 
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':id' => $input['id'],
                    ':updated_by' => $currentUser
                ]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE boletos 
                    SET $campo = :valor, updated_by = :updated_by 
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':id' => $input['id'],
                    ':valor' => $valor,
                    ':updated_by' => $currentUser
                ]);
            }
            
            echo json_encode(['sucesso' => true]);
            break;

        case 'apagar':
            $stmt = $pdo->prepare("
                UPDATE boletos 
                SET deleted_at = NOW(), 
                    deleted_by = :deleted_by 
                WHERE id = :id
            ");
            
            $stmt->execute([
                ':id' => $input['id'],
                ':deleted_by' => $currentUser
            ]);
            
            echo json_encode(['sucesso' => true]);
            break;

        default:
            echo json_encode(['erro' => 'Ação inválida']);
            break;
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('ml-app boletos: ' . $e->getMessage());
    echo json_encode(['erro' => 'Não foi possível concluir a operação. Tente novamente.']);
}
?>
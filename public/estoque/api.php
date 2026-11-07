<?php
require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';
$pdo = ml_db('ml_estoque');

header('Content-Type: application/json');
$action =$_GET['action'] ?? '';

// Função auxiliar para gravar histórico
function logHist($pdo, $tipo,$sku, $qtd,$antes) {
    $user =$_SESSION['user'] ?? 'sistema';
    $stmt =$pdo->prepare('INSERT INTO historico (username, tipo, sku, qtd, antes) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$user, $tipo,$sku, $qtd,$antes]);
}

/* ===== LISTAR ESTOQUE ===== */
if ($action === 'listar') {
    $stmt =$pdo->query('SELECT sku, qtd FROM estoque');
    $dados =$stmt->fetchAll();
    
    // Sanitização contra XSS direto na saída da API
    foreach ($dados as &$d) {
        $d['sku'] = htmlspecialchars($d['sku'], ENT_QUOTES, 'UTF-8');
        $d['qtd'] = (int)$d['qtd'];
    }
    
    echo json_encode($dados);
    exit;
}

/* ===== ADICIONAR / REMOVER ===== */
if ($action === 'adicionar' || $action === 'remover') {
    $req = json_decode(file_get_contents('php://input'), true);
    $sku = strtoupper(trim($req['sku'] ?? ''));
    $qtd_movimento = (int)($req['qtd'] ?? 0);

    if (empty($sku) || $qtd_movimento <= 0) {
        echo json_encode(['error' => 'Dados inválidos']);
        exit;
    }
    
    $updated_by = $_SESSION['user'] ?? 'sistema';
    try {
        // Inicia a transação
        $pdo->beginTransaction();

        // FOR UPDATE bloqueia a linha no DB até o commit, evitando Race Conditions
        $stmt =$pdo->prepare('SELECT qtd FROM estoque WHERE sku = ? FOR UPDATE');
        $stmt->execute([$sku]);
        $row =$stmt->fetch();

        $antes = $row ? (int)$row['qtd'] : 0;

        if ($action === 'adicionar') {
            $nova_qtd = $antes + $qtd_movimento;
            $qtd_historico = $qtd_movimento;
            $tipo = 'ADD';
        } else {
            // O histórico registra o que saiu de fato, que pode ser menos que o pedido
            $qtd_historico = min($antes, $qtd_movimento);
            if ($qtd_historico === 0) {
                $pdo->rollBack();
                echo json_encode(['error' => 'Este item já está sem estoque.']);
                exit;
            }
            $nova_qtd = $antes - $qtd_historico;
            $tipo = 'REM';
        }

        // Atualiza ou Insere
        if ($row) {
            $stmt_up =$pdo->prepare('UPDATE estoque SET qtd = ?, updated_by = ?  WHERE sku = ?');
            $stmt_up->execute([
                $nova_qtd,
                $updated_by,
                $sku
            ]);
        } else {
            $stmt_in = $pdo->prepare('INSERT INTO estoque (sku, updated_by, qtd) VALUES (?, ?, ?)');$stmt_in->execute([$sku,$updated_by,$nova_qtd]);
        }

        // Grava o log na mesma transação
        logHist($pdo, $tipo, $sku, $qtd_historico, $antes);

        $pdo->commit();
        $resposta = ['success' => true];
        if ($tipo === 'REM') {
            $resposta['removido'] = $qtd_historico;
            $resposta['pedido'] = $qtd_movimento;
        }
        echo json_encode($resposta);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        echo json_encode([
            'error' => 'Erro ao processar alteração no banco'
        ]);
    }
    exit;
}

/* ===== HISTÓRICO ===== */
if ($action === 'historico') {
    $stmt =$pdo->query('SELECT username as user, tipo, sku, qtd, created_at FROM historico ORDER BY id DESC LIMIT 10');
    $ultimas =$stmt->fetchAll();

    $out = [];
    foreach ($ultimas as $l) {$out[] = [
            'hora' => date('H:i:s', strtotime($l['created_at'])),
            'user' => htmlspecialchars($l['user'], ENT_QUOTES, 'UTF-8'),             'tipo' =>$l['tipo'],
            'sku'  => htmlspecialchars($l['sku'], ENT_QUOTES, 'UTF-8'),             'qtd'  => (int)$l['qtd']
        ];
    }
    
    echo json_encode($out);
    exit;
}
<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

require_once '../includes/db.php';

$usuario_id = $_SESSION['usuario_id'];

// Obter ID do aluno e dojo via JOIN
$stmtAluno = $pdo->prepare("
    SELECT a.id, a.dojo_id
    FROM alunos a
    WHERE a.usuario_id = ?
");
$stmtAluno->execute([$usuario_id]);
$aluno = $stmtAluno->fetch();

if (!$aluno) {
    echo json_encode(['success' => false, 'message' => 'Aluno não encontrado']);
    exit;
}

$aluno_id = $aluno['id'];
$dojo_id = $aluno['dojo_id'];

// 🕓 Verifica se a data e hora atual estão dentro dos dias e horários permitidos
date_default_timezone_set('America/Sao_Paulo'); // Ajuste se necessário

$diaAtual = strtolower(date('l')); // Ex: 'monday', 'tuesday'
$horaAtual = date('H:i:s');

// Traduz para português (e sem acento)
$mapDias = [
    'monday' => 'segunda',
    'tuesday' => 'terca',
    'wednesday' => 'quarta',
    'thursday' => 'quinta',
    'friday' => 'sexta',
    'saturday' => 'sabado',
    'sunday' => 'domingo'
];
$diaSemana = $mapDias[$diaAtual] ?? '';

$stmtHorarios = $pdo->prepare("SELECT id, horario FROM treinos_horarios WHERE dojo_id = ? AND LOWER(dia_semana) = ?");
$stmtHorarios->execute([$dojo_id, $diaSemana]);
$horarios = $stmtHorarios->fetchAll(PDO::FETCH_ASSOC);

$presencaValida = false;
$idTreinoValido = null;

foreach ($horarios as $horario) {
    $horaPermitida = strtotime($horario['horario']);
    $horaAtualTS = strtotime($horaAtual);

    // Tolerância de 1 hora para antes ou depois do horário permitido
    if (abs($horaAtualTS - $horaPermitida) <= 3600) {
        $presencaValida = true;
        $idTreinoValido = $horario['id'];
        break;
    }
}

if (!$presencaValida) {
    echo json_encode(['success' => false, 'message' => 'Você só pode marcar presença nos dias e horários permitidos do seu dojo.']);
    exit;
}

// Verifica se já marcou presença hoje para esse treino
$stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM presencas WHERE aluno_id = ? AND data_presenca = CURDATE() AND treino_id = ?");
$stmtCheck->execute([$aluno_id, $idTreinoValido]);
$jaPresente = $stmtCheck->fetchColumn();

if ($jaPresente > 0) {
    echo json_encode(['success' => false, 'message' => 'Você já marcou presença para esse horário hoje.']);
    exit;
}

// Obter nome da faixa atual
$stmtFaixa = $pdo->prepare("
    SELECT f.faixa_cor
    FROM faixas_treinos ft
    INNER JOIN faixas f ON ft.id_faixa = f.id
    WHERE ft.aluno_id = ?
    ORDER BY ft.data_inicio DESC 
    LIMIT 1
");
$stmtFaixa->execute([$aluno_id]);
$faixaData = $stmtFaixa->fetch();
$faixaAtual = $faixaData ? $faixaData['faixa_cor'] : 'Branca';

try {
    $temDojoId = false;
    $result = $pdo->query("SHOW COLUMNS FROM presencas LIKE 'dojo_id'");
    if ($result->rowCount() > 0) {
        $temDojoId = true;
    }

    if ($temDojoId && $dojo_id !== null) {
        $stmtInsert = $pdo->prepare("
            INSERT INTO presencas (aluno_id, data_presenca, status, dojo_id, treino_id) 
            VALUES (?, CURDATE(), ?, ?, ?)
        ");
        $stmtInsert->execute([$aluno_id, 'presente', $dojo_id, $idTreinoValido]);
    } else {
        $stmtInsert = $pdo->prepare("
            INSERT INTO presencas (aluno_id, data_presenca, status, treino_id) 
            VALUES (?, CURDATE(), ?, ?)
        ");
        $stmtInsert->execute([$aluno_id, 'presente', $idTreinoValido]);
    }

    // Atualizar número de treinos na faixa atual
    $stmtFaixaTreinoId = $pdo->prepare("
        SELECT id FROM faixas_treinos 
        WHERE aluno_id = ? 
        ORDER BY data_inicio DESC 
        LIMIT 1
    ");
    $stmtFaixaTreinoId->execute([$aluno_id]);
    $faixaTreino = $stmtFaixaTreinoId->fetch();

    if ($faixaTreino) {
        $idFaixaTreino = $faixaTreino['id'];
        $stmtUpdateTreinos = $pdo->prepare("
            UPDATE faixas_treinos 
            SET numero_treinos = numero_treinos + 1 
            WHERE id = ?
        ");
        $stmtUpdateTreinos->execute([$idFaixaTreino]);
    }

    echo json_encode(['success' => true, 'message' => 'Presença marcada com sucesso!']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao marcar presença: ' . $e->getMessage()]);
}

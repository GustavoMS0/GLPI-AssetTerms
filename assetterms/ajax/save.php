<?php

/**
 * ------------------------------------------------------------------------
 * Asset Terms - Geração e envio do termo (aba do computador)
 *
 * POST (cabeçalho X-Glpi-Csrf-Token)
 *   computers_id, tipo_termo (entrega|devolucao), users_id, target_state_id,
 *   checklist[], observacoes, signature_image (data:image/png;base64,...),
 *   modo (arquivar|papel|email)
 *
 * modo=arquivar: assinatura na tela. Grava o PDF na aba Documentos, atualiza usuário e
 *                status do computador e registra no histórico. Devolve JSON.
 * modo=papel   : devolve o PDF sem assinatura, para imprimir e assinar à mão. Nada é gravado.
 * modo=email   : envia ao colaborador o link do termo. O computador só muda quando ele assinar.
 * ------------------------------------------------------------------------
 */

// GLPI 10 precisa carregar o núcleo; no GLPI 11 ele já está carregado
if (!defined('GLPI_ROOT')) {
    include __DIR__ . '/../../../inc/includes.php';
}

/** @var array $CFG_GLPI */
global $CFG_GLPI, $DB;

Session::checkLoginUser();
$json = [PluginAssettermsTerm::class, 'json'];

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $json(['success' => false, 'message' => 'Método não permitido.'], 405);
    return;
}

$cid      = (int) ($_POST['computers_id'] ?? 0);
$computer = new Computer();
if ($cid <= 0 || !$computer->getFromDB($cid)) {
    $json(['success' => false, 'message' => 'Equipamento não encontrado.'], 404);
    return;
}
if (!$computer->can($cid, UPDATE)) {
    $json(['success' => false, 'message' => 'Você não tem permissão para alterar este equipamento.'], 403);
    return;
}

$modo = in_array($_POST['modo'] ?? '', ['papel', 'email'], true) ? $_POST['modo'] : 'arquivar';
$in   = PluginAssettermsTerm::collectInput($_POST);
if (is_string($in)) {
    $json(['success' => false, 'message' => $in], 422);
    return;
}

$tech_id = (int) Session::getLoginUserID();
$agora   = time();
$dados   = PluginAssettermsTerm::buildData($computer, $in, $tech_id);
$titulo  = $dados['tipo'] === 'devolucao' ? 'Termo de Devolução' : 'Termo de Entrega';

// ---------------------------------------------------------- Envio por e-mail
if ($modo === 'email') {
    if ($dados['colaborador']['email'] === '') {
        $json(['success' => false, 'message' => "{$dados['colaborador']['nome']} não tem e-mail cadastrado no GLPI. Cadastre em Administração > Usuários."], 422);
        return;
    }
    $now = date('Y-m-d H:i:s', $agora);
    $DB->insert(PluginAssettermsTerm::TABLE, PluginAssettermsTerm::dbValues([
        'computers_id'  => $cid,
        'entities_id'   => (int) $computer->fields['entities_id'],
        'users_id'      => $in['users_id'],
        'users_id_tech' => $tech_id,
        'type'          => $dados['tipo'],
        'code'          => $dados['codigo'],
        'status'        => PluginAssettermsTerm::PENDING,
        'data'          => json_encode($dados, JSON_UNESCAPED_UNICODE),
        'date_send'     => $now,
        'date_creation' => $now,
        'date_mod'      => $now,
    ]));
    $req = PluginAssettermsTerm::getRequest((int) $DB->insertId());
    $err = $req !== null ? PluginAssettermsTerm::sendRequestMail($req) : 'falha ao gravar o pedido';
    if ($err !== null) {
        if ($req !== null) {
            $DB->delete(PluginAssettermsTerm::TABLE, ['id' => (int) $req['id']]);
        }
        $json(['success' => false, 'message' => "O e-mail não foi enviado: {$err}."], 502);
        return;
    }
    Log::history($cid, 'Computer', [0, '', "{$titulo} enviado por e-mail para assinatura de {$dados['colaborador']['nome']} ({$dados['colaborador']['email']}). Código: {$dados['codigo']}."], '', Log::HISTORY_LOG_SIMPLE_MESSAGE);
    $json([
        'success' => true,
        'reload'  => true,
        'message' => "{$titulo} enviado para {$dados['colaborador']['email']}. O equipamento será atualizado quando {$dados['colaborador']['nome']} assinar.",
    ]);
    return;
}

// ------------------------------------------------------- Assinatura na tela
$assinatura = '';
if ($modo === 'arquivar') {
    $assinatura = PluginAssettermsTerm::decodeSignature((string) ($_POST['signature_image'] ?? ''));
    if ($assinatura === null) {
        $json(['success' => false, 'message' => 'Assinatura inválida.'], 422);
        return;
    }
}
$dados = PluginAssettermsTerm::withDate($dados, $agora) + [
    'assinatura' => $assinatura,
    'modo'       => $assinatura !== '' ? 'tela' : 'manual',
    'ip'         => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
];

try {
    if ($modo === 'papel') {
        $pdf = PluginAssettermsTerm::buildPdf($dados);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . rawurlencode(PluginAssettermsTerm::fileName($dados, $agora)) . '"');
        header('Cache-Control: no-store');
        echo $pdf;
        return;
    }
    [$doc_id, $status] = PluginAssettermsTerm::archive($computer, $dados, $agora);
} catch (RuntimeException $e) {
    $json(['success' => false, 'message' => $e->getMessage()], 500);
    return;
} catch (Throwable $e) {
    Toolbox::logInFile('php-errors', 'Asset Terms: falha ao gerar o PDF: ' . $e->getMessage() . "\n");
    $json(['success' => false, 'message' => 'Falha ao gerar o PDF. Veja o log php-errors do GLPI.'], 500);
    return;
}

$json([
    'success'     => true,
    'message'     => "{$titulo} gerado e arquivado na aba Documentos.",
    'doc_id'      => $doc_id,
    'view_url'    => $CFG_GLPI['root_doc'] . '/front/document.send.php?docid=' . $doc_id,
    'status_name' => $status,
    'user_name'   => $dados['tipo'] === 'entrega' ? $dados['colaborador']['nome'] : 'Nenhum',
]);

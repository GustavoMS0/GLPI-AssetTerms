<?php

/**
 * ------------------------------------------------------------------------
 * Asset Terms - Reenviar ou cancelar um termo enviado por e-mail
 *
 * POST (cabeçalho X-Glpi-Csrf-Token): id, acao (reenviar|cancelar)
 * Exige permissão de alterar o computador do termo.
 * ------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    include __DIR__ . '/../../../inc/includes.php';
}

/** @var DBmysql $DB */
global $DB;

Session::checkLoginUser();
$json = [PluginAssettermsTerm::class, 'json'];

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $json(['success' => false, 'message' => 'Método não permitido.'], 405);
    return;
}

$req      = PluginAssettermsTerm::getRequest((int) ($_POST['id'] ?? 0));
$computer = new Computer();
if ($req === null || !$computer->getFromDB((int) $req['computers_id'])) {
    $json(['success' => false, 'message' => 'Termo não encontrado.'], 404);
    return;
}
if (!$computer->can((int) $req['computers_id'], UPDATE)) {
    $json(['success' => false, 'message' => 'Você não tem permissão para alterar este equipamento.'], 403);
    return;
}
if ((int) $req['status'] !== PluginAssettermsTerm::PENDING) {
    $json(['success' => false, 'message' => 'Este termo não está mais aguardando assinatura.', 'reload' => true], 409);
    return;
}

$nome   = $req['data']['colaborador']['nome'] ?? '';
$titulo = $req['type'] === 'devolucao' ? 'Termo de Devolução' : 'Termo de Entrega';

if (($_POST['acao'] ?? '') === 'cancelar') {
    $DB->update(PluginAssettermsTerm::TABLE, ['status' => PluginAssettermsTerm::CANCELED, 'date_mod' => date('Y-m-d H:i:s')], ['id' => (int) $req['id']]);
    Log::history((int) $req['computers_id'], 'Computer', [0, '', "{$titulo} enviado para {$nome} cancelado. Código: {$req['code']}."], '', Log::HISTORY_LOG_SIMPLE_MESSAGE);
    $json(['success' => true, 'reload' => true, 'message' => 'Termo cancelado. O link enviado deixou de funcionar.']);
    return;
}

if (($_POST['acao'] ?? '') === 'reenviar') {
    if (!PluginAssettermsTerm::mailConfigured()) {
        $json(['success' => false, 'message' => 'O envio de e-mails do GLPI não está configurado. Use "Copiar link".'], 422);
        return;
    }
    // Usa o e-mail atual do colaborador, caso tenha sido corrigido depois do primeiro envio
    $atual = PluginAssettermsTerm::userData((int) $req['users_id'])['email'];
    if ($atual !== '') {
        $req['data']['colaborador']['email'] = $atual;
    }
    if (($req['data']['colaborador']['email'] ?? '') === '') {
        $json(['success' => false, 'message' => "{$nome} não tem e-mail cadastrado no GLPI. Use \"Copiar link\"."], 422);
        return;
    }
    $req['data']['canal'] = 'email';
    $DB->update(PluginAssettermsTerm::TABLE, PluginAssettermsTerm::dbValues(['data' => json_encode($req['data'], JSON_UNESCAPED_UNICODE)]), ['id' => (int) $req['id']]);
    $err = PluginAssettermsTerm::sendRequestMail($req);
    if ($err !== null) {
        $json(['success' => false, 'message' => "O e-mail não foi enviado: {$err}."], 502);
        return;
    }
    $DB->update(PluginAssettermsTerm::TABLE, ['date_send' => date('Y-m-d H:i:s'), 'date_mod' => date('Y-m-d H:i:s')], ['id' => (int) $req['id']]);
    $json(['success' => true, 'reload' => true, 'message' => 'E-mail reenviado para ' . ($req['data']['colaborador']['email'] ?? '') . '.']);
    return;
}

$json(['success' => false, 'message' => 'Ação inválida.'], 422);

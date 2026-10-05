<?php

/**
 * ------------------------------------------------------------------------
 * Asset Terms - Assinatura do termo pelo próprio colaborador (link do e-mail)
 *
 * POST (cabeçalho X-Glpi-Csrf-Token): id, aceite=1, signature_image (data:image/png;base64,...)
 * Só o colaborador destinatário, logado no GLPI, pode assinar, e uma única vez.
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

$req = PluginAssettermsTerm::getRequest((int) ($_POST['id'] ?? 0));
$me  = (int) Session::getLoginUserID();
if ($req === null || (int) $req['users_id'] !== $me) {
    $json(['success' => false, 'message' => 'Este termo não existe ou foi enviado para outro usuário.'], 403);
    return;
}
if ((int) $req['status'] !== PluginAssettermsTerm::PENDING) {
    $json(['success' => false, 'message' => 'Este termo não está mais aguardando assinatura.'], 409);
    return;
}
if (($_POST['aceite'] ?? '') !== '1') {
    $json(['success' => false, 'message' => 'Marque que leu e concorda com o termo.'], 422);
    return;
}
$assinatura = PluginAssettermsTerm::decodeSignature((string) ($_POST['signature_image'] ?? ''));
if ($assinatura === null || $assinatura === '') {
    $json(['success' => false, 'message' => 'Assine no quadro antes de enviar.'], 422);
    return;
}

$computer = new Computer();
if (!$computer->getFromDB((int) $req['computers_id'])) {
    $json(['success' => false, 'message' => 'O equipamento deste termo não existe mais. Fale com a TI.'], 410);
    return;
}

// Reserva o pedido: um segundo envio (duplo clique, outra aba) não gera outro termo
$agora = time();
$DB->update(PluginAssettermsTerm::TABLE, [
    'status'      => PluginAssettermsTerm::SIGNED,
    'date_signed' => date('Y-m-d H:i:s', $agora),
    'sign_ip'     => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    'date_mod'    => date('Y-m-d H:i:s', $agora),
], ['id' => (int) $req['id'], 'status' => PluginAssettermsTerm::PENDING]);
if ($DB->affectedRows() !== 1) {
    $json(['success' => false, 'message' => 'Este termo já foi assinado.'], 409);
    return;
}

$user  = new User();
$user->getFromDB($me);
$dados = PluginAssettermsTerm::withDate($req['data'], $agora) + [
    'assinatura' => $assinatura,
    'modo'       => 'email',
    'ip'         => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    'login'      => (string) ($user->fields['name'] ?? ''),
    'enviado_em' => date('d/m/Y H:i', strtotime((string) $req['date_send'])),
];

try {
    [$doc_id, , $pdf] = PluginAssettermsTerm::archive($computer, $dados, $agora);
} catch (Throwable $e) {
    // Libera o pedido para uma nova tentativa
    $DB->update(PluginAssettermsTerm::TABLE, ['status' => PluginAssettermsTerm::PENDING, 'date_signed' => null, 'sign_ip' => ''], ['id' => (int) $req['id']]);
    Toolbox::logInFile('php-errors', 'Asset Terms: falha ao arquivar o termo #' . (int) $req['id'] . ': ' . $e->getMessage() . "\n");
    $json(['success' => false, 'message' => 'Não foi possível concluir a assinatura agora. Tente de novo em alguns minutos ou fale com a TI.'], 500);
    return;
}

$DB->update(PluginAssettermsTerm::TABLE, ['documents_id' => $doc_id], ['id' => (int) $req['id']]);
PluginAssettermsTerm::sendSignedMails($req, $pdf, PluginAssettermsTerm::fileName($dados, $agora), $dados['data']);

$json([
    'success' => true,
    'message' => 'Termo assinado. Obrigado! Uma cópia em PDF foi enviada para o seu e-mail.',
]);

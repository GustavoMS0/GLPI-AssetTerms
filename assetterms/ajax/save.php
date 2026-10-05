<?php

/**
 * ------------------------------------------------------------------------
 * Asset Terms - Geração do PDF
 *
 * POST (cabeçalho X-Glpi-Csrf-Token)
 *   computers_id, tipo_termo (entrega|devolucao), users_id, target_state_id,
 *   checklist[], observacoes, signature_image (data:image/png;base64,...), modo (arquivar|papel)
 *
 * modo=papel   : devolve o PDF sem assinatura, para imprimir e assinar à mão. Nada é gravado.
 * modo=arquivar: grava o PDF na aba Documentos do computador, atualiza usuário e status
 *                e registra no histórico. Devolve JSON.
 * ------------------------------------------------------------------------
 */

// GLPI 10 precisa carregar o núcleo; no GLPI 11 ele já está carregado
if (!defined('GLPI_ROOT')) {
    include __DIR__ . '/../../../inc/includes.php';
}

/** Responde em JSON. Retorna para o chamador encerrar o script com return. */
function plugin_assetterms_json(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
}

/** @var array $CFG_GLPI */
global $CFG_GLPI;

Session::checkLoginUser();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    plugin_assetterms_json(['success' => false, 'message' => 'Método não permitido.'], 405);
    return;
}

$cid      = (int) ($_POST['computers_id'] ?? 0);
$computer = new Computer();
if ($cid <= 0 || !$computer->getFromDB($cid)) {
    plugin_assetterms_json(['success' => false, 'message' => 'Equipamento não encontrado.'], 404);
    return;
}
if (!$computer->can($cid, UPDATE)) {
    plugin_assetterms_json(['success' => false, 'message' => 'Você não tem permissão para alterar este equipamento.'], 403);
    return;
}

$papel = ($_POST['modo'] ?? '') === 'papel';
$tipo  = ($_POST['tipo_termo'] ?? '') === 'devolucao' ? 'devolucao' : 'entrega';
$uid   = (int) ($_POST['users_id'] ?? 0);

$colaborador = PluginAssettermsTerm::userData($uid);
if ($colaborador['nome'] === '') {
    plugin_assetterms_json(['success' => false, 'message' => 'Selecione o colaborador.'], 422);
    return;
}

// Só aceita os acessórios conhecidos e os status visíveis para computadores
$checklist = [];
foreach ((array) ($_POST['checklist'] ?? []) as $key) {
    if (is_string($key) && isset(PluginAssettermsTerm::CHECKLIST[$key])) {
        $checklist[$key] = PluginAssettermsTerm::CHECKLIST[$key];
    }
}
$state_id = (int) ($_POST['target_state_id'] ?? 0);
if ($state_id > 0 && !isset(PluginAssettermsTerm::getStates()[$state_id])) {
    $state_id = 0;
}
$observacoes = mb_substr(trim((string) ($_POST['observacoes'] ?? '')), 0, 2000);

// Assinatura: PNG em base64 gerado pelo canvas, com no máximo 1 MB
$assinatura = '';
if (!$papel) {
    $raw = (string) ($_POST['signature_image'] ?? '');
    if ($raw !== '') {
        if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $raw, $m) || strlen($m[1]) > 1400000) {
            plugin_assetterms_json(['success' => false, 'message' => 'Assinatura inválida.'], 422);
            return;
        }
        $assinatura = (string) base64_decode($m[1], true);
        $info = $assinatura !== '' ? @getimagesizefromstring($assinatura) : false;
        if ($info === false || ($info['mime'] ?? '') !== 'image/png') {
            plugin_assetterms_json(['success' => false, 'message' => 'Assinatura inválida.'], 422);
            return;
        }
    }
}

$entity = new Entity();
$entity->getFromDB((int) $computer->fields['entities_id']);
$specs    = PluginAssettermsTerm::getComputerSpecs($computer);
$tech_id  = (int) Session::getLoginUserID();
$agora    = time();
$meses    = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$codigo   = strtoupper(substr(hash('sha256', implode('|', [$cid, $tipo, $uid, $tech_id, $agora, random_bytes(8)])), 0, 12));
$codigo   = implode('-', str_split($codigo, 4));

$dados = [
    'tipo'         => $tipo,
    'empresa'      => (string) ($entity->fields['name'] ?? ''),
    'cidade'       => trim((string) ($entity->fields['town'] ?? '')),
    'data'         => date('d/m/Y H:i', $agora),
    'data_extenso' => date('j', $agora) . ' de ' . $meses[(int) date('n', $agora) - 1] . ' de ' . date('Y', $agora),
    'codigo'       => $codigo,
    'tecnico'      => PluginAssettermsTerm::userName($tech_id) ?: 'TI',
    'ip'           => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    'colaborador'  => $colaborador,
    'equipamento'  => $specs + [
        'nome'       => (string) ($computer->fields['name'] ?? ''),
        'serial'     => (string) ($computer->fields['serial'] ?? ''),
        'patrimonio' => (string) ($computer->fields['otherserial'] ?? ''),
    ],
    'checklist'    => array_values($checklist),
    'observacoes'  => $observacoes,
    'assinatura'   => $assinatura,
];

try {
    $pdf = PluginAssettermsTerm::buildPdf($dados);
} catch (Throwable $e) {
    Toolbox::logInFile('php-errors', 'Asset Terms: falha ao gerar o PDF: ' . $e->getMessage() . "\n");
    plugin_assetterms_json(['success' => false, 'message' => 'Falha ao gerar o PDF. Veja o log php-errors do GLPI.'], 500);
    return;
}

$titulo   = $tipo === 'devolucao' ? 'Termo de Devolução' : 'Termo de Entrega';
$arquivo  = sprintf(
    '%s - %s - %s.pdf',
    $titulo,
    preg_replace('/[^\pL\pN ._-]+/u', '', $dados['equipamento']['nome'] ?: 'equipamento'),
    date('Y-m-d His', $agora)
);

if ($papel) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . rawurlencode($arquivo) . '"');
    header('Cache-Control: no-store');
    echo $pdf;
    return;
}

// Grava no diretório temporário do GLPI e deixa o próprio GLPI mover, conferir o tipo e calcular o checksum
$tmp = GLPI_TMP_DIR . '/' . $arquivo;
if (file_put_contents($tmp, $pdf) === false) {
    plugin_assetterms_json(['success' => false, 'message' => 'Não foi possível gravar o arquivo em ' . GLPI_TMP_DIR . '.'], 500);
    return;
}

$detalhes = sprintf(
    '%s de %s. Código %s. %s.',
    $titulo,
    $colaborador['nome'],
    $codigo,
    $assinatura !== '' ? 'Assinado na tela' : 'Para assinatura manual'
);
$input = [
    'name'         => $titulo . ' - ' . ($dados['equipamento']['nome'] ?: 'equipamento') . ' - ' . $colaborador['nome'] . ' - ' . date('d/m/Y', $agora),
    'entities_id'  => (int) $computer->fields['entities_id'],
    'is_recursive' => 0,
    'comment'      => $detalhes,
    'users_id'     => $tech_id,
    '_filename'    => [$arquivo],
];
if (version_compare(GLPI_VERSION, '11.0.0-dev', '<')) {
    // O GLPI 10 espera a entrada escapada, como vem de um formulário
    $input = Toolbox::addslashes_deep($input);
}

$document = new Document();
$doc_id   = (int) $document->add($input);
if (is_file($tmp)) {
    @unlink($tmp);
}
if ($doc_id <= 0) {
    plugin_assetterms_json(['success' => false, 'message' => 'O GLPI recusou o documento. Confira se o tipo PDF está liberado em Configurar > Listas suspensas > Tipos de documento.'], 500);
    return;
}

// Vínculo feito à parte: criando o documento já vinculado, o GLPI 10 troca o nome pelo do computador
$document_item = new Document_Item();
$document_item->add([
    'documents_id' => $doc_id,
    'itemtype'     => 'Computer',
    'items_id'     => $cid,
    'entities_id'  => (int) $computer->fields['entities_id'],
]);

// Usuário e status do equipamento
$update = ['id' => $cid, 'users_id' => $tipo === 'entrega' ? $uid : 0];
if ($state_id > 0) {
    $update['states_id'] = $state_id;
}
$computer->update($update);

$status = $state_id > 0 ? (string) Dropdown::getDropdownName('glpi_states', $state_id) : '';
$msg    = "{$titulo} #{$doc_id} arquivado. Colaborador: {$colaborador['nome']}. Código: {$codigo}." . ($status !== '' ? " Status: {$status}." : '');
Log::history($cid, 'Computer', [0, '', $msg], '', Log::HISTORY_LOG_SIMPLE_MESSAGE);

plugin_assetterms_json([
    'success'     => true,
    'message'     => "{$titulo} gerado e arquivado na aba Documentos.",
    'doc_id'      => $doc_id,
    'view_url'    => $CFG_GLPI['root_doc'] . '/front/document.send.php?docid=' . $doc_id,
    'status_name' => $status,
    'user_name'   => $tipo === 'entrega' ? $colaborador['nome'] : 'Nenhum',
]);

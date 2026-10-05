<?php

/**
 * ------------------------------------------------------------------------
 * Asset Terms - PDF de exemplo da tela de configuração
 *
 * POST (cabeçalho X-Glpi-Csrf-Token): os campos do formulário de configuração,
 * entities_id e preview_tipo (entrega|devolucao). Devolve o PDF com dados fictícios.
 * Nada é gravado.
 * ------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    include __DIR__ . '/../../../inc/includes.php';
}

Session::checkLoginUser();
$json = [PluginAssettermsTerm::class, 'json'];
PluginAssettermsTerm::normalizeInput();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !Session::haveRight('config', UPDATE)) {
    $json(['success' => false, 'message' => 'Sem permissão.'], 403);
    return;
}

$data = PluginAssettermsConfig::collect($_POST);
if (is_string($data)) {
    $json(['success' => false, 'message' => $data], 422);
    return;
}

$logo = PluginAssettermsConfig::readLogo($_FILES['logo'] ?? null);
if (is_string($logo)) {
    $json(['success' => false, 'message' => $logo], 422);
    return;
}

$eid = (int) ($_POST['entities_id'] ?? 0);
$cfg = PluginAssettermsConfig::getEffective($eid);
foreach (['company_name', 'company_doc', 'city'] as $field) {
    if ($data[$field] !== '') {
        $cfg[$field] = $data[$field];
    }
}
foreach (['texts', 'code_format', 'code_prefix', 'code_digits', 'code_yearly', 'color', 'footer'] as $field) {
    $cfg[$field] = $data[$field];
}
// Logo: o recém-escolhido no formulário, nenhum (se marcou remover) ou o atual
if ($logo !== null) {
    $cfg['logo']      = $logo['data'];
    $cfg['logo_mime'] = $logo['mime'];
} elseif ($data['remove_logo']) {
    $cfg['logo'] = '';
}

$tipo  = ($_POST['preview_tipo'] ?? '') === 'devolucao' ? 'devolucao' : 'entrega';
$dados = PluginAssettermsTerm::withDate([
    'tipo'         => $tipo,
    'entities_id'  => $eid,
    'empresa'      => $cfg['company_name'],
    'cnpj'         => $cfg['company_doc'],
    'cidade'       => $cfg['city'],
    'codigo'       => PluginAssettermsConfig::buildCode($cfg, $tipo, time(), false),
    'marca'        => $cfg,
    'tecnico'      => PluginAssettermsTerm::userName((int) Session::getLoginUserID()) ?: 'Técnico da TI',
    'tecnico_id'   => (int) Session::getLoginUserID(),
    'colaborador'  => ['id' => 0, 'nome' => 'Nome do Colaborador', 'matricula' => '000123', 'email' => 'colaborador@empresa.com.br'],
    'equipamento'  => [
        'nome' => 'NB-EXEMPLO', 'tipo' => 'Notebook', 'fabricante' => 'Dell Inc.', 'modelo' => 'Latitude 5440',
        'serial' => 'ABC1234', 'patrimonio' => 'PAT-0001', 'cpu' => 'Intel Core i5-1335U', 'ram' => '16 GB',
        'disk' => '512 GB', 'so' => 'Windows 11 Pro',
    ],
    'checklist'    => ['Fonte / carregador original', 'Mouse'],
    'observacoes'  => 'Exemplo de observação sobre o estado do equipamento.',
    'state_id'     => 0,
    'texto'        => PluginAssettermsConfig::render($cfg, $tipo),
    'assinatura'   => '',
    'modo'         => 'manual',
    'ip'           => '',
], time());

try {
    $pdf = PluginAssettermsTerm::buildPdf($dados);
} catch (Throwable $e) {
    $json(['success' => false, 'message' => 'Falha ao gerar o PDF: ' . $e->getMessage()], 500);
    return;
}
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="termo-exemplo.pdf"');
header('Cache-Control: no-store');
echo $pdf;

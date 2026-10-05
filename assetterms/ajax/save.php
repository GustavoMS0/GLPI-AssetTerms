<?php

/**
 * ------------------------------------------------------------------------
 * Asset Terms - Geração e envio do termo (aba do computador)
 *
 * POST (cabeçalho X-Glpi-Csrf-Token)
 *   computers_id, tipo_termo (entrega|devolucao), users_id, target_state_id,
 *   checklist[], observacoes, signature_image (data:image/png;base64,...),
 *   modo (arquivar|papel|email|link), equip_* (dados do equipamento no termo)
 *   tipo_termo=status: usuario_acao (manter|remover), target_state_id, observacoes
 *
 * modo=arquivar: assinatura na tela. Grava o PDF na aba Documentos, atualiza usuário e
 *                status do computador e registra no histórico. Devolve JSON.
 * modo=papel   : devolve o PDF sem assinatura, para imprimir e assinar à mão. Nada é gravado.
 * modo=email   : envia ao colaborador o link do termo. O computador só muda quando ele assinar.
 * modo=link    : igual ao e-mail, mas devolve o link para o técnico mandar por outro meio.
 * tipo=status  : só muda o status (ciclo de vida) e, se pedido, remove o usuário. Sem termo.
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
PluginAssettermsTerm::normalizeInput();

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

// ----------------------------------------------- Somente ciclo de vida (sem termo)
if (($_POST['tipo_termo'] ?? '') === 'status') {
    $state_id = (int) ($_POST['target_state_id'] ?? 0);
    if ($state_id <= 0 || !isset(PluginAssettermsTerm::getStates()[$state_id])) {
        $json(['success' => false, 'message' => 'Escolha o novo status.'], 422);
        return;
    }
    $acao   = $_POST['usuario_acao'] ?? 'manter';
    $obs    = mb_substr(trim((string) ($_POST['observacoes'] ?? '')), 0, 2000);
    $update = ['id' => $cid, 'states_id' => $state_id];
    $quem   = '';

    if ($acao === 'remover') {
        // Sem ninguém com o equipamento: limpa também o nome informado (Usuário alternativo)
        $update['users_id'] = 0;
        $update['contact']  = '';
        $quem = ' e usuário removido';
    } elseif ($acao === 'mover') {
        $novo = (int) ($_POST['users_id_status'] ?? 0);
        $nome = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($_POST['nome_status'] ?? ''))), 0, 255);
        $novo_nome = $novo > 0 ? PluginAssettermsTerm::userName($novo) : '';
        if ($novo > 0 && $novo_nome === '') {
            $json(['success' => false, 'message' => 'Usuário do GLPI não encontrado.'], 422);
            return;
        }
        if ($novo_nome === '' && $nome === '') {
            $json(['success' => false, 'message' => 'Para mover, escolha o novo usuário do GLPI ou digite o nome de preferência.'], 422);
            return;
        }
        $update['users_id'] = $novo;
        $update['contact']  = $nome;
        $quem = ' e equipamento movido para ' . ($novo_nome !== '' ? $novo_nome . ($nome !== '' && $nome !== $novo_nome ? " ({$nome})" : '') : "{$nome} (sem usuário no GLPI)");
    }

    if (version_compare(GLPI_VERSION, '11.0.0-dev', '<')) {
        // O GLPI 10 espera a entrada escapada, como vem de um formulário
        $update = Toolbox::addslashes_deep($update);
    }
    $computer->update($update);
    $status = (string) Dropdown::getDropdownName('glpi_states', $state_id);
    Log::history($cid, 'Computer', [0, '', "Ciclo de vida: status alterado para {$status}{$quem}, sem termo." . ($obs !== '' ? " Observação: {$obs}" : '')], '', Log::HISTORY_LOG_SIMPLE_MESSAGE);
    $computer->getFromDB($cid);
    $json([
        'success'     => true,
        'message'     => "Status alterado para {$status}{$quem}. A mudança ficou registrada no histórico do equipamento.",
        'status_name' => $status,
        'user_name'   => PluginAssettermsTerm::holderName($computer),
    ]);
    return;
}

$modo = in_array($_POST['modo'] ?? '', ['papel', 'email', 'link'], true) ? $_POST['modo'] : 'arquivar';
$in   = PluginAssettermsTerm::collectInput($_POST);
if (is_string($in)) {
    $json(['success' => false, 'message' => $in], 422);
    return;
}

$tech_id = (int) Session::getLoginUserID();
$agora   = time();
$dados   = PluginAssettermsTerm::buildData($computer, $in, $tech_id);
$titulo  = $dados['tipo'] === 'devolucao' ? 'Termo de Devolução' : 'Termo de Entrega';

// ---------------------------------------- Assinatura pelo link (e-mail ou copiado)
if ($modo === 'email' || $modo === 'link') {
    if ($modo === 'email' && !PluginAssettermsTerm::mailConfigured()) {
        $json(['success' => false, 'message' => 'O envio de e-mails do GLPI não está configurado. Use "Gerar link de assinatura".'], 422);
        return;
    }
    if ($modo === 'email' && $dados['colaborador']['email'] === '') {
        $json(['success' => false, 'message' => "{$dados['colaborador']['nome']} não tem e-mail cadastrado no GLPI. Cadastre em Administração > Usuários ou use \"Gerar link de assinatura\"."], 422);
        return;
    }
    $dados['canal'] = $modo;
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
    if ($req === null) {
        $err = 'falha ao gravar o pedido';
    } else {
        $err = $modo === 'email' ? PluginAssettermsTerm::sendRequestMail($req) : null;
    }
    if ($err !== null) {
        if ($req !== null) {
            $DB->delete(PluginAssettermsTerm::TABLE, ['id' => (int) $req['id']]);
        }
        $json(['success' => false, 'message' => "O e-mail não foi enviado: {$err}."], 502);
        return;
    }
    $como = $modo === 'email' ? "enviado por e-mail ({$dados['colaborador']['email']})" : 'com link de assinatura gerado';
    Log::history($cid, 'Computer', [0, '', "{$titulo} {$como} para assinatura de {$dados['colaborador']['nome']}. Código: {$dados['codigo']}."], '', Log::HISTORY_LOG_SIMPLE_MESSAGE);
    if ($modo === 'link') {
        $json([
            'success' => true,
            'link'    => PluginAssettermsTerm::signUrl((int) $req['id']),
            'message' => "Link criado. Mande para {$dados['colaborador']['nome']}: ao abrir, ele entra no GLPI com o próprio usuário e assina. O equipamento será atualizado depois da assinatura.",
        ]);
        return;
    }
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

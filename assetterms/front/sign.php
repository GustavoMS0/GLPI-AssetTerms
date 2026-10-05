<?php

/**
 * ------------------------------------------------------------------------
 * Asset Terms - Página de assinatura do termo enviado por e-mail
 *
 * GET ?id=<pedido>
 * Exige login no GLPI: sem sessão, o GLPI leva ao login e volta para esta página.
 * Só o colaborador destinatário do termo pode ver e assinar.
 * ------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    include __DIR__ . '/../../../inc/includes.php';
}

/** @var array $CFG_GLPI */
global $CFG_GLPI;

// Sem sessão: vai direto para a tela de login e volta para este termo depois de entrar
// (o padrão do GLPI 11 mostraria "sua sessão expirou", o que confunde quem vem do e-mail)
if (Session::getLoginUserID() === false) {
    $target = substr(PluginAssettermsTerm::webPath(), strlen($CFG_GLPI['root_doc'])) . '/front/sign.php?id=' . (int) ($_GET['id'] ?? 0);
    $home   = version_compare(GLPI_VERSION, '11.0.0-dev', '>=') ? '/' : '/index.php';
    Html::redirect($CFG_GLPI['root_doc'] . $home . '?redirect=' . rawurlencode($target));
    return;
}
Session::checkLoginUser();

$e   = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$or  = static fn ($v): string => trim((string) $v) !== '' ? (string) $v : 'Não informado';
$req = PluginAssettermsTerm::getRequest((int) ($_GET['id'] ?? 0));
$me  = (int) Session::getLoginUserID();

$helpdesk = Session::getCurrentInterface() === 'helpdesk';
if ($helpdesk) {
    Html::helpHeader('Termo de Responsabilidade');
} else {
    Html::header('Termo de Responsabilidade', '', 'assets');
}

echo '<div class="termo-container termo-sign-page">';

if ($req === null || (int) $req['users_id'] !== $me) {
    echo '<div class="termo-card"><h3 class="termo-title"><i class="ti ti-lock"></i> Termo não disponível</h3>'
        . '<p>Este termo não existe ou foi enviado para outro usuário. Confira se você entrou no GLPI com o seu próprio usuário.</p></div>';
} elseif ((int) $req['status'] === PluginAssettermsTerm::SIGNED) {
    echo '<div class="termo-card"><h3 class="termo-title"><i class="ti ti-circle-check text-success"></i> Termo já assinado</h3>'
        . '<p>Você assinou este termo em <strong>' . $e(Html::convDateTime($req['date_signed'])) . '</strong>. ' . (PluginAssettermsTerm::mailConfigured() ? 'A cópia em PDF foi enviada para o seu e-mail.' : 'Se precisar de uma cópia, peça à TI.') . '</p></div>';
} elseif ((int) $req['status'] === PluginAssettermsTerm::CANCELED) {
    echo '<div class="termo-card"><h3 class="termo-title"><i class="ti ti-circle-x text-danger"></i> Termo cancelado</h3>'
        . '<p>A TI cancelou este termo. Se tiver dúvidas, fale com a equipe de TI.</p></div>';
} else {
    $d     = $req['data'];
    $eq    = $d['equipamento'];
    $texto = PluginAssettermsTerm::clausula($d['tipo'], $d['empresa']);
    $acao  = $d['tipo'] === 'devolucao' ? 'devolução' : 'entrega';
    ?>
    <div class="termo-card termo-header-card">
        <h3 class="termo-title"><i class="ti ti-file-certificate"></i> <?= $e($texto['titulo']) ?></h3>
        <p class="termo-subtitle">
            <?= $e($d['tecnico']) ?> registrou a <?= $acao ?> deste equipamento para você. Leia o termo e assine no final.
        </p>
        <div class="termo-specs-grid">
            <div class="spec-item"><span class="spec-label">Colaborador:</span><span class="spec-val"><?= $e($d['colaborador']['nome']) ?></span></div>
            <div class="spec-item"><span class="spec-label">Equipamento:</span><span class="spec-val"><?= $e($or($eq['nome'])) ?></span></div>
            <div class="spec-item"><span class="spec-label">Fabricante / modelo:</span><span class="spec-val"><?= $e($or(trim($eq['fabricante'] . ' ' . $eq['modelo']))) ?></span></div>
            <div class="spec-item"><span class="spec-label">Número de série:</span><span class="spec-val highlight"><?= $e($or($eq['serial'])) ?></span></div>
            <div class="spec-item"><span class="spec-label">Patrimônio:</span><span class="spec-val highlight"><?= $e($or($eq['patrimonio'])) ?></span></div>
            <div class="spec-item"><span class="spec-label">Código do termo:</span><span class="spec-val"><?= $e($d['codigo']) ?></span></div>
            <div class="spec-item full-width">
                <span class="spec-label">Acessórios <?= $d['tipo'] === 'devolucao' ? 'devolvidos' : 'entregues' ?>:</span>
                <span class="spec-val"><?= $e($d['checklist'] ? implode(', ', $d['checklist']) : 'Nenhum') ?></span>
            </div>
            <?php if ($d['observacoes'] !== ''): ?>
            <div class="spec-item full-width"><span class="spec-label">Observações:</span><span class="spec-val"><?= nl2br($e($d['observacoes'])) ?></span></div>
            <?php endif; ?>
        </div>
    </div>

    <form id="form-termo-assinatura" class="termo-card" method="post"
          action="<?= $e(PluginAssettermsTerm::webPath() . '/ajax/sign.php') ?>" onsubmit="return false;">
        <input type="hidden" name="id" value="<?= (int) $req['id'] ?>">

        <div class="termo-clausula-box">
            <strong><?= $e($texto['titulo']) ?></strong>
            <p><?= $e($texto['declaracao']) ?></p>
            <?= $d['tipo'] === 'entrega' ? '<ol type="I">' : '<ul>' ?>
                <?php foreach ($texto['compromissos'] as $c): ?><li><?= $e($c) ?></li><?php endforeach; ?>
            <?= $d['tipo'] === 'entrega' ? '</ol>' : '</ul>' ?>
            <p><?= $e($texto['ciencia']) ?></p>
        </div>

        <label class="termo-aceite">
            <input type="checkbox" name="aceite" value="1" id="termo-aceite">
            Li o termo e concordo com as condições acima.
        </label>

        <div class="termo-signature-area">
            <div class="signature-header">
                <label class="termo-label"><i class="ti ti-writing"></i> Sua assinatura (dedo, caneta ou mouse)</label>
                <button type="button" id="btn-clear-signature" class="btn btn-sm btn-outline-secondary">
                    <i class="ti ti-eraser"></i> Limpar
                </button>
            </div>
            <div class="canvas-wrapper">
                <canvas id="signature-canvas"></canvas>
                <div class="canvas-placeholder">Assine aqui</div>
            </div>
        </div>

        <div class="termo-actions-bar">
            <button type="button" id="btn-sign-termo" class="btn btn-primary btn-lg">
                <i class="ti ti-check"></i> Assinar termo
            </button>
            <div id="termo-loading-spinner" class="spinner-border text-primary ms-3 d-none" role="status">
                <span class="visually-hidden">Enviando...</span>
            </div>
        </div>
        <div id="termo-alert-box" class="alert d-none mt-3"></div>
    </form>
    <?php
}

echo '</div>';

if ($helpdesk) {
    Html::helpFooter();
} else {
    Html::footer();
}

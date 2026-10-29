<?php

/**
 * ------------------------------------------------------------------------
 * Asset Terms - Minha assinatura (quem emite os termos)
 *
 * O técnico cadastra a própria assinatura uma vez (desenhada ou com o nome digitado).
 * Ela vai no lado da TI de todo termo que ele gerar a partir de agora.
 *
 * POST: signature_image (data:image/png;base64,...), signature_kind, ou remove=1.
 * O CSRF é validado pelo GLPI antes deste script (validar de novo falharia: o token é consumido).
 * ------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    include __DIR__ . '/../../../inc/includes.php';
}

Session::checkRight('computer', UPDATE);
PluginAssettermsTerm::normalizeInput();

$self = PluginAssettermsSignature::pageUrl();
$me   = (int) Session::getLoginUserID();
$e    = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

// ----------------------------------------------------------------- Gravação
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (isset($_POST['remove'])) {
        PluginAssettermsSignature::delete($me);
        Session::addMessageAfterRedirect('Assinatura removida. Os próximos termos saem com o seu nome e o registro do seu usuário, sem a imagem.', true, INFO);
    } else {
        $png = PluginAssettermsTerm::decodeSignature((string) ($_POST['signature_image'] ?? ''));
        if ($png === null || $png === '') {
            Session::addMessageAfterRedirect('Assine no quadro ou digite o seu nome antes de salvar.', false, ERROR);
        } elseif (!PluginAssettermsSignature::save($me, $png, (string) ($_POST['signature_kind'] ?? ''))) {
            Session::addMessageAfterRedirect('Não foi possível gravar a assinatura.', false, ERROR);
        } else {
            Session::addMessageAfterRedirect('Assinatura salva. Ela vai nos próximos termos que você gerar.', true, INFO);
        }
    }
    Html::redirect($self);
    return;
}

$atual = PluginAssettermsSignature::get($me);
$nome  = PluginAssettermsTerm::userName($me);

Html::header(PluginAssettermsSignature::getTypeName(), '', 'assets', 'PluginAssettermsMenu');

echo '<div class="termo-container">';
?>
<div class="termo-card termo-header-card">
    <h3 class="termo-title"><i class="ti ti-signature"></i> Minha assinatura</h3>
    <p class="termo-subtitle">
        Vai no lado <strong>Entregue por / Recebido por (TI)</strong> de todo termo que você gerar: na tela, no PDF para o papel e nos enviados por e-mail ou link.
        O PDF registra que ela foi aplicada a partir do seu cadastro, com o seu usuário do GLPI.
        Trocar a assinatura não muda os termos já gerados.
    </p>
    <?php if ($atual !== null): ?>
        <div class="termo-tech-signature">
            <img alt="Assinatura cadastrada" src="data:image/png;base64,<?= $e($atual['image']) ?>">
            <small class="termo-hint">
                <?= $atual['kind'] === 'digitada' ? 'Com o nome digitado' : 'Desenhada' ?>, cadastrada em <?= $e(Html::convDateTime($atual['date_mod'])) ?>.
            </small>
        </div>
    <?php else: ?>
        <div class="alert alert-secondary mt-3 mb-0">
            Você ainda não cadastrou sua assinatura. Até cadastrar, os termos saem com o seu nome e o registro do seu usuário, sem a imagem.
        </div>
    <?php endif; ?>
</div>

<form id="form-termo-minha-assinatura" class="termo-card" method="post" action="<?= $e($self) ?>">
    <input type="hidden" name="signature_image" value="">
    <input type="hidden" name="signature_kind" value="">

    <h4 class="section-title"><i class="ti ti-writing"></i> <?= $atual !== null ? 'Trocar a assinatura' : 'Cadastrar a assinatura' ?></h4>

    <div class="termo-signature-area">
        <div class="termo-signature-tabs mb-2">
            <div class="btn-group btn-group-sm" role="group" aria-label="Modo de assinatura">
                <button type="button" class="btn btn-outline-primary active btn-sig-mode" data-mode="draw">
                    <i class="ti ti-pencil"></i> Desenhar assinatura
                </button>
                <button type="button" class="btn btn-outline-primary btn-sig-mode" data-mode="type">
                    <i class="ti ti-keyboard"></i> Digitar nome
                </button>
            </div>
        </div>

        <div class="sig-draw-container">
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

        <div class="sig-type-container" style="display: none;">
            <div class="signature-header">
                <label class="termo-label" for="sig-type-input"><i class="ti ti-signature"></i> Seu nome para a assinatura eletrônica</label>
            </div>
            <div class="row g-2 mb-2">
                <div class="col-sm-8">
                    <input type="text" id="sig-type-input" class="form-control" maxlength="120" placeholder="Seu nome completo" value="<?= $e($nome) ?>" autocomplete="name">
                </div>
                <div class="col-sm-4">
                    <select id="sig-type-font" class="form-select">
                        <option value="cursive1">Cursiva Elegante</option>
                        <option value="cursive2">Caligráfica Moderna</option>
                        <option value="formal">Formal / Serif</option>
                    </select>
                </div>
            </div>
            <div class="sig-type-preview-card">
                <div id="sig-type-preview-text" class="sig-font-cursive1"><?= $e($nome) ?></div>
                <div class="sig-type-metadata">
                    <i class="ti ti-info-circle"></i>
                    <span>O PDF registra que a sua assinatura foi feita com o nome digitado.</span>
                </div>
            </div>
        </div>
    </div>

    <div class="termo-actions-bar">
        <button type="button" id="btn-save-my-signature" class="btn btn-primary btn-lg">
            <i class="ti ti-device-floppy"></i> Salvar minha assinatura
        </button>
        <?php if ($atual !== null): ?>
        <button type="submit" name="remove" value="1" class="btn btn-outline-danger btn-lg"
                onclick="return confirm('Remover a sua assinatura? Os próximos termos saem sem a imagem, só com o seu nome e o registro do seu usuário.');">
            <i class="ti ti-trash"></i> Remover
        </button>
        <?php endif; ?>
    </div>
    <div id="termo-alert-box" class="alert d-none mt-3"></div>
<?php
Html::closeForm();
echo '</div>';

Html::footer();

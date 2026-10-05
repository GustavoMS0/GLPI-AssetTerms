<?php

/**
 * ------------------------------------------------------------------------
 * Asset Terms - Configuração (Configurar > Plugins > Asset Terms)
 *
 * Por empresa (entidade): nome, CNPJ, cidade e o texto dos termos.
 * Só para quem pode alterar a configuração do GLPI.
 * ------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    include __DIR__ . '/../../../inc/includes.php';
}

/** @var array $CFG_GLPI */
global $CFG_GLPI;

Session::checkRight('config', UPDATE);
PluginAssettermsTerm::normalizeInput();

$self = PluginAssettermsTerm::webPath() . '/front/config.php';
$e    = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

// Entidade escolhida (padrão: a raiz, se o usuário tiver acesso; senão, a entidade ativa)
$eid = (int) ($_REQUEST['entities_id'] ?? (Session::haveAccessToEntity(0) ? 0 : ($_SESSION['glpiactive_entity'] ?? 0)));
if (!Session::haveAccessToEntity($eid)) {
    $eid = (int) ($_SESSION['glpiactive_entity'] ?? 0);
}

// ----------------------------------------------------------------- Gravação
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (isset($_POST['restore'])) {
        PluginAssettermsConfig::delete($eid);
        Session::addMessageAfterRedirect('Configuração removida. Esta entidade passa a usar a da entidade acima ou o texto padrão.', true, INFO);
    } else {
        $data = PluginAssettermsConfig::collect($_POST);
        $logo = PluginAssettermsConfig::readLogo($_FILES['logo'] ?? null);
        if (is_string($data) || is_string($logo)) {
            Session::addMessageAfterRedirect(is_string($data) ? $data : $logo, false, ERROR);
        } else {
            PluginAssettermsConfig::save($eid, $data, $logo);
            if ($data['code_next'] !== null) {
                PluginAssettermsConfig::setNextNumber(PluginAssettermsConfig::getEffective($eid), $data['code_next'], time());
            }
            Session::addMessageAfterRedirect('Configuração salva. Os próximos termos já usam estes dados.', true, INFO);
        }
    }
    Html::redirect($self . '?entities_id=' . $eid);
    return;
}

$own       = PluginAssettermsConfig::getOwn($eid);
$effective = PluginAssettermsConfig::getEffective($eid);
$entity    = (string) Dropdown::getDropdownName('glpi_entities', $eid);

Html::header('Asset Terms', '', 'config', 'plugin');

echo '<div class="termo-container">';

// Escolha da empresa
echo '<form class="termo-card termo-picker" method="get" action="' . $e($self) . '">';
echo '<h3 class="termo-title"><i class="ti ti-settings"></i> Termos de Responsabilidade: configuração</h3>';
echo '<p class="termo-subtitle">Escolha a empresa (entidade). Matriz e filiais podem ter dados e textos próprios. '
    . 'Uma filial sem configuração própria usa a da entidade acima.</p>';
echo '<div class="termo-picker-row">';
Entity::dropdown([
    'name'      => 'entities_id',
    'value'     => $eid,
    'entity'    => $_SESSION['glpiactiveentities'] ?? 0,
    'on_change' => 'this.form.submit()',
    'width'     => '100%',
]);
echo '<button type="submit" class="btn btn-primary"><i class="ti ti-arrow-right"></i> Abrir</button>';
echo '</div>';

if ($own !== null) {
    echo '<div class="alert alert-info mt-3 mb-0">Configuração própria de <strong>' . $e($entity) . '</strong>.</div>';
} elseif ($effective['source'] !== null) {
    echo '<div class="alert alert-secondary mt-3 mb-0"><strong>' . $e($entity) . '</strong> usa a configuração de <strong>'
        . $e(Dropdown::getDropdownName('glpi_entities', (int) $effective['source'])) . '</strong>. Ao salvar, ela passa a ter uma própria.</div>';
} else {
    echo '<div class="alert alert-secondary mt-3 mb-0"><strong>' . $e($entity) . '</strong> usa o texto padrão do plugin. Ao salvar, ela passa a ter uma configuração própria.</div>';
}
echo '</form>';

// Formulário: empresa e textos (preenchido com o que vale hoje para a entidade)
$t = $effective['texts'];
?>
<form id="form-assetterms-config" class="termo-card" method="post" action="<?= $e($self) ?>" enctype="multipart/form-data"
      data-preview="<?= $e(PluginAssettermsTerm::webPath() . '/ajax/preview.php') ?>">
    <input type="hidden" name="entities_id" value="<?= $eid ?>">

    <h4 class="section-title"><i class="ti ti-building"></i> Empresa</h4>
    <div class="termo-config-grid">
        <label class="termo-equip-item">
            <span>Nome da empresa</span>
            <input type="text" class="form-control" name="company_name" maxlength="255" value="<?= $e($effective['company_name']) ?>"
                   placeholder="Ex.: Minha Empresa Ltda.">
            <small class="termo-hint">Vai no cabeçalho do termo e no lugar de <code>{empresa}</code>. Vazio = nome da entidade.</small>
        </label>
        <label class="termo-equip-item">
            <span>CNPJ</span>
            <input type="text" class="form-control" name="company_doc" maxlength="50" value="<?= $e($effective['company_doc']) ?>"
                   placeholder="00.000.000/0000-00">
            <small class="termo-hint">Opcional. Vai no cabeçalho e no lugar de <code>{cnpj}</code>.</small>
        </label>
        <label class="termo-equip-item">
            <span>Cidade</span>
            <input type="text" class="form-control" name="city" maxlength="255" value="<?= $e($effective['city']) ?>"
                   placeholder="Ex.: São Paulo">
            <small class="termo-hint">Usada em "Cidade, 5 de outubro de 2026." acima das assinaturas.</small>
        </label>
    </div>

    <?php
    $now     = time();
    $proximo = PluginAssettermsConfig::peekNumber($effective, $now);
    ?>
    <h4 class="section-title mt-4"><i class="ti ti-hash"></i> Código do documento</h4>
    <div class="termo-config-grid">
        <label class="termo-equip-item">
            <span>Formato</span>
            <input type="text" class="form-control" name="code_format" maxlength="100" value="<?= $e($effective['code_format']) ?>"
                   placeholder="{prefixo}-{ano}-{seq}">
            <small class="termo-hint">Ex.: <code>{prefixo}-{ano}-{seq}</code> gera TR-<?= date('Y', $now) ?>-000124. Precisa ter <code>{seq}</code> ou <code>{aleatorio}</code>.</small>
        </label>
        <label class="termo-equip-item">
            <span>Prefixo</span>
            <input type="text" class="form-control" name="code_prefix" maxlength="20" value="<?= $e($effective['code_prefix']) ?>" placeholder="Ex.: TR">
            <small class="termo-hint">Usado no lugar de <code>{prefixo}</code>.</small>
        </label>
        <label class="termo-equip-item">
            <span>Dígitos do número</span>
            <input type="number" class="form-control" name="code_digits" min="1" max="10" value="<?= (int) $effective['code_digits'] ?>">
            <small class="termo-hint">Com 6, o número 124 sai como 000124.</small>
        </label>
        <label class="termo-equip-item">
            <span>Próximo número</span>
            <input type="number" class="form-control" name="code_next" min="1" placeholder="Hoje: <?= $proximo ?>">
            <small class="termo-hint">Deixe vazio para seguir a numeração (próximo: <strong><?= $proximo ?></strong>). Preencha para começar ou continuar de outro número.</small>
        </label>
    </div>
    <label class="termo-aceite termo-config-check mt-2">
        <input type="checkbox" name="code_yearly" value="1" <?= $effective['code_yearly'] ? 'checked' : '' ?>>
        Recomeçar a numeração do 1 a cada ano
    </label>
    <small class="termo-hint d-block">
        Ao ligar ou desligar esta opção, a numeração passa a usar outro contador. Confira o <strong>Próximo número</strong> para não repetir códigos já usados.
    </small>
    <div class="termo-config-help">
        <strong>Marcadores do código:</strong>
        <?php foreach (PluginAssettermsConfig::CODE_TAGS as $tag => $label): ?>
            <br><code><?= $e($tag) ?></code> = <?= $e($label) ?>
        <?php endforeach; ?>
        <br><strong>Exemplo com a configuração salva:</strong> <code id="termo-code-example"><?= $e(PluginAssettermsConfig::buildCode($effective, 'entrega', $now, false)) ?></code>
        <br>O código vai no PDF, no comentário do documento e no histórico do computador.
        <?php if ($effective['source'] !== null && $effective['source'] !== $eid): ?>
            <br>A numeração é a de <strong><?= $e(Dropdown::getDropdownName('glpi_entities', (int) $effective['source'])) ?></strong>. Ao salvar uma configuração própria, esta entidade passa a ter numeração própria.
        <?php endif; ?>
    </div>

    <h4 class="section-title mt-4"><i class="ti ti-palette"></i> Aparência do documento</h4>
    <div class="termo-config-grid">
        <div class="termo-equip-item">
            <span>Logo</span>
            <?php if ($effective['logo'] !== ''): ?>
                <img class="termo-logo-preview" alt="Logo atual"
                     src="data:<?= $e($effective['logo_mime']) ?>;base64,<?= base64_encode($effective['logo']) ?>">
                <label class="termo-hint"><input type="checkbox" name="remove_logo" value="1"> Remover o logo</label>
            <?php endif; ?>
            <input type="file" class="form-control" name="logo" accept="image/png,image/jpeg">
            <small class="termo-hint">PNG ou JPG de até 1 MB. Fica no topo do PDF, à esquerda, com até 5 x 1,6 cm.</small>
        </div>
        <label class="termo-equip-item">
            <span>Cor principal</span>
            <input type="color" class="form-control form-control-color" name="color" value="<?= $e($effective['color']) ?>">
            <small class="termo-hint">Cor do título e das seções do PDF.</small>
        </label>
        <label class="termo-equip-item termo-config-wide">
            <span>Rodapé</span>
            <input type="text" class="form-control" name="footer" maxlength="300" value="<?= $e($effective['footer']) ?>"
                   placeholder="Ex.: Rua Exemplo, 123 - Centro - São Paulo/SP - (11) 0000-0000 - www.empresa.com.br">
            <small class="termo-hint">Aparece no pé de todas as páginas, ao lado do número da página.</small>
        </label>
    </div>

    <?php $dc = $effective['doc_control']; ?>
    <h4 class="section-title mt-4"><i class="ti ti-layout-navbar"></i> Cabeçalho e rodapé</h4>
    <div class="termo-config-grid">
        <label class="termo-equip-item termo-config-wide">
            <span>Modelo do cabeçalho</span>
            <select name="dc_layout" id="dc_layout" class="form-select">
                <option value="simples" <?= $dc['layout'] === 'simples' ? 'selected' : '' ?>>Simples: logo, título e nome da empresa na primeira página</option>
                <option value="controle" <?= $dc['layout'] === 'controle' ? 'selected' : '' ?>>Controle de documentos (ISO): tabela com tipo, código, título, revisão e datas em todas as páginas</option>
            </select>
            <small class="termo-hint">
                No modelo de controle, o cabeçalho repete em todas as páginas, com o logo à esquerda e "Página X de Y",
                e o rodapé mostra quem elaborou e quem aprovou o formulário.
            </small>
        </label>
    </div>
    <div class="termo-config-grid mt-2" data-dc="controle" <?= $dc['layout'] === 'controle' ? '' : 'hidden' ?>>
        <?php foreach (PluginAssettermsConfig::DOC_CONTROL_FIELDS as $field => [$label, $placeholder, $max]): ?>
            <label class="termo-equip-item <?= $field === 'titulo' ? 'termo-config-wide' : '' ?>">
                <span><?= $e($label) ?></span>
                <input type="text" class="form-control" name="dc_<?= $field ?>" maxlength="<?= (int) $max ?>"
                       value="<?= $e($dc[$field]) ?>" placeholder="<?= $e($placeholder) ?>">
            </label>
        <?php endforeach; ?>
    </div>

    <div class="termo-config-help">
        <strong>Marcadores:</strong>
        <?php foreach (PluginAssettermsConfig::PLACEHOLDERS as $tag => $label): ?>
            <code><?= $e($tag) ?></code> = <?= $e($label) ?>&nbsp;&nbsp;
        <?php endforeach; ?>
        <br>Escreva o texto como deve aparecer no termo. Os marcadores são trocados pelos dados da empresa.
    </div>

    <?php foreach (['entrega' => 'Termo de entrega', 'devolucao' => 'Termo de devolução'] as $tipo => $label): ?>
    <h4 class="section-title mt-4"><i class="ti ti-file-text"></i> <?= $e($label) ?></h4>
    <div class="termo-config-text">
        <label class="termo-label" for="<?= $tipo ?>_titulo">Título</label>
        <input type="text" class="form-control" id="<?= $tipo ?>_titulo" name="<?= $tipo ?>_titulo" maxlength="255" value="<?= $e($t[$tipo]['titulo']) ?>">

        <label class="termo-label mt-3" for="<?= $tipo ?>_declaracao">Declaração</label>
        <textarea class="form-control" id="<?= $tipo ?>_declaracao" name="<?= $tipo ?>_declaracao" rows="4" maxlength="4000"><?= $e($t[$tipo]['declaracao']) ?></textarea>

        <label class="termo-label mt-3" for="<?= $tipo ?>_compromissos">
            <?= $tipo === 'entrega' ? 'Compromissos (numerados I, II, III...)' : 'Itens da conferência' ?>: um por linha
        </label>
        <textarea class="form-control" id="<?= $tipo ?>_compromissos" name="<?= $tipo ?>_compromissos" rows="6"><?= $e(implode("\n", $t[$tipo]['compromissos'])) ?></textarea>

        <label class="termo-label mt-3" for="<?= $tipo ?>_ciencia">Parágrafo final</label>
        <textarea class="form-control" id="<?= $tipo ?>_ciencia" name="<?= $tipo ?>_ciencia" rows="4" maxlength="4000"><?= $e($t[$tipo]['ciencia']) ?></textarea>

        <button type="button" class="btn btn-sm btn-outline-secondary mt-2 termo-config-preview" data-tipo="<?= $tipo ?>">
            <i class="ti ti-eye"></i> Ver PDF de exemplo
        </button>
    </div>
    <?php endforeach; ?>

    <div class="termo-actions-bar">
        <button type="submit" name="save" value="1" class="btn btn-primary btn-lg">
            <i class="ti ti-device-floppy"></i> Salvar para <?= $e($entity) ?>
        </button>
        <?php if ($own !== null): ?>
        <button type="submit" name="restore" value="1" class="btn btn-outline-danger btn-lg"
                onclick="return confirm('Apagar a configuração própria desta entidade? Ela volta a usar a da entidade acima ou o texto padrão.');">
            <i class="ti ti-restore"></i> Restaurar padrão
        </button>
        <?php endif; ?>
        <button type="button" class="btn btn-outline-secondary btn-lg termo-config-default">
            <i class="ti ti-text-recognition"></i> Preencher com o texto padrão
        </button>
    </div>
    <small class="termo-hint d-block mt-2">
        A mudança vale para os próximos termos. Os termos já assinados não mudam, e os que aguardam assinatura mantêm o texto com que foram enviados.
    </small>
    <script type="application/json" id="assetterms-default-texts"><?= json_encode(PluginAssettermsConfig::defaultTexts(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php
Html::closeForm();
echo '</div>';

Html::footer();

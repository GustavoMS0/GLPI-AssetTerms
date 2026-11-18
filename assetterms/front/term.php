<?php

/**
 * ------------------------------------------------------------------------
 * Asset Terms - Ativos > Termos de Responsabilidade
 *
 * O técnico escolhe o equipamento e já preenche o termo, sem abrir a ficha
 * do ativo. Abaixo, todos os termos que aguardam assinatura e os arquivados.
 * ------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    include __DIR__ . '/../../../inc/includes.php';
}

if (!PluginAssettermsTerm::canViewAnyAsset()) {
    Html::displayRightError();
    return;
}

PluginAssettermsTerm::ensureSchema();

$e        = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$viewable = PluginAssettermsTerm::viewableAssetTypes();
$firstKey = (string) key($viewable);

$rawType       = is_string($_GET['itemtype'] ?? null) ? trim($_GET['itemtype']) : '';
$selectedType  = $rawType !== ''
    ? PluginAssettermsTerm::normalizeItemtype($rawType)
    : (isset($viewable['Computer']) ? 'Computer' : $firstKey);
$canViewType   = isset($viewable[$selectedType]);

$id    = (int) ($_GET['id'] ?? 0);
$busca = PluginAssettermsTerm::archivedSearchQuery();
$asset = $canViewType ? PluginAssettermsTerm::loadAsset($selectedType, $id) : null;
$found = $asset !== null
    && $asset->canViewItem()
    && !$asset->isTemplate()
    && empty($asset->fields['is_deleted']);

Html::header(PluginAssettermsMenu::getTypeName(), '', 'assets', 'PluginAssettermsMenu');

echo '<div class="termo-container">';
echo '<form class="termo-card termo-picker" method="get" action="' . $e(PluginAssettermsTerm::webPath() . '/front/term.php') . '">';
if ($busca !== '') {
    echo '<input type="hidden" name="busca" value="' . $e($busca) . '">';
}
echo '<h3 class="termo-title"><i class="ti ti-file-certificate"></i> Termos de Responsabilidade';
if (PluginAssettermsTerm::canUpdateAnyAsset()) {
    echo ' <a class="btn btn-sm btn-outline-secondary ms-2" href="' . $e(PluginAssettermsSignature::pageUrl())
        . '"><i class="ti ti-signature"></i> Minha assinatura</a>';
}
if (Session::haveRight('config', UPDATE)) {
    echo ' <a class="btn btn-sm btn-outline-secondary ms-2" href="' . $e(PluginAssettermsTerm::webPath() . '/front/config.php')
        . '"><i class="ti ti-settings"></i> Configurar empresa e texto</a>';
}
echo '</h3>';
echo '<p class="termo-subtitle">Escolha o tipo e o equipamento para fazer a entrega, a devolução ou mudar o status.</p>';
echo '<div class="termo-picker-row">';
echo '<select name="itemtype" class="form-select termo-type-select" aria-label="Tipo de equipamento" '
    . 'onchange="if (this.form.elements.id) this.form.elements.id.value=\'\'; this.form.submit();">';
foreach ($viewable as $type => $meta) {
    $selected = $type === $selectedType ? ' selected' : '';
    echo '<option value="' . $e($type) . '"' . $selected . '>' . $e($meta['label']) . '</option>';
}
echo '</select>';
echo '<div class="termo-picker-asset">';
if ($canViewType) {
    $selectedType::dropdown([
        'name'      => 'id',
        'value'     => $found ? $id : 0,
        'entity'    => $_SESSION['glpiactiveentities'] ?? 0,
        'on_change' => 'this.form.submit()',
        'width'     => '100%',
    ]);
}
echo '</div>';
echo '<button type="submit" class="btn btn-primary"><i class="ti ti-arrow-right"></i> Abrir</button>';
echo '</div>';
if ($id > 0 && !$found) {
    echo '<div class="alert alert-warning mt-3 mb-0">Equipamento não encontrado ou sem permissão para vê-lo.</div>';
}
echo '</form>';
echo '</div>';

if ($found) {
    echo '<div class="termo-container termo-picker-link"><a class="btn btn-sm btn-outline-secondary" href="'
        . $e($asset->getLinkURL()) . '"><i class="ti ti-external-link"></i> Abrir a ficha do equipamento</a></div>';
    PluginAssettermsTerm::showTermoForm($asset, false);
}

echo '<div class="termo-container">';
PluginAssettermsTerm::showPending(PluginAssettermsTerm::getAllPendingRequests(), true);
$archived_has_more = false;
$archived          = PluginAssettermsTerm::getArchivedDocuments($busca, 100, $archived_has_more);
PluginAssettermsTerm::showArchived($archived, $busca, $found ? $id : 0, $archived_has_more, $selectedType);
echo '</div>';

Html::footer();

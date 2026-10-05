<?php

/**
 * ------------------------------------------------------------------------
 * Asset Terms - Ativos > Termos de Responsabilidade
 *
 * O técnico escolhe o computador e já preenche o termo, sem abrir a ficha
 * do equipamento. Abaixo, todos os termos que aguardam assinatura.
 * ------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    include __DIR__ . '/../../../inc/includes.php';
}

Session::checkRight('computer', READ);

$id       = (int) ($_GET['id'] ?? 0);
$computer = new Computer();
$found    = $id > 0 && $computer->getFromDB($id) && $computer->canViewItem() && !$computer->isTemplate();

Html::header(PluginAssettermsMenu::getTypeName(), '', 'assets', 'PluginAssettermsMenu');

echo '<div class="termo-container">';
echo '<form class="termo-card termo-picker" method="get" action="' . htmlspecialchars(PluginAssettermsTerm::webPath() . '/front/term.php', ENT_QUOTES) . '">';
echo '<h3 class="termo-title"><i class="ti ti-file-certificate"></i> Termos de Responsabilidade';
if (Session::haveRight('config', UPDATE)) {
    echo ' <a class="btn btn-sm btn-outline-secondary ms-2" href="' . htmlspecialchars(PluginAssettermsTerm::webPath() . '/front/config.php', ENT_QUOTES)
        . '"><i class="ti ti-settings"></i> Configurar empresa e texto</a>';
}
echo '</h3>';
echo '<p class="termo-subtitle">Escolha o computador para fazer a entrega, a devolução ou mudar o status.</p>';
echo '<div class="termo-picker-row">';
Computer::dropdown([
    'name'      => 'id',
    'value'     => $found ? $id : 0,
    'entity'    => $_SESSION['glpiactiveentities'] ?? 0,
    'on_change' => 'this.form.submit()',
    'width'     => '100%',
]);
echo '<button type="submit" class="btn btn-primary"><i class="ti ti-arrow-right"></i> Abrir</button>';
echo '</div>';
if ($id > 0 && !$found) {
    echo '<div class="alert alert-warning mt-3 mb-0">Computador não encontrado ou sem permissão para vê-lo.</div>';
}
echo '</form>';
echo '</div>';

if ($found) {
    echo '<div class="termo-container termo-picker-link"><a class="btn btn-sm btn-outline-secondary" href="'
        . htmlspecialchars($computer->getLinkURL(), ENT_QUOTES) . '"><i class="ti ti-external-link"></i> Abrir a ficha do computador</a></div>';
    PluginAssettermsTerm::showTermoForm($computer, false);
}

echo '<div class="termo-container">';
PluginAssettermsTerm::showPending(PluginAssettermsTerm::getAllPendingRequests(), true);
echo '</div>';

Html::footer();

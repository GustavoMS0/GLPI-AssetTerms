<?php

/**
 * ------------------------------------------------------------------------
 * Asset Terms - Instalação e desinstalação
 *
 * O GLPI (10 e 11) carrega este arquivo junto com o setup.php ao instalar,
 * atualizar ou desinstalar o plugin.
 *
 * Copyright (C) 2026 by G. Martins
 * Licença GPLv3+ (ver setup.php e LICENSE)
 * ------------------------------------------------------------------------
 */

/**
 * Cria as tabelas dos termos enviados para assinatura, das assinaturas cadastradas pela TI e da configuração
 * por empresa. Os PDFs ficam na aba Documentos do GLPI
 * e continuam lá mesmo se o plugin for removido.
 */
function plugin_assetterms_install()
{
    include_once __DIR__ . '/inc/term.class.php';
    include_once __DIR__ . '/inc/config.class.php';
    include_once __DIR__ . '/inc/signature.class.php';
    PluginAssettermsTerm::installSchema();
    PluginAssettermsSignature::installSchema();
    PluginAssettermsConfig::installSchema();
    return true;
}

/**
 * Remove as tabelas do plugin, menos a dos contadores do código sequencial ({seq}):
 * reinstalando, a numeração continua e nenhum código já emitido se repete.
 */
function plugin_assetterms_uninstall()
{
    include_once __DIR__ . '/inc/term.class.php';
    include_once __DIR__ . '/inc/config.class.php';
    include_once __DIR__ . '/inc/signature.class.php';
    PluginAssettermsTerm::uninstallSchema();
    PluginAssettermsSignature::uninstallSchema();
    PluginAssettermsConfig::uninstallSchema();
    return true;
}

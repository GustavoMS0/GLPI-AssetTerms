<?php

/**
 * ------------------------------------------------------------------------
 * Asset Terms - Termos de Responsabilidade para o GLPI
 *
 * Termos de entrega e devolução de equipamentos em PDF, com checklist de
 * acessórios, assinatura na tela (toque ou mouse), arquivamento na aba
 * Documentos e registro no histórico do ativo. O termo também pode ser enviado
 * por e-mail: o colaborador entra no GLPI pelo link e assina.
 *
 * Copyright (C) 2026 by G. Martins
 * ------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Asset Terms.
 *
 * Asset Terms is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Asset Terms is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 * ------------------------------------------------------------------------
 */

define('PLUGIN_ASSETTERMS_VERSION', '1.3.0');
define('PLUGIN_ASSETTERMS_MIN_GLPI', '10.0.0');
define('PLUGIN_ASSETTERMS_MAX_GLPI', '11.0.99');

/**
 * Inicialização: registra a aba nos computadores e carrega CSS e JS na tela do computador
 * e na página de assinatura.
 */
function plugin_init_assetterms()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['assetterms'] = true;

    Plugin::registerClass('PluginAssettermsTerm', [
        'addtabon' => ['Computer'],
    ]);

    // GLPI 11: a página do link do e-mail faz a própria checagem de login (front/sign.php),
    // para levar quem não está logado direto à tela de login e voltar ao termo depois
    if (class_exists(\Glpi\Http\Firewall::class) && method_exists(\Glpi\Http\Firewall::class, 'addPluginStrategyForLegacyScripts')) {
        \Glpi\Http\Firewall::addPluginStrategyForLegacyScripts('assetterms', '#^/front/sign\.php$#', \Glpi\Http\Firewall::STRATEGY_NO_CHECK);
    }

    // Configurar > Plugins > Asset Terms: empresa e texto dos termos
    $PLUGIN_HOOKS['config_page']['assetterms'] = 'front/config.php';

    // Ativos > Termos de Responsabilidade (escolha do computador e termos pendentes)
    $PLUGIN_HOOKS['menu_toadd']['assetterms'] = ['assets' => 'PluginAssettermsMenu'];

    // GLPI 11 serve os arquivos estáticos a partir de public/; o GLPI 10, da raiz do plugin
    $uri   = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $pages = ['/front/computer.form.php', '/assetterms/front/sign.php', '/assetterms/front/term.php', '/assetterms/front/config.php'];
    if (array_filter($pages, static fn ($p) => str_ends_with($uri, $p))) {
        $prefix = version_compare(GLPI_VERSION, '11.0.0-dev', '>=') ? '' : 'public/';
        $PLUGIN_HOOKS['add_javascript']['assetterms'] = [$prefix . 'js/assetterms.js'];
        $PLUGIN_HOOKS['add_css']['assetterms']        = [$prefix . 'css/assetterms.css'];
    }
}

/**
 * Informações exibidas em Configurar > Plugins.
 */
function plugin_version_assetterms()
{
    return [
        'name'         => 'Asset Terms',
        'version'      => PLUGIN_ASSETTERMS_VERSION,
        'author'       => 'G. Martins',
        'license'      => 'GPLv3+',
        'homepage'     => 'https://github.com/GustavoMS0/GLPI-AssetTerms',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_ASSETTERMS_MIN_GLPI,
                'max' => PLUGIN_ASSETTERMS_MAX_GLPI,
            ],
        ],
    ];
}

function plugin_assetterms_check_prerequisites()
{
    return true;
}

function plugin_assetterms_check_config($verbose = false)
{
    return true;
}

/**
 * Cria as tabelas dos termos enviados para assinatura e da configuração por empresa. Os PDFs ficam na aba Documentos do GLPI
 * e continuam lá mesmo se o plugin for removido.
 */
function plugin_assetterms_install()
{
    include_once __DIR__ . '/inc/term.class.php';
    include_once __DIR__ . '/inc/config.class.php';
    PluginAssettermsTerm::installSchema();
    PluginAssettermsConfig::installSchema();
    return true;
}

function plugin_assetterms_uninstall()
{
    include_once __DIR__ . '/inc/term.class.php';
    include_once __DIR__ . '/inc/config.class.php';
    PluginAssettermsTerm::uninstallSchema();
    PluginAssettermsConfig::uninstallSchema();
    return true;
}

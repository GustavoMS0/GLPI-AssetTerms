<?php

/**
 * ------------------------------------------------------------------------
 * Asset Terms - Entrada no menu Ativos (Ativos > Termos de Responsabilidade)
 * ------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Acesso direto não permitido.");
}

class PluginAssettermsMenu extends CommonGLPI
{
    public static $rightname = 'computer';

    public static function getTypeName($nb = 0)
    {
        return 'Termos de Responsabilidade';
    }

    public static function getMenuName()
    {
        return self::getTypeName();
    }

    public static function getIcon()
    {
        return 'ti ti-file-certificate';
    }

    /** Caminho da página, relativo à raiz do GLPI */
    public static function pagePath(): string
    {
        if (version_compare(GLPI_VERSION, '11.0.0-dev', '>=')) {
            return '/plugins/assetterms/front/term.php';
        }
        return '/' . ltrim((string) Plugin::getWebDir('assetterms', false), '/') . '/front/term.php';
    }

    public static function getMenuContent()
    {
        if (!Computer::canView()) {
            return false;
        }
        return [
            'title' => self::getMenuName(),
            'page'  => self::pagePath(),
            'icon'  => self::getIcon(),
        ];
    }
}

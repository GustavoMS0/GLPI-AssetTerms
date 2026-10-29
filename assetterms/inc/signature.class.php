<?php

/**
 * ------------------------------------------------------------------------
 * Asset Terms - Assinatura cadastrada de quem emite os termos (TI)
 *
 * Cada técnico cadastra a própria assinatura uma vez (desenhada ou com o nome
 * digitado). Ela vai no lado "Entregue por / Recebido por (TI)" de todo termo
 * que ele gerar. Sem assinatura cadastrada, o PDF registra quem emitiu e o login.
 * ------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Acesso direto não permitido.");
}

class PluginAssettermsSignature extends CommonGLPI
{
    public static $rightname = 'computer';

    public const TABLE = 'glpi_plugin_assetterms_signatures';

    public static function getTypeName($nb = 0)
    {
        return 'Minha assinatura';
    }

    public static function installSchema(): void
    {
        global $DB;
        if ($DB->tableExists(self::TABLE)) {
            return;
        }
        $DB->doQuery("CREATE TABLE `" . self::TABLE . "` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `users_id` int unsigned NOT NULL DEFAULT '0',
            `image` mediumtext COMMENT 'PNG da assinatura em base64',
            `kind` varchar(20) NOT NULL DEFAULT 'desenhada' COMMENT 'desenhada | digitada',
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `users_id` (`users_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC");
    }

    public static function uninstallSchema(): void
    {
        global $DB;
        if ($DB->tableExists(self::TABLE)) {
            $DB->doQuery('DROP TABLE `' . self::TABLE . '`');
        }
    }

    /**
     * Assinatura cadastrada pelo usuário ou null.
     *
     * @return array{image: string, kind: string, date_mod: string}|null image = PNG em base64
     */
    public static function get(int $users_id): ?array
    {
        global $DB;
        if ($users_id <= 0 || !$DB->tableExists(self::TABLE)) {
            return null;
        }
        foreach ($DB->request(['FROM' => self::TABLE, 'WHERE' => ['users_id' => $users_id]]) as $row) {
            if ((string) $row['image'] === '') {
                return null;
            }
            return [
                'image'    => (string) $row['image'],
                'kind'     => $row['kind'] === 'digitada' ? 'digitada' : 'desenhada',
                'date_mod' => (string) $row['date_mod'],
            ];
        }
        return null;
    }

    /** Grava (ou troca) a assinatura do usuário. $png é o PNG binário já conferido por decodeSignature() */
    public static function save(int $users_id, string $png, string $kind): bool
    {
        global $DB;
        self::installSchema();
        return (bool) $DB->updateOrInsert(self::TABLE, PluginAssettermsTerm::dbValues([
            'users_id' => $users_id,
            'image'    => base64_encode($png),
            'kind'     => $kind === 'digitada' ? 'digitada' : 'desenhada',
            'date_mod' => date('Y-m-d H:i:s'),
        ]), ['users_id' => $users_id]);
    }

    public static function delete(int $users_id): void
    {
        global $DB;
        if ($DB->tableExists(self::TABLE)) {
            $DB->delete(self::TABLE, ['users_id' => $users_id]);
        }
    }

    public static function pageUrl(): string
    {
        return PluginAssettermsTerm::webPath() . '/front/signature.php';
    }
}

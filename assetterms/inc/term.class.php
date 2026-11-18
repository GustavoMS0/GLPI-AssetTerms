<?php

/**
 * ------------------------------------------------------------------------
 * Asset Terms - Aba do equipamento, texto do termo e PDF
 * ------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Acesso direto não permitido.");
}

interface PluginAssettermsTermConstants
{
    /**
     * Tipos de ativos suportados pelo plugin (chave = classe CommonDBTM do GLPI).
     */
    public const ASSET_TYPES = [
        'Computer' => [
            'label'        => 'Computador',
            'rightname'    => 'computer',
            'table'        => 'glpi_computers',
            'model_field'  => 'computermodels_id',
            'model_table'  => 'glpi_computermodels',
            'type_field'   => 'computertypes_id',
            'type_table'   => 'glpi_computertypes',
            'brand_field'  => '',
            'state_col'    => 'is_visible_computer',
            'form_page'    => '/front/computer.form.php',
            'has_hw_specs' => true,
            'has_os_spec'  => true,
            'icon'         => 'ti ti-device-laptop',
        ],
        'Phone' => [
            'label'        => 'Telefone / Smartphone',
            'rightname'    => 'phone',
            'table'        => 'glpi_phones',
            'model_field'  => 'phonemodels_id',
            'model_table'  => 'glpi_phonemodels',
            'type_field'   => 'phonetypes_id',
            'type_table'   => 'glpi_phonetypes',
            'brand_field'  => 'brand',
            'state_col'    => 'is_visible_phone',
            'form_page'    => '/front/phone.form.php',
            'has_hw_specs' => false,
            'has_os_spec'  => true,
            'icon'         => 'ti ti-device-mobile',
        ],
        'Monitor' => [
            'label'        => 'Monitor',
            'rightname'    => 'monitor',
            'table'        => 'glpi_monitors',
            'model_field'  => 'monitormodels_id',
            'model_table'  => 'glpi_monitormodels',
            'type_field'   => 'monitortypes_id',
            'type_table'   => 'glpi_monitortypes',
            'brand_field'  => '',
            'state_col'    => 'is_visible_monitor',
            'form_page'    => '/front/monitor.form.php',
            'has_hw_specs' => false,
            'has_os_spec'  => false,
            'icon'         => 'ti ti-device-desktop',
        ],
        'Peripheral' => [
            'label'        => 'Periférico / Dispositivo',
            'rightname'    => 'peripheral',
            'table'        => 'glpi_peripherals',
            'model_field'  => 'peripheralmodels_id',
            'model_table'  => 'glpi_peripheralmodels',
            'type_field'   => 'peripheraltypes_id',
            'type_table'   => 'glpi_peripheraltypes',
            'brand_field'  => 'brand',
            'state_col'    => 'is_visible_peripheral',
            'form_page'    => '/front/peripheral.form.php',
            'has_hw_specs' => false,
            'has_os_spec'  => false,
            'icon'         => 'ti ti-devices-pc',
        ],
    ];

    /** Acessórios do checklist: chave enviada pelo formulário => texto do termo */
    public const CHECKLIST = [
        'fonte'    => 'Fonte / carregador original',
        'cabo'     => 'Cabo de força',
        'hdmi'     => 'Cabo de vídeo (HDMI / DisplayPort)',
        'mochila'  => 'Mochila ou case de transporte',
        'capa'     => 'Capa protetora / película',
        'chip'     => 'Chip SIM corporativo',
        'mouse'    => 'Mouse',
        'teclado'  => 'Teclado',
        'hub'      => 'Adaptador de vídeo / hub USB-C',
        'headset'  => 'Headset / fone de ouvido',
        'trava'    => 'Trava de segurança',
    ];

    /** Acessórios marcados por padrão na entrega de computadores */
    public const CHECKLIST_DEFAULT = ['fonte', 'cabo'];

    /** Pedidos de assinatura enviados por e-mail */
    public const TABLE = 'glpi_plugin_assetterms_requests';
    public const PENDING  = 0;
    public const SIGNED   = 1;
    public const CANCELED = 2;

    /**
     * Dimensões máximas (pixels) da imagem da assinatura. O quadro tem até ~1050 x 180 px na tela,
     * multiplicados pela densidade do monitor (devicePixelRatio, até 3 em celulares e telas 4K).
     */
    public const SIGNATURE_MAX_W = 4000;
    public const SIGNATURE_MAX_H = 1500;

    /** Documentos gerados pelo plugin (histórico de termos da aba) */
    public const DOCS_TABLE = 'glpi_plugin_assetterms_documents';

    /** Campos do equipamento que o técnico pode conferir ou completar no termo */
    public const EQUIP_FIELDS = [
        'fabricante' => 'Fabricante',
        'modelo'     => 'Modelo',
        'tipo'       => 'Tipo',
        'cpu'        => 'Processador',
        'ram'        => 'Memória',
        'disk'       => 'Disco',
        'so'         => 'Sistema operacional',
    ];
}

trait PluginAssettermsTermBody
{
    private static bool $schemaChecked = false;

    public static function resetSchemaCheck(): void
    {
        self::$schemaChecked = false;
    }

    /** Normaliza o itemtype para um dos tipos suportados (lista branca estrita; padrão: Computer) */
    public static function normalizeItemtype(?string $raw): string
    {
        $raw = trim((string) $raw);
        return isset(self::ASSET_TYPES[$raw]) ? $raw : 'Computer';
    }

    /** Verifica se o item pertence a um dos tipos de ativos suportados */
    public static function isSupportedItem($item): bool
    {
        return $item instanceof CommonDBTM && isset(self::ASSET_TYPES[$item->getType()]);
    }

    /** Carrega o ativo pelo tipo e ID, ou retorna null se inválido/inexistente */
    public static function loadAsset(string $itemtype, int $id): ?CommonDBTM
    {
        $itemtype = self::normalizeItemtype($itemtype);
        if ($id <= 0 || !class_exists($itemtype)) {
            return null;
        }
        $asset = new $itemtype();
        if (!($asset instanceof CommonDBTM) || !$asset->getFromDB($id)) {
            return null;
        }
        return $asset;
    }

    /** Usuário logado pode visualizar ao menos um dos tipos de ativos suportados? */
    public static function canViewAnyAsset(): bool
    {
        foreach (array_keys(self::ASSET_TYPES) as $class) {
            if (class_exists($class) && $class::canView()) {
                return true;
            }
        }
        return false;
    }

    /** Usuário logado pode alterar ao menos um dos tipos de ativos suportados? */
    public static function canUpdateAnyAsset(): bool
    {
        foreach (array_keys(self::ASSET_TYPES) as $class) {
            if (class_exists($class) && $class::canUpdate()) {
                return true;
            }
        }
        return false;
    }

    /** Lista de tipos de ativos suportados que o usuário logado pode visualizar (itemtype => metadados) */
    public static function viewableAssetTypes(): array
    {
        $out = [];
        foreach (self::ASSET_TYPES as $class => $meta) {
            if (class_exists($class) && $class::canView()) {
                $out[$class] = $meta;
            }
        }
        return $out;
    }

    /** URL da ficha do ativo no GLPI */
    public static function assetFormUrl(string $itemtype, int $id, bool $absolute = false): string
    {
        global $CFG_GLPI;
        $itemtype = self::normalizeItemtype($itemtype);
        $base = rtrim((string) ($absolute ? ($CFG_GLPI['url_base'] ?? '') : ($CFG_GLPI['root_doc'] ?? '')), '/');
        return $base . self::ASSET_TYPES[$itemtype]['form_page'] . '?id=' . $id;
    }

    /** Rótulo amigável da categoria do ativo */
    public static function assetLabel(string $itemtype): string
    {
        $itemtype = self::normalizeItemtype($itemtype);
        return self::ASSET_TYPES[$itemtype]['label'];
    }

    /** Acessórios marcados por padrão na entrega para cada tipo de ativo */
    public static function checklistDefaultsFor(string $itemtype): array
    {
        $itemtype = self::normalizeItemtype($itemtype);
        $defaults = [
            'Computer'   => ['fonte', 'cabo'],
            'Phone'      => ['fonte'],
            'Monitor'    => ['cabo'],
            'Peripheral' => [],
        ];
        return $defaults[$itemtype] ?? [];
    }

    /**
     * Garante de forma idempotente (uma vez por requisição) que as tabelas do plugin e a coluna
     * itemtype existem mesmo se os arquivos forem atualizados sem rodar plugin:install.
     */
    public static function ensureSchema(): void
    {
        global $DB;
        if (self::$schemaChecked) {
            return;
        }
        self::$schemaChecked = true;

        if (!$DB->tableExists(self::TABLE) || !$DB->tableExists(self::DOCS_TABLE)) {
            self::installSchema();
        }
        if (
            ($DB->tableExists(self::TABLE) && !$DB->fieldExists(self::TABLE, 'itemtype'))
            || ($DB->tableExists(self::DOCS_TABLE) && !$DB->fieldExists(self::DOCS_TABLE, 'itemtype'))
        ) {
            if (!class_exists('PluginAssettermsConfig')) {
                include_once __DIR__ . '/config.class.php';
            }
            PluginAssettermsConfig::migrate();
        }
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (self::isSupportedItem($item) && !$withtemplate && $item::canView()) {
            return __('Termo de Responsabilidade', 'assetterms');
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if (self::isSupportedItem($item) && $item::canView()) {
            self::showTermoForm($item);
        }
        return true;
    }

    // --------------------------------------------------------------- Dados

    /**
     * Texto lido do núcleo. O GLPI 10 guarda < > & como entidades HTML; aqui o texto volta ao
     * original, para o PDF e a tela escaparem uma vez só e coreInput() não codificar de novo.
     */
    public static function fromCore(string $value): string
    {
        if (version_compare(GLPI_VERSION, '11.0.0-dev', '<') && class_exists(\Glpi\Toolbox\Sanitizer::class)) {
            return (string) \Glpi\Toolbox\Sanitizer::unsanitize($value);
        }
        return $value;
    }

    /** Nome de exibição do usuário (Nome Sobrenome), ou o login */
    public static function userName(int $users_id): string
    {
        $user = new User();
        if ($users_id <= 0 || !$user->getFromDB($users_id)) {
            return '';
        }
        $name = trim(($user->fields['firstname'] ?? '') . ' ' . ($user->fields['realname'] ?? ''));
        return self::fromCore($name !== '' ? $name : (string) $user->fields['name']);
    }

    /**
     * Quem está com o equipamento: o usuário do GLPI ou, sem usuário, o nome informado
     * no campo "Usuário alternativo" do equipamento.
     */
    public static function holderName(CommonDBTM $item): string
    {
        $user = self::userName((int) ($item->fields['users_id'] ?? 0));
        if ($user !== '') {
            return $user;
        }
        $contact = trim(self::fromCore((string) ($item->fields['contact'] ?? '')));
        return $contact !== '' ? $contact . ' (sem usuário no GLPI)' : 'Nenhum colaborador vinculado';
    }

    /** Dados do colaborador usados no termo */
    public static function userData(int $users_id): array
    {
        $user = new User();
        if ($users_id <= 0 || !$user->getFromDB($users_id)) {
            return ['nome' => '', 'matricula' => '', 'email' => ''];
        }
        return [
            'nome'      => self::userName($users_id),
            'matricula' => (string) ($user->fields['registration_number'] ?? ''),
            'email'     => (string) ($user->getDefaultEmail() ?: ''),
        ];
    }

    /** Especificações do equipamento conforme o inventário */
    public static function getItemSpecs(CommonDBTM $item): array
    {
        global $DB;
        $itemtype = self::normalizeItemtype($item->getType());
        $meta     = self::ASSET_TYPES[$itemtype];
        $id       = (int) $item->getID();

        $cpu     = '';
        $ram_mb  = 0;
        $disk_mb = 0;
        if (!empty($meta['has_hw_specs'])) {
            foreach (
                $DB->request([
                    'SELECT'     => ['dev.designation'],
                    'FROM'       => 'glpi_items_deviceprocessors AS item',
                    'INNER JOIN' => ['glpi_deviceprocessors AS dev' => ['ON' => ['item' => 'deviceprocessors_id', 'dev' => 'id']]],
                    'WHERE'      => ['item.items_id' => $id, 'item.itemtype' => $itemtype, 'item.is_deleted' => 0],
                    'LIMIT'      => 1,
                ]) as $row
            ) {
                $cpu = (string) $row['designation'];
            }

            foreach (
                $DB->request([
                    'SELECT' => ['size'],
                    'FROM'   => 'glpi_items_devicememories',
                    'WHERE'  => ['items_id' => $id, 'itemtype' => $itemtype, 'is_deleted' => 0],
                ]) as $row
            ) {
                $ram_mb += (int) ($row['size'] ?? 0);
            }

            foreach (
                $DB->request([
                    'SELECT' => ['capacity'],
                    'FROM'   => 'glpi_items_deviceharddrives',
                    'WHERE'  => ['items_id' => $id, 'itemtype' => $itemtype, 'is_deleted' => 0],
                ]) as $row
            ) {
                $disk_mb += (int) ($row['capacity'] ?? 0);
            }
        }

        $so = '';
        if (!empty($meta['has_os_spec'])) {
            foreach (
                $DB->request([
                    'SELECT'    => ['os.name AS os_name', 'ver.name AS ver_name'],
                    'FROM'      => 'glpi_items_operatingsystems AS item',
                    'LEFT JOIN' => [
                        'glpi_operatingsystems AS os'         => ['ON' => ['item' => 'operatingsystems_id', 'os' => 'id']],
                        'glpi_operatingsystemversions AS ver' => ['ON' => ['item' => 'operatingsystemversions_id', 'ver' => 'id']],
                    ],
                    'WHERE'     => ['item.items_id' => $id, 'item.itemtype' => $itemtype],
                    'LIMIT'     => 1,
                ]) as $row
            ) {
                $so = trim(($row['os_name'] ?? '') . ' ' . ($row['ver_name'] ?? ''));
            }
        }

        $dropdown = static function (string $table, string $field) use ($item): string {
            $fid = (int) ($item->fields[$field] ?? 0);
            return $fid > 0 ? self::fromCore((string) Dropdown::getDropdownName($table, $fid)) : '';
        };

        $fabricante = $dropdown('glpi_manufacturers', 'manufacturers_id');
        if ($fabricante === '' && $meta['brand_field'] !== '') {
            $fabricante = trim(self::fromCore((string) ($item->fields[$meta['brand_field']] ?? '')));
        }

        return [
            'cpu'        => $cpu,
            'ram'        => $ram_mb > 0 ? self::formatMemory($ram_mb) : '',
            'disk'       => $disk_mb > 0 ? self::formatDisk($disk_mb) : '',
            'so'         => $so,
            'fabricante' => $fabricante,
            'modelo'     => $dropdown($meta['model_table'], $meta['model_field']),
            'tipo'       => $dropdown($meta['type_table'], $meta['type_field']),
        ];
    }

    /**
     * @deprecated Use getItemSpecs()
     */
    public static function getComputerSpecs(CommonDBTM $computer): array
    {
        return self::getItemSpecs($computer);
    }

    /**
     * Status disponíveis para o tipo de ativo informado (id => nome).
     * GLPI 11+ guarda a visibilidade em glpi_dropdownvisibilities; o GLPI 10, em glpi_states.is_visible_<tipo>.
     */
    public static function getStates(string $itemtype = 'Computer'): array
    {
        global $DB;
        $itemtype = self::normalizeItemtype($itemtype);
        $stateCol = self::ASSET_TYPES[$itemtype]['state_col'];
        $criteria = ['SELECT' => ['glpi_states.id', 'glpi_states.completename'], 'FROM' => 'glpi_states', 'ORDER' => 'glpi_states.completename'];
        if ($DB->tableExists('glpi_dropdownvisibilities')) {
            $criteria['INNER JOIN'] = [
                'glpi_dropdownvisibilities AS vis' => [
                    'ON' => ['vis' => 'items_id', 'glpi_states' => 'id', ['AND' => ['vis.itemtype' => 'State']]],
                ],
            ];
            $criteria['WHERE'] = ['vis.visible_itemtype' => $itemtype, 'vis.is_visible' => 1];
        } elseif ($DB->fieldExists('glpi_states', $stateCol)) {
            $criteria['WHERE'] = [$stateCol => 1];
        }
        $states = [];
        foreach ($DB->request($criteria) as $row) {
            $states[(int) $row['id']] = self::fromCore((string) $row['completename']);
        }
        return $states;
    }

    /** Id do status cujo nome contém o texto informado (ex.: "Em uso"), ou 0 */
    public static function findState(array $states, string $name): int
    {
        foreach ($states as $id => $label) {
            if (mb_stripos($label, $name) !== false) {
                return (int) $id;
            }
        }
        return 0;
    }

    // ------------------------------------------------- Pedidos de assinatura

    public static function installSchema(): void
    {
        global $DB;
        self::installDocsTable();
        if ($DB->tableExists(self::TABLE)) {
            return;
        }
        $DB->doQuery("CREATE TABLE `" . self::TABLE . "` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `itemtype` varchar(64) NOT NULL DEFAULT 'Computer',
            `computers_id` int unsigned NOT NULL DEFAULT '0',
            `entities_id` int unsigned NOT NULL DEFAULT '0',
            `users_id` int unsigned NOT NULL DEFAULT '0' COMMENT 'colaborador que assina',
            `users_id_tech` int unsigned NOT NULL DEFAULT '0' COMMENT 'quem enviou',
            `type` varchar(20) NOT NULL DEFAULT 'entrega',
            `code` varchar(100) NOT NULL DEFAULT '',
            `status` tinyint NOT NULL DEFAULT '0',
            `data` longtext COMMENT 'dados do termo no momento do envio (JSON)',
            `documents_id` int unsigned NOT NULL DEFAULT '0',
            `sign_ip` varchar(45) NOT NULL DEFAULT '',
            `date_send` timestamp NULL DEFAULT NULL,
            `date_signed` timestamp NULL DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `item` (`itemtype`, `computers_id`),
            KEY `computers_id` (`computers_id`),
            KEY `users_id` (`users_id`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC");
    }

    /** Documentos gerados pelo plugin (desde a 1.5.1). Os anteriores são achados pelo nome ("Termo de ...") */
    public static function installDocsTable(): void
    {
        global $DB;
        if ($DB->tableExists(self::DOCS_TABLE)) {
            return;
        }
        $DB->doQuery("CREATE TABLE `" . self::DOCS_TABLE . "` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `documents_id` int unsigned NOT NULL DEFAULT '0',
            `itemtype` varchar(64) NOT NULL DEFAULT 'Computer',
            `computers_id` int unsigned NOT NULL DEFAULT '0',
            `type` varchar(20) NOT NULL DEFAULT 'entrega',
            `code` varchar(100) NOT NULL DEFAULT '',
            `date_creation` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `documents_id` (`documents_id`),
            KEY `item` (`itemtype`, `computers_id`),
            KEY `computers_id` (`computers_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC");
    }

    public static function uninstallSchema(): void
    {
        global $DB;
        foreach ([self::TABLE, self::DOCS_TABLE] as $table) {
            if ($DB->tableExists($table)) {
                $DB->doQuery('DROP TABLE `' . $table . '`');
            }
        }
    }

    /**
     * Valores para $DB->insert/update nas tabelas do plugin. O query builder do GLPI 10 não escapa
     * os valores (conta com a entrada já sanitizada, que normalizeInput() desfaz); o do GLPI 11 escapa.
     */
    public static function dbValues(array $values): array
    {
        global $DB;
        if (version_compare(GLPI_VERSION, '11.0.0-dev', '<')) {
            foreach ($values as $k => $v) {
                if (is_string($v)) {
                    $values[$k] = $DB->escape($v);
                }
            }
        }
        return $values;
    }

    /**
     * Entrada para o núcleo (CommonDBTM::add/update, Log::history). O GLPI 10 espera os valores
     * sanitizados como vêm de um formulário: escapados para SQL e com < > & em entidades HTML.
     *
     * @param mixed $values
     * @return mixed
     */
    public static function coreInput($values)
    {
        if (version_compare(GLPI_VERSION, '11.0.0-dev', '<')) {
            return class_exists(\Glpi\Toolbox\Sanitizer::class)
                ? \Glpi\Toolbox\Sanitizer::sanitize($values)
                : Toolbox::addslashes_deep($values);
        }
        return $values;
    }

    /** Mensagem simples no histórico do equipamento */
    public static function history(int $items_id, string $msg, string $itemtype = 'Computer'): void
    {
        $itemtype = self::normalizeItemtype($itemtype);
        Log::history($items_id, $itemtype, [0, '', self::coreInput($msg)], '', Log::HISTORY_LOG_SIMPLE_MESSAGE);
    }

    /**
     * Cancela os termos do equipamento que ainda aguardam assinatura: depois de outro termo ou de
     * uma mudança de ciclo de vida, uma assinatura atrasada devolveria o equipamento à situação antiga.
     */
    public static function cancelPending(int $items_id, int $except_id = 0, string $itemtype = 'Computer'): void
    {
        global $DB;
        self::ensureSchema();
        $itemtype = self::normalizeItemtype($itemtype);
        $where = [
            'itemtype'     => $itemtype,
            'computers_id' => $items_id,
            'status'       => self::PENDING,
        ];
        if ($except_id > 0) {
            $where['NOT'] = ['id' => $except_id];
        }
        $DB->update(self::TABLE, ['status' => self::CANCELED, 'date_mod' => date('Y-m-d H:i:s')], $where);
        $n = (int) $DB->affectedRows();
        if ($n > 0) {
            self::history(
                $items_id,
                $n === 1
                    ? 'Termo que aguardava assinatura cancelado: a situação do equipamento mudou. O link enviado deixou de funcionar.'
                    : "{$n} termos que aguardavam assinatura cancelados: a situação do equipamento mudou. Os links enviados deixaram de funcionar.",
                $itemtype
            );
        }
    }

    /**
     * O GLPI 10 entrega $_POST já escapado para SQL e com < > & convertidos em entidades HTML.
     * Os endpoints do plugin trabalham com o texto original (que vai para o PDF, o JSON e a tela)
     * e escapam só na hora de gravar. No GLPI 11, $_POST já chega original.
     */
    public static function normalizeInput(): void
    {
        if (version_compare(GLPI_VERSION, '11.0.0-dev', '<') && class_exists(\Glpi\Toolbox\Sanitizer::class)) {
            $_POST = \Glpi\Toolbox\Sanitizer::unsanitize($_POST);
        }
    }

    /** Responde em JSON. Quem chama encerra o script com return. */
    public static function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data);
    }

    /** Endereço web do plugin; com $absolute, inclui o endereço do GLPI (para e-mails) */
    public static function webPath(bool $absolute = false): string
    {
        global $CFG_GLPI;
        if (version_compare(GLPI_VERSION, '11.0.0-dev', '>=')) {
            return ($absolute ? $CFG_GLPI['url_base'] : $CFG_GLPI['root_doc']) . '/plugins/assetterms';
        }
        return Plugin::getWebDir('assetterms', true, $absolute);
    }

    public static function signUrl(int $request_id): string
    {
        return self::webPath(true) . '/front/sign.php?id=' . $request_id;
    }

    /** Pedido de assinatura (linha da tabela, com os dados do termo decodificados) ou null */
    public static function getRequest(int $id): ?array
    {
        global $DB;
        self::ensureSchema();
        if ($id <= 0) {
            return null;
        }
        foreach ($DB->request(['FROM' => self::TABLE, 'WHERE' => ['id' => $id]]) as $row) {
            $row['itemtype'] = self::normalizeItemtype((string) ($row['itemtype'] ?? 'Computer'));
            $row['data']     = json_decode((string) $row['data'], true) ?: [];
            return $row;
        }
        return null;
    }

    /** Pedidos aguardando assinatura de um equipamento */
    public static function getPendingRequests(int $items_id, string $itemtype = 'Computer'): array
    {
        global $DB;
        self::ensureSchema();
        $itemtype = self::normalizeItemtype($itemtype);
        $rows = [];
        foreach (
            $DB->request([
                'FROM'  => self::TABLE,
                'WHERE' => ['itemtype' => $itemtype, 'computers_id' => $items_id, 'status' => self::PENDING],
                'ORDER' => 'date_send DESC',
            ]) as $row
        ) {
            $row['itemtype'] = self::normalizeItemtype((string) ($row['itemtype'] ?? $itemtype));
            $row['data']     = json_decode((string) $row['data'], true) ?: [];
            $rows[] = $row;
        }
        return $rows;
    }

    // ---------------------------------------------------- Montagem do termo

    /**
     * Valida o formulário da aba. Retorna os campos limpos ou uma mensagem de erro (string).
     *
     * @return array|string
     */
    public static function collectInput(array $post, CommonDBTM $item)
    {
        $itemtype = self::normalizeItemtype($item->getType());
        $tipo = ($post['tipo_termo'] ?? '') === 'devolucao' ? 'devolucao' : 'entrega';
        $uid  = (int) ($post['users_id'] ?? 0);
        if (self::userName($uid) === '') {
            return 'Selecione o colaborador.';
        }
        if (!self::userAllowedFor($uid, $item)) {
            return 'O colaborador escolhido não está ativo ou não tem acesso à entidade deste equipamento.';
        }
        $checklist = [];
        foreach ((array) ($post['checklist'] ?? []) as $key) {
            if (is_string($key) && isset(self::CHECKLIST[$key])) {
                $checklist[$key] = self::CHECKLIST[$key];
            }
        }
        $state_id = (int) ($post['target_state_id'] ?? 0);
        if ($state_id > 0 && !isset(self::getStates($itemtype)[$state_id])) {
            $state_id = 0;
        }
        // Dados do equipamento conferidos ou completados pelo técnico (só valem para o termo)
        $equip = [];
        foreach (array_keys(self::EQUIP_FIELDS) as $field) {
            $value = trim(preg_replace('/\s+/u', ' ', (string) ($post['equip_' . $field] ?? '')));
            if ($value !== '') {
                $equip[$field] = mb_substr($value, 0, 150);
            }
        }
        return [
            'tipo'        => $tipo,
            'users_id'    => $uid,
            'equipamento' => $equip,
            'checklist'   => array_values($checklist),
            'state_id'    => $state_id,
            'observacoes' => mb_substr(trim((string) ($post['observacoes'] ?? '')), 0, 2000),
        ];
    }

    /**
     * O usuário pode ser escolhido para este equipamento? Mesma regra da lista de usuários da aba
     * (User::dropdown com 'entity' e 'right' => 'all'): ativo, não excluído, dentro do período de
     * validade e com algum perfil na entidade do equipamento ou, recursivo, numa entidade acima.
     * O usuário que já está no equipamento sempre vale (ex.: devolução de quem já foi desativado).
     */
    public static function userAllowedFor(int $users_id, CommonDBTM $item): bool
    {
        global $DB;
        if ($users_id <= 0) {
            return false;
        }
        if ($users_id === (int) ($item->fields['users_id'] ?? 0)) {
            return true;
        }
        $user = new User();
        if (!$user->getFromDB($users_id) || empty($user->fields['is_active']) || !empty($user->fields['is_deleted'])) {
            return false;
        }
        $now = date('Y-m-d H:i:s');
        if (
            (!empty($user->fields['begin_date']) && $user->fields['begin_date'] > $now)
            || (!empty($user->fields['end_date']) && $user->fields['end_date'] < $now)
        ) {
            return false;
        }
        $entities_id = (int) ($item->fields['entities_id'] ?? 0);
        $criteria = [
            'COUNT' => 'cpt',
            'FROM'  => 'glpi_profiles_users',
            'WHERE' => ['users_id' => $users_id] + getEntitiesRestrictCriteria('glpi_profiles_users', '', $entities_id, true),
        ];
        foreach ($DB->request($criteria) as $row) {
            return (int) $row['cpt'] > 0;
        }
        return false;
    }

    /**
     * Dados do termo (sem assinatura). Também é o que fica guardado no pedido enviado por e-mail,
     * para o colaborador assinar exatamente o que a TI registrou.
     */
    public static function buildData(CommonDBTM $item, array $in, int $tech_id): array
    {
        $itemtype = self::normalizeItemtype($item->getType());
        $id       = (int) $item->getID();
        $cfg      = PluginAssettermsConfig::getEffective((int) $item->fields['entities_id']);

        return self::techData($tech_id) + [
            'tipo'         => $in['tipo'],
            'itemtype'     => $itemtype,
            'items_id'     => $id,
            'computers_id' => $id,
            'entities_id'  => (int) $item->fields['entities_id'],
            'empresa'      => $cfg['company_name'],
            'cnpj'         => $cfg['company_doc'],
            'cidade'       => $cfg['city'],
            // Texto do momento em que o termo foi feito: quem assina pelo link vê exatamente este
            'texto'        => self::clausula($in['tipo'], $cfg),
            // Formato definido na configuração (padrão: aleatório); {seq} reserva o próximo número
            'codigo'       => PluginAssettermsConfig::buildCode($cfg, $in['tipo'], time()),
            'colaborador'  => self::userData($in['users_id']) + ['id' => $in['users_id']],
            'equipamento'  => array_merge(self::getItemSpecs($item), $in['equipamento'] ?? []) + [
                'nome'       => self::fromCore((string) ($item->fields['name'] ?? '')),
                'serial'     => (string) ($item->fields['serial'] ?? ''),
                'patrimonio' => (string) ($item->fields['otherserial'] ?? ''),
            ],
            'checklist'    => $in['checklist'],
            'observacoes'  => $in['observacoes'],
            'state_id'     => $in['state_id'],
        ];
    }

    /**
     * Quem emite o termo (TI): nome, login e a assinatura cadastrada naquele momento (PNG em base64,
     * '' se não houver). Fica no pedido enviado por link: trocar a assinatura depois não muda o termo.
     */
    public static function techData(int $tech_id): array
    {
        $user = new User();
        $sig  = PluginAssettermsSignature::get($tech_id);
        return [
            'tecnico'                 => self::userName($tech_id) ?: 'TI',
            'tecnico_id'              => $tech_id,
            'tecnico_login'           => $user->getFromDB($tech_id) ? self::fromCore((string) $user->fields['name']) : '',
            'tecnico_assinatura'      => $sig['image'] ?? '',
            'tecnico_assinatura_tipo' => $sig['kind'] ?? '',
        ];
    }

    /** Data/hora do termo em texto (dd/mm/aaaa hh:mm e por extenso) */
    public static function withDate(array $d, int $time): array
    {
        $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        $d['data']         = date('d/m/Y H:i', $time);
        $d['data_extenso'] = date('j', $time) . ' de ' . $meses[(int) date('n', $time) - 1] . ' de ' . date('Y', $time);
        return $d;
    }

    /**
     * Confere a imagem da assinatura enviada pelo canvas (data:image/png;base64,...).
     * Retorna o PNG binário, '' se não houver assinatura, ou null se for inválida.
     */
    public static function decodeSignature(string $raw): ?string
    {
        if ($raw === '') {
            return '';
        }
        if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $raw, $m) || strlen($m[1]) > 1400000) {
            return null;
        }
        $png  = (string) base64_decode($m[1], true);
        $info = $png !== '' ? @getimagesizefromstring($png) : false;
        if ($info === false || ($info['mime'] ?? '') !== 'image/png') {
            return null;
        }
        // Um PNG pequeno pode declarar dimensões enormes e estourar a memória ao entrar no PDF
        if ((int) $info[0] < 1 || (int) $info[1] < 1 || (int) $info[0] > self::SIGNATURE_MAX_W || (int) $info[1] > self::SIGNATURE_MAX_H) {
            return null;
        }
        return $png;
    }

    public static function fileName(array $d, int $time): string
    {
        return sprintf(
            '%s - %s - %s.pdf',
            $d['tipo'] === 'devolucao' ? 'Termo de Devolução' : 'Termo de Entrega',
            preg_replace('/[^\pL\pN ._-]+/u', '', $d['equipamento']['nome'] ?: 'equipamento'),
            date('Y-m-d His', $time)
        );
    }

    /**
     * Gera o PDF, grava na aba Documentos, atualiza usuário e status do equipamento e registra no histórico.
     * $d precisa ter data, assinatura, modo e ip. Retorna [documents_id, nome do status, conteúdo do PDF].
     *
     * @throws RuntimeException com a mensagem para o usuário
     */
    public static function archive(CommonDBTM $item, array $d, int $time): array
    {
        global $DB;
        self::ensureSchema();
        $itemtype = self::normalizeItemtype($item->getType());
        $itemId   = (int) $item->getID();

        if (!isset($d['itemtype'])) {
            $d['itemtype'] = $itemtype;
        }

        $pdf     = self::buildPdf($d);
        $titulo  = $d['tipo'] === 'devolucao' ? 'Termo de Devolução' : 'Termo de Entrega';
        $arquivo = self::fileName($d, $time);
        $nome    = $d['colaborador']['nome'];

        // O GLPI move o arquivo do diretório temporário, confere o tipo e calcula o checksum.
        // O prefixo único evita que dois termos do mesmo equipamento no mesmo segundo usem o mesmo arquivo;
        // o GLPI o tira do nome (_prefix_filename), como faz com os uploads.
        $prefixo = uniqid('', true);
        $tmp     = GLPI_TMP_DIR . '/' . $prefixo . $arquivo;
        if (file_put_contents($tmp, $pdf) === false) {
            throw new RuntimeException('Não foi possível gravar o arquivo em ' . GLPI_TMP_DIR . '.');
        }
        $modo = [
            'email' => 'Assinado pelo link enviado por e-mail',
            'link'  => 'Assinado pelo link de assinatura',
            'tela'  => 'Assinado na tela',
        ][$d['modo']] ?? 'Para assinatura manual';
        if ($d['assinatura'] !== '' && ($d['assinatura_tipo'] ?? '') === 'digitada') {
            $modo .= ' (nome digitado)';
        }
        $input = self::coreInput([
            'name'              => $titulo . ' - ' . ($d['equipamento']['nome'] ?: 'equipamento') . ' - ' . $nome . ' - ' . date('d/m/Y', $time),
            'entities_id'       => (int) $item->fields['entities_id'],
            'is_recursive'      => 0,
            'comment'           => sprintf('%s de %s. Código %s. %s.', $titulo, $nome, $d['codigo'], $modo),
            'users_id'          => (int) $d['tecnico_id'],
            '_filename'         => [$prefixo . $arquivo],
            '_prefix_filename'  => [$prefixo],
        ]);
        $document = new Document();
        $doc_id   = (int) $document->add($input);
        if (is_file($tmp)) {
            @unlink($tmp);
        }
        if ($doc_id <= 0) {
            throw new RuntimeException('O GLPI recusou o documento. Confira se o tipo PDF está liberado em Configurar > Listas suspensas > Tipos de documento.');
        }

        try {
            // Vínculo feito à parte: criando o documento já vinculado, o GLPI 10 troca o nome pelo do equipamento
            $link = new Document_Item();
            $linked = $link->add([
                'documents_id' => $doc_id,
                'itemtype'     => $itemtype,
                'items_id'     => $itemId,
                'entities_id'  => (int) $item->fields['entities_id'],
            ]);
            if (!$linked) {
                throw new RuntimeException('Não foi possível vincular o documento ao equipamento.');
            }
            self::registerDocument($doc_id, $itemId, $d, $itemtype);
            // O GLPI grava como dono do documento quem está logado, e na assinatura pelo link é o colaborador
            if ((int) $d['tecnico_id'] > 0 && (int) ($document->fields['users_id'] ?? 0) !== (int) $d['tecnico_id']) {
                $DB->update('glpi_documents', ['users_id' => (int) $d['tecnico_id']], ['id' => $doc_id]);
            }

            $update = ['id' => $itemId, 'users_id' => $d['tipo'] === 'entrega' ? (int) $d['colaborador']['id'] : 0];
            if ($d['tipo'] === 'devolucao') {
                $update['contact'] = '';
            }
            if ((int) $d['state_id'] > 0) {
                $update['states_id'] = (int) $d['state_id'];
            }
            if (!$item->update($update)) {
                throw new RuntimeException('Não foi possível atualizar o usuário e o status do equipamento.');
            }
        } catch (Throwable $e) {
            // Desfaz o documento (e o arquivo), para uma nova tentativa não deixar termo duplicado
            self::discardDocument($doc_id);
            throw $e instanceof RuntimeException ? $e : new RuntimeException('Não foi possível arquivar o termo.', 0, $e);
        }

        $status = (int) $d['state_id'] > 0 ? self::fromCore((string) Dropdown::getDropdownName('glpi_states', (int) $d['state_id'])) : '';
        $msg    = "{$titulo} #{$doc_id} arquivado ({$modo}). Colaborador: {$nome}. Código: {$d['codigo']}." . ($status !== '' ? " Status: {$status}." : '');
        // O termo já está arquivado: uma falha daqui em diante não pode levar quem chamou a tentar de novo
        try {
            self::history($itemId, $msg, $itemtype);
            // O pedido assinado pelo link já está SIGNED; sobram só os pendentes antigos deste ativo
            self::cancelPending($itemId, 0, $itemtype);
        } catch (Throwable $e) {
            Toolbox::logInFile('php-errors', 'Asset Terms: termo #' . $doc_id . ' arquivado, mas falhou o histórico ou o cancelamento dos pendentes: ' . $e->getMessage() . "\n");
        }

        return [$doc_id, $status, $pdf];
    }

    /** Registra o documento gerado pelo plugin, para o histórico não depender do nome do documento */
    private static function registerDocument(int $doc_id, int $items_id, array $d, string $itemtype = 'Computer'): void
    {
        global $DB;
        $itemtype = self::normalizeItemtype($itemtype);
        $ok = $DB->insert(self::DOCS_TABLE, self::dbValues([
            'documents_id'  => $doc_id,
            'itemtype'      => $itemtype,
            'computers_id'  => $items_id,
            'type'          => $d['tipo'] === 'devolucao' ? 'devolucao' : 'entrega',
            'code'          => (string) $d['codigo'],
            'date_creation' => date('Y-m-d H:i:s'),
        ]));
        if (!$ok) {
            throw new RuntimeException('Não foi possível registrar o documento do termo.');
        }
    }

    /**
     * Apaga de vez um documento criado por archive() que não pôde ser concluído.
     * O GLPI remove junto os vínculos (Document_Item) e o arquivo, se nenhum outro documento o usa.
     */
    public static function discardDocument(int $doc_id): void
    {
        global $DB;
        if ($doc_id <= 0) {
            return;
        }
        try {
            if ($DB->tableExists(self::DOCS_TABLE)) {
                $DB->delete(self::DOCS_TABLE, ['documents_id' => $doc_id]);
            }
            $document = new Document();
            if ($document->getFromDB($doc_id)) {
                $document->delete(['id' => $doc_id], true);
            }
        } catch (Throwable $e) {
            Toolbox::logInFile('php-errors', 'Asset Terms: falha ao remover o documento #' . $doc_id . ' de um termo não concluído: ' . $e->getMessage() . "\n");
        }
    }

    // ------------------------------------------------- Dados do equipamento

    /**
     * Valores já cadastrados no GLPI para cada campo do equipamento (sugestões do formulário).
     *
     * @return array<string, string[]>
     */
    public static function equipmentOptions(string $itemtype = 'Computer'): array
    {
        global $DB;
        $itemtype = self::normalizeItemtype($itemtype);
        $meta     = self::ASSET_TYPES[$itemtype];

        $names = static function (string $table) use ($DB): array {
            $out = [];
            foreach ($DB->request(['SELECT' => ['name'], 'DISTINCT' => true, 'FROM' => $table, 'WHERE' => ['NOT' => ['name' => '']], 'ORDER' => 'name', 'LIMIT' => 300]) as $r) {
                $out[] = (string) $r['name'];
            }
            return $out;
        };

        $cpu  = [];
        $ram  = [];
        $disk = [];
        if (!empty($meta['has_hw_specs'])) {
            // Totais por equipamento já inventariados, mais tamanhos comuns
            $totals = static function (string $table, string $field, callable $fmt, array $common) use ($DB, $itemtype): array {
                $out = array_map($fmt, $common);
                // GLPI 11: Glpi\DBAL\QueryExpression | GLPI 10: QueryExpression
                $sum = 'SUM(' . $DB->quoteName($field) . ') AS total';
                $sum = class_exists(\Glpi\DBAL\QueryExpression::class) ? new \Glpi\DBAL\QueryExpression($sum) : new \QueryExpression($sum);
                foreach (
                    $DB->request([
                        'SELECT'  => [$sum],
                        'FROM'    => $table,
                        'WHERE'   => ['itemtype' => $itemtype, 'is_deleted' => 0],
                        'GROUPBY' => 'items_id',
                        'LIMIT'   => 2000,
                    ]) as $r
                ) {
                    if ((int) $r['total'] > 0) {
                        $out[] = $fmt((int) $r['total']);
                    }
                }
                $out = array_values(array_unique($out));
                usort($out, static fn ($a, $b) => (float) $a <=> (float) $b ?: strcmp($a, $b));
                return $out;
            };

            foreach ($DB->request(['SELECT' => ['designation'], 'DISTINCT' => true, 'FROM' => 'glpi_deviceprocessors', 'ORDER' => 'designation', 'LIMIT' => 300]) as $r) {
                $cpu[] = (string) $r['designation'];
            }
            $cpu  = array_values(array_filter($cpu));
            $ram  = $totals('glpi_items_devicememories', 'size', [self::class, 'formatMemory'], [4096, 8192, 16384, 32768, 65536]);
            $disk = $totals('glpi_items_deviceharddrives', 'capacity', [self::class, 'formatDisk'], [262144, 524288, 1048576, 2097152]);
        }

        $so = [];
        if (!empty($meta['has_os_spec'])) {
            foreach (
                $DB->request([
                    'SELECT'    => ['os.name AS os_name', 'ver.name AS ver_name'],
                    'DISTINCT'  => true,
                    'FROM'      => 'glpi_items_operatingsystems AS item',
                    'LEFT JOIN' => [
                        'glpi_operatingsystems AS os'         => ['ON' => ['item' => 'operatingsystems_id', 'os' => 'id']],
                        'glpi_operatingsystemversions AS ver' => ['ON' => ['item' => 'operatingsystemversions_id', 'ver' => 'id']],
                    ],
                    'LIMIT'     => 300,
                ]) as $r
            ) {
                $so[] = trim(($r['os_name'] ?? '') . ' ' . ($r['ver_name'] ?? ''));
            }
            $so = array_values(array_unique(array_filter(array_merge($so, $names('glpi_operatingsystems')))));
            sort($so);
        }

        return [
            'fabricante' => $names('glpi_manufacturers'),
            'modelo'     => $names($meta['model_table']),
            'tipo'       => $names($meta['type_table']),
            'cpu'        => $cpu,
            'ram'        => $ram,
            'disk'       => $disk,
            'so'         => $so,
        ];
    }

    public static function formatMemory(int $mb): string
    {
        return round($mb / 1024) . ' GB';
    }

    public static function formatDisk(int $mb): string
    {
        return $mb >= 1048576 ? round($mb / 1048576, 1) . ' TB' : round($mb / 1024) . ' GB';
    }

    // ---------------------------------------------------------------- E-mail

    /**
     * O GLPI está configurado para enviar e-mails? (notificações por e-mail ativas e remetente definido)
     * Sem isso, o plugin oferece o link de assinatura para o técnico compartilhar por outro meio.
     */
    public static function mailConfigured(): bool
    {
        global $CFG_GLPI;
        return !empty($CFG_GLPI['notifications_mailing'])
            && (!empty($CFG_GLPI['from_email']) || !empty($CFG_GLPI['admin_email']));
    }

    /**
     * Envia um e-mail pela configuração de e-mail do GLPI. Retorna null ou a mensagem de erro.
     *
     * @param array|null $attachment [conteúdo, nome do arquivo]
     */
    public static function sendMail(string $to, string $name, string $subject, string $html, string $text, int $entities_id, ?array $attachment = null): ?string
    {
        if (!GLPIMailer::validateAddress($to)) {
            return "o e-mail \"{$to}\" é inválido";
        }
        $sender = @Config::getEmailSender($entities_id);
        if (empty($sender['email'])) {
            return 'o GLPI não tem e-mail de remetente. Configure em Configurar > Notificações > Configuração das notificações por e-mail';
        }

        try {
            $mailer = new GLPIMailer();
            if (method_exists($mailer, 'getEmail')) {
                // GLPI 11+: Symfony Mailer
                $email = $mailer->getEmail();
                $email->from(new \Symfony\Component\Mime\Address($sender['email'], (string) ($sender['name'] ?? '')));
                $email->to(new \Symfony\Component\Mime\Address($to, $name));
                $email->subject($subject);
                $email->html($html);
                $email->text($text);
                if ($attachment !== null) {
                    $email->attach($attachment[0], $attachment[1], 'application/pdf');
                }
                return $mailer->send() ? null : self::mailFailure($to, (string) ($mailer->getError() ?: 'falha no envio'));
            }

            // GLPI 10: PHPMailer
            $mailer->CharSet = 'utf-8';
            $mailer->setFrom($sender['email'], (string) ($sender['name'] ?? ''), false);
            $mailer->addAddress($to, $name);
            $mailer->isHTML(true);
            $mailer->Subject = $subject;
            $mailer->Body    = $html;
            $mailer->AltBody = $text;
            if ($attachment !== null) {
                $mailer->addStringAttachment($attachment[0], $attachment[1], 'base64', 'application/pdf');
            }
            return $mailer->send() ? null : self::mailFailure($to, (string) ($mailer->ErrorInfo ?: 'falha no envio'));
        } catch (Throwable $e) {
            return self::mailFailure($to, get_class($e) . ': ' . $e->getMessage());
        }
    }

    /**
     * Grava o erro do servidor de e-mail no log do GLPI e devolve uma mensagem genérica:
     * os detalhes (servidor, porta, resposta SMTP) não vão para a tela.
     */
    private static function mailFailure(string $to, string $detail): string
    {
        Toolbox::logInFile('php-errors', "Asset Terms: falha ao enviar e-mail para {$to}: {$detail}\n");
        return 'falha no envio pelo servidor de e-mail (detalhes no log php-errors do GLPI)';
    }

    /** Corpo HTML simples para os e-mails do plugin */
    private static function mailHtml(string $title, array $paragraphs, ?array $button = null): string
    {
        $e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $html = '<div style="font-family:Segoe UI,Arial,sans-serif;font-size:14px;color:#1f2937;max-width:560px;">'
            . '<h2 style="color:' . PluginAssettermsConfig::DEFAULT_COLOR . ';font-size:18px;">' . $e($title) . '</h2>';
        foreach ($paragraphs as $p) {
            $html .= '<p style="line-height:1.5;">' . $p . '</p>';
        }
        if ($button !== null) {
            $html .= '<p style="margin:24px 0;"><a href="' . $e($button[1]) . '" style="background:#1f6feb;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;font-weight:600;">'
                . $e($button[0]) . '</a></p>'
                . '<p style="font-size:12px;color:#6b7280;">Se o botão não funcionar, copie este endereço no navegador:<br>' . $e($button[1]) . '</p>';
        }
        return $html . '<p style="font-size:12px;color:#6b7280;">Mensagem automática do GLPI.</p></div>';
    }

    /** E-mail para o colaborador com o link do termo. Retorna null ou a mensagem de erro. */
    public static function sendRequestMail(array $req): ?string
    {
        $e    = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $d    = $req['data'];
        $acao = $d['tipo'] === 'devolucao' ? 'devolução' : 'entrega';
        $url  = self::signUrl((int) $req['id']);
        $eq   = trim($d['equipamento']['nome'] . ($d['equipamento']['patrimonio'] !== '' ? ' (patrimônio ' . $d['equipamento']['patrimonio'] . ')' : ''));
        $subject = "Termo de {$acao} do equipamento {$d['equipamento']['nome']} para assinatura";

        $html = self::mailHtml("Termo de {$acao} de equipamento", [
            'Olá, ' . $e($d['colaborador']['nome']) . '.',
            $e($d['tecnico']) . " registrou a {$acao} do equipamento <b>" . $e($eq) . '</b> e precisa da sua assinatura no termo de responsabilidade.',
            'Clique no botão abaixo, entre no GLPI com o seu usuário, leia o termo e assine na tela.',
        ], ['Ler e assinar o termo', $url]);
        $text = "Olá, {$d['colaborador']['nome']}.\n\n{$d['tecnico']} registrou a {$acao} do equipamento {$eq} e precisa da sua assinatura no termo de responsabilidade.\n\n"
            . "Entre no GLPI com o seu usuário, leia o termo e assine:\n{$url}\n";

        return self::sendMail($d['colaborador']['email'], $d['colaborador']['nome'], $subject, $html, $text, (int) $req['entities_id']);
    }

    /**
     * Depois da assinatura: cópia em PDF para o colaborador e aviso para quem enviou.
     * Só tenta se o e-mail do GLPI estiver configurado; erros vão para o log.
     * Retorna true se a cópia chegou a ser enviada para o colaborador.
     */
    public static function sendSignedMails(array $req, string $pdf, string $arquivo, string $quando): bool
    {
        if (!self::mailConfigured()) {
            return false;
        }

        $e        = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $d        = $req['data'];
        $acao     = $d['tipo'] === 'devolucao' ? 'devolução' : 'entrega';
        $eq       = $d['equipamento']['nome'];
        $itemtype = self::normalizeItemtype((string) ($req['itemtype'] ?? ($d['itemtype'] ?? 'Computer')));
        $itemId   = (int) ($req['computers_id'] ?? ($d['items_id'] ?? 0));
        $errors   = [];
        $copia    = false;

        $err = $d['colaborador']['email'] === '' ? 'colaborador sem e-mail cadastrado' : self::sendMail(
            $d['colaborador']['email'],
            $d['colaborador']['nome'],
            "Cópia do termo de {$acao} assinado - {$eq}",
            self::mailHtml("Termo de {$acao} assinado", [
                'Olá, ' . $e($d['colaborador']['nome']) . '.',
                "Você assinou o termo de {$acao} do equipamento <b>" . $e($eq) . '</b> em ' . $e($quando) . '. A cópia em PDF está anexada a este e-mail.',
            ]),
            "Olá, {$d['colaborador']['nome']}.\n\nVocê assinou o termo de {$acao} do equipamento {$eq} em {$quando}. A cópia em PDF está anexada a este e-mail.\n",
            (int) $req['entities_id'],
            [$pdf, $arquivo]
        );
        if ($err !== null) {
            $errors[] = "cópia para o colaborador: {$err}";
        } else {
            $copia = true;
        }

        $tech = self::userData((int) $req['users_id_tech']);
        if ($tech['email'] !== '') {
            $link = self::assetFormUrl($itemtype, $itemId, true);
            $err = self::sendMail(
                $tech['email'],
                $tech['nome'],
                "Termo de {$acao} assinado por {$d['colaborador']['nome']} - {$eq}",
                self::mailHtml("Termo de {$acao} assinado", [
                    $e($d['colaborador']['nome']) . " assinou o termo de {$acao} do equipamento <b>" . $e($eq) . '</b> em ' . $e($quando) . '.',
                    'O PDF foi arquivado na aba Documentos do equipamento, e o usuário e o status do equipamento foram atualizados.',
                ], ['Abrir o equipamento', $link]),
                "{$d['colaborador']['nome']} assinou o termo de {$acao} do equipamento {$eq} em {$quando}.\n{$link}\n",
                (int) $req['entities_id'],
                [$pdf, $arquivo]
            );
            if ($err !== null) {
                $errors[] = "aviso para {$tech['nome']}: {$err}";
            }
        }
        if ($errors) {
            Toolbox::logInFile('php-errors', 'Asset Terms: termo #' . (int) $req['id'] . ' assinado, mas houve falha no e-mail (' . implode('; ', $errors) . ")\n");
        }
        return $copia;
    }

    // --------------------------------------------------------- Texto do termo

    /**
     * Texto do termo para a configuração da empresa (ver PluginAssettermsConfig). É o mesmo na tela e no PDF.
     *
     * @return array{titulo: string, declaracao: string, compromissos: string[], ciencia: string}
     */
    public static function clausula(string $tipo, array $cfg): array
    {
        return PluginAssettermsConfig::render($cfg, $tipo);
    }

    /** Texto guardado no termo (desde a 1.3.0) ou, em termos antigos, o da configuração atual */
    public static function textoFor(array $d): array
    {
        return $d['texto'] ?? self::clausula($d['tipo'], PluginAssettermsConfig::getEffective((int) ($d['entities_id'] ?? 0)));
    }

    // ------------------------------------------------------------------- PDF

    /**
     * Gera o termo em PDF e retorna o conteúdo binário.
     *
     * @param array $d Dados do termo, montados em ajax/save.php
     */
    public static function buildPdf(array $d): string
    {
        $e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $or = static fn ($v): string => trim((string) $v) !== '' ? (string) $v : 'Não informado';
        $texto = self::textoFor($d);
        $devolucao = $d['tipo'] === 'devolucao';
        $itemtype = self::normalizeItemtype((string) ($d['itemtype'] ?? 'Computer'));

        // Aparência (logo, cor, rodapé e cabeçalho) da configuração da empresa; o PDF de exemplo manda a do formulário
        $marca    = $d['marca'] ?? PluginAssettermsConfig::getEffective((int) ($d['entities_id'] ?? 0));
        $cor      = $marca['color'] ?: PluginAssettermsConfig::DEFAULT_COLOR;
        $layout   = ($marca['doc_control'] ?? [])['layout'] ?? 'simples';
        $controle = $layout === 'controle' ? $marca['doc_control'] : null;
        $logo     = (string) ($marca['logo'] ?? '');
        $logoType = ($marca['logo_mime'] ?? '') === 'image/jpeg' ? 'JPG' : 'PNG';
        // Logo salvo antes da 1.5.4, quando as dimensões não eram conferidas: grande demais, fica de fora
        $logoInfo = $logo !== '' ? @getimagesizefromstring($logo) : false;
        if ($logo !== '' && ($logoInfo === false || $logoInfo[0] > PluginAssettermsConfig::LOGO_MAX_PX || $logoInfo[1] > PluginAssettermsConfig::LOGO_MAX_PX)) {
            $logo = '';
        }

        // Sem o texto "Powered by TCPDF" que a biblioteca acrescenta ao fim do documento.
        // Layout "simples": logo e título na primeira página, rodapé com o número da página.
        // Layout "controle": tabela de controle de documentos em todas as páginas (tipo, código do
        // formulário, título, revisão, datas e "Página X de Y") e rodapé com quem elaborou e aprovou.
        $pdf = new class ('P', 'mm', 'A4', true, 'UTF-8') extends TCPDF {
            public string $rodape = '';
            public ?array $controle = null;
            public string $logo = '';
            public string $logoType = 'PNG';
            public string $tituloCabecalho = '';

            public function semCredito(): void
            {
                $this->tcpdflink = false;
            }

            /**
             * O TCPDF encerra o PHP com die() em caso de erro (K_TCPDF_THROW_EXCEPTION_ERROR é false
             * por padrão), e aí nenhum catch roda: o número reservado não volta e a resposta não sai em
             * JSON. Com a exceção, quem chama trata como qualquer outra falha ao gerar o PDF.
             */
            public function Error($msg)
            {
                throw new Exception('TCPDF: ' . $msg);
            }

            /** Célula do cabeçalho: rótulo pequeno em cima e valor em negrito embaixo */
            private function celula(float $x, float $y, float $w, float $h, string $rotulo, string $valor, string $align = 'L', float $tam = 9): void
            {
                $e = static fn ($v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
                // Diminui a fonte do valor até caber em uma linha (a altura das linhas é fixa)
                $this->SetFont('dejavusans', 'B', $tam);
                while ($tam > 6 && $this->GetStringWidth($valor) > $w - 3) {
                    $tam -= 0.5;
                    $this->SetFont('dejavusans', 'B', $tam);
                }
                $html = '<span style="font-size:6.5pt;color:#333333;">' . $e($rotulo) . '</span><br>'
                    . '<b style="font-size:' . $tam . 'pt;">' . $e($valor) . '</b>';
                $this->writeHTMLCell($w, $h, $x, $y, $html, 1, 0, false, true, $align, true);
            }

            public function Header()
            {
                if ($this->controle === null) {
                    return;
                }
                $dc = $this->controle;
                // Mesmas proporções das colunas do modelo em Word (largura útil de 174 mm)
                $w    = [29.6, 41.8, 39.5, 34.8, 28.3];
                $h    = 9.5;
                $x0   = 18;
                $y0   = 10;
                $x1   = $x0 + $w[0];
                $span = $w[1] + $w[2] + $w[3];
                $x5   = $x1 + $span;

                $this->SetFont('dejavusans', '', 8);
                $this->SetTextColor(0, 0, 0);
                $this->SetDrawColor(0, 0, 0);
                $this->SetLineWidth(0.2);

                // Logo ocupando as três linhas
                $this->Rect($x0, $y0, $w[0], $h * 3);
                if ($this->logo !== '') {
                    $this->Image('@' . $this->logo, $x0 + 1.5, $y0 + 1.5, $w[0] - 3, $h * 3 - 3, $this->logoType, '', '', true, 300, '', false, false, 0, 'CM');
                }

                $titulo = $this->tituloCabecalho;
                $this->celula($x1, $y0, $span, $h, 'Tipo:', (string) $dc['tipo']);
                $this->celula($x5, $y0, $w[4], $h, 'Código:', (string) $dc['codigo'], 'C');
                $this->celula($x1, $y0 + $h, $span, $h, 'Título:', $titulo);
                $this->celula($x5, $y0 + $h, $w[4], $h, 'Nº Revisão:', (string) $dc['revisao'], 'C');
                $this->celula($x1, $y0 + 2 * $h, $w[1], $h, 'Data de emissão:', (string) $dc['emissao'], 'C');
                $this->celula($x1 + $w[1], $y0 + 2 * $h, $w[2], $h, 'Última revisão:', (string) $dc['ultima_revisao'], 'C');
                $this->celula($x1 + $w[1] + $w[2], $y0 + 2 * $h, $w[3], $h, 'Próxima revisão:', (string) $dc['proxima_revisao'], 'C');

                // "Página X de Y": os números só existem no fim, então a centralização usa a largura de
                // um texto equivalente (o TCPDF centralizaria pelo marcador, que é mais largo)
                $this->Rect($x5, $y0 + 2 * $h, $w[4], $h);
                // Termos longos passam de 9 páginas: a referência tem dois dígitos, a fonte diminui se
                // não couber e a célula fica sem a margem interna, para o texto não passar da borda
                $tam = 9;
                $this->SetFont('dejavusans', 'B', $tam);
                while ($tam > 7 && $this->GetStringWidth('Página 99 de 99') > $w[4] - 2) {
                    $tam -= 0.5;
                    $this->SetFont('dejavusans', 'B', $tam);
                }
                $largura = $this->GetStringWidth('Página 99 de 99');
                $padding = $this->getCellPaddings();
                $this->setCellPaddings(0, $padding['T'], 0, $padding['B']);
                $this->SetXY($x5 + max(1, ($w[4] - $largura) / 2), $y0 + 2 * $h);
                $this->Cell($largura, $h, 'Página ' . $this->getAliasNumPage() . ' de ' . $this->getAliasNbPages(), 0, 0, 'L');
                $this->setCellPaddings($padding['L'], $padding['T'], $padding['R'], $padding['B']);
            }

            public function Footer()
            {
                if ($this->controle !== null) {
                    $partes = array_filter([
                        $this->controle['elaborado'] !== '' ? 'Elaborado e revisado por: ' . $this->controle['elaborado'] : '',
                        $this->controle['aprovado'] !== '' ? 'Aprovado por: ' . $this->controle['aprovado'] : '',
                    ]);
                    $this->SetY(-14);
                    $this->SetFont('dejavusans', '', 8);
                    $this->SetTextColor(0, 0, 0);
                    $this->Cell(0, 4, implode('          ', $partes), 0, 1, 'C');
                    if ($this->rodape !== '') {
                        $this->SetFont('dejavusans', '', 6.5);
                        $this->SetTextColor(110, 110, 110);
                        $this->Cell(0, 3.5, $this->rodape, 0, 0, 'C');
                    }
                    return;
                }
                $this->SetY(-15);
                $this->SetDrawColor(200, 200, 200);
                $this->Line(18, $this->GetY(), 192, $this->GetY());
                $this->SetY(-14);
                $this->SetFont('dejavusans', '', 7);
                $this->SetTextColor(110, 110, 110);
                $this->MultiCell(150, 3.2, $this->rodape, 0, 'L', false, 0, 18);
                $this->Cell(0, 3.2, $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'R');
            }
        };
        $pdf->semCredito();
        $pdf->rodape          = (string) $marca['footer'];
        $pdf->controle        = $controle;
        $pdf->logo            = $logo;
        $pdf->logoType        = $logoType;
        $pdf->tituloCabecalho = $controle !== null ? (($controle['titulo'] ?? '') !== '' ? $controle['titulo'] : mb_strtoupper($texto['titulo'])) : '';
        $pdf->SetCreator('GLPI - Asset Terms');
        $pdf->SetAuthor($d['tecnico']);
        $pdf->SetTitle($texto['titulo'] . ' - ' . $d['equipamento']['nome']);
        $pdf->setPrintHeader($controle !== null);
        $pdf->setPrintFooter(true);
        $pdf->setFooterFont(['dejavusans', '', 7]);
        $pdf->SetMargins(18, $controle !== null ? 42 : 16, 18);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->SetFont('dejavusans', '', 8.5);
        $pdf->setHtmlVSpace([
            'h2' => [0 => ['h' => 0, 'n' => 0], 1 => ['h' => 1, 'n' => 1]],
            'h4' => [0 => ['h' => 1, 'n' => 1], 1 => ['h' => 0.5, 'n' => 1]],
            'p'  => [0 => ['h' => 0, 'n' => 0], 1 => ['h' => 1, 'n' => 1]],
            'ul' => [0 => ['h' => 0, 'n' => 0], 1 => ['h' => 0.5, 'n' => 1]],
            'ol' => [0 => ['h' => 0, 'n' => 0], 1 => ['h' => 0.5, 'n' => 1]],
        ]);
        $pdf->AddPage();

        // Layout simples: logo no topo, à esquerda (até 50 x 16 mm, sem distorcer)
        if ($controle === null && $logo !== '') {
            $pdf->Image('@' . $logo, 18, 10, 50, 16, $logoType, '', '', true, 300, '', false, false, 0, 'LM');
            $pdf->SetY(29);
        }

        $th = 'style="background-color:#eef2f7;width:32%;"';
        $row = static fn (string $label, string $value) => "<tr><td {$th}><b>{$e($label)}</b></td><td style=\"width:68%;\">{$e($value)}</td></tr>";

        $eq = $d['equipamento'];
        $configParts = array_filter([
            $eq['cpu'] ?? '',
            !empty($eq['ram']) ? 'RAM ' . $eq['ram'] : '',
            !empty($eq['disk']) ? 'Disco ' . $eq['disk'] : '',
            $eq['so'] ?? '',
        ]);
        $configText = implode(' | ', $configParts);
        $configRow  = ($itemtype === 'Computer' || $configText !== '')
            ? $row('Configuração', $or($configText))
            : '';

        // No layout de controle, o título e a empresa já estão no cabeçalho
        $html = ($controle === null
                ? '<h2 style="text-align:center;color:' . $cor . ';">' . $e(mb_strtoupper($texto['titulo'])) . '</h2>'
                    . '<p style="text-align:center;color:#555;">' . $e($d['empresa']) . (($d['cnpj'] ?? '') !== '' ? ' &bull; CNPJ ' . $e($d['cnpj']) : '') . '</p>'
                : '')
            . '<table cellpadding="3" style="border:0.3px solid #ccc;"><tr>'
            . '<td><b>Data:</b> ' . $e($d['data']) . '</td>'
            . '<td style="text-align:right;"><b>Código do documento:</b> ' . $e($d['codigo']) . '</td>'
            . '</tr></table>'

            . '<h4 style="color:' . $cor . ';">1. Colaborador</h4>'
            . '<table cellpadding="3" border="0.3">'
            . $row('Nome', $or($d['colaborador']['nome']))
            . $row('Matrícula', $or($d['colaborador']['matricula']))
            . $row('E-mail', $or($d['colaborador']['email']))
            . '</table>'

            . '<h4 style="color:' . $cor . ';">2. Equipamento</h4>'
            . '<table cellpadding="3" border="0.3">'
            . $row('Categoria', self::assetLabel($itemtype))
            . $row('Nome do ativo', $or($eq['nome']))
            . $row('Tipo', $or($eq['tipo']))
            . $row('Fabricante / modelo', $or(trim(($eq['fabricante'] ?? '') . ' ' . ($eq['modelo'] ?? ''))))
            . $row('Número de série', $or($eq['serial']))
            . $row('Patrimônio', $or($eq['patrimonio']))
            . $configRow
            . '</table>'

            . '<h4 style="color:' . $cor . ';">3. Acessórios ' . ($devolucao ? 'devolvidos' : 'entregues') . '</h4>';

        if (empty($d['checklist'])) {
            $html .= '<p><i>Nenhum acessório.</i></p>';
        } else {
            $html .= '<ul>';
            foreach ($d['checklist'] as $item) {
                $html .= '<li>' . $e($item) . '</li>';
            }
            $html .= '</ul>';
        }

        $html .= '<h4 style="color:' . $cor . ';">4. Observações sobre o estado do equipamento</h4>'
            . '<p>' . ($d['observacoes'] !== '' ? nl2br($e($d['observacoes'])) : '<i>Sem observações.</i>') . '</p>'

            . '<h4 style="color:' . $cor . ';">5. Declaração</h4>'
            . '<p style="text-align:justify;">' . $e($texto['declaracao']) . '</p>';

        if ($devolucao) {
            $html .= '<ul>';
            foreach ($texto['compromissos'] as $c) {
                $html .= '<li style="text-align:justify;">' . $e($c) . '</li>';
            }
            $html .= '</ul>';
        } else {
            $html .= '<ol type="I">';
            foreach ($texto['compromissos'] as $c) {
                $html .= '<li style="text-align:justify;">' . $e($c) . '</li>';
            }
            $html .= '</ol>';
        }
        $html .= '<p style="text-align:justify;">' . $e($texto['ciencia']) . '</p>';

        $pdf->writeHTML($html, true, false, true, false, '');

        // Assinaturas (cerca de 60 mm): o bloco inteiro fica na mesma página
        if ($pdf->GetY() + 60 > $pdf->getPageHeight() - 18) {
            $pdf->AddPage();
        }
        $pdf->Ln(4);
        $pdf->writeHTML('<p>' . $e(($d['cidade'] !== '' ? $d['cidade'] . ', ' : '') . $d['data_extenso']) . '.</p>', true, false, true, false, '');
        $pdf->Ln(4);

        $y = $pdf->GetY();
        $w = 80;
        $left = 18;
        $right = 210 - 18 - $w;
        if ($d['assinatura'] !== '') {
            $pdf->Image('@' . $d['assinatura'], $left + 5, $y, $w - 10, 22, 'PNG', '', '', true, 300, '', false, false, 0, 'CM');
        }
        // Assinatura cadastrada por quem emitiu (termos anteriores à 1.5.3 não têm o campo)
        $tech_sig = (string) base64_decode((string) ($d['tecnico_assinatura'] ?? ''), true);
        if ($tech_sig !== '') {
            $pdf->Image('@' . $tech_sig, $right + 5, $y, $w - 10, 22, 'PNG', '', '', true, 300, '', false, false, 0, 'CM');
        }
        $line = $y + 24;
        $pdf->Line($left, $line, $left + $w, $line);
        $pdf->Line($right, $line, $right + $w, $line);

        $pdf->SetXY($left, $line + 1);
        $pdf->MultiCell($w, 4, $or($d['colaborador']['nome']) . "\nColaborador(a)", 0, 'C', false, 0);
        $pdf->SetXY($right, $line + 1);
        $pdf->MultiCell($w, 4, $d['tecnico'] . "\n" . ($devolucao ? 'Recebido por (TI)' : 'Entregue por (TI)'), 0, 'C', false, 1);

        $pdf->Ln(6);
        $pdf->SetFont('dejavusans', '', 7);
        $pdf->SetTextColor(110, 110, 110);
        // Assinatura com o nome digitado fica identificada como tal, sem se passar por manuscrita
        $forma = ($d['assinatura_tipo'] ?? '') === 'digitada' ? 'com o nome digitado' : 'desenhada';
        if ($d['assinatura'] !== '' && in_array($d['modo'] ?? '', ['email', 'link'], true)) {
            $registro = sprintf(
                'Assinatura eletrônica simples (%s) feita pelo(a) próprio(a) colaborador(a) em %s, autenticado(a) no GLPI com o usuário "%s", '
                . 'a partir do endereço IP %s, depois de declarar que leu e concorda com este termo. '
                . 'Termo enviado para assinatura por %s em %s. Documento gerado e arquivado no GLPI.',
                $forma,
                $d['data'],
                $d['login'],
                $d['ip'],
                $d['tecnico'],
                $d['enviado_em']
            );
        } elseif ($d['assinatura'] !== '') {
            $registro = sprintf(
                'Assinatura eletrônica simples (%s) do(a) colaborador(a) feita na tela em %s, na presença de %s, a partir do endereço IP %s. '
                . 'Documento gerado e arquivado no GLPI.',
                $forma,
                $d['data'],
                $d['tecnico'],
                $d['ip']
            );
        } else {
            $registro = sprintf(
                'Documento gerado no GLPI em %s para assinatura manual. Após assinado, anexe a via digitalizada à aba Documentos do equipamento.',
                $d['data']
            );
        }
        // Quem emitiu: a assinatura cadastrada vale pelo login de quem gerou o termo
        $login = (string) ($d['tecnico_login'] ?? '');
        if ($tech_sig !== '') {
            $registro .= sprintf(
                ' Assinatura do(a) responsável pela TI (%s) aplicada eletronicamente a partir da assinatura cadastrada no GLPI pelo(a) próprio(a) %s, autenticado(a) com o usuário "%s", que emitiu o termo.',
                ($d['tecnico_assinatura_tipo'] ?? '') === 'digitada' ? 'com o nome digitado' : 'desenhada',
                $d['tecnico'],
                $login
            );
        } elseif ($login !== '') {
            $registro .= sprintf(' Termo emitido por %s, autenticado(a) no GLPI com o usuário "%s".', $d['tecnico'], $login);
        }
        $registro .= ' Código do documento: ' . $d['codigo'] . '.';
        $pdf->MultiCell(0, 3.5, $registro, 0, 'L');

        return $pdf->Output('termo.pdf', 'S');
    }

    // ---------------------------------------------------------------- Tela

    /**
     * Carrega em lote os campos básicos dos ativos referenciados em todas as tabelas suportadas.
     *
     * @param array<string, int[]> $idsByType
     * @return array<string, array<int, array>>
     */
    private static function loadAssetsBatch(array $idsByType): array
    {
        global $DB;
        $loaded = [];
        foreach ($idsByType as $type => $ids) {
            $type = self::normalizeItemtype($type);
            $ids  = array_values(array_unique(array_filter(array_map('intval', $ids))));
            if (empty($ids)) {
                continue;
            }
            $table = self::ASSET_TYPES[$type]['table'];
            $loaded[$type] = [];
            foreach (
                $DB->request([
                    'SELECT' => ['id', 'name', 'is_deleted', 'is_template', 'entities_id', 'is_recursive'],
                    'FROM'   => $table,
                    'WHERE'  => ['id' => $ids],
                ]) as $assetRow
            ) {
                $loaded[$type][(int) $assetRow['id']] = $assetRow;
            }
        }
        return $loaded;
    }

    /** Pedidos aguardando assinatura de todos os equipamentos que o usuário pode ver (menu do plugin) */
    public static function getAllPendingRequests(): array
    {
        global $DB;
        self::ensureSchema();
        $viewable = self::viewableAssetTypes();
        if (empty($viewable)) {
            return [];
        }

        $where = [
            'status'   => self::PENDING,
            'itemtype' => array_keys($viewable),
        ] + getEntitiesRestrictCriteria(self::TABLE);

        $rawRows   = [];
        $idsByType = [];
        foreach (
            $DB->request([
                'FROM'  => self::TABLE,
                'WHERE' => $where,
                'ORDER' => 'date_send DESC',
                'LIMIT' => 400,
            ]) as $row
        ) {
            $type   = self::normalizeItemtype((string) ($row['itemtype'] ?? 'Computer'));
            $itemId = (int) ($row['computers_id'] ?? 0);
            if (!isset($viewable[$type]) || $itemId <= 0) {
                continue;
            }
            $row['itemtype'] = $type;
            $rawRows[] = $row;
            $idsByType[$type][] = $itemId;
        }

        if (empty($rawRows)) {
            return [];
        }

        $assets = self::loadAssetsBatch($idsByType);
        $rows   = [];
        foreach ($rawRows as $row) {
            $type   = $row['itemtype'];
            $itemId = (int) $row['computers_id'];
            $asset  = $assets[$type][$itemId] ?? null;
            if ($asset === null || !empty($asset['is_deleted'])) {
                continue;
            }
            if (!Session::haveAccessToEntity((int) ($asset['entities_id'] ?? 0), !empty($asset['is_recursive']))) {
                continue;
            }
            $row['item_name']     = (string) ($asset['name'] ?? '');
            $row['computer_name'] = $row['item_name'];
            $row['data']          = json_decode((string) $row['data'], true) ?: [];
            $rows[] = $row;
            if (count($rows) >= 200) {
                break;
            }
        }
        return $rows;
    }

    /** Tabela "Aguardando assinatura" (aba do equipamento e menu do plugin) */
    public static function showPending(array $rows, bool $with_asset = false): void
    {
        $e    = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $url  = self::webPath() . '/ajax/request.php';
        $mail = self::mailConfigured();
        ?>
        <div class="termo-card mt-4" id="termo-pendentes">
            <h4 class="section-title"><i class="ti ti-clock"></i> Aguardando assinatura</h4>
            <?php if (empty($rows)): ?>
                <p class="text-muted p-3">Nenhum termo aguardando assinatura.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover termo-history-table">
                    <thead>
                        <tr>
                            <th>Criado em</th>
                            <?php if ($with_asset): ?><th>Equipamento</th><?php endif; ?>
                            <th>Termo</th><th>Colaborador</th><th>Enviado por</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $p):
                            $itemtype   = self::normalizeItemtype((string) ($p['itemtype'] ?? 'Computer'));
                            $itemId     = (int) ($p['computers_id'] ?? 0);
                            $assetClass = $itemtype;
                            $asset      = class_exists($assetClass) ? new $assetClass() : null;
                            $can        = $asset !== null && $assetClass::canUpdate() && $asset->can($itemId, UPDATE);
                            $email      = (string) ($p['data']['colaborador']['email'] ?? '');
                            $itemName   = trim(self::fromCore((string) ($p['item_name'] ?? ($p['computer_name'] ?? ''))));
                            ?>
                            <tr data-request="<?= (int) $p['id'] ?>">
                                <td><strong><?= $e(Html::convDateTime($p['date_send'])) ?></strong><br>
                                    <small class="text-muted"><?= ($p['data']['canal'] ?? 'email') === 'link' ? 'Link copiado' : 'Por e-mail' ?></small></td>
                                <?php if ($with_asset): ?>
                                    <td>
                                        <span class="badge bg-secondary-lt me-1"><?= $e(self::assetLabel($itemtype)) ?></span>
                                        <a href="<?= $e(self::assetFormUrl($itemtype, $itemId)) ?>"><?= $e($itemName !== '' ? $itemName : '#' . $itemId) ?></a>
                                    </td>
                                <?php endif; ?>
                                <td><?= $p['type'] === 'devolucao' ? 'Devolução' : 'Entrega' ?> <span class="text-muted">(<?= $e($p['code']) ?>)</span></td>
                                <td><?= $e($p['data']['colaborador']['nome'] ?? '') ?><br><small class="text-muted"><?= $e($email) ?></small></td>
                                <td><?= $e($p['data']['tecnico'] ?? '') ?></td>
                                <td class="text-nowrap">
                                    <?php if ($can): ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary termo-copy-link" data-link="<?= $e(self::signUrl((int) $p['id'])) ?>">
                                        <i class="ti ti-copy"></i> Copiar link
                                    </button>
                                    <?php if ($mail && $email !== ''): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary termo-request-action" data-action="reenviar" data-url="<?= $e($url) ?>">
                                        <i class="ti ti-send"></i> <?= ($p['data']['canal'] ?? 'email') === 'link' ? 'Enviar por e-mail' : 'Reenviar' ?>
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger termo-request-action" data-action="cancelar" data-url="<?= $e($url) ?>">
                                        <i class="ti ti-x"></i> Cancelar
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /** Lê e normaliza o filtro de busca da listagem de termos arquivados. */
    public static function archivedSearchQuery(): string
    {
        $raw   = is_string($_GET['busca'] ?? null) ? $_GET['busca'] : '';
        $busca = trim(self::fromCore($raw));

        return function_exists('mb_substr') ? mb_substr($busca, 0, 150, 'UTF-8') : substr($busca, 0, 150);
    }

    /**
     * Lista termos arquivados em PDF na entidade ativa (até $limit registros mais recentes).
     * Perfis sem Document::canView() veem apenas documentos vinculados a equipamentos ativos visíveis.
     */
    public static function getArchivedDocuments(string $busca = '', int $limit = 100, ?bool &$has_more = null): array
    {
        global $DB;
        self::ensureSchema();

        $has_more = false;
        $limit    = max(1, min(500, $limit));

        if (!$DB->tableExists(self::DOCS_TABLE)) {
            return [];
        }

        $can_view_docs = Document::canView();
        $viewable      = self::viewableAssetTypes();
        if (!$can_view_docs && empty($viewable)) {
            return [];
        }

        $where = ['glpi_documents.is_deleted' => 0];
        $doc_ent = getEntitiesRestrictCriteria('glpi_documents', '', '', true);
        if (!empty($doc_ent)) {
            $where[] = $doc_ent;
        }
        if (!$can_view_docs) {
            $where[self::DOCS_TABLE . '.itemtype'] = array_keys($viewable);
        }

        $busca = trim($busca);
        if ($busca !== '') {
            $raw_like = '%' . strtr($busca, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%';
            $db_like  = self::dbValues(['v' => $raw_like])['v'];
            $doc_like = self::coreInput($raw_like);

            $or = [
                'glpi_documents.name'      => ['LIKE', $doc_like],
                'glpi_documents.comment'   => ['LIKE', $doc_like],
                self::DOCS_TABLE . '.code' => ['LIKE', $db_like],
            ];

            foreach ($viewable as $type => $meta) {
                $assetWhere = [
                    'name'        => ['LIKE', $doc_like],
                    'is_template' => 0,
                ];
                if (!$can_view_docs) {
                    $assetWhere['is_deleted'] = 0;
                }
                $assetEnt = getEntitiesRestrictCriteria($meta['table'], '', '', true);
                if (!empty($assetEnt)) {
                    $assetWhere[] = $assetEnt;
                }
                $matchedIds = [];
                foreach (
                    $DB->request([
                        'SELECT' => ['id'],
                        'FROM'   => $meta['table'],
                        'WHERE'  => $assetWhere,
                        'LIMIT'  => 500,
                    ]) as $m
                ) {
                    $matchedIds[] = (int) $m['id'];
                }
                if (!empty($matchedIds)) {
                    $or[] = [
                        self::DOCS_TABLE . '.itemtype'     => $type,
                        self::DOCS_TABLE . '.computers_id' => $matchedIds,
                    ];
                }
            }

            $where[] = ['OR' => $or];
        }

        $baseCriteria = [
            'SELECT' => [
                self::DOCS_TABLE . '.id AS doc_link_id',
                self::DOCS_TABLE . '.itemtype',
                self::DOCS_TABLE . '.computers_id',
                self::DOCS_TABLE . '.type',
                self::DOCS_TABLE . '.code',
                self::DOCS_TABLE . '.date_creation',
                'glpi_documents.id',
                'glpi_documents.name',
                'glpi_documents.comment',
                'glpi_documents.users_id',
                'glpi_documents.date_creation AS doc_date_creation',
            ],
            'FROM'       => self::DOCS_TABLE,
            'INNER JOIN' => [
                'glpi_documents' => [
                    'ON' => [
                        self::DOCS_TABLE => 'documents_id',
                        'glpi_documents' => 'id',
                    ],
                ],
            ],
            'WHERE'      => $where,
            'ORDER'      => [self::DOCS_TABLE . '.date_creation DESC', self::DOCS_TABLE . '.id DESC'],
        ];

        $rows      = [];
        $users     = [];
        $batchSize = max(100, $limit + 1);
        $offset    = 0;

        try {
            while (count($rows) <= $limit) {
                $chunkCriteria          = $baseCriteria;
                $chunkCriteria['START'] = $offset;
                $chunkCriteria['LIMIT'] = $batchSize;

                $chunk     = [];
                $idsByType = [];
                foreach ($DB->request($chunkCriteria) as $row) {
                    $type   = self::normalizeItemtype((string) ($row['itemtype'] ?? 'Computer'));
                    $itemId = (int) ($row['computers_id'] ?? 0);
                    $row['itemtype'] = $type;
                    $chunk[] = $row;
                    if ($itemId > 0) {
                        $idsByType[$type][] = $itemId;
                    }
                }

                if (empty($chunk)) {
                    break;
                }

                $assets = self::loadAssetsBatch($idsByType);
                foreach ($chunk as $row) {
                    $type   = $row['itemtype'];
                    $itemId = (int) ($row['computers_id'] ?? 0);
                    $asset  = $itemId > 0 ? ($assets[$type][$itemId] ?? null) : null;

                    $inEntity = $asset !== null
                        && Session::haveAccessToEntity((int) ($asset['entities_id'] ?? 0), !empty($asset['is_recursive']));

                    if (!$can_view_docs) {
                        if (
                            !isset($viewable[$type])
                            || $asset === null
                            || !empty($asset['is_deleted'])
                            || !empty($asset['is_template'])
                            || !$inEntity
                        ) {
                            continue;
                        }
                    }

                    if (count($rows) >= $limit) {
                        $has_more = true;
                        break 2;
                    }

                    $uid = (int) ($row['users_id'] ?? 0);
                    if ($uid > 0 && !isset($users[$uid])) {
                        $users[$uid] = self::userName($uid);
                    }
                    $row['issuer']              = $uid > 0 ? $users[$uid] : '';
                    $row['item_id']             = $asset !== null ? (int) $asset['id'] : 0;
                    $row['item_name']           = $asset !== null ? (string) ($asset['name'] ?? '') : '';
                    $row['item_is_deleted']     = $asset !== null ? (int) ($asset['is_deleted'] ?? 0) : 0;
                    $row['item_entities_id']    = $asset !== null ? (int) ($asset['entities_id'] ?? 0) : 0;
                    $row['item_is_recursive']   = $asset !== null ? (int) ($asset['is_recursive'] ?? 0) : 0;
                    $row['computer_id']         = $row['item_id'];
                    $row['computer_name']       = $row['item_name'];
                    $row['computer_is_deleted'] = $row['item_is_deleted'];
                    $rows[]                     = $row;
                }

                if (count($chunk) < $batchSize) {
                    break;
                }
                $offset += $batchSize;
            }
        } catch (\Throwable $ex) {
            Toolbox::logInFile('php-errors', 'Asset Terms: falha ao listar termos arquivados: ' . $ex->getMessage() . "\n");
            return [];
        }

        return $rows;
    }

    /** Lista e busca os termos de responsabilidade já arquivados em PDF. */
    public static function showArchived(array $rows, string $busca = '', int $item_id = 0, bool $has_more = false, string $itemtype = 'Computer'): void
    {
        global $CFG_GLPI;
        $e        = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $itemtype = self::normalizeItemtype($itemtype);
        $form_url = self::webPath() . '/front/term.php#termo-arquivados';
        $docs_url = $CFG_GLPI['root_doc']
            . '/front/document.php?is_deleted=0&criteria[0][field]=1&criteria[0][searchtype]=contains&criteria[0][value]=Termo+de&reset=reset';
        $clear_params = [];
        if ($itemtype !== 'Computer' || $item_id > 0) {
            $clear_params['itemtype'] = $itemtype;
        }
        if ($item_id > 0) {
            $clear_params['id'] = $item_id;
        }
        $clear_url = self::webPath() . '/front/term.php'
            . (!empty($clear_params) ? '?' . http_build_query($clear_params) : '')
            . '#termo-arquivados';
        ?>
        <div class="termo-card mt-4" id="termo-arquivados">
            <div class="termo-section-header">
                <h4 class="section-title mb-0"><i class="ti ti-archive"></i> Termos arquivados</h4>
                <?php if (Document::canView()): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= $e($docs_url) ?>">
                        <i class="ti ti-external-link"></i> Ver todos em Documentos
                    </a>
                <?php endif; ?>
            </div>
            <form class="termo-archived-search" method="get" action="<?= $e($form_url) ?>">
                <?php if ($itemtype !== 'Computer' || $item_id > 0): ?>
                    <input type="hidden" name="itemtype" value="<?= $e($itemtype) ?>">
                <?php endif; ?>
                <?php if ($item_id > 0): ?>
                    <input type="hidden" name="id" value="<?= $item_id ?>">
                <?php endif; ?>
                <input type="search" class="form-control form-control-sm" name="busca" value="<?= $e($busca) ?>" maxlength="150"
                       placeholder="Buscar equipamento, colaborador, código..." aria-label="Buscar termos arquivados">
                <button type="submit" class="btn btn-sm btn-outline-primary">
                    <i class="ti ti-search"></i> Buscar
                </button>
                <?php if ($busca !== ''): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= $e($clear_url) ?>">
                        <i class="ti ti-x"></i> Limpar
                    </a>
                <?php endif; ?>
            </form>
            <?php if (empty($rows)): ?>
                <p class="text-muted p-3 mb-0"><?= $busca !== '' ? 'Nenhum termo arquivado encontrado para a busca informada.' : 'Nenhum termo arquivado registrado ainda.' ?></p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped termo-history-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Equipamento</th>
                                <th>Termo</th>
                                <th>Detalhes</th>
                                <th>Arquivado por</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $t):
                                $date       = (string) (!empty($t['date_creation']) ? $t['date_creation'] : ($t['doc_date_creation'] ?? ''));
                                $rowType    = self::normalizeItemtype((string) ($t['itemtype'] ?? 'Computer'));
                                $assetClass = $rowType;
                                $itemId     = (int) ($t['item_id'] ?? ($t['computer_id'] ?? 0));
                                $rawId      = (int) ($t['computers_id'] ?? 0);
                                $itemDel    = !empty($t['item_is_deleted']) || !empty($t['computer_is_deleted']);
                                $inEntity   = $itemId > 0 && Session::haveAccessToEntity((int) ($t['item_entities_id'] ?? 0), !empty($t['item_is_recursive']));
                                $canType    = class_exists($assetClass) && $assetClass::canView();
                                $canItem    = $itemId > 0 && $inEntity && $canType;
                                $tipo_label = $t['type'] === 'devolucao' ? 'Devolução' : ($t['type'] === 'entrega' ? 'Entrega' : '-');
                                $code       = trim((string) ($t['code'] ?? ''));
                                $doc_name   = self::fromCore((string) ($t['name'] ?? ''));
                                $comment    = self::fromCore((string) ($t['comment'] ?? ''));
                                $issuer     = trim((string) ($t['issuer'] ?? ''));
                                ?>
                                <tr>
                                    <td><strong><?= $e(Html::convDateTime($date)) ?></strong></td>
                                    <td>
                                        <span class="badge bg-secondary-lt me-1"><?= $e(self::assetLabel($rowType)) ?></span>
                                        <?php if ($canItem):
                                            $item_name = trim(self::fromCore((string) ($t['item_name'] ?? ($t['computer_name'] ?? '')))); ?>
                                            <a href="<?= $e(self::assetFormUrl($rowType, $itemId)) ?>">
                                                <?= $e($item_name !== '' ? $item_name : '#' . $itemId) ?>
                                            </a>
                                            <?php if ($itemDel): ?>
                                                <span class="text-muted">(na lixeira)</span>
                                            <?php endif; ?>
                                        <?php elseif ($itemId > 0 && !$inEntity): ?>
                                            <span class="text-muted">Equipamento de outra entidade</span>
                                        <?php elseif ($itemId > 0): ?>
                                            <span class="text-muted">Equipamento sem permissão</span>
                                        <?php else: ?>
                                            <span class="text-muted">Equipamento removido<?= $rawId > 0 ? ' (#' . $rawId . ')' : '' ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $e($tipo_label) ?>
                                        <?php if ($code !== ''): ?>
                                            <span class="text-muted">(<?= $e($code) ?>)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><i class="ti ti-file-text text-primary"></i> <?= $e($doc_name) ?></div>
                                        <?php if ($comment !== ''): ?>
                                            <small class="text-muted"><?= $e($comment) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $e($issuer !== '' ? $issuer : '-') ?></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-primary"
                                           href="<?= $e($CFG_GLPI['root_doc'] . '/front/document.send.php?docid=' . (int) $t['id']) ?>"
                                           target="_blank" rel="noopener noreferrer">
                                            <i class="ti ti-file-type-pdf"></i> Abrir
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($has_more): ?>
                    <p class="termo-hint mt-2 mb-0">
                        Mostrando os <?= count($rows) ?> termos mais recentes<?= $busca !== '' ? ' para a busca' : '' ?>. Refine a busca<?= Document::canView() ? ' ou abra a tela de Documentos' : '' ?> para ver registros anteriores.
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /** Formulário do termo. $show_pending = false na página do menu, que já lista todos os pendentes. */
    public static function showTermoForm(CommonDBTM $item, bool $show_pending = true)
    {
        global $DB, $CFG_GLPI;

        self::ensureSchema();
        $itemtype  = self::normalizeItemtype($item->getType());
        $meta      = self::ASSET_TYPES[$itemtype];
        $cid       = (int) $item->getID();
        $specs     = self::getItemSpecs($item);
        $can_edit  = $item->can($cid, UPDATE);
        $states    = self::getStates($itemtype);
        $user_id   = (int) ($item->fields['users_id'] ?? 0);
        $user_name = self::holderName($item);
        $status    = (int) ($item->fields['states_id'] ?? 0) > 0
            ? self::fromCore((string) Dropdown::getDropdownName('glpi_states', (int) $item->fields['states_id']))
            : 'Não definido';
        $or = static fn ($v): string => trim((string) $v) !== '' ? (string) $v : 'Não informado';
        $e  = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        // Termos já gerados para este equipamento: os registrados pelo plugin (desde a 1.5.1) e,
        // dos documentos anteriores à atualização, os que têm o nome "Termo de ..."
        $origem = [['NOT' => ['pd.id' => null]]];
        $legacy = PluginAssettermsConfig::legacyMaxDocumentId();
        if ($legacy > 0) {
            $origem[] = ['doc.id' => ['<=', $legacy], 'doc.name' => ['LIKE', 'Termo de %']];
        }
        $termos = [];
        foreach (
            $DB->request([
                'SELECT'     => ['doc.id', 'doc.name', 'doc.date_creation', 'doc.comment', 'doc.users_id'],
                'FROM'       => 'glpi_documents_items AS di',
                'INNER JOIN' => ['glpi_documents AS doc' => ['ON' => ['di' => 'documents_id', 'doc' => 'id']]],
                'LEFT JOIN'  => [self::DOCS_TABLE . ' AS pd' => ['ON' => ['pd' => 'documents_id', 'doc' => 'id']]],
                'WHERE'      => [
                    'di.items_id'    => $cid,
                    'di.itemtype'    => $itemtype,
                    'doc.is_deleted' => 0,
                    'OR'             => $origem,
                ],
                'ORDER'      => 'doc.date_creation DESC',
            ]) as $d
        ) {
            $termos[] = $d;
        }

        $base             = self::webPath();
        $mail             = self::mailConfigured();
        $state_uso        = self::findState($states, 'Em uso');
        $state_est        = self::findState($states, 'Em estoque');
        $cfg              = PluginAssettermsConfig::getEffective((int) ($item->fields['entities_id'] ?? 0));
        $clausulas        = ['entrega' => self::clausula('entrega', $cfg), 'devolucao' => self::clausula('devolucao', $cfg)];
        $options          = $can_edit ? self::equipmentOptions($itemtype) : [];
        $tech_sig         = $can_edit && PluginAssettermsSignature::get((int) Session::getLoginUserID()) !== null;
        $checklistDefault = self::checklistDefaultsFor($itemtype);

        $visibleEquipFields = ['fabricante', 'modelo', 'tipo'];
        if (!empty($meta['has_hw_specs'])) {
            $visibleEquipFields = array_merge($visibleEquipFields, ['cpu', 'ram', 'disk']);
        }
        if (!empty($meta['has_os_spec'])) {
            $visibleEquipFields[] = 'so';
        }
        ?>
        <div class="termo-container">
            <div class="termo-card termo-header-card">
                <div class="termo-header-info">
                    <div class="termo-title-group">
                        <h3 class="termo-title"><i class="ti ti-file-certificate"></i> Termo de Responsabilidade</h3>
                        <p class="termo-subtitle">Entrega, devolução ou mudança de status do equipamento. Os termos ficam em PDF na aba Documentos.</p>
                    </div>
                    <div class="termo-badges">
                        <span class="termo-badge badge-type"><i class="<?= $e($meta['icon']) ?>"></i> Tipo: <strong><?= $e($meta['label']) ?></strong></span>
                        <span class="termo-badge badge-status"><i class="ti ti-activity"></i> Status: <strong><?= $e($status) ?></strong></span>
                        <span class="termo-badge badge-user"><i class="ti ti-user"></i> Usuário: <strong><?= $e($user_name) ?></strong></span>
                    </div>
                </div>

                <div class="termo-specs-grid">
                    <div class="spec-item"><span class="spec-label">Equipamento:</span><span class="spec-val"><?= $e($or(self::fromCore((string) ($item->fields['name'] ?? '')))) ?></span></div>
                    <div class="spec-item"><span class="spec-label">Fabricante / modelo:</span><span class="spec-val"><?= $e($or(trim($specs['fabricante'] . ' ' . $specs['modelo']))) ?></span></div>
                    <div class="spec-item"><span class="spec-label">Número de série:</span><span class="spec-val highlight"><?= $e($or($item->fields['serial'] ?? '')) ?></span></div>
                    <div class="spec-item"><span class="spec-label">Patrimônio:</span><span class="spec-val highlight"><?= $e($or($item->fields['otherserial'] ?? '')) ?></span></div>
                </div>
            </div>

            <?php if ($can_edit): ?>
            <form id="form-termo-responsabilidade" class="termo-card" method="post" action="<?= $e($base . '/ajax/save.php') ?>"
                  onsubmit="return false;">
                <input type="hidden" name="itemtype" value="<?= $e($itemtype) ?>">
                <input type="hidden" name="computers_id" value="<?= $cid ?>">
                <input type="hidden" name="_glpi_csrf_token" value="<?= Session::getNewCSRFToken() ?>">

                <h4 class="section-title"><i class="ti ti-forms"></i> O que você quer fazer?</h4>

                <div class="termo-form-row">
                    <div class="termo-radio-group termo-radio-3">
                        <label class="radio-card selected">
                            <input type="radio" name="tipo_termo" value="entrega" data-state="<?= $state_uso ?>" checked>
                            <div class="radio-content">
                                <span class="radio-title"><i class="<?= $e($meta['icon']) ?>"></i> Entrega ao colaborador</span>
                                <span class="radio-desc">Termo de responsabilidade. Vincula o equipamento ao colaborador e muda o status para <strong>Em uso</strong></span>
                            </div>
                        </label>
                        <label class="radio-card">
                            <input type="radio" name="tipo_termo" value="devolucao" data-state="<?= $state_est ?>">
                            <div class="radio-content">
                                <span class="radio-title"><i class="ti ti-archive"></i> Devolução à TI</span>
                                <span class="radio-desc">Termo de devolução. Desvincula o colaborador e muda o status para <strong>Em estoque</strong></span>
                            </div>
                        </label>
                        <label class="radio-card">
                            <input type="radio" name="tipo_termo" value="status" data-state="0">
                            <div class="radio-content">
                                <span class="radio-title"><i class="ti ti-refresh"></i> Somente ciclo de vida</span>
                                <span class="radio-desc">Muda só o status (manutenção, empréstimo, descarte...), <strong>sem termo</strong> e sem colaborador</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="termo-form-row two-cols">
                    <div class="termo-field" data-mode="termo">
                        <label class="termo-label">Colaborador *</label>
                        <?php
                        User::dropdown([
                            'name'   => 'users_id',
                            'value'  => $user_id,
                            'entity' => (int) ($item->fields['entities_id'] ?? 0),
                            'right'  => 'all',
                        ]);
                        ?>
                        <small class="termo-hint">Quem recebe ou devolve o equipamento.</small>
                    </div>

                    <div class="termo-field" data-mode="status" hidden>
                        <label class="termo-label" for="usuario_acao">Usuário do equipamento</label>
                        <select name="usuario_acao" id="usuario_acao" class="form-select">
                            <option value="manter">Manter: <?= $e($user_name) ?></option>
                            <option value="mover">Mover para outra pessoa</option>
                            <option value="remover">Remover o usuário do equipamento</option>
                        </select>
                        <small class="termo-hint">Nenhum termo é gerado. A mudança fica no histórico do equipamento.</small>

                        <div class="termo-mover" data-acao="mover" hidden>
                            <label class="termo-label mt-2">Novo usuário no GLPI</label>
                            <?php
                            User::dropdown([
                                'name'   => 'users_id_status',
                                'value'  => 0,
                                'entity' => (int) ($item->fields['entities_id'] ?? 0),
                                'right'  => 'all',
                            ]);
                            ?>
                            <label class="termo-label mt-2" for="nome_status">Nome de preferência</label>
                            <input type="text" name="nome_status" id="nome_status" class="form-control" maxlength="255"
                                   placeholder="Ex.: Maria (Comercial) ou o nome de quem não tem usuário no GLPI">
                            <small class="termo-hint">
                                Escolha o usuário do GLPI, digite o nome, ou os dois. O nome fica no campo
                                <strong>Usuário alternativo</strong> do equipamento. Sem usuário escolhido, é ele que identifica quem está com o equipamento.
                            </small>
                        </div>
                    </div>

                    <div class="termo-field">
                        <label class="termo-label" for="target_state_id">
                            <span data-mode="termo">Status após o termo</span><span data-mode="status" hidden>Novo status *</span>
                        </label>
                        <select name="target_state_id" id="target_state_id" class="form-select">
                            <option value="0">Não alterar</option>
                            <?php foreach ($states as $sid => $sname): ?>
                                <option value="<?= (int) $sid ?>" <?= $sid === $state_uso ? 'selected' : '' ?>><?= $e($sname) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="termo-hint" data-mode="termo">Muda sozinho conforme o tipo: entrega = Em uso, devolução = Em estoque.</small>
                    </div>
                </div>

                <div class="termo-field full-width" data-mode="termo">
                    <label class="termo-label">
                        <span data-show="entrega">Acessórios entregues</span>
                        <span data-show="devolucao" hidden>Acessórios devolvidos</span>
                    </label>
                    <div class="termo-checklist-grid">
                        <?php foreach (self::CHECKLIST as $key => $label): ?>
                            <label class="check-item">
                                <input type="checkbox" name="checklist[]" value="<?= $e($key) ?>" <?= in_array($key, $checklistDefault, true) ? 'checked' : '' ?>>
                                <?= $e($label) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="termo-field full-width" data-mode="termo">
                    <label class="termo-label">Dados do equipamento no termo</label>
                    <small class="termo-hint d-block mb-2">
                        Vêm do inventário. Confira e complete: clique no campo para escolher um valor já cadastrado no GLPI ou digite.
                        Muda só o termo, não o cadastro do equipamento.
                    </small>
                    <div class="termo-equip-grid">
                        <?php foreach ($visibleEquipFields as $field):
                            $label = self::EQUIP_FIELDS[$field]; ?>
                            <label class="termo-equip-item">
                                <span><?= $e($label) ?></span>
                                <input type="text" class="form-control" name="equip_<?= $field ?>" value="<?= $e($specs[$field] ?? '') ?>"
                                       list="termo-opt-<?= $field ?>" maxlength="150" autocomplete="off" placeholder="Não informado">
                                <datalist id="termo-opt-<?= $field ?>">
                                    <?php foreach ($options[$field] ?? [] as $opt): ?><option value="<?= $e($opt) ?>"></option><?php endforeach; ?>
                                </datalist>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="termo-field full-width">
                    <label class="termo-label" for="observacoes">
                        <span data-mode="termo">Observações sobre o estado do equipamento</span><span data-mode="status" hidden>Motivo / observação</span>
                    </label>
                    <textarea name="observacoes" id="observacoes" rows="2" maxlength="2000" class="form-control"
                        placeholder="Ex.: equipamento novo, sem riscos; etiqueta de patrimônio intacta; bateria testada."></textarea>
                </div>

                <?php foreach ($clausulas as $tipo => $t): ?>
                <div class="termo-clausula-box" data-show="<?= $tipo ?>" <?= $tipo === 'devolucao' ? 'hidden' : '' ?>>
                    <strong><?= $e($t['titulo']) ?></strong>
                    <p><?= $e($t['declaracao']) ?></p>
                    <?= $tipo === 'entrega' ? '<ol type="I">' : '<ul>' ?>
                        <?php foreach ($t['compromissos'] as $c): ?><li><?= $e($c) ?></li><?php endforeach; ?>
                    <?= $tipo === 'entrega' ? '</ol>' : '</ul>' ?>
                    <p><?= $e($t['ciencia']) ?></p>
                </div>
                <?php endforeach; ?>

                <div class="termo-signature-area" data-mode="termo">
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
                            <label class="termo-label"><i class="ti ti-writing"></i> Assinatura do colaborador, se ele estiver presente</label>
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
                            <label class="termo-label" for="sig-type-input"><i class="ti ti-signature"></i> Nome completo para assinatura eletrônica</label>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-sm-8">
                                <input type="text" id="sig-type-input" class="form-control" maxlength="120" placeholder="Nome completo do colaborador" value="" autocomplete="off">
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
                            <div id="sig-type-preview-text" class="sig-font-cursive1"></div>
                            <div class="sig-type-metadata">
                                <i class="ti ti-info-circle"></i>
                                <span>O colaborador digita o próprio nome. O PDF registra que a assinatura foi feita com o nome digitado.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="termo-actions-bar" data-mode="termo">
                    <button type="button" id="btn-save-termo" class="btn btn-primary btn-lg">
                        <i class="ti ti-check"></i> Gerar e arquivar termo
                    </button>
                    <?php if ($mail): ?>
                    <button type="button" id="btn-send-email" class="btn btn-outline-primary btn-lg">
                        <i class="ti ti-mail-forward"></i> Enviar por e-mail para assinatura
                    </button>
                    <?php endif; ?>
                    <button type="button" id="btn-send-link" class="btn btn-outline-primary btn-lg">
                        <i class="ti ti-link"></i> Gerar link de assinatura
                    </button>
                    <button type="button" id="btn-print-blank" class="btn btn-outline-secondary btn-lg">
                        <i class="ti ti-printer"></i> PDF para assinar no papel
                    </button>
                    <div id="termo-loading-spinner" class="spinner-border text-primary ms-3 d-none" role="status">
                        <span class="visually-hidden">Gerando...</span>
                    </div>
                </div>
                <small class="termo-hint d-block mt-2" data-mode="termo">
                    <?php if ($tech_sig): ?>
                        <i class="ti ti-signature"></i> O termo sai com a <strong>sua assinatura cadastrada</strong> no lado da TI.
                        <a href="<?= $e(PluginAssettermsSignature::pageUrl()) ?>" target="_blank" rel="noopener">Trocar</a>
                    <?php else: ?>
                        <i class="ti ti-signature"></i> Você não tem assinatura cadastrada: o lado da TI sai com o seu nome e o registro do seu usuário, sem a imagem.
                        <a href="<?= $e(PluginAssettermsSignature::pageUrl()) ?>" target="_blank" rel="noopener">Cadastrar minha assinatura</a>
                    <?php endif; ?>
                    <br>
                    <?php if ($mail): ?>
                        Pelo e-mail ou pelo link, o colaborador entra no GLPI com o próprio usuário e assina. O equipamento só é atualizado depois da assinatura.
                    <?php else: ?>
                        O envio de e-mails do GLPI não está configurado. Use <strong>Gerar link de assinatura</strong> e mande o link ao colaborador
                        pelo Teams, WhatsApp ou chat: ele entra no GLPI com o próprio usuário e assina. O equipamento só é atualizado depois da assinatura.
                    <?php endif; ?>
                </small>

                <div class="termo-actions-bar" data-mode="status" hidden>
                    <button type="button" id="btn-status" class="btn btn-primary btn-lg">
                        <i class="ti ti-refresh"></i> Atualizar status
                    </button>
                </div>

                <div id="termo-alert-box" class="alert d-none mt-3"></div>
            </form>
            <?php endif; ?>

            <?php
            $pendentes = $show_pending ? self::getPendingRequests($cid, $itemtype) : [];
            if (!empty($pendentes)) {
                self::showPending($pendentes);
            }
            ?>

            <div class="termo-card mt-4">
                <h4 class="section-title"><i class="ti ti-history"></i> Termos deste equipamento</h4>
                <?php if (empty($termos)): ?>
                    <p class="text-muted p-3">Nenhum termo arquivado para este equipamento.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped termo-history-table">
                            <thead>
                                <tr><th>Data</th><th>Documento</th><th>Detalhes</th><th>Arquivado por</th><th></th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($termos as $t): ?>
                                    <tr>
                                        <td><strong><?= $e(Html::convDateTime($t['date_creation'])) ?></strong></td>
                                        <td><i class="ti ti-file-text text-primary"></i> <?= $e(self::fromCore((string) $t['name'])) ?></td>
                                        <td><?= $e(self::fromCore((string) $t['comment'])) ?></td>
                                        <td><?= $e(self::userName((int) $t['users_id']) ?: '-') ?></td>
                                        <td>
                                            <a href="<?= $e($CFG_GLPI['root_doc'] . '/front/document.send.php?docid=' . (int) $t['id']) ?>"
                                               target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                                                <i class="ti ti-eye"></i> Abrir
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

if (
    class_exists('CommonGLPI')
    && property_exists('CommonGLPI', 'rightname')
    && (new ReflectionProperty('CommonGLPI', 'rightname'))->hasType()
) {
    class PluginAssettermsTerm extends CommonGLPI implements PluginAssettermsTermConstants
    {
        use PluginAssettermsTermBody;

        public static string $rightname = 'computer';
    }
} else {
    class PluginAssettermsTerm extends CommonGLPI implements PluginAssettermsTermConstants
    {
        use PluginAssettermsTermBody;

        public static $rightname = 'computer';
    }
}

<?php

/**
 * ------------------------------------------------------------------------
 * Asset Terms - Suíte de testes automatizados (GLPI 10, 11 e 12)
 * ------------------------------------------------------------------------
 */

require_once __DIR__ . '/bootstrap.php';

$pluginDir = GLPI_ROOT . '/assetterms';
require_once $pluginDir . '/setup.php';
require_once $pluginDir . '/hook.php';
require_once $pluginDir . '/inc/term.class.php';
require_once $pluginDir . '/inc/config.class.php';
require_once $pluginDir . '/inc/menu.class.php';
require_once $pluginDir . '/inc/signature.class.php';

class TestRunner
{
    public int $passed = 0;
    public int $failed = 0;

    public function assertTrue(bool $cond, string $msg): void
    {
        if ($cond) {
            $this->passed++;
            return;
        }
        $this->failed++;
        fwrite(STDERR, "[FAIL] {$msg}\n");
    }

    public function assertSame($expected, $actual, string $msg): void
    {
        if ($expected === $actual) {
            $this->passed++;
            return;
        }
        $this->failed++;
        fwrite(STDERR, "[FAIL] {$msg} | esperado=" . var_export($expected, true) . " atual=" . var_export($actual, true) . "\n");
    }

    public function assertContains(string $needle, string $haystack, string $msg): void
    {
        if (strpos($haystack, $needle) !== false) {
            $this->passed++;
            return;
        }
        $this->failed++;
        fwrite(STDERR, "[FAIL] {$msg} | esperado conter: {$needle}\n");
    }

    public function assertNotContains(string $needle, string $haystack, string $msg): void
    {
        if (strpos($haystack, $needle) === false) {
            $this->passed++;
            return;
        }
        $this->failed++;
        fwrite(STDERR, "[FAIL] {$msg} | não deveria conter: {$needle}\n");
    }
}

$t = new TestRunner();

/** Helper para executar um script AJAX capturando JSON e status HTTP */
function run_ajax(string $script, array $post): array
{
    $_SERVER['REQUEST_METHOD']         = 'POST';
    $_SERVER['HTTP_X_GLPI_CSRF_TOKEN'] = 'valid-csrf';
    $_SERVER['REMOTE_ADDR']            = '10.0.0.50';
    $_POST                             = $post;
    ob_start();
    include $script;
    $out = ob_get_clean();
    return [json_decode((string) $out, true), (int) http_response_code()];
}

/** Helper para popular massa de dados padrão em cada bateria de testes */
function seed_base_data(): void
{
    global $DB;
    test_init_core_tables();
    Log::$entries         = [];
    GLPIMailer::$sent     = [];
    Toolbox::$logs        = [];
    Plugin::$webDirCalled = false;
    Html::$rightErrorCalled = false;
    PluginAssettermsTerm::resetSchemaCheck();

    $GLOBALS['TEST_ENTITY_PARENTS'] = [1 => [0], 2 => [0]];
    $GLOBALS['TEST_RIGHTS'] = [
        'computer'   => READ | UPDATE,
        'phone'      => READ | UPDATE,
        'monitor'    => READ | UPDATE,
        'peripheral' => READ | UPDATE,
        'document'   => READ,
        'config'     => READ | UPDATE,
    ];
    $_SESSION['glpiID']             = 10;
    $_SESSION['glpiname']           = 'tech.user';
    $_SESSION['glpiactiveentities'] = [0];
    $_SESSION['glpiactive_entity']  = 0;

    $DB->insert('glpi_entities', ['id' => 0, 'name' => 'Matriz', 'town' => 'São Paulo']);
    $DB->insert('glpi_entities', ['id' => 1, 'name' => 'Filial Sul', 'town' => 'Curitiba']);
    $DB->insert('glpi_entities', ['id' => 2, 'name' => 'Filial Norte', 'town' => 'Manaus']);

    // Técnico (10), Colaborador A (20), Colaborador B (21)
    $DB->insert('glpi_users', ['id' => 10, 'name' => 'tech.user', 'firstname' => 'Ana', 'realname' => 'Silva', 'email' => 'ana@empresa.test', 'is_active' => 1]);
    $DB->insert('glpi_users', ['id' => 20, 'name' => 'colab.a', 'firstname' => 'Carlos', 'realname' => 'Souza', 'registration_number' => 'M100', 'email' => 'carlos@empresa.test', 'is_active' => 1]);
    $DB->insert('glpi_users', ['id' => 21, 'name' => 'colab.b', 'firstname' => 'Julia', 'realname' => 'Lima', 'registration_number' => 'M200', 'email' => 'julia@empresa.test', 'is_active' => 1]);

    $DB->insert('glpi_profiles_users', ['users_id' => 20, 'entities_id' => 0, 'is_recursive' => 1]);
    $DB->insert('glpi_profiles_users', ['users_id' => 21, 'entities_id' => 1, 'is_recursive' => 0]);

    // Status
    $DB->insert('glpi_states', ['id' => 1, 'name' => 'Em uso', 'completename' => 'Em uso', 'is_visible_computer' => 1, 'is_visible_phone' => 1, 'is_visible_monitor' => 1, 'is_visible_peripheral' => 1]);
    $DB->insert('glpi_states', ['id' => 2, 'name' => 'Em estoque', 'completename' => 'Em estoque', 'is_visible_computer' => 1, 'is_visible_phone' => 1, 'is_visible_monitor' => 1, 'is_visible_peripheral' => 1]);
    $DB->insert('glpi_states', ['id' => 3, 'name' => 'Somente PC', 'completename' => 'Somente PC', 'is_visible_computer' => 1, 'is_visible_phone' => 0, 'is_visible_monitor' => 0, 'is_visible_peripheral' => 0]);

    if (version_compare(GLPI_VERSION, '11.0.0-dev', '>=')) {
        foreach (['Computer', 'Phone', 'Monitor', 'Peripheral'] as $type) {
            $DB->insert('glpi_dropdownvisibilities', ['itemtype' => 'State', 'items_id' => 1, 'visible_itemtype' => $type, 'is_visible' => 1]);
            $DB->insert('glpi_dropdownvisibilities', ['itemtype' => 'State', 'items_id' => 2, 'visible_itemtype' => $type, 'is_visible' => 1]);
        }
        $DB->insert('glpi_dropdownvisibilities', ['itemtype' => 'State', 'items_id' => 3, 'visible_itemtype' => 'Computer', 'is_visible' => 1]);
    }

    // Fabricantes / Modelos / Tipos / Hardware / SO
    $DB->insert('glpi_manufacturers', ['id' => 1, 'name' => 'Dell Inc.']);
    $DB->insert('glpi_manufacturers', ['id' => 2, 'name' => 'LG']);
    $DB->insert('glpi_computermodels', ['id' => 1, 'name' => 'Latitude 5440']);
    $DB->insert('glpi_computertypes', ['id' => 1, 'name' => 'Notebook']);
    $DB->insert('glpi_phonemodels', ['id' => 1, 'name' => 'Galaxy A55']);
    $DB->insert('glpi_phonetypes', ['id' => 1, 'name' => 'Smartphone']);
    $DB->insert('glpi_monitormodels', ['id' => 1, 'name' => 'UltraSharp 24']);
    $DB->insert('glpi_monitortypes', ['id' => 1, 'name' => 'LED']);
    $DB->insert('glpi_peripheralmodels', ['id' => 1, 'name' => 'MX Master 3']);
    $DB->insert('glpi_peripheraltypes', ['id' => 1, 'name' => 'Mouse sem fio']);

    $DB->insert('glpi_deviceprocessors', ['id' => 1, 'designation' => 'Intel Core i5-1335U']);
    $DB->insert('glpi_operatingsystems', ['id' => 1, 'name' => 'Windows 11']);
    $DB->insert('glpi_operatingsystems', ['id' => 2, 'name' => 'Android']);
    $DB->insert('glpi_operatingsystemversions', ['id' => 1, 'name' => 'Pro']);
    $DB->insert('glpi_operatingsystemversions', ['id' => 2, 'name' => '14']);

    // Ativos com mesmo ID numérico (5) para validar isolamento estrito (itemtype, id)
    $DB->insert('glpi_computers', [
        'id' => 5, 'name' => 'NB-CORP-05', 'entities_id' => 0, 'manufacturers_id' => 1,
        'computermodels_id' => 1, 'computertypes_id' => 1, 'serial' => 'SN-NB-05', 'otherserial' => 'PAT-NB-05',
    ]);
    $DB->insert('glpi_items_deviceprocessors', ['items_id' => 5, 'itemtype' => 'Computer', 'deviceprocessors_id' => 1]);
    $DB->insert('glpi_items_devicememories', ['items_id' => 5, 'itemtype' => 'Computer', 'size' => 16384]);
    $DB->insert('glpi_items_deviceharddrives', ['items_id' => 5, 'itemtype' => 'Computer', 'capacity' => 524288]);
    $DB->insert('glpi_items_operatingsystems', ['items_id' => 5, 'itemtype' => 'Computer', 'operatingsystems_id' => 1, 'operatingsystemversions_id' => 1]);

    $DB->insert('glpi_phones', [
        'id' => 5, 'name' => 'CEL-CORP-05', 'entities_id' => 0, 'manufacturers_id' => 0, 'brand' => 'Samsung',
        'phonemodels_id' => 1, 'phonetypes_id' => 1, 'serial' => 'IMEI-05', 'otherserial' => 'PAT-CEL-05',
    ]);
    $DB->insert('glpi_items_operatingsystems', ['items_id' => 5, 'itemtype' => 'Phone', 'operatingsystems_id' => 2, 'operatingsystemversions_id' => 2]);

    $DB->insert('glpi_monitors', [
        'id' => 5, 'name' => 'MON-CORP-05', 'entities_id' => 0, 'manufacturers_id' => 2,
        'monitormodels_id' => 1, 'monitortypes_id' => 1, 'serial' => 'SN-MON-05', 'otherserial' => 'PAT-MON-05',
    ]);

    $DB->insert('glpi_peripherals', [
        'id' => 5, 'name' => 'PER-CORP-05', 'entities_id' => 0, 'manufacturers_id' => 0, 'brand' => 'Logitech',
        'peripheralmodels_id' => 1, 'peripheraltypes_id' => 1, 'serial' => 'SN-PER-05', 'otherserial' => 'PAT-PER-05',
    ]);
}

// =====================================================================
// 1. Setup, registro de abas, hooks de assets e ReflectionProperty
// =====================================================================
seed_base_data();
$ver = plugin_version_assetterms();
$t->assertSame('1.6.0-rc1', $ver['version'], 'setup.php declara versão 1.6.0-rc1');
$t->assertSame('7.4', $ver['requirements']['php']['min'], 'PHP mínimo 7.4');

$expectedPages = [
    '/glpi/front/computer.form.php?id=5',
    '/glpi/front/phone.form.php?id=5',
    '/glpi/front/monitor.form.php?id=5',
    '/glpi/front/peripheral.form.php?id=5',
    '/glpi/plugins/assetterms/front/sign.php?id=1',
    '/glpi/plugins/assetterms/front/term.php',
    '/glpi/plugins/assetterms/front/config.php',
    '/glpi/plugins/assetterms/front/signature.php',
];
$expectedPrefix = version_compare(GLPI_VERSION, '11.0.0-dev', '>=') ? '' : 'public/';
foreach ($expectedPages as $uri) {
    $PLUGIN_HOOKS = [];
    $_SERVER['REQUEST_URI'] = $uri;
    plugin_init_assetterms();
    $t->assertSame(['Computer', 'Phone', 'Monitor', 'Peripheral'], Plugin::$registered['PluginAssettermsTerm']['addtabon'] ?? [], "addtabon registra 4 tipos ({$uri})");
    $t->assertSame([$expectedPrefix . 'js/assetterms.js'], $PLUGIN_HOOKS['add_javascript']['assetterms'] ?? [], "JS carregado em {$uri}");
    $t->assertSame([$expectedPrefix . 'css/assetterms.css'], $PLUGIN_HOOKS['add_css']['assetterms'] ?? [], "CSS carregado em {$uri}");
}
$PLUGIN_HOOKS = [];
$_SERVER['REQUEST_URI'] = '/glpi/front/ticket.form.php?id=1';
plugin_init_assetterms();
$t->assertTrue(empty($PLUGIN_HOOKS['add_javascript']['assetterms']), 'JS não carrega fora das páginas esperadas');

Plugin::$webDirCalled = false;
$wp = PluginAssettermsTerm::webPath();
$t->assertSame('/glpi/plugins/assetterms', $wp, 'webPath retorna caminho correto');
if (version_compare(GLPI_VERSION, '11.0.0-dev', '>=')) {
    $t->assertTrue(!Plugin::$webDirCalled, 'GLPI 11/12 não chama Plugin::getWebDir()');
}

// =====================================================================
// 2. Migração v3 -> v4 + backfill + ensureSchema idempotente
// =====================================================================
seed_base_data();
$DB->doQuery("CREATE TABLE `glpi_plugin_assetterms_requests` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `computers_id` int unsigned NOT NULL DEFAULT '0',
    `entities_id` int unsigned NOT NULL DEFAULT '0',
    `users_id` int unsigned NOT NULL DEFAULT '0',
    `users_id_tech` int unsigned NOT NULL DEFAULT '0',
    `type` varchar(20) NOT NULL DEFAULT 'entrega',
    `code` varchar(100) NOT NULL DEFAULT '',
    `status` tinyint NOT NULL DEFAULT '0',
    `data` longtext,
    `documents_id` int unsigned NOT NULL DEFAULT '0',
    `sign_ip` varchar(45) NOT NULL DEFAULT '',
    `date_send` timestamp NULL DEFAULT NULL,
    `date_signed` timestamp NULL DEFAULT NULL,
    `date_creation` timestamp NULL DEFAULT NULL,
    `date_mod` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
)");
$DB->doQuery("CREATE TABLE `glpi_plugin_assetterms_documents` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `documents_id` int unsigned NOT NULL DEFAULT '0',
    `computers_id` int unsigned NOT NULL DEFAULT '0',
    `type` varchar(20) NOT NULL DEFAULT 'entrega',
    `code` varchar(100) NOT NULL DEFAULT '',
    `date_creation` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
)");
Config::setConfigurationValues(PluginAssettermsConfig::CONFIG_CONTEXT, ['schema_version' => '3']);
$DB->insert('glpi_plugin_assetterms_requests', ['computers_id' => 5, 'entities_id' => 0, 'users_id' => 20, 'type' => 'entrega', 'code' => 'LEGACY-1', 'status' => 0]);
$DB->insert('glpi_plugin_assetterms_documents', ['documents_id' => 99, 'computers_id' => 5, 'type' => 'entrega', 'code' => 'LEGACY-DOC-1']);

PluginAssettermsTerm::ensureSchema();
$t->assertTrue($DB->fieldExists(PluginAssettermsTerm::TABLE, 'itemtype'), 'Migração v3->v4 adiciona itemtype em requests');
$t->assertTrue($DB->fieldExists(PluginAssettermsTerm::DOCS_TABLE, 'itemtype'), 'Migração v3->v4 adiciona itemtype em documents');
$t->assertTrue($DB->indexExists(PluginAssettermsTerm::TABLE, 'item'), 'Migração v3->v4 adiciona índice item em requests');
$t->assertTrue($DB->indexExists(PluginAssettermsTerm::DOCS_TABLE, 'item'), 'Migração v3->v4 adiciona índice item em documents');

$migratedReq = PluginAssettermsTerm::getRequest(1);
$t->assertSame('Computer', $migratedReq['itemtype'] ?? '', 'Registro legado em requests assume itemtype=Computer');
$docRows = $DB->request(['FROM' => PluginAssettermsTerm::DOCS_TABLE, 'WHERE' => ['documents_id' => 99]]);
$t->assertSame('Computer', $docRows[0]['itemtype'] ?? '', 'Registro legado em documents assume itemtype=Computer');
$sv = Config::getConfigurationValues(PluginAssettermsConfig::CONFIG_CONTEXT, ['schema_version']);
$t->assertSame('4', $sv['schema_version'] ?? '', 'schema_version atualizado para 4');

// Chamada idempotente
PluginAssettermsTerm::resetSchemaCheck();
PluginAssettermsTerm::ensureSchema();
PluginAssettermsConfig::migrate();
$t->assertSame('4', Config::getConfigurationValues(PluginAssettermsConfig::CONFIG_CONTEXT, ['schema_version'])['schema_version'] ?? '', 'Migração repetida mantém schema_version=4');

// =====================================================================
// 3. Helpers, checklist, especificações, estados e PDF
// =====================================================================
seed_base_data();
plugin_assetterms_install();

$t->assertSame('Computer', PluginAssettermsTerm::normalizeItemtype(null), 'normalizeItemtype(null) retorna Computer');
$t->assertSame('Computer', PluginAssettermsTerm::normalizeItemtype('Ticket'), 'normalizeItemtype(Ticket) retorna Computer');
$t->assertSame('Phone', PluginAssettermsTerm::normalizeItemtype('Phone'), 'normalizeItemtype(Phone) retorna Phone');

$t->assertSame(['fonte', 'cabo'], PluginAssettermsTerm::checklistDefaultsFor('Computer'), 'Checklist padrão Computer');
$t->assertSame(['fonte'], PluginAssettermsTerm::checklistDefaultsFor('Phone'), 'Checklist padrão Phone');
$t->assertSame(['cabo'], PluginAssettermsTerm::checklistDefaultsFor('Monitor'), 'Checklist padrão Monitor');
$t->assertSame([], PluginAssettermsTerm::checklistDefaultsFor('Peripheral'), 'Checklist padrão Peripheral');

$comp  = PluginAssettermsTerm::loadAsset('Computer', 5);
$phone = PluginAssettermsTerm::loadAsset('Phone', 5);
$mon   = PluginAssettermsTerm::loadAsset('Monitor', 5);
$per   = PluginAssettermsTerm::loadAsset('Peripheral', 5);

$t->assertTrue(PluginAssettermsTerm::isSupportedItem($comp) && PluginAssettermsTerm::isSupportedItem($phone), 'isSupportedItem aceita Computer/Phone');
$t->assertTrue(!PluginAssettermsTerm::isSupportedItem(new User()), 'isSupportedItem recusa User');

$cSpecs = PluginAssettermsTerm::getItemSpecs($comp);
$t->assertSame('Intel Core i5-1335U', $cSpecs['cpu'], 'Computer lê CPU');
$t->assertSame('16 GB', $cSpecs['ram'], 'Computer lê RAM');
$t->assertSame('512 GB', $cSpecs['disk'], 'Computer lê Disco');
$t->assertSame('Windows 11 Pro', $cSpecs['so'], 'Computer lê SO');

$pSpecs = PluginAssettermsTerm::getItemSpecs($phone);
$t->assertSame('Samsung', $pSpecs['fabricante'], 'Phone usa fallback brand quando manufacturers_id <= 0');
$t->assertSame('Android 14', $pSpecs['so'], 'Phone lê SO');
$t->assertSame('', $pSpecs['cpu'], 'Phone não consulta hardware de CPU');

$mSpecs = PluginAssettermsTerm::getItemSpecs($mon);
$t->assertSame('LG', $mSpecs['fabricante'], 'Monitor lê fabricante');
$t->assertSame('', $mSpecs['so'], 'Monitor não lê SO');

$perSpecs = PluginAssettermsTerm::getItemSpecs($per);
$t->assertSame('Logitech', $perSpecs['fabricante'], 'Peripheral usa fallback brand');

$compStates  = PluginAssettermsTerm::getStates('Computer');
$phoneStates = PluginAssettermsTerm::getStates('Phone');
$t->assertTrue(isset($compStates[3]), 'Computer vê status específico');
$t->assertTrue(!isset($phoneStates[3]), 'Phone não vê status exclusivo de Computer');

$phoneOpts = PluginAssettermsTerm::equipmentOptions('Phone');
$t->assertSame(['Galaxy A55'], $phoneOpts['modelo'], 'equipmentOptions(Phone) lê modelos de telefone');
$t->assertSame([], $phoneOpts['cpu'], 'equipmentOptions(Phone) não carrega CPU');
$t->assertTrue(in_array('Android 14', $phoneOpts['so'], true), 'equipmentOptions(Phone) carrega SO');

// PDF: Categoria + Configuração condicional
$monData = PluginAssettermsTerm::withDate(PluginAssettermsTerm::buildData($mon, [
    'tipo' => 'entrega', 'users_id' => 20, 'equipamento' => [], 'checklist' => ['Cabo de força'], 'state_id' => 1, 'observacoes' => '',
], 10), time()) + ['assinatura' => '', 'assinatura_tipo' => 'desenhada', 'modo' => 'manual', 'ip' => '127.0.0.1'];
TCPDF::$lastRendered = [];
PluginAssettermsTerm::buildPdf($monData);
$t->assertContains('<td><b>Categoria</b></td>', TCPDF::$lastRendered['html'] ?? '', 'PDF exibe linha Categoria');
$t->assertContains('Monitor', TCPDF::$lastRendered['html'] ?? '', 'PDF exibe rótulo Monitor');
$t->assertNotContains('Configuração', TCPDF::$lastRendered['html'] ?? '', 'PDF de Monitor oculta Configuração quando vazia');

$phoneData = PluginAssettermsTerm::withDate(PluginAssettermsTerm::buildData($phone, [
    'tipo' => 'entrega', 'users_id' => 20, 'equipamento' => [], 'checklist' => ['Fonte / carregador original'], 'state_id' => 1, 'observacoes' => '',
], 10), time()) + ['assinatura' => '', 'assinatura_tipo' => 'desenhada', 'modo' => 'manual', 'ip' => '127.0.0.1'];
TCPDF::$lastRendered = [];
PluginAssettermsTerm::buildPdf($phoneData);
$t->assertContains('Telefone / Smartphone', TCPDF::$lastRendered['html'] ?? '', 'PDF exibe rótulo Telefone / Smartphone');
$t->assertContains('Configuração', TCPDF::$lastRendered['html'] ?? '', 'PDF de Phone mantém Configuração quando há SO');
$t->assertContains('Android 14', TCPDF::$lastRendered['html'] ?? '', 'PDF de Phone inclui SO na Configuração');

// =====================================================================
// 4. Arquivamento, ciclo de vida e isolamento de cancelPending por (itemtype, id)
// =====================================================================
seed_base_data();
plugin_assetterms_install();

// Envia link pendente para Computer #5, Phone #5, Monitor #5 e Peripheral #5 (mesmo ID!)
foreach (['Computer', 'Phone', 'Monitor', 'Peripheral'] as $type) {
    [$res, $code] = run_ajax($pluginDir . '/ajax/save.php', [
        'itemtype'        => $type,
        'computers_id'    => 5,
        'tipo_termo'      => 'entrega',
        'users_id'        => 20,
        'target_state_id' => 1,
        'modo'            => 'link',
        'checklist'       => PluginAssettermsTerm::checklistDefaultsFor($type),
    ]);
    $t->assertSame(200, $code, "Link criado para {$type} #5");
    $t->assertTrue(!empty($res['success']), "Sucesso ao gerar link para {$type} #5");
}

$t->assertSame(1, count(PluginAssettermsTerm::getPendingRequests(5, 'Phone')), '1 pendente específico de Phone #5');
$t->assertSame(4, count(PluginAssettermsTerm::getAllPendingRequests()), '4 pendentes totais entre os 4 tipos');

// Assina somente o termo do Phone #5 via ajax/sign.php (como colaborador 20)
$phoneReq = PluginAssettermsTerm::getPendingRequests(5, 'Phone')[0];
$_SESSION['glpiID'] = 20;
$pngBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
[$signRes, $signCode] = run_ajax($pluginDir . '/ajax/sign.php', [
    'id'              => (int) $phoneReq['id'],
    'aceite'          => '1',
    'signature_image' => $pngBase64,
    'signature_kind'  => 'desenhada',
]);
$t->assertSame(200, $signCode, 'Assinatura de Phone #5 aceita');
$t->assertTrue(!empty($signRes['success']), 'Assinatura de Phone #5 concluída');

// Verifica que APENAS Phone #5 foi atualizado, arquivado e teve pendentes tratados; os outros 3 seguem pendentes!
$phoneAfter = PluginAssettermsTerm::loadAsset('Phone', 5);
$compAfter  = PluginAssettermsTerm::loadAsset('Computer', 5);
$t->assertSame(20, (int) $phoneAfter->fields['users_id'], 'Phone #5 vinculado ao colaborador 20');
$t->assertSame(1, (int) $phoneAfter->fields['states_id'], 'Phone #5 com status Em uso');
$t->assertSame(0, (int) $compAfter->fields['users_id'], 'Computer #5 permanece sem colaborador');

$t->assertSame(0, count(PluginAssettermsTerm::getPendingRequests(5, 'Phone')), 'Phone #5 não tem mais pendentes');
$t->assertSame(1, count(PluginAssettermsTerm::getPendingRequests(5, 'Computer')), 'Computer #5 mantém pendente isolado');
$t->assertSame(1, count(PluginAssettermsTerm::getPendingRequests(5, 'Monitor')), 'Monitor #5 mantém pendente isolado');
$t->assertSame(1, count(PluginAssettermsTerm::getPendingRequests(5, 'Peripheral')), 'Peripheral #5 mantém pendente isolado');

// Verifica Document_Item e tabela de documentos do plugin
$docItems = $DB->request(['FROM' => 'glpi_documents_items', 'WHERE' => ['itemtype' => 'Phone', 'items_id' => 5]]);
$t->assertSame(1, count($docItems), 'Document_Item gravado com itemtype=Phone e items_id=5');
$pluginDocs = $DB->request(['FROM' => PluginAssettermsTerm::DOCS_TABLE, 'WHERE' => ['itemtype' => 'Phone', 'computers_id' => 5]]);
$t->assertSame(1, count($pluginDocs), 'DOCS_TABLE gravada com itemtype=Phone e computers_id=5');

// Verifica Log::history com itemtype correto
$phoneLogs = array_values(array_filter(Log::$entries, static fn (array $e): bool => $e['itemtype'] === 'Phone' && $e['items_id'] === 5));
$t->assertTrue(count($phoneLogs) >= 2, 'Log::history registrado para Phone #5');

// Verifica notificação por e-mail contendo link para phone.form.php
$t->assertSame(2, count(GLPIMailer::$sent), 'E-mails pós-assinatura enviados para colaborador e técnico');
$t->assertContains('/front/phone.form.php?id=5', GLPIMailer::$sent[1]['body'] ?? '', 'E-mail ao técnico aponta para phone.form.php?id=5');

// Arquiva na tela para Monitor #5 e Peripheral #5 (volta sessão para técnico 10)
$_SESSION['glpiID'] = 10;
foreach (['Monitor', 'Peripheral'] as $type) {
    [$res, $code] = run_ajax($pluginDir . '/ajax/save.php', [
        'itemtype'        => $type,
        'computers_id'    => 5,
        'tipo_termo'      => 'entrega',
        'users_id'        => 20,
        'target_state_id' => 1,
        'modo'            => 'arquivar',
        'signature_image' => $pngBase64,
        'checklist'       => PluginAssettermsTerm::checklistDefaultsFor($type),
    ]);
    $t->assertSame(200, $code, "Arquivamento na tela para {$type} #5");
    $t->assertTrue(!empty($res['success']), "Sucesso no arquivamento de {$type} #5");
    $t->assertSame(0, count(PluginAssettermsTerm::getPendingRequests(5, $type)), "{$type} #5 cancelou próprio pendente");
}
// Computer #5 segue intocado
$t->assertSame(1, count(PluginAssettermsTerm::getPendingRequests(5, 'Computer')), 'Computer #5 segue pendente após arquivar Monitor e Peripheral #5');

// Cancelamento explícito via ajax/request.php no Computer #5
$compReq = PluginAssettermsTerm::getPendingRequests(5, 'Computer')[0];
[$cancelRes, $cancelCode] = run_ajax($pluginDir . '/ajax/request.php', ['id' => (int) $compReq['id'], 'acao' => 'cancelar']);
$t->assertSame(200, $cancelCode, 'Cancelamento do Computer #5 via request.php');
$t->assertTrue(!empty($cancelRes['success']), 'Computer #5 cancelado com sucesso');

// Devolução limpa usuário e contact
$phone->update(['id' => 5, 'contact' => 'Nome Alt']);
[$devRes, $devCode] = run_ajax($pluginDir . '/ajax/save.php', [
    'itemtype'        => 'Phone',
    'computers_id'    => 5,
    'tipo_termo'      => 'devolucao',
    'users_id'        => 20,
    'target_state_id' => 2,
    'modo'            => 'arquivar',
    'signature_image' => $pngBase64,
]);
$t->assertSame(200, $devCode, 'Devolução de Phone #5 concluída');
$phoneAfterDev = PluginAssettermsTerm::loadAsset('Phone', 5);
$t->assertSame(0, (int) $phoneAfterDev->fields['users_id'], 'Devolução limpa users_id');
$t->assertSame('', (string) $phoneAfterDev->fields['contact'], 'Devolução limpa contact');
$t->assertSame(2, (int) $phoneAfterDev->fields['states_id'], 'Devolução atualiza status para Em estoque');

// =====================================================================
// 5. Isolamento de permissões e documentos arquivados (sem Document::canView)
// =====================================================================
// Adiciona Computer #6 arquivado + Phone #7 na lixeira + Phone #8 template + Phone #9 em outra entidade (1)
$comp6 = new Computer();
$comp6->add(['name' => 'NB-SECRETO-06', 'entities_id' => 0]);
$phone7 = new Phone();
$phone7->add(['name' => 'CEL-LIXEIRA-07', 'entities_id' => 0, 'is_deleted' => 1]);
$phone8 = new Phone();
$phone8->add(['name' => 'CEL-TEMPLATE-08', 'entities_id' => 0, 'is_template' => 1]);
$phone9 = new Phone();
$phone9->add(['name' => 'CEL-FILIAL-09', 'entities_id' => 1]);

// Cria documentos arquivados para Computer #5, Phone #7, Phone #8, Phone #9
$docModel = new Document();
$docComp  = $docModel->add(['name' => 'Termo Comp 5', 'comment' => 'COMP-CODE', 'entities_id' => 0, 'users_id' => 10]);
$DB->insert(PluginAssettermsTerm::DOCS_TABLE, ['documents_id' => $docComp, 'itemtype' => 'Computer', 'computers_id' => 5, 'type' => 'entrega', 'code' => 'C-005', 'date_creation' => '2026-11-18 10:00:00']);

$docDel = $docModel->add(['name' => 'Termo Phone Del 7', 'comment' => 'DEL-CODE', 'entities_id' => 0, 'users_id' => 10]);
$DB->insert(PluginAssettermsTerm::DOCS_TABLE, ['documents_id' => $docDel, 'itemtype' => 'Phone', 'computers_id' => (int) $phone7->getID(), 'type' => 'entrega', 'code' => 'P-DEL', 'date_creation' => '2026-11-18 10:01:00']);

$docTpl = $docModel->add(['name' => 'Termo Phone Tpl 8', 'comment' => 'TPL-CODE', 'entities_id' => 0, 'users_id' => 10]);
$DB->insert(PluginAssettermsTerm::DOCS_TABLE, ['documents_id' => $docTpl, 'itemtype' => 'Phone', 'computers_id' => (int) $phone8->getID(), 'type' => 'entrega', 'code' => 'P-TPL', 'date_creation' => '2026-11-18 10:02:00']);

$docEnt1 = $docModel->add(['name' => 'Termo Phone Filial 9', 'comment' => 'FIL-CODE', 'entities_id' => 1, 'users_id' => 10]);
$DB->insert(PluginAssettermsTerm::DOCS_TABLE, ['documents_id' => $docEnt1, 'itemtype' => 'Phone', 'computers_id' => (int) $phone9->getID(), 'type' => 'entrega', 'code' => 'P-FIL', 'date_creation' => '2026-11-18 10:03:00']);

// Pendente em Computer #5 e Phone #5 para testar getAllPendingRequests com permissão apenas de Phone
run_ajax($pluginDir . '/ajax/save.php', ['itemtype' => 'Computer', 'computers_id' => 5, 'tipo_termo' => 'entrega', 'users_id' => 20, 'target_state_id' => 1, 'modo' => 'link']);
run_ajax($pluginDir . '/ajax/save.php', ['itemtype' => 'Phone', 'computers_id' => 5, 'tipo_termo' => 'entrega', 'users_id' => 20, 'target_state_id' => 1, 'modo' => 'link']);

// Usuário com permissão SOMENTE de Phone (READ|UPDATE), SEM Document::canView() e somente entidade 0
$GLOBALS['TEST_RIGHTS'] = [
    'computer'   => 0,
    'phone'      => READ | UPDATE,
    'monitor'    => 0,
    'peripheral' => 0,
    'document'   => 0,
    'config'     => 0,
];
$_SESSION['glpiactiveentities'] = [0];

$t->assertTrue(PluginAssettermsTerm::canViewAnyAsset(), 'Usuário só de Phone passa em canViewAnyAsset()');
$t->assertTrue(PluginAssettermsTerm::canUpdateAnyAsset(), 'Usuário só de Phone passa em canUpdateAnyAsset()');
$t->assertSame(['Phone'], array_keys(PluginAssettermsTerm::viewableAssetTypes()), 'viewableAssetTypes retorna apenas Phone');
$t->assertTrue(PluginAssettermsMenu::getMenuContent() !== false, 'Menu aparece para usuário só de Phone');
$t->assertTrue(PluginAssettermsSignature::canView(), 'Signature acessível para usuário com UPDATE em Phone');

// Tentativa de gerar termo para Computer ou Monitor é bloqueada com 403
[$denyRes, $denyCode] = run_ajax($pluginDir . '/ajax/save.php', [
    'itemtype' => 'Computer', 'computers_id' => 5, 'tipo_termo' => 'entrega', 'users_id' => 20, 'target_state_id' => 1, 'modo' => 'link',
]);
$t->assertSame(403, $denyCode, 'Usuário só de Phone recebe 403 ao tentar gerar termo para Computer');

[$denyMonRes, $denyMonCode] = run_ajax($pluginDir . '/ajax/save.php', [
    'itemtype' => 'Monitor', 'computers_id' => 5, 'tipo_termo' => 'entrega', 'users_id' => 20, 'target_state_id' => 1, 'modo' => 'link',
]);
$t->assertSame(403, $denyMonCode, 'Usuário só de Phone recebe 403 ao tentar gerar termo para Monitor');

// getAllPendingRequests retorna somente o pendente de Phone
$pendingPhoneOnly = PluginAssettermsTerm::getAllPendingRequests();
$t->assertSame(1, count($pendingPhoneOnly), 'getAllPendingRequests filtra apenas tipos visíveis');
$t->assertSame('Phone', $pendingPhoneOnly[0]['itemtype'] ?? '', 'Pendente visível é Phone');

// getArchivedDocuments sem Document::canView() deve retornar APENAS os 2 termos do Phone #5 ativo na entidade 0!
$archPhoneOnly = PluginAssettermsTerm::getArchivedDocuments('', 100);
$t->assertSame(2, count($archPhoneOnly), 'Sem Document::canView(), vê apenas termos de Phone #5 ativo na entidade 0');
foreach ($archPhoneOnly as $row) {
    $t->assertSame('Phone', $row['itemtype'], 'Documento arquivado visível é apenas Phone');
    $t->assertSame(5, (int) $row['item_id'], 'Documento arquivado visível pertence apenas ao Phone #5 ativo');
}

// Busca pelo nome do ativo Phone #5 funciona sem Document::canView()
$archSearchPhone = PluginAssettermsTerm::getArchivedDocuments('CEL-CORP-05', 100);
$t->assertSame(2, count($archSearchPhone), 'Busca por nome do Phone encontra termos arquivados');

// Busca por nome do Monitor (sem permissão) não vaza documento
$archSearchMonitor = PluginAssettermsTerm::getArchivedDocuments('MON-CORP-05', 100);
$t->assertSame(0, count($archSearchMonitor), 'Busca por Monitor não vaza documento para usuário sem permissão');

// Renderização de front/term.php para usuário só de Phone: lista apenas Phone no <select name="itemtype">
$_GET = [];
ob_start();
include $pluginDir . '/front/term.php';
$termHtml = (string) ob_get_clean();
$t->assertContains('<option value="Phone" selected>Telefone / Smartphone</option>', $termHtml, 'front/term.php seleciona Phone automaticamente');
$t->assertNotContains('<option value="Computer"', $termHtml, 'front/term.php oculta Computer sem permissão');
$t->assertTrue(!Html::$rightErrorCalled, 'front/term.php não exibe erro de permissão para usuário de Phone');

// Regressão: showArchived() com itemtype=Phone e item_id=0 preserva itemtype na busca e no link Limpar
ob_start();
PluginAssettermsTerm::showArchived([], 'teste', 0, false, 'Phone');
$archivedHtml = (string) ob_get_clean();
$t->assertContains('<input type="hidden" name="itemtype" value="Phone">', $archivedHtml, 'showArchived() preserva itemtype=Phone mesmo com item_id=0');
$t->assertContains('front/term.php?itemtype=Phone#termo-arquivados', $archivedHtml, 'showArchived() mantém itemtype=Phone no link Limpar mesmo com item_id=0');
$t->assertNotContains('<input type="hidden" name="id"', $archivedHtml, 'showArchived() não inclui input id quando item_id=0');

// Usuário sem NENHUMA permissão de ativo recebe Html::displayRightError() em front/term.php e front/signature.php
$GLOBALS['TEST_RIGHTS']['phone'] = 0;
Html::$rightErrorCalled = false;
ob_start();
include $pluginDir . '/front/term.php';
ob_end_clean();
$t->assertTrue(Html::$rightErrorCalled, 'front/term.php bloqueia usuário sem permissão em nenhum ativo');

Html::$rightErrorCalled = false;
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
include $pluginDir . '/front/signature.php';
ob_end_clean();
$t->assertTrue(Html::$rightErrorCalled, 'front/signature.php bloqueia usuário sem permissão de UPDATE em nenhum ativo');

// =====================================================================
// Resumo do runner atual + execução em matriz GLPI 10 / 11 / 12
// =====================================================================
$modeLabel = 'GLPI ' . GLPI_VERSION . ' (typedRightname=' . ((getenv('TEST_TYPED_RIGHTNAME') ?: '0') === '1' ? '1' : '0') . ')';
echo sprintf("[PASS] %s: %d asserções aprovadas, %d falhas\n", $modeLabel, $t->passed, $t->failed);

if ($t->failed > 0) {
    exit(1);
}

if (!in_array('--single', $argv ?? [], true) && getenv('TEST_SUBRUN') !== '1') {
    $matrix = [
        ['TEST_GLPI_VERSION' => '10.0.28', 'TEST_TYPED_RIGHTNAME' => '0'],
        ['TEST_GLPI_VERSION' => '12.0.0',  'TEST_TYPED_RIGHTNAME' => '1'],
    ];
    $baseEnv = getenv();
    if (!is_array($baseEnv)) {
        $baseEnv = [];
    }
    foreach ($matrix as $env) {
        $subEnv = array_merge($baseEnv, $env, ['TEST_SUBRUN' => '1']);
        $proc = proc_open(
            [PHP_BINARY, __FILE__, '--single'],
            [0 => STDIN, 1 => STDOUT, 2 => STDERR],
            $pipes,
            null,
            $subEnv
        );
        if (!is_resource($proc)) {
            fwrite(STDERR, "[FAIL] Não foi possível iniciar subteste para GLPI {$env['TEST_GLPI_VERSION']}\n");
            exit(1);
        }
        $exitCode = proc_close($proc);
        if ($exitCode !== 0) {
            exit($exitCode);
        }
    }
}

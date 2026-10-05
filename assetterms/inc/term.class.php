<?php

/**
 * ------------------------------------------------------------------------
 * Asset Terms - Aba do computador, texto do termo e PDF
 * ------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Acesso direto não permitido.");
}

class PluginAssettermsTerm extends CommonGLPI
{
    public static $rightname = 'computer';

    /** Acessórios do checklist: chave enviada pelo formulário => texto do termo */
    public const CHECKLIST = [
        'fonte'    => 'Fonte / carregador original',
        'cabo'     => 'Cabo de força',
        'mochila'  => 'Mochila ou case de transporte',
        'mouse'    => 'Mouse',
        'teclado'  => 'Teclado externo',
        'hub'      => 'Adaptador de vídeo / hub USB-C',
        'headset'  => 'Headset / fone de ouvido',
        'trava'    => 'Cabo de rede / trava de segurança',
    ];

    /** Acessórios marcados por padrão na entrega */
    public const CHECKLIST_DEFAULT = ['fonte', 'cabo'];

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Computer && !$withtemplate && Computer::canView()) {
            return __('Termo de Responsabilidade', 'assetterms');
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof Computer) {
            self::showTermoForm($item);
        }
        return true;
    }

    // --------------------------------------------------------------- Dados

    /** Nome de exibição do usuário (Nome Sobrenome), ou o login */
    public static function userName(int $users_id): string
    {
        $user = new User();
        if ($users_id <= 0 || !$user->getFromDB($users_id)) {
            return '';
        }
        $name = trim(($user->fields['firstname'] ?? '') . ' ' . ($user->fields['realname'] ?? ''));
        return $name !== '' ? $name : (string) $user->fields['name'];
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

    /** Especificações do computador conforme o inventário */
    public static function getComputerSpecs(Computer $computer): array
    {
        global $DB;
        $id = (int) $computer->getID();

        $cpu = '';
        foreach (
            $DB->request([
                'SELECT'     => ['dev.designation'],
                'FROM'       => 'glpi_items_deviceprocessors AS item',
                'INNER JOIN' => ['glpi_deviceprocessors AS dev' => ['ON' => ['item' => 'deviceprocessors_id', 'dev' => 'id']]],
                'WHERE'      => ['item.items_id' => $id, 'item.itemtype' => 'Computer', 'item.is_deleted' => 0],
                'LIMIT'      => 1,
            ]) as $row
        ) {
            $cpu = (string) $row['designation'];
        }

        $ram_mb = 0;
        foreach (
            $DB->request([
                'SELECT' => ['size'],
                'FROM'   => 'glpi_items_devicememories',
                'WHERE'  => ['items_id' => $id, 'itemtype' => 'Computer', 'is_deleted' => 0],
            ]) as $row
        ) {
            $ram_mb += (int) ($row['size'] ?? 0);
        }

        $disk_mb = 0;
        foreach (
            $DB->request([
                'SELECT' => ['capacity'],
                'FROM'   => 'glpi_items_deviceharddrives',
                'WHERE'  => ['items_id' => $id, 'itemtype' => 'Computer', 'is_deleted' => 0],
            ]) as $row
        ) {
            $disk_mb += (int) ($row['capacity'] ?? 0);
        }

        $so = '';
        foreach (
            $DB->request([
                'SELECT'    => ['os.name AS os_name', 'ver.name AS ver_name'],
                'FROM'      => 'glpi_items_operatingsystems AS item',
                'LEFT JOIN' => [
                    'glpi_operatingsystems AS os'         => ['ON' => ['item' => 'operatingsystems_id', 'os' => 'id']],
                    'glpi_operatingsystemversions AS ver' => ['ON' => ['item' => 'operatingsystemversions_id', 'ver' => 'id']],
                ],
                'WHERE'     => ['item.items_id' => $id, 'item.itemtype' => 'Computer'],
                'LIMIT'     => 1,
            ]) as $row
        ) {
            $so = trim(($row['os_name'] ?? '') . ' ' . ($row['ver_name'] ?? ''));
        }

        $dropdown = static fn (string $table, string $field): string
            => (int) ($computer->fields[$field] ?? 0) > 0 ? (string) Dropdown::getDropdownName($table, (int) $computer->fields[$field]) : '';

        if ($disk_mb >= 1048576) {
            $disk = round($disk_mb / 1048576, 1) . ' TB';
        } else {
            $disk = $disk_mb > 0 ? round($disk_mb / 1024) . ' GB' : '';
        }

        return [
            'cpu'        => $cpu,
            'ram'        => $ram_mb > 0 ? round($ram_mb / 1024) . ' GB' : '',
            'disk'       => $disk,
            'so'         => $so,
            'fabricante' => $dropdown('glpi_manufacturers', 'manufacturers_id'),
            'modelo'     => $dropdown('glpi_computermodels', 'computermodels_id'),
            'tipo'       => $dropdown('glpi_computertypes', 'computertypes_id'),
        ];
    }

    /**
     * Status disponíveis para computadores (id => nome).
     * GLPI 11 guarda a visibilidade em glpi_dropdownvisibilities; o GLPI 10, em glpi_states.is_visible_computer.
     */
    public static function getStates(): array
    {
        global $DB;
        $criteria = ['SELECT' => ['glpi_states.id', 'glpi_states.completename'], 'FROM' => 'glpi_states', 'ORDER' => 'glpi_states.completename'];
        if ($DB->tableExists('glpi_dropdownvisibilities')) {
            $criteria['INNER JOIN'] = [
                'glpi_dropdownvisibilities AS vis' => [
                    'ON' => ['vis' => 'items_id', 'glpi_states' => 'id', ['AND' => ['vis.itemtype' => 'State']]],
                ],
            ];
            $criteria['WHERE'] = ['vis.visible_itemtype' => 'Computer', 'vis.is_visible' => 1];
        } elseif ($DB->fieldExists('glpi_states', 'is_visible_computer')) {
            $criteria['WHERE'] = ['is_visible_computer' => 1];
        }
        $states = [];
        foreach ($DB->request($criteria) as $row) {
            $states[(int) $row['id']] = (string) $row['completename'];
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

    /** Pedidos de assinatura enviados por e-mail */
    public const TABLE = 'glpi_plugin_assetterms_requests';
    public const PENDING  = 0;
    public const SIGNED   = 1;
    public const CANCELED = 2;

    public static function installSchema(): void
    {
        global $DB;
        if ($DB->tableExists(self::TABLE)) {
            return;
        }
        $DB->doQuery("CREATE TABLE `" . self::TABLE . "` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `computers_id` int unsigned NOT NULL DEFAULT '0',
            `entities_id` int unsigned NOT NULL DEFAULT '0',
            `users_id` int unsigned NOT NULL DEFAULT '0' COMMENT 'colaborador que assina',
            `users_id_tech` int unsigned NOT NULL DEFAULT '0' COMMENT 'quem enviou',
            `type` varchar(20) NOT NULL DEFAULT 'entrega',
            `code` varchar(20) NOT NULL DEFAULT '',
            `status` tinyint NOT NULL DEFAULT '0',
            `data` longtext COMMENT 'dados do termo no momento do envio (JSON)',
            `documents_id` int unsigned NOT NULL DEFAULT '0',
            `sign_ip` varchar(45) NOT NULL DEFAULT '',
            `date_send` timestamp NULL DEFAULT NULL,
            `date_signed` timestamp NULL DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `computers_id` (`computers_id`),
            KEY `users_id` (`users_id`),
            KEY `status` (`status`)
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
     * Valores para $DB->insert/update. O GLPI 11 escapa sozinho; o GLPI 10 espera os
     * valores já escapados (como chegam de um formulário).
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
        if ($id <= 0) {
            return null;
        }
        foreach ($DB->request(['FROM' => self::TABLE, 'WHERE' => ['id' => $id]]) as $row) {
            $row['data'] = json_decode((string) $row['data'], true) ?: [];
            return $row;
        }
        return null;
    }

    /** Pedidos aguardando assinatura de um computador */
    public static function getPendingRequests(int $computers_id): array
    {
        global $DB;
        $rows = [];
        foreach (
            $DB->request([
                'FROM'  => self::TABLE,
                'WHERE' => ['computers_id' => $computers_id, 'status' => self::PENDING],
                'ORDER' => 'date_send DESC',
            ]) as $row
        ) {
            $row['data'] = json_decode((string) $row['data'], true) ?: [];
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
    public static function collectInput(array $post)
    {
        $tipo = ($post['tipo_termo'] ?? '') === 'devolucao' ? 'devolucao' : 'entrega';
        $uid  = (int) ($post['users_id'] ?? 0);
        if (self::userName($uid) === '') {
            return 'Selecione o colaborador.';
        }
        $checklist = [];
        foreach ((array) ($post['checklist'] ?? []) as $key) {
            if (is_string($key) && isset(self::CHECKLIST[$key])) {
                $checklist[$key] = self::CHECKLIST[$key];
            }
        }
        $state_id = (int) ($post['target_state_id'] ?? 0);
        if ($state_id > 0 && !isset(self::getStates()[$state_id])) {
            $state_id = 0;
        }
        return [
            'tipo'        => $tipo,
            'users_id'    => $uid,
            'checklist'   => array_values($checklist),
            'state_id'    => $state_id,
            'observacoes' => mb_substr(trim((string) ($post['observacoes'] ?? '')), 0, 2000),
        ];
    }

    /**
     * Dados do termo (sem assinatura). Também é o que fica guardado no pedido enviado por e-mail,
     * para o colaborador assinar exatamente o que a TI registrou.
     */
    public static function buildData(Computer $computer, array $in, int $tech_id): array
    {
        $entity = new Entity();
        $entity->getFromDB((int) $computer->fields['entities_id']);
        $codigo = strtoupper(substr(hash('sha256', implode('|', [$computer->getID(), $in['tipo'], $in['users_id'], $tech_id, microtime(true), random_bytes(8)])), 0, 12));

        return [
            'tipo'         => $in['tipo'],
            'computers_id' => (int) $computer->getID(),
            'entities_id'  => (int) $computer->fields['entities_id'],
            'empresa'      => (string) ($entity->fields['name'] ?? ''),
            'cidade'       => trim((string) ($entity->fields['town'] ?? '')),
            'codigo'       => implode('-', str_split($codigo, 4)),
            'tecnico'      => self::userName($tech_id) ?: 'TI',
            'tecnico_id'   => $tech_id,
            'colaborador'  => self::userData($in['users_id']) + ['id' => $in['users_id']],
            'equipamento'  => self::getComputerSpecs($computer) + [
                'nome'       => (string) ($computer->fields['name'] ?? ''),
                'serial'     => (string) ($computer->fields['serial'] ?? ''),
                'patrimonio' => (string) ($computer->fields['otherserial'] ?? ''),
            ],
            'checklist'    => $in['checklist'],
            'observacoes'  => $in['observacoes'],
            'state_id'     => $in['state_id'],
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
        return ($info !== false && ($info['mime'] ?? '') === 'image/png') ? $png : null;
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
     * Gera o PDF, grava na aba Documentos, atualiza usuário e status do computador e registra no histórico.
     * $d precisa ter data, assinatura, modo e ip. Retorna [documents_id, nome do status, conteúdo do PDF].
     *
     * @throws RuntimeException com a mensagem para o usuário
     */
    public static function archive(Computer $computer, array $d, int $time): array
    {
        $pdf     = self::buildPdf($d);
        $titulo  = $d['tipo'] === 'devolucao' ? 'Termo de Devolução' : 'Termo de Entrega';
        $arquivo = self::fileName($d, $time);
        $nome    = $d['colaborador']['nome'];

        // O GLPI move o arquivo do diretório temporário, confere o tipo e calcula o checksum
        $tmp = GLPI_TMP_DIR . '/' . $arquivo;
        if (file_put_contents($tmp, $pdf) === false) {
            throw new RuntimeException('Não foi possível gravar o arquivo em ' . GLPI_TMP_DIR . '.');
        }
        $modo = ['email' => 'Assinado pelo link enviado por e-mail', 'tela' => 'Assinado na tela'][$d['modo']] ?? 'Para assinatura manual';
        $input = [
            'name'         => $titulo . ' - ' . ($d['equipamento']['nome'] ?: 'equipamento') . ' - ' . $nome . ' - ' . date('d/m/Y', $time),
            'entities_id'  => (int) $computer->fields['entities_id'],
            'is_recursive' => 0,
            'comment'      => sprintf('%s de %s. Código %s. %s.', $titulo, $nome, $d['codigo'], $modo),
            'users_id'     => (int) $d['tecnico_id'],
            '_filename'    => [$arquivo],
        ];
        if (version_compare(GLPI_VERSION, '11.0.0-dev', '<')) {
            // O GLPI 10 espera a entrada escapada, como vem de um formulário
            $input = Toolbox::addslashes_deep($input);
        }
        $document = new Document();
        $doc_id   = (int) $document->add($input);
        if (is_file($tmp)) {
            @unlink($tmp);
        }
        if ($doc_id <= 0) {
            throw new RuntimeException('O GLPI recusou o documento. Confira se o tipo PDF está liberado em Configurar > Listas suspensas > Tipos de documento.');
        }

        // Vínculo feito à parte: criando o documento já vinculado, o GLPI 10 troca o nome pelo do computador
        $link = new Document_Item();
        $link->add([
            'documents_id' => $doc_id,
            'itemtype'     => 'Computer',
            'items_id'     => (int) $computer->getID(),
            'entities_id'  => (int) $computer->fields['entities_id'],
        ]);

        $update = ['id' => (int) $computer->getID(), 'users_id' => $d['tipo'] === 'entrega' ? (int) $d['colaborador']['id'] : 0];
        if ((int) $d['state_id'] > 0) {
            $update['states_id'] = (int) $d['state_id'];
        }
        $computer->update($update);

        $status = (int) $d['state_id'] > 0 ? (string) Dropdown::getDropdownName('glpi_states', (int) $d['state_id']) : '';
        $msg    = "{$titulo} #{$doc_id} arquivado ({$modo}). Colaborador: {$nome}. Código: {$d['codigo']}." . ($status !== '' ? " Status: {$status}." : '');
        Log::history((int) $computer->getID(), 'Computer', [0, '', $msg], '', Log::HISTORY_LOG_SIMPLE_MESSAGE);

        return [$doc_id, $status, $pdf];
    }

    // ---------------------------------------------------------------- E-mail

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
                // GLPI 11: Symfony Mailer
                $email = $mailer->getEmail();
                $email->from(new \Symfony\Component\Mime\Address($sender['email'], (string) ($sender['name'] ?? '')));
                $email->to(new \Symfony\Component\Mime\Address($to, $name));
                $email->subject($subject);
                $email->html($html);
                $email->text($text);
                if ($attachment !== null) {
                    $email->attach($attachment[0], $attachment[1], 'application/pdf');
                }
                return $mailer->send() ? null : ($mailer->getError() ?: 'falha no envio');
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
            return $mailer->send() ? null : ($mailer->ErrorInfo ?: 'falha no envio');
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    /** Corpo HTML simples para os e-mails do plugin */
    private static function mailHtml(string $title, array $paragraphs, ?array $button = null): string
    {
        $e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $html = '<div style="font-family:Segoe UI,Arial,sans-serif;font-size:14px;color:#1f2937;max-width:560px;">'
            . '<h2 style="color:#1f3a5f;font-size:18px;">' . $e($title) . '</h2>';
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

    /** Depois da assinatura: cópia em PDF para o colaborador e aviso para quem enviou. Erros vão para o log. */
    public static function sendSignedMails(array $req, string $pdf, string $arquivo, string $quando): void
    {
        global $CFG_GLPI;

        $e    = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $d    = $req['data'];
        $acao = $d['tipo'] === 'devolucao' ? 'devolução' : 'entrega';
        $eq   = $d['equipamento']['nome'];
        $errors = [];

        $err = self::sendMail(
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
        }

        $tech = self::userData((int) $req['users_id_tech']);
        if ($tech['email'] !== '') {
            $link = $CFG_GLPI['url_base'] . '/front/computer.form.php?id=' . (int) $req['computers_id'];
            $err = self::sendMail(
                $tech['email'],
                $tech['nome'],
                "Termo de {$acao} assinado por {$d['colaborador']['nome']} - {$eq}",
                self::mailHtml("Termo de {$acao} assinado", [
                    $e($d['colaborador']['nome']) . " assinou o termo de {$acao} do equipamento <b>" . $e($eq) . '</b> em ' . $e($quando) . '.',
                    'O PDF foi arquivado na aba Documentos do computador, e o usuário e o status do equipamento foram atualizados.',
                ], ['Abrir o computador', $link]),
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
    }

    // --------------------------------------------------------- Texto do termo

    /**
     * Texto do termo. É o mesmo na tela e no PDF.
     *
     * @return array{titulo: string, declaracao: string, compromissos: string[], ciencia: string}
     */
    public static function clausula(string $tipo, string $empresa): array
    {
        $empresa = $empresa !== '' ? $empresa : 'a empresa';

        if ($tipo === 'devolucao') {
            return [
                'titulo'       => 'Termo de Devolução de Equipamento',
                'declaracao'   => "Declaro que, nesta data, devolvi à {$empresa} o equipamento e os acessórios descritos neste termo, "
                    . 'conferidos na presença do(a) responsável pela TI.',
                'compromissos' => [
                    'O estado do equipamento e de cada acessório na devolução é o registrado no campo de observações deste termo.',
                    'Acessórios não listados como devolvidos foram considerados ausentes na conferência.',
                    'Removi ou entreguei à TI os arquivos pessoais que mantinha no equipamento, quando havia.',
                ],
                'ciencia'      => 'Estou ciente de que os dados armazenados no equipamento poderão ser apagados para a sua reutilização, '
                    . 'conforme a Política de Segurança da Informação e a Lei Geral de Proteção de Dados (Lei nº 13.709/2018), '
                    . 'e de que danos ou ausências registrados neste termo serão tratados conforme o termo de entrega e as normas internas.',
            ];
        }

        return [
            'titulo'       => 'Termo de Responsabilidade e Entrega de Equipamento',
            'declaracao'   => "Declaro que recebi da {$empresa}, em regime de comodato e para uso exclusivo no exercício das minhas atividades profissionais, "
                . 'o equipamento e os acessórios descritos neste termo, em perfeito estado de conservação e funcionamento, '
                . 'ressalvadas as observações registradas. Comprometo-me a:',
            'compromissos' => [
                'utilizá-lo somente para fins profissionais, conforme a Política de Segurança da Informação e as normas internas;',
                'zelar pela sua guarda e conservação, sem emprestá-lo, cedê-lo ou permitir o uso por pessoas não autorizadas;',
                'não instalar programas não autorizados nem alterar as configurações de segurança, os componentes ou a etiqueta de patrimônio;',
                'comunicar imediatamente à TI qualquer defeito, dano, perda, furto ou roubo, apresentando boletim de ocorrência nos casos de furto ou roubo;',
                'devolvê-lo com os acessórios, nas mesmas condições em que o recebi, ressalvado o desgaste natural pelo uso normal, '
                    . 'sempre que solicitado, na sua substituição ou no encerramento do meu vínculo com a empresa.',
            ],
            'ciencia'      => 'Estou ciente de que o equipamento e os dados corporativos nele armazenados pertencem à empresa, '
                . 'podendo ser acessados, monitorados ou removidos conforme a Política de Segurança da Informação e a '
                . 'Lei Geral de Proteção de Dados (Lei nº 13.709/2018). Em caso de dano causado por dolo ou culpa '
                . '(negligência, imprudência ou imperícia), perda ou extravio, autorizo o desconto do valor correspondente, '
                . 'nos termos do art. 462, § 1º, da Consolidação das Leis do Trabalho (CLT).',
        ];
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
        $texto = self::clausula($d['tipo'], $d['empresa']);
        $devolucao = $d['tipo'] === 'devolucao';

        // Sem o texto "Powered by TCPDF" que a biblioteca acrescenta ao fim do documento
        $pdf = new class ('P', 'mm', 'A4', true, 'UTF-8') extends TCPDF {
            public function semCredito(): void
            {
                $this->tcpdflink = false;
            }
        };
        $pdf->semCredito();
        $pdf->SetCreator('GLPI - Asset Terms');
        $pdf->SetAuthor($d['tecnico']);
        $pdf->SetTitle($texto['titulo'] . ' - ' . $d['equipamento']['nome']);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->setFooterFont(['dejavusans', '', 7]);
        $pdf->SetMargins(18, 16, 18);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->SetFont('dejavusans', '', 8.5);
        $pdf->setHtmlVSpace([
            'h2' => [0 => ['h' => 0, 'n' => 0], 1 => ['h' => 1, 'n' => 1]],
            'h4' => [0 => ['h' => 1, 'n' => 1], 1 => ['h' => 0.5, 'n' => 1]],
            'p'  => [0 => ['h' => 0, 'n' => 0], 1 => ['h' => 1, 'n' => 1]],
            'ul' => [0 => ['h' => 0, 'n' => 0], 1 => ['h' => 0.5, 'n' => 1]],
            'ol' => [0 => ['h' => 0, 'n' => 0], 1 => ['h' => 0.5, 'n' => 1]],
        ]);
        $pdf->AddPage();

        $th = 'style="background-color:#eef2f7;width:32%;"';
        $row = static fn (string $label, string $value) => "<tr><td {$th}><b>{$e($label)}</b></td><td style=\"width:68%;\">{$e($value)}</td></tr>";

        $eq = $d['equipamento'];
        $html = '<h2 style="text-align:center;color:#1f3a5f;">' . $e(mb_strtoupper($texto['titulo'])) . '</h2>'
            . '<p style="text-align:center;color:#555;">' . $e($d['empresa']) . '</p>'
            . '<table cellpadding="3" style="border:0.3px solid #ccc;"><tr>'
            . '<td><b>Data:</b> ' . $e($d['data']) . '</td>'
            . '<td style="text-align:right;"><b>Código do documento:</b> ' . $e($d['codigo']) . '</td>'
            . '</tr></table>'

            . '<h4 style="color:#1f3a5f;">1. Colaborador</h4>'
            . '<table cellpadding="3" border="0.3">'
            . $row('Nome', $or($d['colaborador']['nome']))
            . $row('Matrícula', $or($d['colaborador']['matricula']))
            . $row('E-mail', $or($d['colaborador']['email']))
            . '</table>'

            . '<h4 style="color:#1f3a5f;">2. Equipamento</h4>'
            . '<table cellpadding="3" border="0.3">'
            . $row('Nome do ativo', $or($eq['nome']))
            . $row('Tipo', $or($eq['tipo']))
            . $row('Fabricante / modelo', $or(trim($eq['fabricante'] . ' ' . $eq['modelo'])))
            . $row('Número de série', $or($eq['serial']))
            . $row('Patrimônio', $or($eq['patrimonio']))
            . $row('Configuração', $or(implode(' | ', array_filter([$eq['cpu'], $eq['ram'] ? 'RAM ' . $eq['ram'] : '', $eq['disk'] ? 'Disco ' . $eq['disk'] : '', $eq['so']]))))
            . '</table>'

            . '<h4 style="color:#1f3a5f;">3. Acessórios ' . ($devolucao ? 'devolvidos' : 'entregues') . '</h4>';

        if (empty($d['checklist'])) {
            $html .= '<p><i>Nenhum acessório.</i></p>';
        } else {
            $html .= '<ul>';
            foreach ($d['checklist'] as $item) {
                $html .= '<li>' . $e($item) . '</li>';
            }
            $html .= '</ul>';
        }

        $html .= '<h4 style="color:#1f3a5f;">4. Observações sobre o estado do equipamento</h4>'
            . '<p>' . ($d['observacoes'] !== '' ? nl2br($e($d['observacoes'])) : '<i>Sem observações.</i>') . '</p>'

            . '<h4 style="color:#1f3a5f;">5. Declaração</h4>'
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
        if ($d['assinatura'] !== '' && ($d['modo'] ?? '') === 'email') {
            $registro = sprintf(
                'Assinatura eletrônica feita pelo(a) próprio(a) colaborador(a) em %s, autenticado(a) no GLPI com o usuário "%s", '
                . 'a partir do endereço IP %s, depois de declarar que leu e concorda com este termo. '
                . 'Termo enviado para assinatura por %s em %s. Documento gerado e arquivado no GLPI. Código do documento: %s.',
                $d['data'],
                $d['login'],
                $d['ip'],
                $d['tecnico'],
                $d['enviado_em'],
                $d['codigo']
            );
        } elseif ($d['assinatura'] !== '') {
            $registro = sprintf(
                'Assinatura eletrônica do(a) colaborador(a) feita na tela em %s, na presença de %s, a partir do endereço IP %s. '
                . 'Documento gerado e arquivado no GLPI. Código do documento: %s.',
                $d['data'],
                $d['tecnico'],
                $d['ip'],
                $d['codigo']
            );
        } else {
            $registro = sprintf(
                'Documento gerado no GLPI em %s para assinatura manual. Após assinado, anexe a via digitalizada à aba Documentos do equipamento. Código do documento: %s.',
                $d['data'],
                $d['codigo']
            );
        }
        $pdf->MultiCell(0, 3.5, $registro, 0, 'L');

        return $pdf->Output('termo.pdf', 'S');
    }

    // ---------------------------------------------------------------- Tela

    public static function showTermoForm(Computer $computer)
    {
        global $DB, $CFG_GLPI;

        $cid       = (int) $computer->getID();
        $specs     = self::getComputerSpecs($computer);
        $can_edit  = $computer->can($cid, UPDATE);
        $states    = self::getStates();
        $empresa   = (string) Dropdown::getDropdownName('glpi_entities', (int) ($computer->fields['entities_id'] ?? 0));
        $user_id   = (int) ($computer->fields['users_id'] ?? 0);
        $user_name = self::userName($user_id) ?: 'Nenhum colaborador vinculado';
        $status    = (int) ($computer->fields['states_id'] ?? 0) > 0
            ? (string) Dropdown::getDropdownName('glpi_states', (int) $computer->fields['states_id'])
            : 'Não definido';
        $or = static fn ($v): string => trim((string) $v) !== '' ? (string) $v : 'Não informado';
        $e  = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        // Termos já gerados para este equipamento
        $termos = [];
        foreach (
            $DB->request([
                'SELECT'     => ['doc.id', 'doc.name', 'doc.date_creation', 'doc.comment', 'doc.users_id'],
                'FROM'       => 'glpi_documents_items AS di',
                'INNER JOIN' => ['glpi_documents AS doc' => ['ON' => ['di' => 'documents_id', 'doc' => 'id']]],
                'WHERE'      => [
                    'di.items_id' => $cid,
                    'di.itemtype' => 'Computer',
                    'doc.is_deleted' => 0,
                    'doc.name'    => ['LIKE', 'Termo de %'],
                ],
                'ORDER'      => 'doc.date_creation DESC',
            ]) as $d
        ) {
            $termos[] = $d;
        }

        $base       = self::webPath();
        $pendentes  = self::getPendingRequests($cid);
        $state_uso  = self::findState($states, 'Em uso');
        $state_est  = self::findState($states, 'Em estoque');
        $entrega    = self::clausula('entrega', $empresa);
        $devolucao  = self::clausula('devolucao', $empresa);
        ?>
        <div class="termo-container">
            <div class="termo-card termo-header-card">
                <div class="termo-header-info">
                    <div class="termo-title-group">
                        <h3 class="termo-title"><i class="ti ti-file-certificate"></i> Termo de Responsabilidade</h3>
                        <p class="termo-subtitle">Termo de entrega ou devolução, assinado na tela e arquivado em PDF na aba Documentos.</p>
                    </div>
                    <div class="termo-badges">
                        <span class="termo-badge badge-status"><i class="ti ti-activity"></i> Status: <strong><?= $e($status) ?></strong></span>
                        <span class="termo-badge badge-user"><i class="ti ti-user"></i> Usuário: <strong><?= $e($user_name) ?></strong></span>
                    </div>
                </div>

                <div class="termo-specs-grid">
                    <div class="spec-item"><span class="spec-label">Equipamento:</span><span class="spec-val"><?= $e($or($computer->fields['name'] ?? '')) ?></span></div>
                    <div class="spec-item"><span class="spec-label">Fabricante / modelo:</span><span class="spec-val"><?= $e($or(trim($specs['fabricante'] . ' ' . $specs['modelo']))) ?></span></div>
                    <div class="spec-item"><span class="spec-label">Número de série:</span><span class="spec-val highlight"><?= $e($or($computer->fields['serial'] ?? '')) ?></span></div>
                    <div class="spec-item"><span class="spec-label">Patrimônio:</span><span class="spec-val highlight"><?= $e($or($computer->fields['otherserial'] ?? '')) ?></span></div>
                    <div class="spec-item"><span class="spec-label">Processador:</span><span class="spec-val"><?= $e($or($specs['cpu'])) ?></span></div>
                    <div class="spec-item"><span class="spec-label">Memória / disco:</span><span class="spec-val"><?= $e($or($specs['ram']) . ' / ' . $or($specs['disk'])) ?></span></div>
                    <div class="spec-item full-width"><span class="spec-label">Sistema operacional:</span><span class="spec-val"><?= $e($or($specs['so'])) ?></span></div>
                </div>
            </div>

            <?php if ($can_edit): ?>
            <form id="form-termo-responsabilidade" class="termo-card" method="post" action="<?= $e($base . '/ajax/save.php') ?>" onsubmit="return false;">
                <input type="hidden" name="computers_id" value="<?= $cid ?>">

                <h4 class="section-title"><i class="ti ti-forms"></i> Dados da movimentação</h4>

                <div class="termo-form-row">
                    <div class="termo-field">
                        <label class="termo-label">Tipo de termo *</label>
                        <div class="termo-radio-group">
                            <label class="radio-card selected">
                                <input type="radio" name="tipo_termo" value="entrega" data-state="<?= $state_uso ?>" checked>
                                <div class="radio-content">
                                    <span class="radio-title"><i class="ti ti-device-laptop"></i> Entrega ao colaborador</span>
                                    <span class="radio-desc">Vincula o equipamento ao colaborador e muda o status para <strong>Em uso</strong></span>
                                </div>
                            </label>
                            <label class="radio-card">
                                <input type="radio" name="tipo_termo" value="devolucao" data-state="<?= $state_est ?>">
                                <div class="radio-content">
                                    <span class="radio-title"><i class="ti ti-archive"></i> Devolução à TI</span>
                                    <span class="radio-desc">Desvincula o colaborador e muda o status para <strong>Em estoque</strong></span>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="termo-form-row two-cols">
                    <div class="termo-field">
                        <label class="termo-label">Colaborador *</label>
                        <?php
                        User::dropdown([
                            'name'   => 'users_id',
                            'value'  => $user_id,
                            'entity' => (int) ($computer->fields['entities_id'] ?? 0),
                            'right'  => 'all',
                        ]);
                        ?>
                        <small class="termo-hint">Quem recebe ou devolve o equipamento.</small>
                    </div>

                    <div class="termo-field">
                        <label class="termo-label" for="target_state_id">Status após o termo</label>
                        <select name="target_state_id" id="target_state_id" class="form-select">
                            <option value="0">Não alterar</option>
                            <?php foreach ($states as $sid => $sname): ?>
                                <option value="<?= (int) $sid ?>" <?= $sid === $state_uso ? 'selected' : '' ?>><?= $e($sname) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="termo-hint">Muda sozinho conforme o tipo: entrega = Em uso, devolução = Em estoque.</small>
                    </div>
                </div>

                <div class="termo-field full-width">
                    <label class="termo-label">
                        <span data-show="entrega">Acessórios entregues</span>
                        <span data-show="devolucao" hidden>Acessórios devolvidos</span>
                    </label>
                    <div class="termo-checklist-grid">
                        <?php foreach (self::CHECKLIST as $key => $label): ?>
                            <label class="check-item">
                                <input type="checkbox" name="checklist[]" value="<?= $e($key) ?>" <?= in_array($key, self::CHECKLIST_DEFAULT, true) ? 'checked' : '' ?>>
                                <?= $e($label) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="termo-field full-width">
                    <label class="termo-label" for="observacoes">Observações sobre o estado do equipamento</label>
                    <textarea name="observacoes" id="observacoes" rows="2" maxlength="2000" class="form-control"
                        placeholder="Ex.: equipamento novo, sem riscos; etiqueta de patrimônio intacta; bateria testada."></textarea>
                </div>

                <?php foreach (['entrega' => $entrega, 'devolucao' => $devolucao] as $tipo => $t): ?>
                <div class="termo-clausula-box" data-show="<?= $tipo ?>" <?= $tipo === 'devolucao' ? 'hidden' : '' ?>>
                    <strong><?= $e($t['titulo']) ?></strong>
                    <p><?= $e($t['declaracao']) ?></p>
                    <?= $tipo === 'entrega' ? '<ol type="I">' : '<ul>' ?>
                        <?php foreach ($t['compromissos'] as $c): ?><li><?= $e($c) ?></li><?php endforeach; ?>
                    <?= $tipo === 'entrega' ? '</ol>' : '</ul>' ?>
                    <p><?= $e($t['ciencia']) ?></p>
                </div>
                <?php endforeach; ?>

                <div class="termo-signature-area">
                    <div class="signature-header">
                        <label class="termo-label"><i class="ti ti-writing"></i> Assinatura do colaborador (dedo, caneta ou mouse)</label>
                        <button type="button" id="btn-clear-signature" class="btn btn-sm btn-outline-secondary">
                            <i class="ti ti-eraser"></i> Limpar
                        </button>
                    </div>
                    <div class="canvas-wrapper">
                        <canvas id="signature-canvas"></canvas>
                        <div class="canvas-placeholder">Assine aqui</div>
                    </div>
                </div>

                <div class="termo-actions-bar">
                    <button type="button" id="btn-save-termo" class="btn btn-primary btn-lg">
                        <i class="ti ti-check"></i> Gerar e arquivar termo
                    </button>
                    <button type="button" id="btn-send-email" class="btn btn-outline-primary btn-lg">
                        <i class="ti ti-mail-forward"></i> Enviar por e-mail para assinatura
                    </button>
                    <button type="button" id="btn-print-blank" class="btn btn-outline-secondary btn-lg">
                        <i class="ti ti-printer"></i> PDF para assinar no papel
                    </button>
                    <div id="termo-loading-spinner" class="spinner-border text-primary ms-3 d-none" role="status">
                        <span class="visually-hidden">Gerando...</span>
                    </div>
                </div>
                <small class="termo-hint d-block mt-2">
                    Pelo e-mail, o colaborador recebe um link, entra no GLPI com o próprio usuário e assina.
                    O equipamento só é atualizado depois da assinatura.
                </small>

                <div id="termo-alert-box" class="alert d-none mt-3"></div>
            </form>
            <?php endif; ?>

            <?php if (!empty($pendentes)): ?>
            <div class="termo-card mt-4" id="termo-pendentes">
                <h4 class="section-title"><i class="ti ti-clock"></i> Aguardando assinatura</h4>
                <div class="table-responsive">
                    <table class="table table-hover termo-history-table">
                        <thead>
                            <tr><th>Enviado em</th><th>Termo</th><th>Colaborador</th><th>Enviado por</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendentes as $p): ?>
                                <tr data-request="<?= (int) $p['id'] ?>">
                                    <td><strong><?= $e(Html::convDateTime($p['date_send'])) ?></strong></td>
                                    <td><?= $p['type'] === 'devolucao' ? 'Devolução' : 'Entrega' ?> <span class="text-muted">(<?= $e($p['code']) ?>)</span></td>
                                    <td><?= $e($p['data']['colaborador']['nome'] ?? '') ?><br><small class="text-muted"><?= $e($p['data']['colaborador']['email'] ?? '') ?></small></td>
                                    <td><?= $e($p['data']['tecnico'] ?? '') ?></td>
                                    <td class="text-nowrap">
                                        <?php if ($can_edit): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary termo-request-action" data-action="reenviar" data-url="<?= $e($base . '/ajax/request.php') ?>">
                                            <i class="ti ti-send"></i> Reenviar
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger termo-request-action" data-action="cancelar" data-url="<?= $e($base . '/ajax/request.php') ?>">
                                            <i class="ti ti-x"></i> Cancelar
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <div class="termo-card mt-4">
                <h4 class="section-title"><i class="ti ti-history"></i> Termos deste equipamento</h4>
                <?php if (empty($termos)): ?>
                    <p class="text-muted p-3">Nenhum termo arquivado para este equipamento.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped termo-history-table">
                            <thead>
                                <tr><th>Data</th><th>Documento</th><th>Detalhes</th><th>Gerado por</th><th></th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($termos as $t): ?>
                                    <tr>
                                        <td><strong><?= $e(Html::convDateTime($t['date_creation'])) ?></strong></td>
                                        <td><i class="ti ti-file-text text-primary"></i> <?= $e($t['name']) ?></td>
                                        <td><?= $e($t['comment']) ?></td>
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

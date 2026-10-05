<?php

/**
 * ------------------------------------------------------------------------
 * Asset Terms - Configuração por empresa (entidade)
 *
 * Nome da empresa, CNPJ, cidade e o texto dos termos de entrega e devolução.
 * Uma entidade sem configuração própria usa a da entidade acima (ex.: a filial
 * usa a da matriz). Sem nenhuma configuração, vale o texto padrão do plugin.
 * ------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Acesso direto não permitido.");
}

class PluginAssettermsConfig extends CommonGLPI
{
    public static $rightname = 'config';

    public const TABLE = 'glpi_plugin_assetterms_configs';

    /** Marcadores aceitos no texto */
    public const PLACEHOLDERS = [
        '{empresa}' => 'nome da empresa',
        '{cnpj}'    => 'CNPJ da empresa',
    ];

    /** Limites dos campos de texto */
    public const MAX_LINE = 255;
    public const MAX_TEXT = 4000;
    public const MAX_ITEMS = 20;

    public static function getTypeName($nb = 0)
    {
        return 'Asset Terms';
    }

    // ------------------------------------------------------------- Banco

    /** Contadores do código sequencial do documento, por empresa (entidade da configuração) e ano */
    public const COUNTERS = 'glpi_plugin_assetterms_counters';

    /** Colunas acrescentadas depois da criação da tabela (versão em que entraram) */
    private const MIGRATIONS = [
        // 1.4.0: código do documento e aparência
        'code_format' => "varchar(100) NOT NULL DEFAULT '{aleatorio}'",
        'code_prefix' => "varchar(20) NOT NULL DEFAULT ''",
        'code_digits' => "tinyint NOT NULL DEFAULT '6'",
        'code_yearly' => "tinyint NOT NULL DEFAULT '1'",
        'logo'        => "mediumtext COMMENT 'logo em base64'",
        'logo_mime'   => "varchar(20) NOT NULL DEFAULT ''",
        'color'       => "varchar(7) NOT NULL DEFAULT ''",
        'footer'      => "varchar(300) NOT NULL DEFAULT ''",
        // 1.5.0: cabeçalho e rodapé de controle de documentos (ISO)
        'doc_control' => "text COMMENT 'cabeçalho de controle de documento (JSON)'",
    ];

    /**
     * Cabeçalho "controle de documentos" (tabela com tipo, código do formulário, revisão e datas,
     * repetida em todas as páginas) e rodapé com quem elaborou e aprovou. layout: simples | controle
     */
    public const DOC_CONTROL_FIELDS = [
        'tipo'            => ['Tipo', 'FORMULÁRIO', 40],
        'codigo'          => ['Código do formulário', 'Ex.: FTIN 7.5.3.01', 40],
        'titulo'          => ['Título no cabeçalho', 'Vazio = título do termo', 200],
        'revisao'         => ['Nº revisão', 'Ex.: 06', 20],
        'emissao'         => ['Data de emissão', 'dd/mm/aaaa', 20],
        'ultima_revisao'  => ['Última revisão', 'dd/mm/aaaa', 20],
        'proxima_revisao' => ['Próxima revisão', 'dd/mm/aaaa', 20],
        'elaborado'       => ['Elaborado e revisado por', 'Nome', 100],
        'aprovado'        => ['Aprovado por', 'Nome', 100],
    ];

    public static function defaultDocControl(): array
    {
        $dc = ['layout' => 'simples'];
        foreach (array_keys(self::DOC_CONTROL_FIELDS) as $field) {
            $dc[$field] = $field === 'tipo' ? 'FORMULÁRIO' : '';
        }
        return $dc;
    }

    public static function installSchema(): void
    {
        global $DB;
        if (!$DB->tableExists(self::TABLE)) {
            $DB->doQuery("CREATE TABLE `" . self::TABLE . "` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `entities_id` int unsigned NOT NULL DEFAULT '0',
                `company_name` varchar(255) NOT NULL DEFAULT '',
                `company_doc` varchar(50) NOT NULL DEFAULT '',
                `city` varchar(255) NOT NULL DEFAULT '',
                `texts` longtext COMMENT 'textos dos termos (JSON)',
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `entities_id` (`entities_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC");
        }
        // Atualização de versões anteriores: acrescenta só as colunas que faltam
        foreach (self::MIGRATIONS as $field => $definition) {
            if (!$DB->fieldExists(self::TABLE, $field)) {
                $DB->doQuery('ALTER TABLE `' . self::TABLE . "` ADD `{$field}` {$definition}");
            }
        }
        if (!$DB->tableExists(self::COUNTERS)) {
            $DB->doQuery("CREATE TABLE `" . self::COUNTERS . "` (
                `entities_id` int unsigned NOT NULL DEFAULT '0',
                `year` smallint unsigned NOT NULL DEFAULT '0' COMMENT '0 = numeração contínua',
                `value` int unsigned NOT NULL DEFAULT '0' COMMENT 'último número usado',
                PRIMARY KEY (`entities_id`, `year`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC");
        }
        // Códigos personalizados podem ser maiores que os aleatórios (até 1.3.0 eram 14 caracteres)
        if ($DB->tableExists(PluginAssettermsTerm::TABLE)) {
            $DB->doQuery('ALTER TABLE `' . PluginAssettermsTerm::TABLE . "` MODIFY `code` varchar(100) NOT NULL DEFAULT ''");
        }
    }

    public static function uninstallSchema(): void
    {
        global $DB;
        foreach ([self::TABLE, self::COUNTERS] as $table) {
            if ($DB->tableExists($table)) {
                $DB->doQuery('DROP TABLE `' . $table . '`');
            }
        }
    }

    // ------------------------------------------------------ Texto padrão

    /**
     * Texto padrão dos termos. Os marcadores {empresa} e {cnpj} são trocados pelos dados da empresa.
     *
     * @return array<string, array{titulo: string, declaracao: string, compromissos: string[], ciencia: string}>
     */
    public static function defaultTexts(): array
    {
        return [
            'entrega' => [
                'titulo'       => 'Termo de Responsabilidade e Entrega de Equipamento',
                'declaracao'   => 'Declaro que recebi da empresa {empresa}, em regime de comodato e para uso exclusivo no exercício das minhas atividades profissionais, '
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
            ],
            'devolucao' => [
                'titulo'       => 'Termo de Devolução de Equipamento',
                'declaracao'   => 'Declaro que, nesta data, devolvi à empresa {empresa} o equipamento e os acessórios descritos neste termo, '
                    . 'conferidos na presença do(a) responsável pela TI.',
                'compromissos' => [
                    'O estado do equipamento e de cada acessório na devolução é o registrado no campo de observações deste termo.',
                    'Acessórios não listados como devolvidos foram considerados ausentes na conferência.',
                    'Removi ou entreguei à TI os arquivos pessoais que mantinha no equipamento, quando havia.',
                ],
                'ciencia'      => 'Estou ciente de que os dados armazenados no equipamento poderão ser apagados para a sua reutilização, '
                    . 'conforme a Política de Segurança da Informação e a Lei Geral de Proteção de Dados (Lei nº 13.709/2018), '
                    . 'e de que danos ou ausências registrados neste termo serão tratados conforme o termo de entrega e as normas internas.',
            ],
        ];
    }

    // -------------------------------------------------- Leitura e gravação

    /** Configuração própria de uma entidade (linha da tabela) ou null */
    public static function getOwn(int $entities_id): ?array
    {
        global $DB;
        if (!$DB->tableExists(self::TABLE)) {
            return null;
        }
        foreach ($DB->request(['FROM' => self::TABLE, 'WHERE' => ['entities_id' => $entities_id]]) as $row) {
            $row['texts'] = json_decode((string) $row['texts'], true) ?: [];
            return $row;
        }
        return null;
    }

    /**
     * Configuração que vale para a entidade: a própria ou a da entidade acima mais próxima.
     * Campos vazios caem no padrão (nome e cidade da entidade, texto padrão do plugin).
     *
     * @return array{entities_id: int, source: int|null, company_name: string, company_doc: string, city: string, texts: array}
     */
    public static function getEffective(int $entities_id): array
    {
        $chain = [$entities_id];
        foreach (array_reverse(getAncestorsOf('glpi_entities', $entities_id)) as $parent) {
            $chain[] = (int) $parent;
        }
        $own = null;
        $source = null;
        foreach ($chain as $id) {
            if (($own = self::getOwn($id)) !== null) {
                $source = $id;
                break;
            }
        }

        $entity = new Entity();
        $entity->getFromDB($entities_id);
        $defaults = self::defaultTexts();
        $texts = [];
        foreach ($defaults as $tipo => $default) {
            $saved = $own['texts'][$tipo] ?? [];
            $texts[$tipo] = [
                'titulo'       => trim((string) ($saved['titulo'] ?? '')) ?: $default['titulo'],
                'declaracao'   => trim((string) ($saved['declaracao'] ?? '')) ?: $default['declaracao'],
                'compromissos' => array_values(array_filter((array) ($saved['compromissos'] ?? []), 'strlen')) ?: $default['compromissos'],
                'ciencia'      => trim((string) ($saved['ciencia'] ?? '')) ?: $default['ciencia'],
            ];
        }

        return [
            'entities_id'  => $entities_id,
            'source'       => $source,
            'company_name' => trim((string) ($own['company_name'] ?? '')) ?: (string) ($entity->fields['name'] ?? ''),
            'company_doc'  => trim((string) ($own['company_doc'] ?? '')),
            'city'         => trim((string) ($own['city'] ?? '')) ?: trim((string) ($entity->fields['town'] ?? '')),
            'texts'        => $texts,
            // Código do documento
            'code_format'  => trim((string) ($own['code_format'] ?? '')) ?: self::DEFAULT_CODE_FORMAT,
            'code_prefix'  => (string) ($own['code_prefix'] ?? ''),
            'code_digits'  => max(1, min(10, (int) ($own['code_digits'] ?? 6))),
            'code_yearly'  => (int) ($own['code_yearly'] ?? 1) === 1,
            // Aparência
            'logo'         => ($own['logo'] ?? '') !== '' ? (string) base64_decode((string) $own['logo']) : '',
            'logo_mime'    => (string) ($own['logo_mime'] ?? ''),
            'color'        => preg_match('/^#[0-9a-f]{6}$/i', (string) ($own['color'] ?? '')) ? strtolower($own['color']) : self::DEFAULT_COLOR,
            'footer'       => (string) ($own['footer'] ?? ''),
            'doc_control'  => array_merge(self::defaultDocControl(), array_intersect_key(
                (array) (json_decode((string) ($own['doc_control'] ?? ''), true) ?: []),
                self::defaultDocControl()
            )),
        ];
    }

    // ---------------------------------------------------- Código do documento

    public const DEFAULT_CODE_FORMAT = '{aleatorio}';
    public const DEFAULT_COLOR = '#1f3a5f';

    /** Marcadores aceitos no formato do código */
    public const CODE_TAGS = [
        '{prefixo}'   => 'o prefixo definido ao lado',
        '{ano}'       => 'ano com 4 dígitos (2026)',
        '{mes}'       => 'mês (10)',
        '{dia}'       => 'dia (05)',
        '{seq}'       => 'número sequencial, com os dígitos definidos ao lado',
        '{tipo}'      => 'ENT na entrega, DEV na devolução',
        '{aleatorio}' => 'código aleatório (ex.: 7AE8-6BAC-E7C2)',
    ];

    /** Contador usado pela configuração: o da entidade onde ela está definida, por ano ou contínuo */
    private static function counterKey(array $cfg, int $time): array
    {
        return [(int) ($cfg['source'] ?? 0), $cfg['code_yearly'] ? (int) date('Y', $time) : 0];
    }

    /** Próximo número sem consumir (para mostrar na tela e no PDF de exemplo) */
    public static function peekNumber(array $cfg, int $time): int
    {
        global $DB;
        [$entity, $year] = self::counterKey($cfg, $time);
        foreach ($DB->request(['FROM' => self::COUNTERS, 'WHERE' => ['entities_id' => $entity, 'year' => $year]]) as $row) {
            return (int) $row['value'] + 1;
        }
        return 1;
    }

    /** Reserva o próximo número (atômico: dois termos ao mesmo tempo nunca recebem o mesmo) */
    public static function nextNumber(array $cfg, int $time): int
    {
        global $DB;
        [$entity, $year] = self::counterKey($cfg, $time);
        $DB->doQuery('INSERT INTO `' . self::COUNTERS . "` (`entities_id`, `year`, `value`) VALUES ({$entity}, {$year}, LAST_INSERT_ID(1))
            ON DUPLICATE KEY UPDATE `value` = LAST_INSERT_ID(`value` + 1)");
        $result = $DB->doQuery('SELECT LAST_INSERT_ID() AS v');
        return (int) ($result->fetch_assoc()['v'] ?? 0);
    }

    /** Define o próximo número (para continuar uma numeração que já existe) */
    public static function setNextNumber(array $cfg, int $next, int $time): void
    {
        global $DB;
        [$entity, $year] = self::counterKey($cfg, $time);
        $last = max(0, $next - 1);
        $DB->doQuery('INSERT INTO `' . self::COUNTERS . "` (`entities_id`, `year`, `value`) VALUES ({$entity}, {$year}, {$last})
            ON DUPLICATE KEY UPDATE `value` = {$last}");
    }

    /**
     * Monta o código do documento. Com $consume, reserva o número sequencial;
     * sem ele, usa o próximo número sem consumir (exemplos).
     */
    public static function buildCode(array $cfg, string $tipo, int $time, bool $consume = true): string
    {
        $format = $cfg['code_format'];
        $map = [
            '{prefixo}'   => $cfg['code_prefix'],
            '{ano}'       => date('Y', $time),
            '{mes}'       => date('m', $time),
            '{dia}'       => date('d', $time),
            '{tipo}'      => $tipo === 'devolucao' ? 'DEV' : 'ENT',
            '{aleatorio}' => implode('-', str_split(strtoupper(bin2hex(random_bytes(6))), 4)),
        ];
        if (str_contains($format, '{seq}')) {
            $seq = $consume ? self::nextNumber($cfg, $time) : self::peekNumber($cfg, $time);
            $map['{seq}'] = str_pad((string) $seq, $cfg['code_digits'], '0', STR_PAD_LEFT);
        }
        // Sem prefixo, não sobra separador sobrando no começo ou no fim (ex.: "-2026-000001")
        return trim(strtr($format, $map), '-_./ ');
    }

    // --------------------------------------------------------------- Logo

    /**
     * Lê o logo enviado no formulário ($_FILES['logo']). Retorna null se nenhum arquivo foi enviado,
     * ['data' => binário, 'mime' => ...] se for válido, ou a mensagem de erro (string).
     *
     * @return array|string|null
     */
    public static function readLogo(?array $file)
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || ($file['tmp_name'] ?? '') === '') {
            return null;
        }
        if (($file['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return 'Não foi possível receber o arquivo do logo.';
        }
        if (filesize($file['tmp_name']) > 1024 * 1024) {
            return 'O logo deve ter no máximo 1 MB.';
        }
        $data = (string) file_get_contents($file['tmp_name']);
        $info = @getimagesizefromstring($data);
        if ($info === false || !in_array($info['mime'] ?? '', ['image/png', 'image/jpeg'], true)) {
            return 'O logo deve ser uma imagem PNG ou JPG.';
        }
        return ['data' => $data, 'mime' => $info['mime']];
    }

    /**
     * Texto de um termo com os marcadores trocados pelos dados da empresa.
     *
     * @return array{titulo: string, declaracao: string, compromissos: string[], ciencia: string}
     */
    public static function render(array $cfg, string $tipo): array
    {
        $tipo = $tipo === 'devolucao' ? 'devolucao' : 'entrega';
        $map = [
            '{empresa}' => $cfg['company_name'] !== '' ? $cfg['company_name'] : 'a empresa',
            '{cnpj}'    => $cfg['company_doc'],
        ];
        $replace = static fn (string $s): string => trim(preg_replace('/\s{2,}/u', ' ', strtr($s, $map)));
        $t = $cfg['texts'][$tipo];
        return [
            'titulo'       => $replace($t['titulo']),
            'declaracao'   => $replace($t['declaracao']),
            'compromissos' => array_map($replace, $t['compromissos']),
            'ciencia'      => $replace($t['ciencia']),
        ];
    }

    /**
     * Valida o formulário da tela de configuração. Retorna os dados limpos ou a mensagem de erro (string).
     *
     * @return array|string
     */
    public static function collect(array $post)
    {
        $line  = static fn ($v): string => mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $v)), 0, self::MAX_LINE);
        $block = static fn ($v): string => mb_substr(trim(preg_replace('/[ \t]+/u', ' ', str_replace("\r", '', (string) $v))), 0, self::MAX_TEXT);

        $texts = [];
        foreach (array_keys(self::defaultTexts()) as $tipo) {
            $items = array_values(array_filter(array_map($line, explode("\n", str_replace("\r", '', (string) ($post["{$tipo}_compromissos"] ?? '')))), 'strlen'));
            if (count($items) > self::MAX_ITEMS) {
                return 'Use no máximo ' . self::MAX_ITEMS . ' compromissos por termo.';
            }
            $texts[$tipo] = [
                'titulo'       => $line($post["{$tipo}_titulo"] ?? ''),
                'declaracao'   => $block($post["{$tipo}_declaracao"] ?? ''),
                'compromissos' => $items,
                'ciencia'      => $block($post["{$tipo}_ciencia"] ?? ''),
            ];
            if ($texts[$tipo]['titulo'] === '' || $texts[$tipo]['declaracao'] === '') {
                return 'O título e a declaração dos dois termos são obrigatórios.';
            }
        }
        // Código do documento
        $format = trim((string) ($post['code_format'] ?? '')) ?: self::DEFAULT_CODE_FORMAT;
        if (mb_strlen($format) > 100 || preg_match('/[^A-Za-z0-9{}_\-.\/ ]/', $format)) {
            return 'O formato do código aceita letras, números, os marcadores e os separadores - _ . /';
        }
        if (preg_match_all('/\{[^}]*\}/', $format, $m) && array_diff($m[0], array_keys(self::CODE_TAGS))) {
            return 'Marcador desconhecido no formato do código: ' . implode(', ', array_diff($m[0], array_keys(self::CODE_TAGS))) . '.';
        }
        if (!str_contains($format, '{seq}') && !str_contains($format, '{aleatorio}')) {
            return 'O formato do código precisa ter {seq} ou {aleatorio}, para cada documento ter um código diferente.';
        }
        $prefix = trim((string) ($post['code_prefix'] ?? ''));
        if (mb_strlen($prefix) > 20 || preg_match('/[^A-Za-z0-9_\-.]/', $prefix)) {
            return 'O prefixo aceita até 20 letras, números e os separadores - _ .';
        }
        $next = trim((string) ($post['code_next'] ?? ''));
        if ($next !== '' && (!ctype_digit($next) || (int) $next < 1 || (int) $next > 999999999)) {
            return 'O próximo número precisa ser um número inteiro a partir de 1.';
        }

        // Aparência
        $color = strtolower(trim((string) ($post['color'] ?? '')));
        if ($color !== '' && !preg_match('/^#[0-9a-f]{6}$/', $color)) {
            return 'Cor inválida. Use o formato #1f3a5f.';
        }

        return [
            'company_name' => $line($post['company_name'] ?? ''),
            'company_doc'  => mb_substr(trim((string) ($post['company_doc'] ?? '')), 0, 50),
            'city'         => $line($post['city'] ?? ''),
            'texts'        => $texts,
            'code_format'  => $format,
            'code_prefix'  => $prefix,
            'code_digits'  => max(1, min(10, (int) ($post['code_digits'] ?? 6))),
            'code_yearly'  => !empty($post['code_yearly']),
            'code_next'    => $next !== '' ? (int) $next : null,
            'color'        => $color !== '' ? $color : self::DEFAULT_COLOR,
            'footer'       => mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($post['footer'] ?? ''))), 0, 300),
            'remove_logo'  => !empty($post['remove_logo']),
            'doc_control'  => self::collectDocControl($post),
        ];
    }

    /** Campos do cabeçalho de controle de documentos (prefixo dc_ no formulário) */
    private static function collectDocControl(array $post): array
    {
        $dc = ['layout' => ($post['dc_layout'] ?? '') === 'controle' ? 'controle' : 'simples'];
        foreach (self::DOC_CONTROL_FIELDS as $field => [, , $max]) {
            $dc[$field] = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($post['dc_' . $field] ?? ''))), 0, $max);
        }
        return $dc;
    }

    /**
     * Grava a configuração da entidade.
     *
     * @param array|null $logo Logo novo (readLogo) ou null para manter o atual
     */
    public static function save(int $entities_id, array $data, ?array $logo = null): void
    {
        global $DB;
        $own = self::getOwn($entities_id);
        $values = [
            'company_name' => $data['company_name'],
            'company_doc'  => $data['company_doc'],
            'city'         => $data['city'],
            'texts'        => json_encode($data['texts'], JSON_UNESCAPED_UNICODE),
            'code_format'  => $data['code_format'],
            'code_prefix'  => $data['code_prefix'],
            'code_digits'  => $data['code_digits'],
            'code_yearly'  => $data['code_yearly'] ? 1 : 0,
            'color'        => $data['color'],
            'footer'       => $data['footer'],
            'doc_control'  => json_encode($data['doc_control'], JSON_UNESCAPED_UNICODE),
            'date_mod'     => date('Y-m-d H:i:s'),
        ];
        if ($logo !== null) {
            $values['logo']      = base64_encode($logo['data']);
            $values['logo_mime'] = $logo['mime'];
        } elseif ($data['remove_logo']) {
            $values['logo']      = '';
            $values['logo_mime'] = '';
        } elseif ($own === null) {
            // Primeira configuração própria de uma filial: mantém o logo que ela herdava
            $inherited = self::getEffective($entities_id);
            if ($inherited['logo'] !== '') {
                $values['logo']      = base64_encode($inherited['logo']);
                $values['logo_mime'] = $inherited['logo_mime'];
            }
        }
        $values = PluginAssettermsTerm::dbValues($values);
        if ($own !== null) {
            $DB->update(self::TABLE, $values, ['entities_id' => $entities_id]);
        } else {
            $DB->insert(self::TABLE, $values + ['entities_id' => $entities_id]);
        }
    }

    public static function delete(int $entities_id): void
    {
        global $DB;
        $DB->delete(self::TABLE, ['entities_id' => $entities_id]);
    }

    /** Caminho da tela de configuração, relativo à raiz do GLPI */
    public static function pagePath(): string
    {
        return substr(PluginAssettermsTerm::webPath(), strlen($GLOBALS['CFG_GLPI']['root_doc'])) . '/front/config.php';
    }
}

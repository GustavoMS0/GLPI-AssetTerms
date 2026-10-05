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

    public static function installSchema(): void
    {
        global $DB;
        if ($DB->tableExists(self::TABLE)) {
            return;
        }
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

    public static function uninstallSchema(): void
    {
        global $DB;
        if ($DB->tableExists(self::TABLE)) {
            $DB->doQuery('DROP TABLE `' . self::TABLE . '`');
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
        ];
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
        return [
            'company_name' => $line($post['company_name'] ?? ''),
            'company_doc'  => mb_substr(trim((string) ($post['company_doc'] ?? '')), 0, 50),
            'city'         => $line($post['city'] ?? ''),
            'texts'        => $texts,
        ];
    }

    public static function save(int $entities_id, array $data): void
    {
        global $DB;
        $values = PluginAssettermsTerm::dbValues([
            'company_name' => $data['company_name'],
            'company_doc'  => $data['company_doc'],
            'city'         => $data['city'],
            'texts'        => json_encode($data['texts'], JSON_UNESCAPED_UNICODE),
            'date_mod'     => date('Y-m-d H:i:s'),
        ]);
        if (self::getOwn($entities_id) !== null) {
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

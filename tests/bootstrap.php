<?php

/**
 * ------------------------------------------------------------------------
 * Asset Terms - Bootstrap de testes (simulação isolada de GLPI 10, 11 e 12)
 *
 * Sem dependências externas (Composer/MySQL). Permite validar comportamento
 * de esquema, migrações, permissões, multiativos, PDF e fluxos AJAX.
 * ------------------------------------------------------------------------
 */

namespace {
error_reporting(E_ALL);

if (!defined('GLPI_ROOT')) {
    define('GLPI_ROOT', dirname(__DIR__));
}
if (!defined('GLPI_VERSION')) {
    define('GLPI_VERSION', getenv('TEST_GLPI_VERSION') ?: '11.0.10');
}
if (!defined('GLPI_TMP_DIR')) {
    $tmpDir = sys_get_temp_dir() . '/assetterms_tests_' . getmypid();
    if (!is_dir($tmpDir)) {
        @mkdir($tmpDir, 0777, true);
    }
    define('GLPI_TMP_DIR', $tmpDir);
}

if (!defined('READ')) {
    define('READ', 1);
}
if (!defined('UPDATE')) {
    define('UPDATE', 2);
}
if (!defined('CREATE')) {
    define('CREATE', 4);
}
if (!defined('DELETE')) {
    define('DELETE', 8);
}
if (!defined('INFO')) {
    define('INFO', 0);
}
if (!defined('ERROR')) {
    define('ERROR', 1);
}

// Polyfills PHP 7.4 (equivalentes ao symfony/polyfill-php80 do GLPI 10)
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }
        $len = strlen($needle);
        return strlen($haystack) >= $len && substr($haystack, -$len) === $needle;
    }
}

if (!function_exists('__')) {
    function __(string $text, string $domain = 'glpi'): string
    {
        return $text;
    }
}

global $CFG_GLPI, $PLUGIN_HOOKS, $DB;

$CFG_GLPI = [
    'root_doc'              => '/glpi',
    'url_base'              => 'https://glpi.example.test/glpi',
    'notifications_mailing' => 1,
    'from_email'            => 'glpi@example.test',
    'admin_email'           => 'admin@example.test',
];
$PLUGIN_HOOKS = [];

$GLOBALS['TEST_RIGHTS']         = [];
$GLOBALS['TEST_ENTITY_PARENTS'] = [];
$GLOBALS['TEST_OUTPUT']         = [];
$_SESSION = [
    'glpiID'             => 1,
    'glpiname'           => 'tech',
    'glpiactiveentities' => [0],
    'glpiactive_entity'  => 0,
    'glpiactiveprofile'  => ['interface' => 'central'],
];

if (!function_exists('getAncestorsOf')) {
    function getAncestorsOf(string $table, int $id): array
    {
        return $GLOBALS['TEST_ENTITY_PARENTS'][$id] ?? [];
    }
}

if (!function_exists('getEntitiesRestrictCriteria')) {
    function getEntitiesRestrictCriteria(string $table = '', string $field = '', $value = '', bool $is_recursive = false): array
    {
        if ($value === '' || $value === null) {
            $entities = array_values(array_map('intval', (array) ($_SESSION['glpiactiveentities'] ?? [0])));
        } elseif (is_array($value)) {
            $entities = array_values(array_map('intval', $value));
        } else {
            $entities = [(int) $value];
        }
        $col = ($table !== '' ? $table . '.' : '') . ($field !== '' ? $field : 'entities_id');
        if ($is_recursive && count($entities) > 0) {
            $ancestors = $entities;
            foreach ($entities as $eid) {
                foreach (getAncestorsOf('glpi_entities', $eid) as $anc) {
                    $ancestors[] = (int) $anc;
                }
            }
            $entities = array_values(array_unique($ancestors));
        }
        return [$col => $entities];
    }
}

class QueryExpression
{
    private string $expr;

    public function __construct(string $expr)
    {
        $this->expr = $expr;
    }

    public function getValue(): string
    {
        return $this->expr;
    }

    public function __toString(): string
    {
        return $this->expr;
    }
}

class MockDBResult
{
    private array $rows;
    private int $pos = 0;

    public function __construct(array $rows)
    {
        $this->rows = array_values($rows);
    }

    public function fetch_assoc(): ?array
    {
        if (!isset($this->rows[$this->pos])) {
            return null;
        }
        return $this->rows[$this->pos++];
    }
}

class MockDBmysql
{
    public array $tables = [];
    private int $lastInsertId = 0;
    private int $lastSeqValue = 0;
    private int $affectedRows = 0;

    public function reset(): void
    {
        $this->tables       = [];
        $this->lastInsertId = 0;
        $this->lastSeqValue = 0;
        $this->affectedRows = 0;
    }

    public function tableExists(string $table): bool
    {
        return isset($this->tables[$table]);
    }

    public function fieldExists(string $table, string $field): bool
    {
        return isset($this->tables[$table]['columns'][$field]);
    }

    public function indexExists(string $table, string $index): bool
    {
        return isset($this->tables[$table]['indexes'][$index]);
    }

    public function escape(string $value): string
    {
        return addslashes($value);
    }

    public function quoteName(string $name): string
    {
        return '`' . str_replace('`', '', $name) . '`';
    }

    public function insertId(): int
    {
        return $this->lastInsertId;
    }

    public function affectedRows(): int
    {
        return $this->affectedRows;
    }

    public function doQuery(string $sql)
    {
        $trimmed = trim($sql);

        if (preg_match('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`([^`]+)`\s*\((.*)\)/is', $trimmed, $m)) {
            $table   = $m[1];
            $body    = $m[2];
            $columns = [];
            $indexes = [];
            foreach (explode("\n", $body) as $line) {
                $line = trim($line, " \t\r\n,");
                if ($line === '') {
                    continue;
                }
                if (preg_match('/^`([^`]+)`\s+([^\s,]+(?:\([^)]+\))?)/i', $line, $cm)) {
                    $default = null;
                    if (preg_match("/DEFAULT\s+'([^']*)'/i", $line, $dm)) {
                        $default = $dm[1];
                    }
                    $columns[$cm[1]] = ['type' => strtolower($cm[2]), 'default' => $default];
                } elseif (preg_match('/^(?:UNIQUE\s+)?KEY\s+`([^`]+)`/i', $line, $km)) {
                    $indexes[$km[1]] = true;
                } elseif (stripos($line, 'PRIMARY KEY') === 0) {
                    $indexes['PRIMARY'] = true;
                }
            }
            $this->tables[$table] = [
                'columns'  => $columns,
                'indexes'  => $indexes,
                'rows'     => [],
                'auto_inc' => 1,
            ];
            return true;
        }

        if (preg_match('/^DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?`([^`]+)`/i', $trimmed, $m)) {
            unset($this->tables[$m[1]]);
            return true;
        }

        if (preg_match('/^ALTER\s+TABLE\s+`([^`]+)`\s+ADD\s+`([^`]+)`\s+(.+)$/is', $trimmed, $m)) {
            $table = $m[1];
            $col   = $m[2];
            $def   = $m[3];
            if (!isset($this->tables[$table])) {
                return false;
            }
            preg_match('/^([^\s]+(?:\([^)]+\))?)/i', trim($def), $tm);
            $default = null;
            if (preg_match("/DEFAULT\s+'([^']*)'/i", $def, $dm)) {
                $default = $dm[1];
            }
            $this->tables[$table]['columns'][$col] = ['type' => strtolower($tm[1] ?? 'text'), 'default' => $default];
            if ($default !== null) {
                foreach ($this->tables[$table]['rows'] as $k => $row) {
                    if (!array_key_exists($col, $row)) {
                        $this->tables[$table]['rows'][$k][$col] = $default;
                    }
                }
            }
            return true;
        }

        if (preg_match('/^ALTER\s+TABLE\s+`([^`]+)`\s+ADD\s+(?:UNIQUE\s+)?KEY\s+`([^`]+)`/i', $trimmed, $m)) {
            if (isset($this->tables[$m[1]])) {
                $this->tables[$m[1]]['indexes'][$m[2]] = true;
            }
            return true;
        }

        if (preg_match('/^ALTER\s+TABLE\s+`([^`]+)`\s+MODIFY\s+`([^`]+)`\s+([^\s]+(?:\([^)]+\))?)/i', $trimmed, $m)) {
            if (isset($this->tables[$m[1]]['columns'][$m[2]])) {
                $this->tables[$m[1]]['columns'][$m[2]]['type'] = strtolower($m[3]);
            }
            return true;
        }

        if (preg_match("/^SHOW\s+COLUMNS\s+FROM\s+`([^`]+)`\s+LIKE\s+'([^']+)'/i", $trimmed, $m)) {
            $col = $this->tables[$m[1]]['columns'][$m[2]] ?? null;
            return new MockDBResult($col ? [['Field' => $m[2], 'Type' => $col['type']]] : []);
        }

        if (preg_match("/^SHOW\s+INDEX\s+FROM\s+`([^`]+)`\s+WHERE\s+`Key_name`\s*=\s*'([^']+)'/i", $trimmed, $m)) {
            $exists = !empty($this->tables[$m[1]]['indexes'][$m[2]]);
            return new MockDBResult($exists ? [['Key_name' => $m[2]]] : []);
        }

        if (preg_match('/^INSERT\s+INTO\s+`([^`]+)`\s*\(`entities_id`,\s*`year`,\s*`value`\)\s*VALUES\s*\((\d+),\s*(\d+),\s*LAST_INSERT_ID\(1\)\)/is', $trimmed, $m)) {
            $table  = $m[1];
            $entity = (int) $m[2];
            $year   = (int) $m[3];
            $found  = null;
            foreach ($this->tables[$table]['rows'] as $idx => $r) {
                if ((int) $r['entities_id'] === $entity && (int) $r['year'] === $year) {
                    $found = $idx;
                    break;
                }
            }
            if ($found === null) {
                $this->tables[$table]['rows'][] = ['entities_id' => $entity, 'year' => $year, 'value' => 1];
                $this->lastSeqValue = 1;
            } else {
                $next = (int) $this->tables[$table]['rows'][$found]['value'] + 1;
                $this->tables[$table]['rows'][$found]['value'] = $next;
                $this->lastSeqValue = $next;
            }
            return true;
        }

        if (preg_match('/^INSERT\s+INTO\s+`([^`]+)`\s*\(`entities_id`,\s*`year`,\s*`value`\)\s*VALUES\s*\((\d+),\s*(\d+),\s*(\d+)\)/is', $trimmed, $m)) {
            $table  = $m[1];
            $entity = (int) $m[2];
            $year   = (int) $m[3];
            $val    = (int) $m[4];
            foreach ($this->tables[$table]['rows'] as $idx => $r) {
                if ((int) $r['entities_id'] === $entity && (int) $r['year'] === $year) {
                    $this->tables[$table]['rows'][$idx]['value'] = $val;
                    return true;
                }
            }
            $this->tables[$table]['rows'][] = ['entities_id' => $entity, 'year' => $year, 'value' => $val];
            return true;
        }

        if (stripos($trimmed, 'SELECT LAST_INSERT_ID()') === 0) {
            return new MockDBResult([['v' => $this->lastSeqValue]]);
        }

        throw new RuntimeException('MockDBmysql::doQuery não suportou SQL: ' . $trimmed);
    }

    public function insert(string $table, array $values): bool
    {
        if (!isset($this->tables[$table])) {
            return false;
        }
        $row = [];
        foreach ($this->tables[$table]['columns'] as $col => $meta) {
            if ($col === 'id') {
                continue;
            }
            if ($meta['default'] !== null) {
                $row[$col] = $meta['default'];
            } else {
                $row[$col] = null;
            }
        }
        foreach ($values as $k => $v) {
            $row[$k] = $this->unescapeInput($v);
        }
        if (isset($this->tables[$table]['columns']['id']) && empty($row['id'])) {
            $row['id'] = $this->tables[$table]['auto_inc']++;
            $this->lastInsertId = (int) $row['id'];
        } else {
            $this->lastInsertId = (int) ($row['id'] ?? 0);
        }
        $this->tables[$table]['rows'][] = $row;
        $this->affectedRows = 1;
        return true;
    }

    public function update(string $table, array $values, array $where): bool
    {
        if (!isset($this->tables[$table])) {
            $this->affectedRows = 0;
            return false;
        }
        $count = 0;
        foreach ($this->tables[$table]['rows'] as $idx => $row) {
            if ($this->matchWhere([$table => $row], $where, $table)) {
                foreach ($values as $k => $v) {
                    $this->tables[$table]['rows'][$idx][$k] = $this->unescapeInput($v);
                }
                $count++;
            }
        }
        $this->affectedRows = $count;
        return true;
    }

    public function updateOrInsert(string $table, array $values, array $where): bool
    {
        $existing = $this->request(['FROM' => $table, 'WHERE' => $where]);
        if (count($existing) > 0) {
            return $this->update($table, $values, $where);
        }
        return $this->insert($table, array_merge($where, $values));
    }

    public function delete(string $table, array $where): bool
    {
        if (!isset($this->tables[$table])) {
            $this->affectedRows = 0;
            return false;
        }
        $kept  = [];
        $count = 0;
        foreach ($this->tables[$table]['rows'] as $row) {
            if ($this->matchWhere([$table => $row], $where, $table)) {
                $count++;
            } else {
                $kept[] = $row;
            }
        }
        $this->tables[$table]['rows'] = $kept;
        $this->affectedRows = $count;
        return true;
    }

    private function unescapeInput($value)
    {
        if (is_string($value) && version_compare(GLPI_VERSION, '11.0.0-dev', '<')) {
            return stripslashes($value);
        }
        return $value;
    }

    public function request(array $criteria): array
    {
        [$baseTable, $baseAlias] = $this->parseTableAlias((string) ($criteria['FROM'] ?? ''));
        if ($baseTable === '' || !isset($this->tables[$baseTable])) {
            return [];
        }

        $contexts = [];
        foreach ($this->tables[$baseTable]['rows'] as $row) {
            $ctx = [$baseAlias => $row];
            if ($baseAlias !== $baseTable) {
                $ctx[$baseTable] = $row;
            }
            $contexts[] = $ctx;
        }

        foreach (['INNER JOIN' => false, 'LEFT JOIN' => true] as $joinKey => $isLeft) {
            if (empty($criteria[$joinKey])) {
                continue;
            }
            foreach ($criteria[$joinKey] as $target => $spec) {
                [$joinTable, $joinAlias] = $this->parseTableAlias((string) $target);
                $joinRows = $this->tables[$joinTable]['rows'] ?? [];
                $on       = $spec['ON'] ?? [];
                $next     = [];
                foreach ($contexts as $ctx) {
                    $matched = false;
                    foreach ($joinRows as $jRow) {
                        $candidate = $ctx;
                        $candidate[$joinAlias] = $jRow;
                        if ($joinAlias !== $joinTable) {
                            $candidate[$joinTable] = $jRow;
                        }
                        if ($this->matchJoinOn($candidate, $on)) {
                            $next[]  = $candidate;
                            $matched = true;
                        }
                    }
                    if ($isLeft && !$matched) {
                        $candidate = $ctx;
                        $candidate[$joinAlias] = null;
                        $next[] = $candidate;
                    }
                }
                $contexts = $next;
            }
        }

        if (!empty($criteria['WHERE'])) {
            $contexts = array_values(array_filter($contexts, function (array $ctx) use ($criteria, $baseAlias) {
                return $this->matchWhere($ctx, $criteria['WHERE'], $baseAlias);
            }));
        }

        if (isset($criteria['COUNT'])) {
            return [[(string) $criteria['COUNT'] => count($contexts)]];
        }

        // Expressões agregadas (MAX / SUM)
        $select = $criteria['SELECT'] ?? ['*'];
        if (!is_array($select)) {
            $select = [$select];
        }
        $hasAggregate = false;
        foreach ($select as $s) {
            if (is_object($s) && method_exists($s, 'getValue')) {
                $val = $s->getValue();
                if (stripos($val, 'MAX(') !== false || stripos($val, 'SUM(') !== false) {
                    $hasAggregate = true;
                }
            }
        }

        if ($hasAggregate) {
            $groups = [];
            $groupBy = $criteria['GROUPBY'] ?? null;
            if ($groupBy !== null) {
                foreach ($contexts as $ctx) {
                    $gk = (string) ($this->resolveCol($ctx, (string) $groupBy, $baseAlias) ?? '');
                    $groups[$gk][] = $ctx;
                }
            } else {
                $groups['__all__'] = $contexts;
            }
            $out = [];
            foreach ($groups as $grp) {
                $rowOut = [];
                foreach ($select as $s) {
                    if (is_object($s) && method_exists($s, 'getValue')) {
                        $expr = $s->getValue();
                        if (preg_match('/SUM\(`?([^`\)]+)`?\)\s+AS\s+`?([^`\s]+)`?/i', $expr, $m)) {
                            $sum = 0;
                            foreach ($grp as $ctx) {
                                $sum += (int) ($this->resolveCol($ctx, $m[1], $baseAlias) ?? 0);
                            }
                            $rowOut[$m[2]] = $sum;
                        } elseif (preg_match('/MAX\(`?([^`\)]+)`?\)\s+AS\s+`?([^`\s]+)`?/i', $expr, $m)) {
                            $max = 0;
                            foreach ($grp as $ctx) {
                                $v = (int) ($this->resolveCol($ctx, $m[1], $baseAlias) ?? 0);
                                if ($v > $max) {
                                    $max = $v;
                                }
                            }
                            $rowOut[$m[2]] = $max;
                        }
                    }
                }
                $out[] = $rowOut;
            }
            return $out;
        }

        if (!empty($criteria['ORDER'])) {
            $orders = is_array($criteria['ORDER']) ? $criteria['ORDER'] : [$criteria['ORDER']];
            usort($contexts, function (array $a, array $b) use ($orders, $baseAlias): int {
                foreach ($orders as $ord) {
                    $parts = preg_split('/\s+/', trim((string) $ord));
                    $col   = $parts[0];
                    $dir   = strtoupper($parts[1] ?? 'ASC') === 'DESC' ? -1 : 1;
                    $va    = $this->resolveCol($a, $col, $baseAlias);
                    $vb    = $this->resolveCol($b, $col, $baseAlias);
                    if ($va == $vb) {
                        continue;
                    }
                    return ($va < $vb ? -1 : 1) * $dir;
                }
                return 0;
            });
        }

        $projected = [];
        foreach ($contexts as $ctx) {
            $rowOut = [];
            foreach ($select as $s) {
                $s = (string) $s;
                if ($s === '*') {
                    $rowOut = array_merge($rowOut, $ctx[$baseAlias] ?? []);
                } elseif (preg_match('/^([^\s]+)\.\*$/', $s, $m)) {
                    $rowOut = array_merge($rowOut, $ctx[$m[1]] ?? []);
                } elseif (preg_match('/^([^\s]+)\s+AS\s+([^\s]+)$/i', $s, $m)) {
                    $rowOut[$m[2]] = $this->resolveCol($ctx, $m[1], $baseAlias);
                } else {
                    $colName = strpos($s, '.') !== false ? substr($s, strrpos($s, '.') + 1) : $s;
                    $rowOut[$colName] = $this->resolveCol($ctx, $s, $baseAlias);
                }
            }
            $projected[] = $rowOut;
        }

        if (!empty($criteria['DISTINCT'])) {
            $seen   = [];
            $unique = [];
            foreach ($projected as $r) {
                $key = json_encode($r);
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $unique[]   = $r;
                }
            }
            $projected = $unique;
        }

        $start = (int) ($criteria['START'] ?? 0);
        $limit = isset($criteria['LIMIT']) ? (int) $criteria['LIMIT'] : null;
        if ($start > 0 || $limit !== null) {
            $projected = array_slice($projected, $start, $limit);
        }

        return $projected;
    }

    private function parseTableAlias(string $raw): array
    {
        if (preg_match('/^([^\s]+)\s+AS\s+([^\s]+)$/i', trim($raw), $m)) {
            return [$m[1], $m[2]];
        }
        $t = trim($raw);
        return [$t, $t];
    }

    private function resolveCol(array $ctx, string $col, string $defaultAlias)
    {
        $col = str_replace('`', '', $col);
        if (strpos($col, '.') !== false) {
            [$alias, $field] = explode('.', $col, 2);
            return isset($ctx[$alias]) && is_array($ctx[$alias]) ? ($ctx[$alias][$field] ?? null) : null;
        }
        return $ctx[$defaultAlias][$col] ?? null;
    }

    private function matchJoinOn(array $ctx, array $on): bool
    {
        $pair = [];
        foreach ($on as $k => $v) {
            if ($k === 0 && is_array($v) && isset($v['AND'])) {
                if (!$this->matchWhere($ctx, $v['AND'], '')) {
                    return false;
                }
            } elseif (is_string($k) && is_string($v)) {
                $pair[] = [$k, $v];
            }
        }
        if (count($pair) === 2) {
            $left  = $this->resolveCol($ctx, $pair[0][0] . '.' . $pair[0][1], '');
            $right = $this->resolveCol($ctx, $pair[1][0] . '.' . $pair[1][1], '');
            return $left !== null && $right !== null && (string) $left === (string) $right;
        }
        return true;
    }

    private function matchWhere(array $ctx, array $where, string $defaultAlias): bool
    {
        foreach ($where as $k => $v) {
            if ($k === 'OR' && is_array($v)) {
                $any = false;
                foreach ($v as $subK => $subV) {
                    if (is_int($subK) && is_array($subV)) {
                        if ($this->matchWhere($ctx, $subV, $defaultAlias)) {
                            $any = true;
                            break;
                        }
                    } else {
                        if ($this->matchWhere($ctx, [$subK => $subV], $defaultAlias)) {
                            $any = true;
                            break;
                        }
                    }
                }
                if (!$any) {
                    return false;
                }
            } elseif ($k === 'AND' && is_array($v)) {
                if (!$this->matchWhere($ctx, $v, $defaultAlias)) {
                    return false;
                }
            } elseif ($k === 'NOT' && is_array($v)) {
                if ($this->matchWhere($ctx, $v, $defaultAlias)) {
                    return false;
                }
            } elseif (is_int($k) && is_array($v)) {
                if (!$this->matchWhere($ctx, $v, $defaultAlias)) {
                    return false;
                }
            } else {
                $actual = $this->resolveCol($ctx, (string) $k, $defaultAlias);
                if (is_array($v) && count($v) === 2 && isset($v[0]) && is_string($v[0]) && in_array(strtoupper($v[0]), ['LIKE', '<=', '<', '>=', '>', '!='], true)) {
                    $op  = strtoupper($v[0]);
                    $rhs = $v[1];
                    if ($op === 'LIKE') {
                        $pattern = (string) $rhs;
                        if (version_compare(GLPI_VERSION, '11.0.0-dev', '<')) {
                            $pattern = stripslashes($pattern);
                        }
                        $regex = $this->likeToRegex($pattern);
                        if (!preg_match($regex, (string) ($actual ?? ''))) {
                            return false;
                        }
                    } elseif ($op === '<=') {
                        if (!((int) $actual <= (int) $rhs)) {
                            return false;
                        }
                    } elseif ($op === '<') {
                        if (!((int) $actual < (int) $rhs)) {
                            return false;
                        }
                    } elseif ($op === '>=') {
                        if (!((int) $actual >= (int) $rhs)) {
                            return false;
                        }
                    } elseif ($op === '>') {
                        if (!((int) $actual > (int) $rhs)) {
                            return false;
                        }
                    } elseif ($op === '!=') {
                        if ((string) $actual === (string) $rhs) {
                            return false;
                        }
                    }
                } elseif (is_array($v)) {
                    $matchedIn = false;
                    foreach ($v as $cand) {
                        if ((string) $actual === (string) $cand) {
                            $matchedIn = true;
                            break;
                        }
                    }
                    if (!$matchedIn) {
                        return false;
                    }
                } elseif ($v === null) {
                    if ($actual !== null) {
                        return false;
                    }
                } else {
                    if ($actual === null || (string) $actual !== (string) $v) {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    private function likeToRegex(string $pattern): string
    {
        $regex = '/^';
        $len   = strlen($pattern);
        for ($i = 0; $i < $len; $i++) {
            $ch = $pattern[$i];
            if ($ch === '\\' && $i + 1 < $len) {
                $regex .= preg_quote($pattern[++$i], '/');
            } elseif ($ch === '%') {
                $regex .= '.*';
            } elseif ($ch === '_') {
                $regex .= '.';
            } else {
                $regex .= preg_quote($ch, '/');
            }
        }
        return $regex . '$/iu';
    }
}

$DB = new MockDBmysql();

$typedRightname = (getenv('TEST_TYPED_RIGHTNAME') ?: '0') === '1' || version_compare(GLPI_VERSION, '12.0.0-dev', '>=');
if ($typedRightname) {
    class CommonGLPI
    {
        public static string $rightname = '';

        public function getType(): string
        {
            return static::class;
        }

        public static function canView(): bool
        {
            return Session::haveRight(static::$rightname, READ);
        }

        public static function canUpdate(): bool
        {
            return Session::haveRight(static::$rightname, UPDATE);
        }
    }
} else {
    class CommonGLPI
    {
        public static $rightname = '';

        public function getType(): string
        {
            return static::class;
        }

        public static function canView(): bool
        {
            return Session::haveRight(static::$rightname, READ);
        }

        public static function canUpdate(): bool
        {
            return Session::haveRight(static::$rightname, UPDATE);
        }
    }
}

class CommonDBTM extends CommonGLPI
{
    public array $fields = [];

    public static function getTable(): string
    {
        return 'glpi_' . strtolower(static::class) . 's';
    }

    public function getID(): int
    {
        return (int) ($this->fields['id'] ?? 0);
    }

    public function isTemplate(): bool
    {
        return !empty($this->fields['is_template']);
    }

    public function getFromDB($id): bool
    {
        global $DB;
        foreach ($DB->request(['FROM' => static::getTable(), 'WHERE' => ['id' => (int) $id]]) as $row) {
            $this->fields = $row;
            return true;
        }
        $this->fields = [];
        return false;
    }

    public function add(array $input)
    {
        global $DB;
        $data = $input;
        unset($data['id']);
        if (!$DB->insert(static::getTable(), $data)) {
            return false;
        }
        $id = $DB->insertId();
        $this->getFromDB($id);
        return $id;
    }

    public function update(array $input): bool
    {
        global $DB;
        $id = (int) ($input['id'] ?? $this->getID());
        if ($id <= 0) {
            return false;
        }
        $data = $input;
        unset($data['id']);
        $ok = $DB->update(static::getTable(), $data, ['id' => $id]);
        if ($ok) {
            $this->getFromDB($id);
        }
        return $ok;
    }

    public function delete(array $input, $force = false): bool
    {
        global $DB;
        $id = (int) ($input['id'] ?? $this->getID());
        if ($force) {
            return $DB->delete(static::getTable(), ['id' => $id]);
        }
        return $DB->update(static::getTable(), ['is_deleted' => 1], ['id' => $id]);
    }

    public function can($id, $right): bool
    {
        if (!Session::haveRight(static::$rightname, $right)) {
            return false;
        }
        $tmp = new static();
        if (!$tmp->getFromDB((int) $id)) {
            return false;
        }
        return Session::haveAccessToEntity((int) ($tmp->fields['entities_id'] ?? 0), !empty($tmp->fields['is_recursive']));
    }

    public function canViewItem(): bool
    {
        return Session::haveRight(static::$rightname, READ)
            && Session::haveAccessToEntity((int) ($this->fields['entities_id'] ?? 0), !empty($this->fields['is_recursive']));
    }

    public function getLinkURL(): string
    {
        global $CFG_GLPI;
        return rtrim($CFG_GLPI['root_doc'], '/') . '/front/' . strtolower(static::class) . '.form.php?id=' . $this->getID();
    }

    public static function dropdown(array $options = []): void
    {
        $name  = htmlspecialchars((string) ($options['name'] ?? 'id'), ENT_QUOTES, 'UTF-8');
        $value = (int) ($options['value'] ?? 0);
        echo '<select name="' . $name . '" data-dropdown="' . static::class . '"><option value="' . $value . '">' . $value . '</option></select>';
    }
}

if ($typedRightname) {
    class Computer extends CommonDBTM { public static string $rightname = 'computer'; }
    class Phone extends CommonDBTM { public static string $rightname = 'phone'; }
    class Monitor extends CommonDBTM { public static string $rightname = 'monitor'; }
    class Peripheral extends CommonDBTM { public static string $rightname = 'peripheral'; }
    class User extends CommonDBTM
    {
        public static string $rightname = 'user';
        public function getDefaultEmail(): string
        {
            return (string) ($this->fields['email'] ?? '');
        }
    }
    class Entity extends CommonDBTM { public static string $rightname = 'entity'; }
    class Document extends CommonDBTM { public static string $rightname = 'document'; }
    class Document_Item extends CommonDBTM
    {
        public static string $rightname = 'document';
        public static function getTable(): string { return 'glpi_documents_items'; }
    }
} else {
    class Computer extends CommonDBTM { public static $rightname = 'computer'; }
    class Phone extends CommonDBTM { public static $rightname = 'phone'; }
    class Monitor extends CommonDBTM { public static $rightname = 'monitor'; }
    class Peripheral extends CommonDBTM { public static $rightname = 'peripheral'; }
    class User extends CommonDBTM
    {
        public static $rightname = 'user';
        public function getDefaultEmail(): string
        {
            return (string) ($this->fields['email'] ?? '');
        }
    }
    class Entity extends CommonDBTM { public static $rightname = 'entity'; }
    class Document extends CommonDBTM { public static $rightname = 'document'; }
    class Document_Item extends CommonDBTM
    {
        public static $rightname = 'document';
        public static function getTable(): string { return 'glpi_documents_items'; }
    }
}

class Session
{
    public static array $csrfTokens = ['valid-csrf' => true];
    public static array $messages   = [];

    public static function checkLoginUser(): void
    {
    }

    public static function getLoginUserID()
    {
        return $_SESSION['glpiID'] ?? false;
    }

    public static function getCurrentInterface(): string
    {
        return (string) ($_SESSION['glpiactiveprofile']['interface'] ?? 'central');
    }

    public static function haveRight(string $module, int $right): bool
    {
        return (((int) ($GLOBALS['TEST_RIGHTS'][$module] ?? 0)) & $right) === $right;
    }

    public static function checkRight(string $module, int $right): void
    {
        if (!self::haveRight($module, $right)) {
            throw new RuntimeException("Permissão negada: {$module}");
        }
    }

    public static function haveAccessToEntity(int $entities_id, bool $is_recursive = false): bool
    {
        $active = array_map('intval', (array) ($_SESSION['glpiactiveentities'] ?? [0]));
        if (in_array($entities_id, $active, true)) {
            return true;
        }
        if ($is_recursive) {
            foreach ($active as $aid) {
                if (in_array($entities_id, getAncestorsOf('glpi_entities', $aid), true)) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function getNewCSRFToken(): string
    {
        return 'valid-csrf';
    }

    public static function checkCSRF(array $data): void
    {
        $token = (string) ($data['_glpi_csrf_token'] ?? '');
        if ($token === '' || empty(self::$csrfTokens[$token])) {
            throw new RuntimeException('Invalid CSRF token');
        }
    }

    public static function addMessageAfterRedirect(string $msg, bool $clear = false, int $type = INFO): void
    {
        self::$messages[] = ['msg' => $msg, 'type' => $type];
    }
}

class Config
{
    public static function getConfigurationValues(string $context, array $keys = []): array
    {
        global $DB;
        if (!$DB->tableExists('glpi_configs')) {
            return [];
        }
        $out = [];
        foreach ($DB->request(['FROM' => 'glpi_configs', 'WHERE' => ['context' => $context]]) as $row) {
            if (empty($keys) || in_array($row['name'], $keys, true)) {
                $out[$row['name']] = $row['value'];
            }
        }
        return $out;
    }

    public static function setConfigurationValues(string $context, array $values): void
    {
        global $DB;
        foreach ($values as $name => $value) {
            $DB->updateOrInsert('glpi_configs', ['value' => (string) $value], ['context' => $context, 'name' => (string) $name]);
        }
    }

    public static function deleteConfigurationValues(string $context, array $keys): void
    {
        global $DB;
        foreach ($keys as $name) {
            $DB->delete('glpi_configs', ['context' => $context, 'name' => (string) $name]);
        }
    }

    public static function getEmailSender(int $entities_id = 0): array
    {
        global $CFG_GLPI;
        return ['email' => $CFG_GLPI['from_email'] ?? '', 'name' => 'GLPI'];
    }
}

class Dropdown
{
    public static function getDropdownName(string $table, int $id): string
    {
        global $DB;
        if ($id <= 0 || !$DB->tableExists($table)) {
            return '';
        }
        foreach ($DB->request(['FROM' => $table, 'WHERE' => ['id' => $id]]) as $row) {
            return (string) ($row['completename'] ?? ($row['name'] ?? ''));
        }
        return '';
    }
}

class Log
{
    public const HISTORY_LOG_SIMPLE_MESSAGE = 20;
    public static array $entries = [];

    public static function history($items_id, $itemtype, $changes, $history_type = '', $linked_action = 0): void
    {
        self::$entries[] = [
            'items_id' => (int) $items_id,
            'itemtype' => (string) $itemtype,
            'msg'      => (string) ($changes[2] ?? ''),
        ];
    }
}

class Plugin
{
    public static array $registered = [];
    public static bool $webDirCalled = false;

    public static function registerClass(string $class, array $params = []): void
    {
        self::$registered[$class] = $params;
    }

    public static function getWebDir(string $plugin, bool $full = true, bool $absolute = false): string
    {
        global $CFG_GLPI;
        self::$webDirCalled = true;
        $base = $full ? ($absolute ? $CFG_GLPI['url_base'] : $CFG_GLPI['root_doc']) : '';
        return $base . '/plugins/' . $plugin;
    }
}

class Toolbox
{
    public static array $logs = [];

    public static function logInFile(string $file, string $message): void
    {
        self::$logs[] = ['file' => $file, 'message' => $message];
    }

    public static function addslashes_deep($value)
    {
        if (is_array($value)) {
            return array_map([self::class, 'addslashes_deep'], $value);
        }
        return is_string($value) ? addslashes($value) : $value;
    }
}

class Html
{
    public static bool $rightErrorCalled = false;

    public static function header(string $title = '', string $url = '', string $sector = '', string $item = ''): void
    {
        echo "<header data-title=\"" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "\"></header>\n";
    }

    public static function footer(): void
    {
        echo "<footer></footer>\n";
    }

    public static function helpHeader(string $title = ''): void
    {
        self::header($title);
    }

    public static function helpFooter(): void
    {
        self::footer();
    }

    public static function convDateTime($date): string
    {
        return (string) $date;
    }

    public static function displayRightError(): void
    {
        self::$rightErrorCalled = true;
        echo '<div class="right-error">Acesso negado</div>';
    }

    public static function redirect(string $url): void
    {
        $GLOBALS['TEST_REDIRECT'] = $url;
    }

    public static function closeForm(bool $echo = true): string
    {
        $html = '<input type="hidden" name="_glpi_csrf_token" value="' . Session::getNewCSRFToken() . '"></form>';
        if ($echo) {
            echo $html;
        }
        return $html;
    }
}

class GLPIMailer
{
    public static array $sent = [];
    public string $CharSet = 'utf-8';
    public string $Subject = '';
    public string $Body = '';
    public string $AltBody = '';
    public string $ErrorInfo = '';
    private array $to = [];
    private array $attachments = [];

    public static function validateAddress(string $addr): bool
    {
        return filter_var($addr, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function setFrom(string $email, string $name = '', bool $auto = true): void {}

    public function addAddress(string $email, string $name = ''): void
    {
        $this->to[] = [$email, $name];
    }

    public function isHTML(bool $isHtml = true): void {}

    public function addStringAttachment(string $data, string $filename, string $encoding = 'base64', string $type = 'application/pdf'): void
    {
        $this->attachments[] = [$filename, strlen($data)];
    }

    public function send(): bool
    {
        self::$sent[] = [
            'to'          => $this->to,
            'subject'     => $this->Subject,
            'body'        => $this->Body,
            'attachments' => $this->attachments,
        ];
        return true;
    }
}

class TCPDF
{
    public static array $lastRendered = [];
    protected bool $tcpdflink = true;
    protected string $title = '';
    protected string $author = '';
    protected float $y = 20.0;

    public function __construct($orientation = 'P', $unit = 'mm', $format = 'A4', $unicode = true, $encoding = 'UTF-8')
    {
    }

    public function SetCreator($creator): void {}
    public function SetAuthor($author): void { $this->author = (string) $author; }
    public function SetTitle($title): void { $this->title = (string) $title; }
    public function setPrintHeader($val): void {}
    public function setPrintFooter($val): void {}
    public function setFooterFont($font): void {}
    public function SetMargins($left, $top, $right = -1): void {}
    public function SetHeaderMargin($hm): void {}
    public function SetFooterMargin($fm): void {}
    public function SetAutoPageBreak($auto, $margin = 0): void {}
    public function SetFont($family, $style = '', $size = 0): void {}
    public function SetTextColor($r, $g = 0, $b = 0): void {}
    public function SetDrawColor($r, $g = 0, $b = 0): void {}
    public function SetLineWidth($w): void {}
    public function setHtmlVSpace($arr): void {}
    public function AddPage(): void { $this->y = 20.0; }
    public function GetY(): float { return $this->y; }
    public function SetY($y): void { $this->y = (float) $y; }
    public function SetXY($x, $y): void { $this->y = (float) $y; }
    public function getPageHeight(): float { return 297.0; }
    public function Ln($h = ''): void { $this->y += (float) $h; }
    public function Rect($x, $y, $w, $h): void {}
    public function Line($x1, $y1, $x2, $y2): void {}
    public function Image($file, $x = '', $y = '', $w = 0, $h = 0, $type = ''): void {}
    public function GetStringWidth($s): float { return strlen((string) $s) * 2.0; }
    public function getCellPaddings(): array { return ['L' => 1, 'T' => 1, 'R' => 1, 'B' => 1]; }
    public function setCellPaddings($l, $t, $r, $b): void {}
    public function getAliasNumPage(): string { return '1'; }
    public function getAliasNbPages(): string { return '1'; }
    public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = ''): void {}
    public function MultiCell($w, $h, $txt, $border = 0, $align = 'J', $fill = false, $ln = 1): void
    {
        self::$lastRendered['multicells'][] = (string) $txt;
    }
    public function writeHTMLCell($w, $h, $x, $y, $html, $border = 0, $ln = 0, $fill = false, $reseth = true, $align = '', $autopadding = true): void {}
    public function writeHTML($html, $ln = true, $fill = false, $reseth = false, $cell = false, $align = ''): void
    {
        self::$lastRendered['html'] = (self::$lastRendered['html'] ?? '') . (string) $html;
    }
    public function Output($name = 'doc.pdf', $dest = 'I'): string
    {
        self::$lastRendered['title']  = $this->title;
        self::$lastRendered['author'] = $this->author;
        return '%PDF-1.4 MOCK ' . $this->title;
    }
}
}

namespace Glpi\DBAL {
    class QueryExpression extends \QueryExpression {}
}

namespace Glpi\Toolbox {
    class Sanitizer
    {
        public static function unsanitize($value)
        {
            if (is_array($value)) {
                return array_map([self::class, 'unsanitize'], $value);
            }
            return is_string($value) ? htmlspecialchars_decode(stripslashes($value), ENT_QUOTES) : $value;
        }

        public static function sanitize($value)
        {
            if (is_array($value)) {
                return array_map([self::class, 'sanitize'], $value);
            }
            return is_string($value) ? addslashes(htmlspecialchars($value, ENT_QUOTES, 'UTF-8')) : $value;
        }
    }
}

namespace Glpi\Http {
    class Firewall
    {
        public const STRATEGY_NO_CHECK = 'no_check';
        public static array $strategies = [];
        public static function addPluginStrategyForLegacyScripts(string $plugin, string $regex, string $strategy): void
        {
            self::$strategies[$plugin][] = [$regex, $strategy];
        }
    }
}

namespace {
    // Cria tabelas mínimas do núcleo GLPI usadas pelo plugin
    function test_init_core_tables(): void
    {
        global $DB;
        $DB->reset();
        $DB->doQuery("CREATE TABLE `glpi_configs` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `context` varchar(150) NOT NULL DEFAULT '',
            `name` varchar(150) NOT NULL DEFAULT '',
            `value` text,
            PRIMARY KEY (`id`)
        )");
        $DB->doQuery("CREATE TABLE `glpi_entities` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL DEFAULT '',
            `town` varchar(255) NOT NULL DEFAULT '',
            PRIMARY KEY (`id`)
        )");
        $DB->doQuery("CREATE TABLE `glpi_users` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL DEFAULT '',
            `firstname` varchar(255) NOT NULL DEFAULT '',
            `realname` varchar(255) NOT NULL DEFAULT '',
            `registration_number` varchar(255) NOT NULL DEFAULT '',
            `email` varchar(255) NOT NULL DEFAULT '',
            `is_active` tinyint NOT NULL DEFAULT '1',
            `is_deleted` tinyint NOT NULL DEFAULT '0',
            `begin_date` varchar(30) DEFAULT NULL,
            `end_date` varchar(30) DEFAULT NULL,
            PRIMARY KEY (`id`)
        )");
        $DB->doQuery("CREATE TABLE `glpi_profiles_users` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `users_id` int unsigned NOT NULL DEFAULT '0',
            `entities_id` int unsigned NOT NULL DEFAULT '0',
            `is_recursive` tinyint NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`)
        )");
        $DB->doQuery("CREATE TABLE `glpi_states` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL DEFAULT '',
            `completename` varchar(255) NOT NULL DEFAULT '',
            `is_visible_computer` tinyint NOT NULL DEFAULT '1',
            `is_visible_phone` tinyint NOT NULL DEFAULT '1',
            `is_visible_monitor` tinyint NOT NULL DEFAULT '1',
            `is_visible_peripheral` tinyint NOT NULL DEFAULT '1',
            PRIMARY KEY (`id`)
        )");
        if (version_compare(GLPI_VERSION, '11.0.0-dev', '>=')) {
            $DB->doQuery("CREATE TABLE `glpi_dropdownvisibilities` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `itemtype` varchar(100) NOT NULL DEFAULT '',
                `items_id` int unsigned NOT NULL DEFAULT '0',
                `visible_itemtype` varchar(100) NOT NULL DEFAULT '',
                `is_visible` tinyint NOT NULL DEFAULT '1',
                PRIMARY KEY (`id`)
            )");
        }
        $DB->doQuery("CREATE TABLE `glpi_manufacturers` (`id` int unsigned NOT NULL AUTO_INCREMENT, `name` varchar(255) NOT NULL DEFAULT '', PRIMARY KEY (`id`))");
        $DB->doQuery("CREATE TABLE `glpi_operatingsystems` (`id` int unsigned NOT NULL AUTO_INCREMENT, `name` varchar(255) NOT NULL DEFAULT '', PRIMARY KEY (`id`))");
        $DB->doQuery("CREATE TABLE `glpi_operatingsystemversions` (`id` int unsigned NOT NULL AUTO_INCREMENT, `name` varchar(255) NOT NULL DEFAULT '', PRIMARY KEY (`id`))");
        $DB->doQuery("CREATE TABLE `glpi_items_operatingsystems` (`id` int unsigned NOT NULL AUTO_INCREMENT, `items_id` int unsigned NOT NULL DEFAULT '0', `itemtype` varchar(100) NOT NULL DEFAULT '', `operatingsystems_id` int unsigned NOT NULL DEFAULT '0', `operatingsystemversions_id` int unsigned NOT NULL DEFAULT '0', PRIMARY KEY (`id`))");
        $DB->doQuery("CREATE TABLE `glpi_deviceprocessors` (`id` int unsigned NOT NULL AUTO_INCREMENT, `designation` varchar(255) NOT NULL DEFAULT '', PRIMARY KEY (`id`))");
        $DB->doQuery("CREATE TABLE `glpi_items_deviceprocessors` (`id` int unsigned NOT NULL AUTO_INCREMENT, `items_id` int unsigned NOT NULL DEFAULT '0', `itemtype` varchar(100) NOT NULL DEFAULT '', `deviceprocessors_id` int unsigned NOT NULL DEFAULT '0', `is_deleted` tinyint NOT NULL DEFAULT '0', PRIMARY KEY (`id`))");
        $DB->doQuery("CREATE TABLE `glpi_items_devicememories` (`id` int unsigned NOT NULL AUTO_INCREMENT, `items_id` int unsigned NOT NULL DEFAULT '0', `itemtype` varchar(100) NOT NULL DEFAULT '', `size` int unsigned NOT NULL DEFAULT '0', `is_deleted` tinyint NOT NULL DEFAULT '0', PRIMARY KEY (`id`))");
        $DB->doQuery("CREATE TABLE `glpi_items_deviceharddrives` (`id` int unsigned NOT NULL AUTO_INCREMENT, `items_id` int unsigned NOT NULL DEFAULT '0', `itemtype` varchar(100) NOT NULL DEFAULT '', `capacity` int unsigned NOT NULL DEFAULT '0', `is_deleted` tinyint NOT NULL DEFAULT '0', PRIMARY KEY (`id`))");

        foreach (['computer', 'phone', 'monitor', 'peripheral'] as $t) {
            $DB->doQuery("CREATE TABLE `glpi_{$t}models` (`id` int unsigned NOT NULL AUTO_INCREMENT, `name` varchar(255) NOT NULL DEFAULT '', PRIMARY KEY (`id`))");
            $DB->doQuery("CREATE TABLE `glpi_{$t}types` (`id` int unsigned NOT NULL AUTO_INCREMENT, `name` varchar(255) NOT NULL DEFAULT '', PRIMARY KEY (`id`))");
            $extra = ($t === 'phone' || $t === 'peripheral') ? "`brand` varchar(255) NOT NULL DEFAULT ''," : '';
            $DB->doQuery("CREATE TABLE `glpi_{$t}s` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL DEFAULT '',
                `entities_id` int unsigned NOT NULL DEFAULT '0',
                `is_recursive` tinyint NOT NULL DEFAULT '0',
                `users_id` int unsigned NOT NULL DEFAULT '0',
                `contact` varchar(255) NOT NULL DEFAULT '',
                `states_id` int unsigned NOT NULL DEFAULT '0',
                `manufacturers_id` int unsigned NOT NULL DEFAULT '0',
                {$extra}
                `{$t}models_id` int unsigned NOT NULL DEFAULT '0',
                `{$t}types_id` int unsigned NOT NULL DEFAULT '0',
                `serial` varchar(255) NOT NULL DEFAULT '',
                `otherserial` varchar(255) NOT NULL DEFAULT '',
                `is_deleted` tinyint NOT NULL DEFAULT '0',
                `is_template` tinyint NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`)
            )");
        }

        $DB->doQuery("CREATE TABLE `glpi_documents` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL DEFAULT '',
            `comment` text,
            `entities_id` int unsigned NOT NULL DEFAULT '0',
            `is_recursive` tinyint NOT NULL DEFAULT '0',
            `users_id` int unsigned NOT NULL DEFAULT '0',
            `is_deleted` tinyint NOT NULL DEFAULT '0',
            `date_creation` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`)
        )");
        $DB->doQuery("CREATE TABLE `glpi_documents_items` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `documents_id` int unsigned NOT NULL DEFAULT '0',
            `itemtype` varchar(100) NOT NULL DEFAULT '',
            `items_id` int unsigned NOT NULL DEFAULT '0',
            `entities_id` int unsigned NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`)
        )");
    }

    register_shutdown_function(static function (): void {
        if (defined('GLPI_TMP_DIR') && is_dir(GLPI_TMP_DIR)) {
            foreach (glob(GLPI_TMP_DIR . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir(GLPI_TMP_DIR);
        }
    });
}

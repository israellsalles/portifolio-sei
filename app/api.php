<?php

function systemSelectSql(): string {
  return "SELECT
    s.*,
    vm.name AS vm_name,
    vm.ip AS vm_ip,
    vmh.name AS vm_homolog_name,
    vmh.ip AS vm_homolog_ip
  FROM systems s
  LEFT JOIN virtual_machines vm ON vm.id = s.vm_id
  LEFT JOIN virtual_machines vmh ON vmh.id = s.vm_homolog_id";
}

function normalizeSystemStatus(string $status): string {
  $value = trim($status);
  $map = [
    'ManutenÃ§Ã£o' => 'Manutenção',
    'ImplantaÃ§Ã£o' => 'Implantação',
  ];
  return $map[$value] ?? $value;
}

function normalizeUrlListValue($raw): array {
  $values = [];
  if (is_array($raw)) {
    foreach ($raw as $entry) {
      $parts = preg_split('/[\r\n,;]+/', (string)$entry) ?: [];
      foreach ($parts as $part) {
        $value = trim((string)$part);
        if ($value !== '') { $values[] = $value; }
      }
    }
  } else {
    $parts = preg_split('/[\r\n,;]+/', (string)$raw) ?: [];
    foreach ($parts as $part) {
      $value = trim((string)$part);
      if ($value !== '') { $values[] = $value; }
    }
  }

  $seen = [];
  $out = [];
  foreach ($values as $value) {
    $key = strtolower($value);
    if (isset($seen[$key])) { continue; }
    $seen[$key] = true;
    $out[] = $value;
  }
  return $out;
}

function packUrlListValue($raw): string {
  return implode("\n", normalizeUrlListValue($raw));
}

function normalizeVmInstancesValue($raw): array {
  $list = [];
  if (is_array($raw)) {
    $list = $raw;
  } elseif (is_string($raw)) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) { $list = $decoded; }
  }

  $out = [];
  $seen = [];
  foreach ($list as $item) {
    if (!is_array($item)) { continue; }
    $name = trim((string)($item['name'] ?? $item['technology'] ?? $item['label'] ?? ''));
    $ip = trim((string)($item['ip'] ?? $item['host'] ?? ''));
    if ($name === '' && $ip === '') { continue; }
    if ($name === '') { $name = 'Instancia ' . (count($out) + 1); }
    if ($ip === '') { continue; }
    $key = strtolower($name) . '|' . strtolower($ip);
    if (isset($seen[$key])) { continue; }
    $seen[$key] = true;
    $out[] = ['name' => $name, 'ip' => $ip];
  }
  return $out;
}

function vmInstancesWithFallback(array $vm): array {
  $instances = normalizeVmInstancesValue($vm['vm_instances_list'] ?? ($vm['vm_instances'] ?? ''));
  if ($instances) { return $instances; }
  $ip = trim((string)($vm['ip'] ?? ''));
  if ($ip === '') { return []; }
  return [['name' => 'Instancia principal', 'ip' => $ip]];
}

function resolveVmInstance(array $vm, string $name, string $ip): ?array {
  $instances = vmInstancesWithFallback($vm);
  if (!$instances) { return null; }
  $targetName = trim($name);
  $targetIp = trim($ip);

  if ($targetIp !== '') {
    foreach ($instances as $instance) {
      $instIp = trim((string)($instance['ip'] ?? ''));
      $instName = trim((string)($instance['name'] ?? ''));
      if ($instIp !== $targetIp) { continue; }
      if ($targetName !== '' && strtolower($instName) !== strtolower($targetName)) { continue; }
      return ['name' => $instName, 'ip' => $instIp];
    }
  }

  if ($targetName !== '') {
    foreach ($instances as $instance) {
      $instName = trim((string)($instance['name'] ?? ''));
      $instIp = trim((string)($instance['ip'] ?? ''));
      if (strtolower($instName) === strtolower($targetName)) {
        return ['name' => $instName, 'ip' => $instIp];
      }
    }
  }

  if ($targetName === '' && $targetIp === '') { return $instances[0]; }
  return null;
}

function normalizeSystemRow(array $row): array {
  $row['tech'] = $row['tech'] !== '' ? array_values(array_filter(array_map('trim', explode(',', (string)$row['tech'])))) : [];
  $row['vm_id'] = isset($row['vm_id']) && $row['vm_id'] !== null && (int)$row['vm_id'] > 0 ? (int)$row['vm_id'] : null;
  $row['vm_homolog_id'] = isset($row['vm_homolog_id']) && $row['vm_homolog_id'] !== null && (int)$row['vm_homolog_id'] > 0 ? (int)$row['vm_homolog_id'] : null;
  $row['status'] = normalizeSystemStatus((string)($row['status'] ?? ''));
  $row['url_list'] = normalizeUrlListValue((string)($row['url'] ?? ''));
  $row['url_homolog_list'] = normalizeUrlListValue((string)($row['url_homolog'] ?? ''));
  $row['url'] = implode("\n", $row['url_list']);
  $row['url_homolog'] = implode("\n", $row['url_homolog_list']);

  if (($row['vm_name'] ?? '') === '') { $row['vm_name'] = (string)($row['vm'] ?? ''); }
  if (($row['vm_homolog_name'] ?? '') === '') { $row['vm_homolog_name'] = (string)($row['vm_homolog'] ?? ''); }
  if (($row['vm_ip'] ?? '') === '') { $row['vm_ip'] = (string)($row['ip'] ?? ''); }
  if (($row['vm_homolog_ip'] ?? '') === '') { $row['vm_homolog_ip'] = (string)($row['ip_homolog'] ?? ''); }

  return $row;
}

function fetchSystemsSqlite3(SQLite3 $db, bool $archived=false): array {
  $flag = $archived ? 1 : 0;
  $res = $db->query(systemSelectSql() . " WHERE IFNULL(s.archived,0)=$flag ORDER BY s.name COLLATE NOCASE");
  $out = [];
  while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $out[] = normalizeSystemRow($row);
  }
  return $out;
}

function fetchSystemsPdo(PDO $db, bool $archived=false): array {
  $flag = $archived ? 1 : 0;
  $rows = $db->query(systemSelectSql() . " WHERE IFNULL(s.archived,0)=$flag ORDER BY s.name COLLATE NOCASE")->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as &$row) { $row = normalizeSystemRow($row); }
  unset($row);
  return $rows;
}

function fetchSystemByIdSqlite3(SQLite3 $db, int $id): ?array {
  $st = $db->prepare(systemSelectSql() . " WHERE s.id=:id LIMIT 1");
  $st->bindValue(':id', $id, SQLITE3_INTEGER);
  $res = $st->execute();
  $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;
  if (!is_array($row)) { return null; }
  return normalizeSystemRow($row);
}

function fetchSystemByIdPdo(PDO $db, int $id): ?array {
  $st = $db->prepare(systemSelectSql() . " WHERE s.id=:id LIMIT 1");
  $st->bindValue(':id', $id, PDO::PARAM_INT);
  $st->execute();
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!is_array($row)) { return null; }
  return normalizeSystemRow($row);
}

function bindSystemFieldSqlite3(SQLite3Stmt $st, string $field, array $data): void {
  if ($field === 'vm_id' || $field === 'vm_homolog_id') {
    $id = (int)($data[$field] ?? 0);
    if ($id > 0) { $st->bindValue(':'.$field, $id, SQLITE3_INTEGER); }
    else { $st->bindValue(':'.$field, null, SQLITE3_NULL); }
    return;
  }
  if ($field === 'archived') {
    $st->bindValue(':'.$field, (int)($data[$field] ?? 0), SQLITE3_INTEGER);
    return;
  }
  if ($field === 'archived_at') {
    $val = $data[$field] ?? null;
    if ($val === null || $val === '') { $st->bindValue(':'.$field, null, SQLITE3_NULL); }
    else { $st->bindValue(':'.$field, trim((string)$val), SQLITE3_TEXT); }
    return;
  }
  $st->bindValue(':'.$field, trim((string)($data[$field] ?? '')), SQLITE3_TEXT);
}

function bindSystemFieldPdo(PDOStatement $st, string $field, array $data): void {
  if ($field === 'vm_id' || $field === 'vm_homolog_id') {
    $id = (int)($data[$field] ?? 0);
    if ($id > 0) { $st->bindValue(':'.$field, $id, PDO::PARAM_INT); }
    else { $st->bindValue(':'.$field, null, PDO::PARAM_NULL); }
    return;
  }
  if ($field === 'archived') {
    $st->bindValue(':'.$field, (int)($data[$field] ?? 0), PDO::PARAM_INT);
    return;
  }
  if ($field === 'archived_at') {
    $val = $data[$field] ?? null;
    if ($val === null || $val === '') { $st->bindValue(':'.$field, null, PDO::PARAM_NULL); }
    else { $st->bindValue(':'.$field, trim((string)$val), PDO::PARAM_STR); }
    return;
  }
  $st->bindValue(':'.$field, trim((string)($data[$field] ?? '')), PDO::PARAM_STR);
}

function listVmsSqlite3(SQLite3 $db, bool $archived=false): array {
  $flag = $archived ? 1 : 0;
  $sql = "SELECT
    vm.*,
    (SELECT COUNT(*) FROM systems s WHERE s.vm_id = vm.id AND IFNULL(s.archived,0)=0) AS prod_count,
    (SELECT COUNT(*) FROM systems s WHERE s.vm_homolog_id = vm.id AND IFNULL(s.archived,0)=0) AS hml_count,
    (SELECT COUNT(*) FROM systems s WHERE (s.vm_id = vm.id OR s.vm_homolog_id = vm.id) AND IFNULL(s.archived,0)=0) AS system_count,
    (SELECT COUNT(*) FROM system_databases d WHERE (d.vm_id = vm.id OR d.vm_homolog_id = vm.id) AND IFNULL(d.archived,0)=0) AS database_count
  FROM virtual_machines vm
  WHERE IFNULL(vm.archived,0) = $flag
  ORDER BY vm.name COLLATE NOCASE";
  $res = $db->query($sql);
  $out = [];
  while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $row['id'] = (int)$row['id'];
    $row['vm_category'] = trim((string)($row['vm_category'] ?? '')) !== '' ? trim((string)$row['vm_category']) : 'Producao';
    $row['vm_type'] = trim((string)($row['vm_type'] ?? '')) !== '' ? trim((string)$row['vm_type']) : 'Sistemas';
    $row['vm_access'] = trim((string)($row['vm_access'] ?? '')) !== '' ? trim((string)$row['vm_access']) : 'Interno';
    $row['vm_administration'] = trim((string)($row['vm_administration'] ?? '')) !== '' ? trim((string)$row['vm_administration']) : 'SEI';
    $row['vm_instances'] = trim((string)($row['vm_instances'] ?? ''));
    $row['vm_instances_list'] = normalizeVmInstancesValue($row['vm_instances']);
    $row['vm_tech'] = trim((string)($row['vm_tech'] ?? ''));
    $row['vm_tech_list'] = $row['vm_tech'] !== '' ? array_values(array_filter(array_map('trim', explode(',', $row['vm_tech'])))) : [];
    $row['diagnostic_json_ref'] = trim((string)($row['diagnostic_json_ref'] ?? ''));
    $row['diagnostic_json_updated_at'] = trim((string)($row['diagnostic_json_updated_at'] ?? ''));
    $row['prod_count'] = (int)$row['prod_count'];
    $row['hml_count'] = (int)$row['hml_count'];
    $row['system_count'] = (int)$row['system_count'];
    $row['database_count'] = (int)$row['database_count'];
    $out[] = $row;
  }
  return $out;
}

function listVmsPdo(PDO $db, bool $archived=false): array {
  $flag = $archived ? 1 : 0;
  $sql = "SELECT
    vm.*,
    (SELECT COUNT(*) FROM systems s WHERE s.vm_id = vm.id AND IFNULL(s.archived,0)=0) AS prod_count,
    (SELECT COUNT(*) FROM systems s WHERE s.vm_homolog_id = vm.id AND IFNULL(s.archived,0)=0) AS hml_count,
    (SELECT COUNT(*) FROM systems s WHERE (s.vm_id = vm.id OR s.vm_homolog_id = vm.id) AND IFNULL(s.archived,0)=0) AS system_count,
    (SELECT COUNT(*) FROM system_databases d WHERE (d.vm_id = vm.id OR d.vm_homolog_id = vm.id) AND IFNULL(d.archived,0)=0) AS database_count
  FROM virtual_machines vm
  WHERE IFNULL(vm.archived,0) = $flag
  ORDER BY vm.name COLLATE NOCASE";
  $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as &$row) {
    $row['id'] = (int)$row['id'];
    $row['vm_category'] = trim((string)($row['vm_category'] ?? '')) !== '' ? trim((string)$row['vm_category']) : 'Producao';
    $row['vm_type'] = trim((string)($row['vm_type'] ?? '')) !== '' ? trim((string)$row['vm_type']) : 'Sistemas';
    $row['vm_access'] = trim((string)($row['vm_access'] ?? '')) !== '' ? trim((string)$row['vm_access']) : 'Interno';
    $row['vm_administration'] = trim((string)($row['vm_administration'] ?? '')) !== '' ? trim((string)$row['vm_administration']) : 'SEI';
    $row['vm_instances'] = trim((string)($row['vm_instances'] ?? ''));
    $row['vm_instances_list'] = normalizeVmInstancesValue($row['vm_instances']);
    $row['vm_tech'] = trim((string)($row['vm_tech'] ?? ''));
    $row['vm_tech_list'] = $row['vm_tech'] !== '' ? array_values(array_filter(array_map('trim', explode(',', $row['vm_tech'])))) : [];
    $row['diagnostic_json_ref'] = trim((string)($row['diagnostic_json_ref'] ?? ''));
    $row['diagnostic_json_updated_at'] = trim((string)($row['diagnostic_json_updated_at'] ?? ''));
    $row['prod_count'] = (int)$row['prod_count'];
    $row['hml_count'] = (int)$row['hml_count'];
    $row['system_count'] = (int)$row['system_count'];
    $row['database_count'] = (int)$row['database_count'];
  }
  unset($row);
  return $rows;
}

function fetchVmByIdSqlite3(SQLite3 $db, int $id): ?array {
  $st = $db->prepare("SELECT id,name,ip,vm_category,vm_type,vm_access,vm_administration,vm_instances,vm_tech,diagnostic_json_ref,diagnostic_json_updated_at,os_name,os_version,vcpus,ram,disk,created_at,updated_at FROM virtual_machines WHERE id=:id");
  $st->bindValue(':id', $id, SQLITE3_INTEGER);
  $res = $st->execute();
  $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;
  if (!is_array($row)) { return null; }
  $row['id'] = (int)$row['id'];
  $row['vm_category'] = trim((string)($row['vm_category'] ?? '')) !== '' ? trim((string)$row['vm_category']) : 'Producao';
  $row['vm_type'] = trim((string)($row['vm_type'] ?? '')) !== '' ? trim((string)$row['vm_type']) : 'Sistemas';
  $row['vm_access'] = trim((string)($row['vm_access'] ?? '')) !== '' ? trim((string)$row['vm_access']) : 'Interno';
  $row['vm_administration'] = trim((string)($row['vm_administration'] ?? '')) !== '' ? trim((string)$row['vm_administration']) : 'SEI';
  $row['vm_instances'] = trim((string)($row['vm_instances'] ?? ''));
  $row['vm_instances_list'] = normalizeVmInstancesValue($row['vm_instances']);
  $row['vm_tech'] = trim((string)($row['vm_tech'] ?? ''));
  $row['vm_tech_list'] = $row['vm_tech'] !== '' ? array_values(array_filter(array_map('trim', explode(',', $row['vm_tech'])))) : [];
  $row['diagnostic_json_ref'] = trim((string)($row['diagnostic_json_ref'] ?? ''));
  $row['diagnostic_json_updated_at'] = trim((string)($row['diagnostic_json_updated_at'] ?? ''));
  return $row;
}

function fetchVmByIdPdo(PDO $db, int $id): ?array {
  $st = $db->prepare("SELECT id,name,ip,vm_category,vm_type,vm_access,vm_administration,vm_instances,vm_tech,diagnostic_json_ref,diagnostic_json_updated_at,os_name,os_version,vcpus,ram,disk,created_at,updated_at FROM virtual_machines WHERE id=:id");
  $st->bindValue(':id', $id, PDO::PARAM_INT);
  $st->execute();
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!is_array($row)) { return null; }
  $row['id'] = (int)$row['id'];
  $row['vm_category'] = trim((string)($row['vm_category'] ?? '')) !== '' ? trim((string)$row['vm_category']) : 'Producao';
  $row['vm_type'] = trim((string)($row['vm_type'] ?? '')) !== '' ? trim((string)$row['vm_type']) : 'Sistemas';
  $row['vm_access'] = trim((string)($row['vm_access'] ?? '')) !== '' ? trim((string)$row['vm_access']) : 'Interno';
  $row['vm_administration'] = trim((string)($row['vm_administration'] ?? '')) !== '' ? trim((string)$row['vm_administration']) : 'SEI';
  $row['vm_instances'] = trim((string)($row['vm_instances'] ?? ''));
  $row['vm_instances_list'] = normalizeVmInstancesValue($row['vm_instances']);
  $row['vm_tech'] = trim((string)($row['vm_tech'] ?? ''));
  $row['vm_tech_list'] = $row['vm_tech'] !== '' ? array_values(array_filter(array_map('trim', explode(',', $row['vm_tech'])))) : [];
  $row['diagnostic_json_ref'] = trim((string)($row['diagnostic_json_ref'] ?? ''));
  $row['diagnostic_json_updated_at'] = trim((string)($row['diagnostic_json_updated_at'] ?? ''));
  return $row;
}

function fetchActiveVmForDbSqlite3(SQLite3 $db, int $id): ?array {
  $st = $db->prepare("SELECT id,name,ip,vm_type,vm_instances FROM virtual_machines WHERE id=:id AND IFNULL(archived,0)=0 LIMIT 1");
  $st->bindValue(':id', $id, SQLITE3_INTEGER);
  $res = $st->execute();
  $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;
  if (!is_array($row)) { return null; }
  $row['id'] = (int)($row['id'] ?? 0);
  $row['vm_type'] = trim((string)($row['vm_type'] ?? '')) !== '' ? trim((string)$row['vm_type']) : 'Sistemas';
  $row['ip'] = trim((string)($row['ip'] ?? ''));
  $row['vm_instances'] = trim((string)($row['vm_instances'] ?? ''));
  $row['vm_instances_list'] = normalizeVmInstancesValue($row['vm_instances']);
  return $row;
}

function fetchActiveVmForDbPdo(PDO $db, int $id): ?array {
  $st = $db->prepare("SELECT id,name,ip,vm_type,vm_instances FROM virtual_machines WHERE id=:id AND IFNULL(archived,0)=0 LIMIT 1");
  $st->bindValue(':id', $id, PDO::PARAM_INT);
  $st->execute();
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!is_array($row)) { return null; }
  $row['id'] = (int)($row['id'] ?? 0);
  $row['vm_type'] = trim((string)($row['vm_type'] ?? '')) !== '' ? trim((string)$row['vm_type']) : 'Sistemas';
  $row['ip'] = trim((string)($row['ip'] ?? ''));
  $row['vm_instances'] = trim((string)($row['vm_instances'] ?? ''));
  $row['vm_instances_list'] = normalizeVmInstancesValue($row['vm_instances']);
  return $row;
}

function vmDiagnosticProjectRoot(): string {
  $root = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');
  return $root !== false ? $root : (__DIR__ . DIRECTORY_SEPARATOR . '..');
}

function vmDiagnosticDir(): string {
  return vmDiagnosticProjectRoot() . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'vm_diagnostics';
}

function ensureVmDiagnosticDir(): string {
  $dir = vmDiagnosticDir();
  if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
    throw new RuntimeException('Nao foi possivel criar diretorio de diagnostico');
  }
  if (!is_writable($dir)) {
    throw new RuntimeException('Diretorio de diagnostico sem permissao de escrita');
  }
  return $dir;
}

function sanitizeDiagnosticFilename(string $filename): string {
  $name = trim($filename);
  if ($name === '') { $name = 'diagnostic.json'; }
  $name = str_replace(['\\', '/'], '-', $name);
  $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?? 'diagnostic.json';
  if ($name === '' || $name === '.' || $name === '..') { $name = 'diagnostic.json'; }
  if (!str_ends_with(strtolower($name), '.json')) { $name .= '.json'; }
  return $name;
}

function decodeDiagnosticPayload(string $jsonText): array {
  $payload = json_decode($jsonText, true);
  if (!is_array($payload)) {
    throw new InvalidArgumentException('JSON invalido.');
  }
  if (!is_array($payload['php'] ?? null) || !is_array($payload['extensions'] ?? null) || !is_array($payload['ini'] ?? null)) {
    throw new InvalidArgumentException('JSON fora do modelo esperado (php/extensions/ini).');
  }
  return $payload;
}

function diagnosticSummary(array $payload): array {
  $php = is_array($payload['php'] ?? null) ? $payload['php'] : [];
  $extensions = is_array($payload['extensions'] ?? null) ? $payload['extensions'] : [];
  $ini = is_array($payload['ini'] ?? null) ? $payload['ini'] : [];

  $extNames = [];
  foreach ($extensions as $ext) {
    if (!is_array($ext)) { continue; }
    $name = trim((string)($ext['name'] ?? ''));
    if ($name === '') { continue; }
    $extNames[] = $name;
  }

  return [
    'php_version' => trim((string)($php['version'] ?? '')),
    'sapi' => trim((string)($php['sapi'] ?? '')),
    'os' => trim((string)($php['os'] ?? '')),
    'extension_count' => count($extNames),
    'ini_count' => count($ini),
    'extensions' => $extNames,
  ];
}

function databaseSelectSql(): string {
  return "SELECT
    d.*,
    s.name AS system_name,
    s.system_name AS system_alias,
    vm.name AS vm_name,
    vm.ip AS vm_ip,
    vmh.name AS vm_homolog_name,
    vmh.ip AS vm_homolog_ip
  FROM system_databases d
  LEFT JOIN systems s ON s.id = d.system_id
  LEFT JOIN virtual_machines vm ON vm.id = d.vm_id
  LEFT JOIN virtual_machines vmh ON vmh.id = d.vm_homolog_id";
}

function normalizeDatabaseRow(array $row): array {
  $row['id'] = (int)($row['id'] ?? 0);
  $row['system_id'] = (int)($row['system_id'] ?? 0);
  $row['vm_id'] = isset($row['vm_id']) && $row['vm_id'] !== null && (int)$row['vm_id'] > 0 ? (int)$row['vm_id'] : null;
  $row['vm_homolog_id'] = isset($row['vm_homolog_id']) && $row['vm_homolog_id'] !== null && (int)$row['vm_homolog_id'] > 0 ? (int)$row['vm_homolog_id'] : null;
  $row['db_name'] = trim((string)($row['db_name'] ?? ''));
  $row['db_user'] = trim((string)($row['db_user'] ?? ''));
  $row['db_engine'] = trim((string)($row['db_engine'] ?? ''));
  $row['db_engine_version'] = trim((string)($row['db_engine_version'] ?? ''));
  $row['db_engine_version_homolog'] = trim((string)($row['db_engine_version_homolog'] ?? ''));
  $row['db_instance_name'] = trim((string)($row['db_instance_name'] ?? ''));
  $row['db_instance_ip'] = trim((string)($row['db_instance_ip'] ?? ''));
  $row['db_instance_homolog_name'] = trim((string)($row['db_instance_homolog_name'] ?? ''));
  $row['db_instance_homolog_ip'] = trim((string)($row['db_instance_homolog_ip'] ?? ''));
  if ($row['db_instance_ip'] === '') { $row['db_instance_ip'] = trim((string)($row['vm_ip'] ?? '')); }
  if ($row['db_instance_homolog_ip'] === '') { $row['db_instance_homolog_ip'] = trim((string)($row['vm_homolog_ip'] ?? '')); }
  if ($row['db_instance_name'] === '' && $row['db_instance_ip'] !== '') { $row['db_instance_name'] = 'Instancia principal'; }
  if ($row['db_instance_homolog_name'] === '' && $row['db_instance_homolog_ip'] !== '') { $row['db_instance_homolog_name'] = 'Instancia principal'; }
  $row['notes'] = trim((string)($row['notes'] ?? ''));
  $row['archived'] = (int)($row['archived'] ?? 0);
  return $row;
}

function listDatabasesSqlite3(SQLite3 $db, bool $archived=false): array {
  $flag = $archived ? 1 : 0;
  $res = $db->query(databaseSelectSql() . " WHERE IFNULL(d.archived,0)=$flag ORDER BY d.db_name COLLATE NOCASE");
  $out = [];
  while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $out[] = normalizeDatabaseRow($row);
  }
  return $out;
}

function listDatabasesPdo(PDO $db, bool $archived=false): array {
  $flag = $archived ? 1 : 0;
  $rows = $db->query(databaseSelectSql() . " WHERE IFNULL(d.archived,0)=$flag ORDER BY d.db_name COLLATE NOCASE")->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as &$row) { $row = normalizeDatabaseRow($row); }
  unset($row);
  return $rows;
}

function fetchDatabaseByIdSqlite3(SQLite3 $db, int $id): ?array {
  $st = $db->prepare(databaseSelectSql() . " WHERE d.id=:id LIMIT 1");
  $st->bindValue(':id', $id, SQLITE3_INTEGER);
  $res = $st->execute();
  $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;
  if (!is_array($row)) { return null; }
  return normalizeDatabaseRow($row);
}

function fetchDatabaseByIdPdo(PDO $db, int $id): ?array {
  $st = $db->prepare(databaseSelectSql() . " WHERE d.id=:id LIMIT 1");
  $st->bindValue(':id', $id, PDO::PARAM_INT);
  $st->execute();
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!is_array($row)) { return null; }
  return normalizeDatabaseRow($row);
}

function handleApiRequest(): void {
  header('Content-Type: application/json; charset=utf-8');
  try {
    $db = db();
    $api = (string)($_GET['api'] ?? '');

    if ($api === 'list') {
      $out = $db instanceof SQLite3 ? fetchSystemsSqlite3($db, false) : fetchSystemsPdo($db, false);
      echo json_encode(['ok'=>true,'data'=>$out], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'vm-list') {
      $out = $db instanceof SQLite3 ? listVmsSqlite3($db, false) : listVmsPdo($db, false);
      echo json_encode(['ok'=>true,'data'=>$out], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'db-list') {
      $out = $db instanceof SQLite3 ? listDatabasesSqlite3($db, false) : listDatabasesPdo($db, false);
      echo json_encode(['ok'=>true,'data'=>$out], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'archived-list') {
      $systems = $db instanceof SQLite3 ? fetchSystemsSqlite3($db, true) : fetchSystemsPdo($db, true);
      $vms = $db instanceof SQLite3 ? listVmsSqlite3($db, true) : listVmsPdo($db, true);
      echo json_encode(['ok'=>true,'data'=>['systems'=>$systems,'vms'=>$vms]], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'archive') {
      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
      $data = json_decode((string)file_get_contents('php://input'), true);
      $id = (int)($data['id'] ?? 0);
      if ($id <= 0) { echo json_encode(['ok'=>false,'error'=>'Invalid ID']); return; }
      if ($db instanceof SQLite3) {
        $db->exec("UPDATE systems SET archived=1, archived_at=datetime('now','localtime') WHERE id=$id");
        $db->exec("UPDATE system_databases SET archived=1, archived_at=datetime('now','localtime'), updated_at=datetime('now','localtime') WHERE system_id=$id");
      } else {
        $st = $db->prepare("UPDATE systems SET archived=1, archived_at=datetime('now','localtime') WHERE id=:id");
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
        $stDb = $db->prepare("UPDATE system_databases SET archived=1, archived_at=datetime('now','localtime'), updated_at=datetime('now','localtime') WHERE system_id=:id");
        $stDb->bindValue(':id', $id, PDO::PARAM_INT);
        $stDb->execute();
      }
      echo json_encode(['ok'=>true]);
      return;
    }

    if ($api === 'restore') {
      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
      $data = json_decode((string)file_get_contents('php://input'), true);
      $id = (int)($data['id'] ?? 0);
      if ($id <= 0) { echo json_encode(['ok'=>false,'error'=>'Invalid ID']); return; }
      if ($db instanceof SQLite3) {
        $db->exec("UPDATE systems SET archived=0, archived_at=NULL WHERE id=$id");
        $db->exec("UPDATE system_databases SET archived=0, archived_at=NULL, updated_at=datetime('now','localtime') WHERE system_id=$id");
      } else {
        $st = $db->prepare("UPDATE systems SET archived=0, archived_at=NULL WHERE id=:id");
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
        $stDb = $db->prepare("UPDATE system_databases SET archived=0, archived_at=NULL, updated_at=datetime('now','localtime') WHERE system_id=:id");
        $stDb->bindValue(':id', $id, PDO::PARAM_INT);
        $stDb->execute();
      }
      echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'vm-save') {
      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
      $data = json_decode((string)file_get_contents('php://input'), true);
      if (!is_array($data)) { echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); return; }

      $name = trim((string)($data['name'] ?? ''));
      $ip = trim((string)($data['ip'] ?? ''));
      $vmCategory = trim((string)($data['vm_category'] ?? ''));
      $vmType = trim((string)($data['vm_type'] ?? ''));
      $vmAccess = trim((string)($data['vm_access'] ?? ''));
      $vmAdministration = trim((string)($data['vm_administration'] ?? ''));
      $vmInstancesList = normalizeVmInstancesValue($data['vm_instances'] ?? []);
      $vmInstances = json_encode($vmInstancesList, JSON_UNESCAPED_UNICODE);
      if (!is_string($vmInstances)) { $vmInstances = '[]'; }
      $vmTech = is_array($data['vm_tech'] ?? null) ? implode(',', array_filter(array_map('trim', $data['vm_tech']))) : trim((string)($data['vm_tech'] ?? ''));
      $osName = trim((string)($data['os_name'] ?? ''));
      $osVersion = trim((string)($data['os_version'] ?? ''));
      $vcpus = trim((string)($data['vcpus'] ?? ''));
      $ram = trim((string)($data['ram'] ?? ''));
      $disk = trim((string)($data['disk'] ?? ''));
      if ($name === '' || $ip === '') { echo json_encode(['ok'=>false,'error'=>'Nome e IP sao obrigatorios']); return; }
      $allowedCategories = ['Producao','Homologacao','Desenvolvimento'];
      if (!in_array($vmCategory, $allowedCategories, true)) { $vmCategory = 'Producao'; }
      $allowedTypes = ['Sistemas','SGBD'];
      if (!in_array($vmType, $allowedTypes, true)) { $vmType = 'Sistemas'; }
      $allowedAccess = ['Interno','Externo'];
      if (!in_array($vmAccess, $allowedAccess, true)) { $vmAccess = 'Interno'; }
      $allowedAdministration = ['SEI','PRODEB'];
      if (!in_array($vmAdministration, $allowedAdministration, true)) { $vmAdministration = 'SEI'; }
      if ($vmType === 'SGBD' && count($vmInstancesList) === 0) {
        echo json_encode(['ok'=>false,'error'=>'Para VM do tipo SGBD informe ao menos uma instancia com IP.']);
        return;
      }

      if (!empty($data['id'])) {
        $id = (int)$data['id'];
        if ($db instanceof SQLite3) {
          $st = $db->prepare("UPDATE virtual_machines SET name=:name, ip=:ip, vm_category=:vm_category, vm_type=:vm_type, vm_access=:vm_access, vm_administration=:vm_administration, vm_instances=:vm_instances, vm_tech=:vm_tech, os_name=:os_name, os_version=:os_version, vcpus=:vcpus, ram=:ram, disk=:disk, updated_at=datetime('now','localtime') WHERE id=:id");
          $st->bindValue(':name', $name, SQLITE3_TEXT);
          $st->bindValue(':ip', $ip, SQLITE3_TEXT);
          $st->bindValue(':vm_category', $vmCategory, SQLITE3_TEXT);
          $st->bindValue(':vm_type', $vmType, SQLITE3_TEXT);
          $st->bindValue(':vm_access', $vmAccess, SQLITE3_TEXT);
          $st->bindValue(':vm_administration', $vmAdministration, SQLITE3_TEXT);
          $st->bindValue(':vm_instances', $vmInstances, SQLITE3_TEXT);
          $st->bindValue(':vm_tech', $vmTech, SQLITE3_TEXT);
          $st->bindValue(':os_name', $osName, SQLITE3_TEXT);
          $st->bindValue(':os_version', $osVersion, SQLITE3_TEXT);
          $st->bindValue(':vcpus', $vcpus, SQLITE3_TEXT);
          $st->bindValue(':ram', $ram, SQLITE3_TEXT);
          $st->bindValue(':disk', $disk, SQLITE3_TEXT);
          $st->bindValue(':id', $id, SQLITE3_INTEGER);
          $st->execute();
          $row = fetchVmByIdSqlite3($db, $id);
        } else {
          $st = $db->prepare("UPDATE virtual_machines SET name=:name, ip=:ip, vm_category=:vm_category, vm_type=:vm_type, vm_access=:vm_access, vm_administration=:vm_administration, vm_instances=:vm_instances, vm_tech=:vm_tech, os_name=:os_name, os_version=:os_version, vcpus=:vcpus, ram=:ram, disk=:disk, updated_at=datetime('now','localtime') WHERE id=:id");
          $st->bindValue(':name', $name, PDO::PARAM_STR);
          $st->bindValue(':ip', $ip, PDO::PARAM_STR);
          $st->bindValue(':vm_category', $vmCategory, PDO::PARAM_STR);
          $st->bindValue(':vm_type', $vmType, PDO::PARAM_STR);
          $st->bindValue(':vm_access', $vmAccess, PDO::PARAM_STR);
          $st->bindValue(':vm_administration', $vmAdministration, PDO::PARAM_STR);
          $st->bindValue(':vm_instances', $vmInstances, PDO::PARAM_STR);
          $st->bindValue(':vm_tech', $vmTech, PDO::PARAM_STR);
          $st->bindValue(':os_name', $osName, PDO::PARAM_STR);
          $st->bindValue(':os_version', $osVersion, PDO::PARAM_STR);
          $st->bindValue(':vcpus', $vcpus, PDO::PARAM_STR);
          $st->bindValue(':ram', $ram, PDO::PARAM_STR);
          $st->bindValue(':disk', $disk, PDO::PARAM_STR);
          $st->bindValue(':id', $id, PDO::PARAM_INT);
          $st->execute();
          $row = fetchVmByIdPdo($db, $id);
        }
      } else {
        if ($db instanceof SQLite3) {
          $st = $db->prepare("INSERT INTO virtual_machines(name,ip,vm_category,vm_type,vm_access,vm_administration,vm_instances,vm_tech,os_name,os_version,vcpus,ram,disk) VALUES(:name,:ip,:vm_category,:vm_type,:vm_access,:vm_administration,:vm_instances,:vm_tech,:os_name,:os_version,:vcpus,:ram,:disk)");
          $st->bindValue(':name', $name, SQLITE3_TEXT);
          $st->bindValue(':ip', $ip, SQLITE3_TEXT);
          $st->bindValue(':vm_category', $vmCategory, SQLITE3_TEXT);
          $st->bindValue(':vm_type', $vmType, SQLITE3_TEXT);
          $st->bindValue(':vm_access', $vmAccess, SQLITE3_TEXT);
          $st->bindValue(':vm_administration', $vmAdministration, SQLITE3_TEXT);
          $st->bindValue(':vm_instances', $vmInstances, SQLITE3_TEXT);
          $st->bindValue(':vm_tech', $vmTech, SQLITE3_TEXT);
          $st->bindValue(':os_name', $osName, SQLITE3_TEXT);
          $st->bindValue(':os_version', $osVersion, SQLITE3_TEXT);
          $st->bindValue(':vcpus', $vcpus, SQLITE3_TEXT);
          $st->bindValue(':ram', $ram, SQLITE3_TEXT);
          $st->bindValue(':disk', $disk, SQLITE3_TEXT);
          $st->execute();
          $id = (int)$db->lastInsertRowID();
          $row = fetchVmByIdSqlite3($db, $id);
        } else {
          $st = $db->prepare("INSERT INTO virtual_machines(name,ip,vm_category,vm_type,vm_access,vm_administration,vm_instances,vm_tech,os_name,os_version,vcpus,ram,disk) VALUES(:name,:ip,:vm_category,:vm_type,:vm_access,:vm_administration,:vm_instances,:vm_tech,:os_name,:os_version,:vcpus,:ram,:disk)");
          $st->bindValue(':name', $name, PDO::PARAM_STR);
          $st->bindValue(':ip', $ip, PDO::PARAM_STR);
          $st->bindValue(':vm_category', $vmCategory, PDO::PARAM_STR);
          $st->bindValue(':vm_type', $vmType, PDO::PARAM_STR);
          $st->bindValue(':vm_access', $vmAccess, PDO::PARAM_STR);
          $st->bindValue(':vm_administration', $vmAdministration, PDO::PARAM_STR);
          $st->bindValue(':vm_instances', $vmInstances, PDO::PARAM_STR);
          $st->bindValue(':vm_tech', $vmTech, PDO::PARAM_STR);
          $st->bindValue(':os_name', $osName, PDO::PARAM_STR);
          $st->bindValue(':os_version', $osVersion, PDO::PARAM_STR);
          $st->bindValue(':vcpus', $vcpus, PDO::PARAM_STR);
          $st->bindValue(':ram', $ram, PDO::PARAM_STR);
          $st->bindValue(':disk', $disk, PDO::PARAM_STR);
          $st->execute();
          $id = (int)$db->lastInsertId();
          $row = fetchVmByIdPdo($db, $id);
        }
      }

      if (!$row) { echo json_encode(['ok'=>false,'error'=>'Maquina nao encontrada']); return; }
      echo json_encode(['ok'=>true,'data'=>$row], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'vm-diagnostic-get') {
      $id = (int)($_GET['id'] ?? 0);
      if ($id <= 0) { echo json_encode(['ok'=>false,'error'=>'Invalid ID']); return; }

      $vm = $db instanceof SQLite3 ? fetchVmByIdSqlite3($db, $id) : fetchVmByIdPdo($db, $id);
      if (!$vm) { echo json_encode(['ok'=>false,'error'=>'Maquina nao encontrada']); return; }

      $ref = trim((string)($vm['diagnostic_json_ref'] ?? ''));
      $updatedAt = trim((string)($vm['diagnostic_json_updated_at'] ?? ''));
      if ($ref === '') {
        echo json_encode([
          'ok'=>true,
          'data'=>[
            'vm_id'=>$id,
            'vm_name'=>trim((string)($vm['name'] ?? '')),
            'vm_label'=>trim((string)($vm['name'] ?? '')) !== '' || trim((string)($vm['ip'] ?? '')) !== '' ? trim((string)($vm['name'] ?? '')) . (trim((string)($vm['ip'] ?? '')) !== '' ? ' (' . trim((string)$vm['ip']) . ')' : '') : '',
            'reference'=>'',
            'filename'=>'',
            'updated_at'=>$updatedAt,
            'has_file'=>false,
            'summary'=>null,
            'json'=>null
          ]
        ], JSON_UNESCAPED_UNICODE);
        return;
      }

      $relative = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $ref);
      $fullPath = vmDiagnosticProjectRoot() . DIRECTORY_SEPARATOR . ltrim($relative, DIRECTORY_SEPARATOR);
      if (!is_file($fullPath)) {
        echo json_encode([
          'ok'=>true,
          'data'=>[
            'vm_id'=>$id,
            'vm_name'=>trim((string)($vm['name'] ?? '')),
            'vm_label'=>trim((string)($vm['name'] ?? '')) !== '' || trim((string)($vm['ip'] ?? '')) !== '' ? trim((string)($vm['name'] ?? '')) . (trim((string)($vm['ip'] ?? '')) !== '' ? ' (' . trim((string)$vm['ip']) . ')' : '') : '',
            'reference'=>$ref,
            'filename'=>basename($ref),
            'updated_at'=>$updatedAt,
            'has_file'=>false,
            'summary'=>null,
            'json'=>null
          ]
        ], JSON_UNESCAPED_UNICODE);
        return;
      }

      $jsonText = (string)file_get_contents($fullPath);
      try {
        $payload = decodeDiagnosticPayload($jsonText);
      } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'error'=>'Arquivo JSON referenciado invalido: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        return;
      }

      echo json_encode([
        'ok'=>true,
        'data'=>[
          'vm_id'=>$id,
          'vm_name'=>trim((string)($vm['name'] ?? '')),
          'vm_label'=>trim((string)($vm['name'] ?? '')) !== '' || trim((string)($vm['ip'] ?? '')) !== '' ? trim((string)($vm['name'] ?? '')) . (trim((string)($vm['ip'] ?? '')) !== '' ? ' (' . trim((string)$vm['ip']) . ')' : '') : '',
          'reference'=>$ref,
          'filename'=>basename($fullPath),
          'updated_at'=>$updatedAt,
          'has_file'=>true,
          'summary'=>diagnosticSummary($payload),
          'json'=>$payload
        ]
      ], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'vm-diagnostic-save') {
      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
      $data = json_decode((string)file_get_contents('php://input'), true);
      if (!is_array($data)) { echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); return; }

      $id = (int)($data['id'] ?? 0);
      $filename = sanitizeDiagnosticFilename((string)($data['filename'] ?? 'diagnostic.json'));
      $jsonText = (string)($data['json_text'] ?? '');
      if ($id <= 0) { echo json_encode(['ok'=>false,'error'=>'Invalid ID']); return; }
      if (trim($jsonText) === '') { echo json_encode(['ok'=>false,'error'=>'Conteudo JSON vazio.']); return; }

      $vm = $db instanceof SQLite3 ? fetchVmByIdSqlite3($db, $id) : fetchVmByIdPdo($db, $id);
      if (!$vm) { echo json_encode(['ok'=>false,'error'=>'Maquina nao encontrada']); return; }

      try {
        $payload = decodeDiagnosticPayload($jsonText);
      } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
        return;
      }

      $dir = ensureVmDiagnosticDir();
      $stamp = date('Ymd_His');
      $storedName = "vm_{$id}_{$stamp}_{$filename}";
      $fullPath = $dir . DIRECTORY_SEPARATOR . $storedName;
      $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
      if ($encoded === false || @file_put_contents($fullPath, $encoded) === false) {
        echo json_encode(['ok'=>false,'error'=>'Falha ao salvar arquivo de diagnostico.']);
        return;
      }

      $reference = 'data/vm_diagnostics/' . $storedName;
      if ($db instanceof SQLite3) {
        $st = $db->prepare("UPDATE virtual_machines SET diagnostic_json_ref=:ref, diagnostic_json_updated_at=datetime('now','localtime'), updated_at=datetime('now','localtime') WHERE id=:id");
        $st->bindValue(':ref', $reference, SQLITE3_TEXT);
        $st->bindValue(':id', $id, SQLITE3_INTEGER);
        $st->execute();
        $vmUpdated = fetchVmByIdSqlite3($db, $id);
      } else {
        $st = $db->prepare("UPDATE virtual_machines SET diagnostic_json_ref=:ref, diagnostic_json_updated_at=datetime('now','localtime'), updated_at=datetime('now','localtime') WHERE id=:id");
        $st->bindValue(':ref', $reference, PDO::PARAM_STR);
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
        $vmUpdated = fetchVmByIdPdo($db, $id);
      }

      if (!$vmUpdated) { echo json_encode(['ok'=>false,'error'=>'Maquina nao encontrada']); return; }

      echo json_encode([
        'ok'=>true,
        'data'=>[
          'vm'=>$vmUpdated,
          'vm_name'=>trim((string)($vmUpdated['name'] ?? '')),
          'vm_label'=>trim((string)($vmUpdated['name'] ?? '')) !== '' || trim((string)($vmUpdated['ip'] ?? '')) !== '' ? trim((string)($vmUpdated['name'] ?? '')) . (trim((string)($vmUpdated['ip'] ?? '')) !== '' ? ' (' . trim((string)$vmUpdated['ip']) . ')' : '') : '',
          'reference'=>$reference,
          'filename'=>$storedName,
          'updated_at'=>trim((string)($vmUpdated['diagnostic_json_updated_at'] ?? '')),
          'summary'=>diagnosticSummary($payload),
          'json'=>$payload
        ]
      ], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'vm-diagnostic-clear') {
      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
      $data = json_decode((string)file_get_contents('php://input'), true);
      if (!is_array($data)) { echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); return; }
      $id = (int)($data['id'] ?? 0);
      if ($id <= 0) { echo json_encode(['ok'=>false,'error'=>'Invalid ID']); return; }

      $vm = $db instanceof SQLite3 ? fetchVmByIdSqlite3($db, $id) : fetchVmByIdPdo($db, $id);
      if (!$vm) { echo json_encode(['ok'=>false,'error'=>'Maquina nao encontrada']); return; }

      $ref = trim((string)($vm['diagnostic_json_ref'] ?? ''));
      if ($ref !== '') {
        $relative = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $ref);
        $fullPath = vmDiagnosticProjectRoot() . DIRECTORY_SEPARATOR . ltrim($relative, DIRECTORY_SEPARATOR);
        if (is_file($fullPath)) { @unlink($fullPath); }
      }

      if ($db instanceof SQLite3) {
        $st = $db->prepare("UPDATE virtual_machines SET diagnostic_json_ref='', diagnostic_json_updated_at=NULL, updated_at=datetime('now','localtime') WHERE id=:id");
        $st->bindValue(':id', $id, SQLITE3_INTEGER);
        $st->execute();
        $vmUpdated = fetchVmByIdSqlite3($db, $id);
      } else {
        $st = $db->prepare("UPDATE virtual_machines SET diagnostic_json_ref='', diagnostic_json_updated_at=NULL, updated_at=datetime('now','localtime') WHERE id=:id");
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
        $vmUpdated = fetchVmByIdPdo($db, $id);
      }

      if (!$vmUpdated) { echo json_encode(['ok'=>false,'error'=>'Maquina nao encontrada']); return; }
      echo json_encode([
        'ok'=>true,
        'data'=>[
          'vm'=>$vmUpdated
        ]
      ], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'vm-archive') {
      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
      $data = json_decode((string)file_get_contents('php://input'), true);
      $id = (int)($data['id'] ?? 0);
      if ($id <= 0) { echo json_encode(['ok'=>false,'error'=>'Invalid ID']); return; }

      if ($db instanceof SQLite3) {
        $inUse = (int)$db->querySingle("SELECT COUNT(*) FROM systems WHERE (vm_id=$id OR vm_homolog_id=$id) AND IFNULL(archived,0)=0");
        if ($inUse > 0) { echo json_encode(['ok'=>false,'error'=>'Maquina vinculada a sistemas ativos. Arquive os sistemas antes.']); return; }
        $dbInUse = (int)$db->querySingle("SELECT COUNT(*) FROM system_databases WHERE (vm_id=$id OR vm_homolog_id=$id) AND IFNULL(archived,0)=0");
        if ($dbInUse > 0) { echo json_encode(['ok'=>false,'error'=>'Maquina vinculada a bases ativas. Remova ou mova as bases antes.']); return; }
        $db->exec("UPDATE virtual_machines SET archived=1, archived_at=datetime('now','localtime') WHERE id=$id");
      } else {
        $stCheck = $db->prepare("SELECT COUNT(*) FROM systems WHERE (vm_id=:id OR vm_homolog_id=:id) AND IFNULL(archived,0)=0");
        $stCheck->bindValue(':id', $id, PDO::PARAM_INT);
        $stCheck->execute();
        $inUse = (int)$stCheck->fetchColumn();
        if ($inUse > 0) { echo json_encode(['ok'=>false,'error'=>'Maquina vinculada a sistemas ativos. Arquive os sistemas antes.']); return; }
        $stCheckDb = $db->prepare("SELECT COUNT(*) FROM system_databases WHERE (vm_id=:id OR vm_homolog_id=:id) AND IFNULL(archived,0)=0");
        $stCheckDb->bindValue(':id', $id, PDO::PARAM_INT);
        $stCheckDb->execute();
        $dbInUse = (int)$stCheckDb->fetchColumn();
        if ($dbInUse > 0) { echo json_encode(['ok'=>false,'error'=>'Maquina vinculada a bases ativas. Remova ou mova as bases antes.']); return; }
        $st = $db->prepare("UPDATE virtual_machines SET archived=1, archived_at=datetime('now','localtime') WHERE id=:id");
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
      }
      echo json_encode(['ok'=>true]);
      return;
    }

    if ($api === 'vm-restore') {
      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
      $data = json_decode((string)file_get_contents('php://input'), true);
      $id = (int)($data['id'] ?? 0);
      if ($id <= 0) { echo json_encode(['ok'=>false,'error'=>'Invalid ID']); return; }
      if ($db instanceof SQLite3) {
        $db->exec("UPDATE virtual_machines SET archived=0, archived_at=NULL WHERE id=$id");
      } else {
        $st = $db->prepare("UPDATE virtual_machines SET archived=0, archived_at=NULL WHERE id=:id");
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
      }
      echo json_encode(['ok'=>true]);
      return;
    }

    if ($api === 'vm-delete') {
      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
      $data = json_decode((string)file_get_contents('php://input'), true);
      $id = (int)($data['id'] ?? 0);
      if ($id <= 0) { echo json_encode(['ok'=>false,'error'=>'Invalid ID']); return; }

      if ($db instanceof SQLite3) {
        $isArchived = (int)$db->querySingle("SELECT COUNT(*) FROM virtual_machines WHERE id=$id AND IFNULL(archived,0)=1");
        if ($isArchived === 0) { echo json_encode(['ok'=>false,'error'=>'Apenas maquinas arquivadas podem ser excluidas.']); return; }
        $db->exec("UPDATE systems SET vm_id=NULL WHERE vm_id=$id");
        $db->exec("UPDATE systems SET vm_homolog_id=NULL WHERE vm_homolog_id=$id");
        $db->exec("UPDATE system_databases SET vm_id=NULL, db_instance_name='', db_instance_ip='', updated_at=datetime('now','localtime') WHERE vm_id=$id");
        $db->exec("UPDATE system_databases SET vm_homolog_id=NULL, db_instance_homolog_name='', db_instance_homolog_ip='', updated_at=datetime('now','localtime') WHERE vm_homolog_id=$id");
        $db->exec("DELETE FROM virtual_machines WHERE id=$id");
      } else {
        $stCheck = $db->prepare("SELECT COUNT(*) FROM virtual_machines WHERE id=:id AND IFNULL(archived,0)=1");
        $stCheck->bindValue(':id', $id, PDO::PARAM_INT);
        $stCheck->execute();
        $isArchived = (int)$stCheck->fetchColumn();
        if ($isArchived === 0) { echo json_encode(['ok'=>false,'error'=>'Apenas maquinas arquivadas podem ser excluidas.']); return; }
        $stNullProd = $db->prepare("UPDATE systems SET vm_id=NULL WHERE vm_id=:id");
        $stNullProd->bindValue(':id', $id, PDO::PARAM_INT);
        $stNullProd->execute();
        $stNullHml = $db->prepare("UPDATE systems SET vm_homolog_id=NULL WHERE vm_homolog_id=:id");
        $stNullHml->bindValue(':id', $id, PDO::PARAM_INT);
        $stNullHml->execute();
        $stNullDbVm = $db->prepare("UPDATE system_databases SET vm_id=NULL, db_instance_name='', db_instance_ip='', updated_at=datetime('now','localtime') WHERE vm_id=:id");
        $stNullDbVm->bindValue(':id', $id, PDO::PARAM_INT);
        $stNullDbVm->execute();
        $stNullDbVmHml = $db->prepare("UPDATE system_databases SET vm_homolog_id=NULL, db_instance_homolog_name='', db_instance_homolog_ip='', updated_at=datetime('now','localtime') WHERE vm_homolog_id=:id");
        $stNullDbVmHml->bindValue(':id', $id, PDO::PARAM_INT);
        $stNullDbVmHml->execute();
        $st = $db->prepare("DELETE FROM virtual_machines WHERE id=:id");
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
      }
      echo json_encode(['ok'=>true]);
      return;
    }

    if ($api === 'db-save') {
      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
      $data = json_decode((string)file_get_contents('php://input'), true);
      if (!is_array($data)) { echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); return; }

      $systemId = (int)($data['system_id'] ?? 0);
      $vmId = (int)($data['vm_id'] ?? 0);
      $vmHomologId = (int)($data['vm_homolog_id'] ?? 0);
      $dbName = trim((string)($data['db_name'] ?? ''));
      $dbUser = trim((string)($data['db_user'] ?? ''));
      $dbEngine = trim((string)($data['db_engine'] ?? ''));
      $dbEngineVersion = trim((string)($data['db_engine_version'] ?? ''));
      $dbEngineVersionHomolog = trim((string)($data['db_engine_version_homolog'] ?? ''));
      $dbInstanceName = trim((string)($data['db_instance_name'] ?? ''));
      $dbInstanceIp = trim((string)($data['db_instance_ip'] ?? ''));
      $dbInstanceHomologName = trim((string)($data['db_instance_homolog_name'] ?? ''));
      $dbInstanceHomologIp = trim((string)($data['db_instance_homolog_ip'] ?? ''));
      $notes = trim((string)($data['notes'] ?? ''));

      if ($systemId <= 0) { echo json_encode(['ok'=>false,'error'=>'Sistema obrigatorio']); return; }
      if ($vmId <= 0) { echo json_encode(['ok'=>false,'error'=>'Maquina obrigatoria']); return; }
      if ($dbName === '') { echo json_encode(['ok'=>false,'error'=>'Nome da base obrigatorio']); return; }

      if ($db instanceof SQLite3) {
        $systemExists = (int)$db->querySingle("SELECT COUNT(*) FROM systems WHERE id=$systemId AND IFNULL(archived,0)=0");
        if ($systemExists === 0) { echo json_encode(['ok'=>false,'error'=>'Sistema invalido ou arquivado']); return; }
      } else {
        $stSystem = $db->prepare("SELECT COUNT(*) FROM systems WHERE id=:id AND IFNULL(archived,0)=0");
        $stSystem->bindValue(':id', $systemId, PDO::PARAM_INT);
        $stSystem->execute();
        if ((int)$stSystem->fetchColumn() === 0) { echo json_encode(['ok'=>false,'error'=>'Sistema invalido ou arquivado']); return; }
      }

      if ($dbInstanceName === '' && $dbInstanceIp === '') {
        echo json_encode(['ok'=>false,'error'=>'Selecione a instancia SGBD da base.']);
        return;
      }

      $vmRow = $db instanceof SQLite3 ? fetchActiveVmForDbSqlite3($db, $vmId) : fetchActiveVmForDbPdo($db, $vmId);
      if (!$vmRow) {
        echo json_encode(['ok'=>false,'error'=>'Maquina invalida ou arquivada']);
        return;
      }
      $selectedInstance = resolveVmInstance($vmRow, $dbInstanceName, $dbInstanceIp);
      if (!$selectedInstance) {
        echo json_encode(['ok'=>false,'error'=>'Instancia SGBD invalida para a maquina selecionada.']);
        return;
      }

      $selectedHomologInstance = ['name' => '', 'ip' => ''];
      if ($vmHomologId > 0) {
        if ($dbInstanceHomologName === '' && $dbInstanceHomologIp === '') {
          echo json_encode(['ok'=>false,'error'=>'Selecione a instancia SGBD de homologacao.']);
          return;
        }
        $vmHomologRow = $db instanceof SQLite3 ? fetchActiveVmForDbSqlite3($db, $vmHomologId) : fetchActiveVmForDbPdo($db, $vmHomologId);
        if (!$vmHomologRow) {
          echo json_encode(['ok'=>false,'error'=>'Maquina de homologacao invalida ou arquivada']);
          return;
        }
        $resolvedHomolog = resolveVmInstance($vmHomologRow, $dbInstanceHomologName, $dbInstanceHomologIp);
        if (!$resolvedHomolog) {
          echo json_encode(['ok'=>false,'error'=>'Instancia SGBD de homologacao invalida para a maquina selecionada.']);
          return;
        }
        $selectedHomologInstance = $resolvedHomolog;
      }

      if (!empty($data['id'])) {
        $id = (int)$data['id'];
        if ($db instanceof SQLite3) {
          $st = $db->prepare("UPDATE system_databases
            SET system_id=:system_id, vm_id=:vm_id, vm_homolog_id=:vm_homolog_id, db_name=:db_name, db_user=:db_user, db_engine=:db_engine, db_engine_version=:db_engine_version, db_engine_version_homolog=:db_engine_version_homolog, db_instance_name=:db_instance_name, db_instance_ip=:db_instance_ip, db_instance_homolog_name=:db_instance_homolog_name, db_instance_homolog_ip=:db_instance_homolog_ip, notes=:notes, archived=0, archived_at=NULL, updated_at=datetime('now','localtime')
            WHERE id=:id");
          $st->bindValue(':system_id', $systemId, SQLITE3_INTEGER);
          $st->bindValue(':vm_id', $vmId, SQLITE3_INTEGER);
          if ($vmHomologId > 0) { $st->bindValue(':vm_homolog_id', $vmHomologId, SQLITE3_INTEGER); }
          else { $st->bindValue(':vm_homolog_id', null, SQLITE3_NULL); }
          $st->bindValue(':db_name', $dbName, SQLITE3_TEXT);
          $st->bindValue(':db_user', $dbUser, SQLITE3_TEXT);
          $st->bindValue(':db_engine', $dbEngine, SQLITE3_TEXT);
          $st->bindValue(':db_engine_version', $dbEngineVersion, SQLITE3_TEXT);
          $st->bindValue(':db_engine_version_homolog', $dbEngineVersionHomolog, SQLITE3_TEXT);
          $st->bindValue(':db_instance_name', (string)($selectedInstance['name'] ?? ''), SQLITE3_TEXT);
          $st->bindValue(':db_instance_ip', (string)($selectedInstance['ip'] ?? ''), SQLITE3_TEXT);
          $st->bindValue(':db_instance_homolog_name', (string)($selectedHomologInstance['name'] ?? ''), SQLITE3_TEXT);
          $st->bindValue(':db_instance_homolog_ip', (string)($selectedHomologInstance['ip'] ?? ''), SQLITE3_TEXT);
          $st->bindValue(':notes', $notes, SQLITE3_TEXT);
          $st->bindValue(':id', $id, SQLITE3_INTEGER);
          $st->execute();
          $row = fetchDatabaseByIdSqlite3($db, $id);
        } else {
          $st = $db->prepare("UPDATE system_databases
            SET system_id=:system_id, vm_id=:vm_id, vm_homolog_id=:vm_homolog_id, db_name=:db_name, db_user=:db_user, db_engine=:db_engine, db_engine_version=:db_engine_version, db_engine_version_homolog=:db_engine_version_homolog, db_instance_name=:db_instance_name, db_instance_ip=:db_instance_ip, db_instance_homolog_name=:db_instance_homolog_name, db_instance_homolog_ip=:db_instance_homolog_ip, notes=:notes, archived=0, archived_at=NULL, updated_at=datetime('now','localtime')
            WHERE id=:id");
          $st->bindValue(':system_id', $systemId, PDO::PARAM_INT);
          $st->bindValue(':vm_id', $vmId, PDO::PARAM_INT);
          if ($vmHomologId > 0) { $st->bindValue(':vm_homolog_id', $vmHomologId, PDO::PARAM_INT); }
          else { $st->bindValue(':vm_homolog_id', null, PDO::PARAM_NULL); }
          $st->bindValue(':db_name', $dbName, PDO::PARAM_STR);
          $st->bindValue(':db_user', $dbUser, PDO::PARAM_STR);
          $st->bindValue(':db_engine', $dbEngine, PDO::PARAM_STR);
          $st->bindValue(':db_engine_version', $dbEngineVersion, PDO::PARAM_STR);
          $st->bindValue(':db_engine_version_homolog', $dbEngineVersionHomolog, PDO::PARAM_STR);
          $st->bindValue(':db_instance_name', (string)($selectedInstance['name'] ?? ''), PDO::PARAM_STR);
          $st->bindValue(':db_instance_ip', (string)($selectedInstance['ip'] ?? ''), PDO::PARAM_STR);
          $st->bindValue(':db_instance_homolog_name', (string)($selectedHomologInstance['name'] ?? ''), PDO::PARAM_STR);
          $st->bindValue(':db_instance_homolog_ip', (string)($selectedHomologInstance['ip'] ?? ''), PDO::PARAM_STR);
          $st->bindValue(':notes', $notes, PDO::PARAM_STR);
          $st->bindValue(':id', $id, PDO::PARAM_INT);
          $st->execute();
          $row = fetchDatabaseByIdPdo($db, $id);
        }
      } else {
        if ($db instanceof SQLite3) {
          $st = $db->prepare("INSERT INTO system_databases(system_id,vm_id,vm_homolog_id,db_name,db_user,db_engine,db_engine_version,db_engine_version_homolog,db_instance_name,db_instance_ip,db_instance_homolog_name,db_instance_homolog_ip,notes) VALUES(:system_id,:vm_id,:vm_homolog_id,:db_name,:db_user,:db_engine,:db_engine_version,:db_engine_version_homolog,:db_instance_name,:db_instance_ip,:db_instance_homolog_name,:db_instance_homolog_ip,:notes)");
          $st->bindValue(':system_id', $systemId, SQLITE3_INTEGER);
          $st->bindValue(':vm_id', $vmId, SQLITE3_INTEGER);
          if ($vmHomologId > 0) { $st->bindValue(':vm_homolog_id', $vmHomologId, SQLITE3_INTEGER); }
          else { $st->bindValue(':vm_homolog_id', null, SQLITE3_NULL); }
          $st->bindValue(':db_name', $dbName, SQLITE3_TEXT);
          $st->bindValue(':db_user', $dbUser, SQLITE3_TEXT);
          $st->bindValue(':db_engine', $dbEngine, SQLITE3_TEXT);
          $st->bindValue(':db_engine_version', $dbEngineVersion, SQLITE3_TEXT);
          $st->bindValue(':db_engine_version_homolog', $dbEngineVersionHomolog, SQLITE3_TEXT);
          $st->bindValue(':db_instance_name', (string)($selectedInstance['name'] ?? ''), SQLITE3_TEXT);
          $st->bindValue(':db_instance_ip', (string)($selectedInstance['ip'] ?? ''), SQLITE3_TEXT);
          $st->bindValue(':db_instance_homolog_name', (string)($selectedHomologInstance['name'] ?? ''), SQLITE3_TEXT);
          $st->bindValue(':db_instance_homolog_ip', (string)($selectedHomologInstance['ip'] ?? ''), SQLITE3_TEXT);
          $st->bindValue(':notes', $notes, SQLITE3_TEXT);
          $st->execute();
          $id = (int)$db->lastInsertRowID();
          $row = fetchDatabaseByIdSqlite3($db, $id);
        } else {
          $st = $db->prepare("INSERT INTO system_databases(system_id,vm_id,vm_homolog_id,db_name,db_user,db_engine,db_engine_version,db_engine_version_homolog,db_instance_name,db_instance_ip,db_instance_homolog_name,db_instance_homolog_ip,notes) VALUES(:system_id,:vm_id,:vm_homolog_id,:db_name,:db_user,:db_engine,:db_engine_version,:db_engine_version_homolog,:db_instance_name,:db_instance_ip,:db_instance_homolog_name,:db_instance_homolog_ip,:notes)");
          $st->bindValue(':system_id', $systemId, PDO::PARAM_INT);
          $st->bindValue(':vm_id', $vmId, PDO::PARAM_INT);
          if ($vmHomologId > 0) { $st->bindValue(':vm_homolog_id', $vmHomologId, PDO::PARAM_INT); }
          else { $st->bindValue(':vm_homolog_id', null, PDO::PARAM_NULL); }
          $st->bindValue(':db_name', $dbName, PDO::PARAM_STR);
          $st->bindValue(':db_user', $dbUser, PDO::PARAM_STR);
          $st->bindValue(':db_engine', $dbEngine, PDO::PARAM_STR);
          $st->bindValue(':db_engine_version', $dbEngineVersion, PDO::PARAM_STR);
          $st->bindValue(':db_engine_version_homolog', $dbEngineVersionHomolog, PDO::PARAM_STR);
          $st->bindValue(':db_instance_name', (string)($selectedInstance['name'] ?? ''), PDO::PARAM_STR);
          $st->bindValue(':db_instance_ip', (string)($selectedInstance['ip'] ?? ''), PDO::PARAM_STR);
          $st->bindValue(':db_instance_homolog_name', (string)($selectedHomologInstance['name'] ?? ''), PDO::PARAM_STR);
          $st->bindValue(':db_instance_homolog_ip', (string)($selectedHomologInstance['ip'] ?? ''), PDO::PARAM_STR);
          $st->bindValue(':notes', $notes, PDO::PARAM_STR);
          $st->execute();
          $id = (int)$db->lastInsertId();
          $row = fetchDatabaseByIdPdo($db, $id);
        }
      }

      if (!$row) { echo json_encode(['ok'=>false,'error'=>'Base nao encontrada']); return; }
      echo json_encode(['ok'=>true,'data'=>$row], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'db-delete') {
      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
      $data = json_decode((string)file_get_contents('php://input'), true);
      $id = (int)($data['id'] ?? 0);
      if ($id <= 0) { echo json_encode(['ok'=>false,'error'=>'Invalid ID']); return; }

      if ($db instanceof SQLite3) {
        $db->exec("DELETE FROM system_databases WHERE id=$id");
      } else {
        $st = $db->prepare("DELETE FROM system_databases WHERE id=:id");
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
      }
      echo json_encode(['ok'=>true]);
      return;
    }

    if ($api === 'save') {
      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
      $data = json_decode((string)file_get_contents('php://input'), true);
      if (!is_array($data)) { echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); return; }
      $name = trim((string)($data['name'] ?? ''));
      if ($name === '') { echo json_encode(['ok'=>false,'error'=>'Name is required']); return; }
      $data['status'] = normalizeSystemStatus((string)($data['status'] ?? ''));
      $data['url'] = packUrlListValue($data['url'] ?? '');
      $data['url_homolog'] = packUrlListValue($data['url_homolog'] ?? '');
      $tech = is_array($data['tech'] ?? null) ? implode(',', array_filter(array_map('trim', $data['tech']))) : trim((string)($data['tech'] ?? ''));
      $fields = ['name','system_name','vm_id','vm_homolog_id','category','system_group','status','url','url_homolog','description','owner','criticality','version','notes','responsible_sector','responsible_coordinator','extension_number','email','support','support_contact','analytics','ssl','waf','bundle','directory','size','repository','archived','archived_at'];
      if (!empty($data['id'])) {
        $sets = implode(',', array_map(fn($f)=>"$f=:$f", $fields));
        $st = $db->prepare("UPDATE systems SET $sets, tech=:tech, updated_at=datetime('now','localtime') WHERE id=:id");
        if ($db instanceof SQLite3) { $st->bindValue(':id', (int)$data['id'], SQLITE3_INTEGER); }
        else { $st->bindValue(':id', (int)$data['id'], PDO::PARAM_INT); }
      } else {
        $cols = implode(',', $fields) . ',tech';
        $vals = implode(',', array_map(fn($f)=>":$f", $fields)) . ',:tech';
        $st = $db->prepare("INSERT INTO systems ($cols) VALUES ($vals)");
      }

      foreach ($fields as $f) {
        if ($f === 'archived') { $data[$f] = 0; }
        if ($f === 'archived_at') { $data[$f] = null; }
        if ($db instanceof SQLite3) { bindSystemFieldSqlite3($st, $f, $data); }
        else { bindSystemFieldPdo($st, $f, $data); }
      }

      if ($db instanceof SQLite3) {
        $st->bindValue(':name', $name, SQLITE3_TEXT);
        $st->bindValue(':tech', $tech, SQLITE3_TEXT);
        $st->execute();
        $id = !empty($data['id']) ? (int)$data['id'] : (int)$db->lastInsertRowID();
        $row = fetchSystemByIdSqlite3($db, $id);
      } else {
        $st->bindValue(':name', $name, PDO::PARAM_STR);
        $st->bindValue(':tech', $tech, PDO::PARAM_STR);
        $st->execute();
        $id = !empty($data['id']) ? (int)$data['id'] : (int)$db->lastInsertId();
        $row = fetchSystemByIdPdo($db, $id);
      }
      if (!$row) { echo json_encode(['ok'=>false,'error'=>'Record not found']); return; }
      echo json_encode(['ok'=>true,'data'=>$row], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'delete') {
      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
      $data = json_decode((string)file_get_contents('php://input'), true);
      $id = (int)($data['id'] ?? 0);
      if ($id <= 0) { echo json_encode(['ok'=>false,'error'=>'Invalid ID']); return; }
      if ($db instanceof SQLite3) {
        $isArchived = (int)$db->querySingle("SELECT COUNT(*) FROM systems WHERE id=$id AND IFNULL(archived,0)=1");
        if ($isArchived === 0) { echo json_encode(['ok'=>false,'error'=>'Apenas sistemas arquivados podem ser excluidos.']); return; }
        $db->exec("DELETE FROM system_databases WHERE system_id=$id");
        $db->exec("DELETE FROM systems WHERE id=$id");
      } else {
        $stCheck = $db->prepare("SELECT COUNT(*) FROM systems WHERE id=:id AND IFNULL(archived,0)=1");
        $stCheck->bindValue(':id', $id, PDO::PARAM_INT);
        $stCheck->execute();
        $isArchived = (int)$stCheck->fetchColumn();
        if ($isArchived === 0) { echo json_encode(['ok'=>false,'error'=>'Apenas sistemas arquivados podem ser excluidos.']); return; }
        $stDb = $db->prepare("DELETE FROM system_databases WHERE system_id=:id");
        $stDb->bindValue(':id', $id, PDO::PARAM_INT);
        $stDb->execute();
        $st = $db->prepare("DELETE FROM systems WHERE id=:id");
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
      }
      echo json_encode(['ok'=>true]);
      return;
    }

    echo json_encode(['ok'=>false,'error'=>'Unknown action']);
  } catch (Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
  }
}

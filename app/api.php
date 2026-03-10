<?php

function systemSelectSql(): string {
  return "SELECT
    s.*,
    vm.name AS vm_name,
    vm.ip AS vm_ip,
    vmh.name AS vm_homolog_name,
    vmh.ip AS vm_homolog_ip,
    vmd.name AS vm_dev_name,
    vmd.ip AS vm_dev_ip
  FROM systems s
  LEFT JOIN virtual_machines vm ON vm.id = s.vm_id
  LEFT JOIN virtual_machines vmh ON vmh.id = s.vm_homolog_id
  LEFT JOIN virtual_machines vmd ON vmd.id = s.vm_dev_id";
}

function normalizeSystemStatus(string $status): string {
  $value = trim($status);
  $map = [
    'ManutenÃƒÂ§ÃƒÂ£o' => 'Manutenção',
    'ManutenÃ§Ã£o' => 'Manutenção',
    'ImplantaÃƒÂ§ÃƒÂ£o' => 'Implantação',
    'ImplantaÃ§Ã£o' => 'Implantação',
  ];
  return $map[$value] ?? $value;
}

function normalizeUtf8Text(string $value): string {
  if ($value === '' || !str_contains($value, 'Ã')) { return $value; }
  static $map = [
    'Ã¡' => 'á',
    'Ã¢' => 'â',
    'Ã£' => 'ã',
    'Ã¤' => 'ä',
    'Ã©' => 'é',
    'Ãª' => 'ê',
    'Ã­' => 'í',
    'Ã³' => 'ó',
    'Ã´' => 'ô',
    'Ãµ' => 'õ',
    'Ãº' => 'ú',
    'Ã§' => 'ç',
    'Ã�' => 'Á',
    'Ã‚' => 'Â',
    'Ãƒ' => 'Ã',
    'Ã‰' => 'É',
    'ÃŠ' => 'Ê',
    'Ã“' => 'Ó',
    'Ã”' => 'Ô',
    'Ã•' => 'Õ',
    'Ãš' => 'Ú',
    'Ã‡' => 'Ç',
    'â€“' => '–',
    'â€”' => '—',
    'â€œ' => '“',
    'â€' => '”',
    'â€˜' => '‘',
    'â€™' => '’',
    'â€¦' => '…',
    'Âº' => 'º',
    'Âª' => 'ª',
    'Â°' => '°',
    'Â·' => '·',
    'Â ' => ' ',
  ];
  return strtr($value, $map);
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
  foreach ($row as $key => $value) {
    if (is_string($value)) { $row[$key] = normalizeUtf8Text($value); }
  }
  $row['tech'] = $row['tech'] !== '' ? array_values(array_filter(array_map('trim', explode(',', (string)$row['tech'])))) : [];
  $row['vm_id'] = isset($row['vm_id']) && $row['vm_id'] !== null && (int)$row['vm_id'] > 0 ? (int)$row['vm_id'] : null;
  $row['vm_homolog_id'] = isset($row['vm_homolog_id']) && $row['vm_homolog_id'] !== null && (int)$row['vm_homolog_id'] > 0 ? (int)$row['vm_homolog_id'] : null;
  $row['vm_dev_id'] = isset($row['vm_dev_id']) && $row['vm_dev_id'] !== null && (int)$row['vm_dev_id'] > 0 ? (int)$row['vm_dev_id'] : null;
  $row['status'] = normalizeSystemStatus((string)($row['status'] ?? ''));
  $row['url_list'] = normalizeUrlListValue((string)($row['url'] ?? ''));
  $row['url_homolog_list'] = normalizeUrlListValue((string)($row['url_homolog'] ?? ''));
  $row['url'] = implode("\n", $row['url_list']);
  $row['url_homolog'] = implode("\n", $row['url_homolog_list']);

  if (($row['vm_name'] ?? '') === '') { $row['vm_name'] = (string)($row['vm'] ?? ''); }
  if (($row['vm_homolog_name'] ?? '') === '') { $row['vm_homolog_name'] = (string)($row['vm_homolog'] ?? ''); }
  if (($row['vm_ip'] ?? '') === '') { $row['vm_ip'] = (string)($row['ip'] ?? ''); }
  if (($row['vm_homolog_ip'] ?? '') === '') { $row['vm_homolog_ip'] = (string)($row['ip_homolog'] ?? ''); }
  if (($row['vm_dev_name'] ?? '') === '') { $row['vm_dev_name'] = (string)($row['vm_dev'] ?? ''); }
  if (($row['vm_dev_ip'] ?? '') === '') { $row['vm_dev_ip'] = (string)($row['ip_dev'] ?? ''); }

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
  if ($field === 'vm_id' || $field === 'vm_homolog_id' || $field === 'vm_dev_id') {
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
  if ($field === 'vm_id' || $field === 'vm_homolog_id' || $field === 'vm_dev_id') {
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

function splitCsvList(string $raw): array {
  $value = trim($raw);
  if ($value === '') { return []; }
  return array_values(array_filter(array_map('trim', explode(',', $value))));
}

function vmTechListFromRow(array $row): array {
  if (is_array($row['vm_tech_list'] ?? null)) {
    return array_values(array_filter(array_map(fn($v) => trim((string)$v), $row['vm_tech_list'])));
  }
  return splitCsvList((string)($row['vm_tech'] ?? ''));
}

function vmLanguageListFromRow(array $row): array {
  if (is_array($row['vm_language_list'] ?? null)) {
    return array_values(array_filter(array_map(fn($v) => trim((string)$v), $row['vm_language_list'])));
  }
  $list = splitCsvList((string)($row['vm_language'] ?? ''));
  if ($list) { return $list; }

  // Compatibilidade com bases antigas onde linguagens eram misturadas em vm_tech.
  $legacy = [];
  foreach (vmTechListFromRow($row) as $item) {
    $value = trim((string)$item);
    if ($value === '') { continue; }
    if (stripos($value, 'php') !== false) { $legacy['php'] = 'PHP'; continue; }
    if (isRTechnologyLabel($value)) { $legacy['r'] = 'R'; continue; }
  }
  return array_values($legacy);
}

function languageVersionFromDiagnosticSummary(string $tech, array $summary): string {
  $normalizedTech = normalizeDiagnosticTech($tech);
  if ($normalizedTech === 'r') {
    $rVersion = trim((string)($summary['r_version'] ?? ''));
    if ($rVersion !== '') { return $rVersion; }
    return trim((string)($summary['base_version'] ?? ''));
  }
  return trim((string)($summary['php_version'] ?? ''));
}

function vmLanguageVersionsFromRow(array $row): array {
  $versions = [
    'php' => '',
    'r' => '',
  ];
  foreach (['php', 'r'] as $tech) {
    try {
      $entry = loadVmDiagnosticEntry($row, $tech);
    } catch (Throwable $e) {
      continue;
    }
    if (!is_array($entry) || !($entry['has_file'] ?? false)) { continue; }
    $summary = is_array($entry['summary'] ?? null) ? $entry['summary'] : [];
    $versions[$tech] = languageVersionFromDiagnosticSummary($tech, $summary);
  }
  return $versions;
}

function listVmsSqlite3(SQLite3 $db, bool $archived=false): array {
  $flag = $archived ? 1 : 0;
  $sql = "SELECT
    vm.*,
    (SELECT COUNT(*) FROM systems s WHERE s.vm_id = vm.id AND IFNULL(s.archived,0)=0) AS prod_count,
    (SELECT COUNT(*) FROM systems s WHERE s.vm_homolog_id = vm.id AND IFNULL(s.archived,0)=0) AS hml_count,
    (SELECT COUNT(*) FROM systems s WHERE s.vm_dev_id = vm.id AND IFNULL(s.archived,0)=0) AS dev_count,
    (SELECT COUNT(*) FROM systems s WHERE (s.vm_id = vm.id OR s.vm_homolog_id = vm.id OR s.vm_dev_id = vm.id) AND IFNULL(s.archived,0)=0) AS system_count,
    (SELECT COUNT(*) FROM system_databases d WHERE (d.vm_id = vm.id OR d.vm_homolog_id = vm.id) AND IFNULL(d.archived,0)=0) AS database_count
  FROM virtual_machines vm
  WHERE IFNULL(vm.archived,0) = $flag
  ORDER BY vm.name COLLATE NOCASE";
  $res = $db->query($sql);
  $out = [];
  while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    foreach ($row as $key => $value) {
      if (is_string($value)) { $row[$key] = normalizeUtf8Text($value); }
    }
    $row['id'] = (int)$row['id'];
    $row['vm_category'] = trim((string)($row['vm_category'] ?? '')) !== '' ? trim((string)$row['vm_category']) : 'Producao';
    $row['vm_type'] = trim((string)($row['vm_type'] ?? '')) !== '' ? trim((string)$row['vm_type']) : 'Sistemas';
    $row['vm_access'] = trim((string)($row['vm_access'] ?? '')) !== '' ? trim((string)$row['vm_access']) : 'Interno';
    $row['vm_administration'] = trim((string)($row['vm_administration'] ?? '')) !== '' ? trim((string)$row['vm_administration']) : 'SEI';
    $row['vm_instances'] = trim((string)($row['vm_instances'] ?? ''));
    $row['vm_instances_list'] = normalizeVmInstancesValue($row['vm_instances']);
    $row['vm_language'] = trim((string)($row['vm_language'] ?? ''));
    $row['vm_language_list'] = vmLanguageListFromRow($row);
    $row['vm_tech'] = trim((string)($row['vm_tech'] ?? ''));
    $row['vm_tech_list'] = vmTechListFromRow($row);
    $row['diagnostic_json_ref'] = trim((string)($row['diagnostic_json_ref'] ?? ''));
    $row['diagnostic_json_updated_at'] = trim((string)($row['diagnostic_json_updated_at'] ?? ''));
    $row['diagnostic_json_ref_r'] = trim((string)($row['diagnostic_json_ref_r'] ?? ''));
    $row['diagnostic_json_updated_at_r'] = trim((string)($row['diagnostic_json_updated_at_r'] ?? ''));
    $row['vm_language_versions'] = vmLanguageVersionsFromRow($row);
    $row['prod_count'] = (int)$row['prod_count'];
    $row['hml_count'] = (int)$row['hml_count'];
    $row['dev_count'] = (int)$row['dev_count'];
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
    (SELECT COUNT(*) FROM systems s WHERE s.vm_dev_id = vm.id AND IFNULL(s.archived,0)=0) AS dev_count,
    (SELECT COUNT(*) FROM systems s WHERE (s.vm_id = vm.id OR s.vm_homolog_id = vm.id OR s.vm_dev_id = vm.id) AND IFNULL(s.archived,0)=0) AS system_count,
    (SELECT COUNT(*) FROM system_databases d WHERE (d.vm_id = vm.id OR d.vm_homolog_id = vm.id) AND IFNULL(d.archived,0)=0) AS database_count
  FROM virtual_machines vm
  WHERE IFNULL(vm.archived,0) = $flag
  ORDER BY vm.name COLLATE NOCASE";
  $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as &$row) {
    foreach ($row as $key => $value) {
      if (is_string($value)) { $row[$key] = normalizeUtf8Text($value); }
    }
    $row['id'] = (int)$row['id'];
    $row['vm_category'] = trim((string)($row['vm_category'] ?? '')) !== '' ? trim((string)$row['vm_category']) : 'Producao';
    $row['vm_type'] = trim((string)($row['vm_type'] ?? '')) !== '' ? trim((string)$row['vm_type']) : 'Sistemas';
    $row['vm_access'] = trim((string)($row['vm_access'] ?? '')) !== '' ? trim((string)$row['vm_access']) : 'Interno';
    $row['vm_administration'] = trim((string)($row['vm_administration'] ?? '')) !== '' ? trim((string)$row['vm_administration']) : 'SEI';
    $row['vm_instances'] = trim((string)($row['vm_instances'] ?? ''));
    $row['vm_instances_list'] = normalizeVmInstancesValue($row['vm_instances']);
    $row['vm_language'] = trim((string)($row['vm_language'] ?? ''));
    $row['vm_language_list'] = vmLanguageListFromRow($row);
    $row['vm_tech'] = trim((string)($row['vm_tech'] ?? ''));
    $row['vm_tech_list'] = vmTechListFromRow($row);
    $row['diagnostic_json_ref'] = trim((string)($row['diagnostic_json_ref'] ?? ''));
    $row['diagnostic_json_updated_at'] = trim((string)($row['diagnostic_json_updated_at'] ?? ''));
    $row['diagnostic_json_ref_r'] = trim((string)($row['diagnostic_json_ref_r'] ?? ''));
    $row['diagnostic_json_updated_at_r'] = trim((string)($row['diagnostic_json_updated_at_r'] ?? ''));
    $row['vm_language_versions'] = vmLanguageVersionsFromRow($row);
    $row['prod_count'] = (int)$row['prod_count'];
    $row['hml_count'] = (int)$row['hml_count'];
    $row['dev_count'] = (int)$row['dev_count'];
    $row['system_count'] = (int)$row['system_count'];
    $row['database_count'] = (int)$row['database_count'];
  }
  unset($row);
  return $rows;
}

function fetchVmByIdSqlite3(SQLite3 $db, int $id): ?array {
  $st = $db->prepare("SELECT id,name,ip,vm_category,vm_type,vm_access,vm_administration,vm_instances,vm_language,vm_tech,diagnostic_json_ref,diagnostic_json_updated_at,diagnostic_json_ref_r,diagnostic_json_updated_at_r,os_name,os_version,vcpus,ram,disk,created_at,updated_at FROM virtual_machines WHERE id=:id");
  $st->bindValue(':id', $id, SQLITE3_INTEGER);
  $res = $st->execute();
  $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;
  if (!is_array($row)) { return null; }
  foreach ($row as $key => $value) {
    if (is_string($value)) { $row[$key] = normalizeUtf8Text($value); }
  }
  $row['id'] = (int)$row['id'];
  $row['vm_category'] = trim((string)($row['vm_category'] ?? '')) !== '' ? trim((string)$row['vm_category']) : 'Producao';
  $row['vm_type'] = trim((string)($row['vm_type'] ?? '')) !== '' ? trim((string)$row['vm_type']) : 'Sistemas';
  $row['vm_access'] = trim((string)($row['vm_access'] ?? '')) !== '' ? trim((string)$row['vm_access']) : 'Interno';
  $row['vm_administration'] = trim((string)($row['vm_administration'] ?? '')) !== '' ? trim((string)$row['vm_administration']) : 'SEI';
  $row['vm_instances'] = trim((string)($row['vm_instances'] ?? ''));
  $row['vm_instances_list'] = normalizeVmInstancesValue($row['vm_instances']);
  $row['vm_language'] = trim((string)($row['vm_language'] ?? ''));
  $row['vm_language_list'] = vmLanguageListFromRow($row);
  $row['vm_tech'] = trim((string)($row['vm_tech'] ?? ''));
  $row['vm_tech_list'] = vmTechListFromRow($row);
  $row['diagnostic_json_ref'] = trim((string)($row['diagnostic_json_ref'] ?? ''));
  $row['diagnostic_json_updated_at'] = trim((string)($row['diagnostic_json_updated_at'] ?? ''));
  $row['diagnostic_json_ref_r'] = trim((string)($row['diagnostic_json_ref_r'] ?? ''));
  $row['diagnostic_json_updated_at_r'] = trim((string)($row['diagnostic_json_updated_at_r'] ?? ''));
  $row['vm_language_versions'] = vmLanguageVersionsFromRow($row);
  return $row;
}

function fetchVmByIdPdo(PDO $db, int $id): ?array {
  $st = $db->prepare("SELECT id,name,ip,vm_category,vm_type,vm_access,vm_administration,vm_instances,vm_language,vm_tech,diagnostic_json_ref,diagnostic_json_updated_at,diagnostic_json_ref_r,diagnostic_json_updated_at_r,os_name,os_version,vcpus,ram,disk,created_at,updated_at FROM virtual_machines WHERE id=:id");
  $st->bindValue(':id', $id, PDO::PARAM_INT);
  $st->execute();
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!is_array($row)) { return null; }
  foreach ($row as $key => $value) {
    if (is_string($value)) { $row[$key] = normalizeUtf8Text($value); }
  }
  $row['id'] = (int)$row['id'];
  $row['vm_category'] = trim((string)($row['vm_category'] ?? '')) !== '' ? trim((string)$row['vm_category']) : 'Producao';
  $row['vm_type'] = trim((string)($row['vm_type'] ?? '')) !== '' ? trim((string)$row['vm_type']) : 'Sistemas';
  $row['vm_access'] = trim((string)($row['vm_access'] ?? '')) !== '' ? trim((string)$row['vm_access']) : 'Interno';
  $row['vm_administration'] = trim((string)($row['vm_administration'] ?? '')) !== '' ? trim((string)$row['vm_administration']) : 'SEI';
  $row['vm_instances'] = trim((string)($row['vm_instances'] ?? ''));
  $row['vm_instances_list'] = normalizeVmInstancesValue($row['vm_instances']);
  $row['vm_language'] = trim((string)($row['vm_language'] ?? ''));
  $row['vm_language_list'] = vmLanguageListFromRow($row);
  $row['vm_tech'] = trim((string)($row['vm_tech'] ?? ''));
  $row['vm_tech_list'] = vmTechListFromRow($row);
  $row['diagnostic_json_ref'] = trim((string)($row['diagnostic_json_ref'] ?? ''));
  $row['diagnostic_json_updated_at'] = trim((string)($row['diagnostic_json_updated_at'] ?? ''));
  $row['diagnostic_json_ref_r'] = trim((string)($row['diagnostic_json_ref_r'] ?? ''));
  $row['diagnostic_json_updated_at_r'] = trim((string)($row['diagnostic_json_updated_at_r'] ?? ''));
  $row['vm_language_versions'] = vmLanguageVersionsFromRow($row);
  return $row;
}

function fetchActiveVmForDbSqlite3(SQLite3 $db, int $id): ?array {
  $st = $db->prepare("SELECT id,name,ip,vm_type,vm_instances FROM virtual_machines WHERE id=:id AND IFNULL(archived,0)=0 LIMIT 1");
  $st->bindValue(':id', $id, SQLITE3_INTEGER);
  $res = $st->execute();
  $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;
  if (!is_array($row)) { return null; }
  foreach ($row as $key => $value) {
    if (is_string($value)) { $row[$key] = normalizeUtf8Text($value); }
  }
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
  foreach ($row as $key => $value) {
    if (is_string($value)) { $row[$key] = normalizeUtf8Text($value); }
  }
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

function normalizeDiagnosticTech(string $tech): string {
  $value = strtolower(trim($tech));
  return $value === 'r' ? 'r' : 'php';
}

function diagnosticRefColumnByTech(string $tech): string {
  return normalizeDiagnosticTech($tech) === 'r' ? 'diagnostic_json_ref_r' : 'diagnostic_json_ref';
}

function diagnosticUpdatedAtColumnByTech(string $tech): string {
  return normalizeDiagnosticTech($tech) === 'r' ? 'diagnostic_json_updated_at_r' : 'diagnostic_json_updated_at';
}

function diagnosticReferencesFromVmRow(array $row): array {
  $refs = [];
  foreach (['php', 'r'] as $tech) {
    $ref = trim((string)($row[diagnosticRefColumnByTech($tech)] ?? ''));
    if ($ref === '') { continue; }
    $refs[$ref] = $ref;
  }
  return array_values($refs);
}

function vmLabelFromRow(array $vm): string {
  $name = trim((string)($vm['name'] ?? ''));
  $ip = trim((string)($vm['ip'] ?? ''));
  if ($name === '' && $ip === '') { return ''; }
  if ($name !== '' && $ip !== '') { return $name . ' (' . $ip . ')'; }
  return $name !== '' ? $name : $ip;
}

function isRTechnologyLabel(string $value): bool {
  $normalized = strtolower(trim($value));
  if ($normalized === '' || $normalized === 'php') { return false; }
  if ($normalized === 'r') { return true; }
  return preg_match('/^r(?:[\s\-\/_.]|\d)/i', $normalized) === 1;
}

function vmSupportsDiagnosticTech(array $vm, string $tech): bool {
  $target = normalizeDiagnosticTech($tech);
  $values = vmLanguageListFromRow($vm);
  if (!$values) {
    // Compatibilidade: em bases antigas, linguagens ficavam em vm_tech.
    $values = vmTechListFromRow($vm);
  }
  if (!$values) { return false; }
  if ($target === 'php') {
    foreach ($values as $value) {
      if (str_contains(strtolower((string)$value), 'php')) { return true; }
    }
    return false;
  }
  foreach ($values as $value) {
    if (isRTechnologyLabel((string)$value)) { return true; }
  }
  return false;
}

function decodePhpDiagnosticPayload(string $jsonText): array {
  $payload = json_decode($jsonText, true);
  if (!is_array($payload)) {
    throw new InvalidArgumentException('JSON invalido.');
  }
  if (!is_array($payload['php'] ?? null) || !is_array($payload['extensions'] ?? null) || !is_array($payload['ini'] ?? null)) {
    throw new InvalidArgumentException('JSON fora do modelo esperado (php/extensions/ini).');
  }
  return $payload;
}

function normalizeRPackageList(array $rows): array {
  $out = [];
  $seen = [];
  foreach ($rows as $row) {
    if (!is_array($row)) { continue; }
    $name = trim((string)($row['Package'] ?? $row['package'] ?? $row['name'] ?? $row['_row'] ?? ''));
    if ($name === '') { continue; }
    $version = trim((string)($row['Version'] ?? $row['version'] ?? ''));
    $key = strtolower($name);
    if (isset($seen[$key])) { continue; }
    $seen[$key] = true;
    $item = ['Package' => $name, 'Version' => $version];
    $rowValue = trim((string)($row['_row'] ?? ''));
    if ($rowValue !== '') { $item['_row'] = $rowValue; }
    $out[] = $item;
  }
  return $out;
}

function isSequentialArray(array $arr): bool {
  return $arr === [] || array_keys($arr) === range(0, count($arr) - 1);
}

function firstTextFromMixed($value): string {
  if (is_array($value)) {
    foreach ($value as $item) {
      $text = firstTextFromMixed($item);
      if ($text !== '') { return $text; }
    }
    return '';
  }
  if ($value === null) { return ''; }
  if (is_bool($value)) { return $value ? 'true' : 'false'; }
  if (!is_scalar($value)) { return ''; }
  return trim((string)$value);
}

function firstIntFromMixed($value): ?int {
  if (is_array($value)) {
    foreach ($value as $item) {
      $num = firstIntFromMixed($item);
      if ($num !== null) { return $num; }
    }
    return null;
  }
  if ($value === null || $value === '') { return null; }
  if (is_int($value)) { return $value; }
  if (is_float($value)) { return (int)$value; }
  if (is_string($value) && preg_match('/^-?\d+$/', trim($value)) === 1) {
    return (int)trim($value);
  }
  return null;
}

function normalizeRDiagnosticPayload(array $payload): array {
  $rows = [];
  if (isSequentialArray($payload)) {
    $rows = $payload;
  } elseif (is_array($payload['packages'] ?? null)) {
    $rows = $payload['packages'];
  }

  $packages = normalizeRPackageList($rows);
  $rVersion = firstTextFromMixed($payload['r_version'] ?? ($payload['rVersion'] ?? ($payload['R.version.string'] ?? ($payload['version'] ?? ''))));
  $platform = firstTextFromMixed($payload['platform'] ?? '');
  $totalPackages = firstIntFromMixed($payload['total_packages'] ?? null);
  if ($totalPackages === null || $totalPackages < 0) {
    $totalPackages = count($packages);
  }

  return [
    'r_version' => $rVersion,
    'platform' => $platform,
    'total_packages' => $totalPackages,
    'packages' => $packages,
  ];
}

function decodeRDiagnosticPayload(string $jsonText): array {
  $payload = json_decode($jsonText, true);
  if (!is_array($payload)) {
    throw new InvalidArgumentException('JSON invalido.');
  }
  $hasPackages = isSequentialArray($payload) || is_array($payload['packages'] ?? null);
  if (!$hasPackages) {
    throw new InvalidArgumentException('JSON fora do modelo esperado para R (objeto com "packages" ou lista de pacotes).');
  }
  $normalized = normalizeRDiagnosticPayload($payload);
  if (!$normalized['packages']) {
    throw new InvalidArgumentException('JSON de R sem pacotes validos.');
  }
  return $normalized;
}

function decodeDiagnosticPayloadByTech(string $tech, string $jsonText): array {
  $normalizedTech = normalizeDiagnosticTech($tech);
  if ($normalizedTech === 'r') { return decodeRDiagnosticPayload($jsonText); }
  return decodePhpDiagnosticPayload($jsonText);
}

function decodeDiagnosticPayload(string $jsonText): array {
  return decodePhpDiagnosticPayload($jsonText);
}

function diagnosticSummaryPhp(array $payload): array {
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

function diagnosticSummaryR(array $payload): array {
  $normalized = normalizeRDiagnosticPayload($payload);
  $packages = $normalized['packages'];
  $rVersion = trim((string)($normalized['r_version'] ?? ''));
  $platform = trim((string)($normalized['platform'] ?? ''));
  $names = [];
  $baseVersion = '';
  foreach ($packages as $pkg) {
    $name = trim((string)($pkg['Package'] ?? ''));
    if ($name === '') { continue; }
    $version = trim((string)($pkg['Version'] ?? ''));
    if ($baseVersion === '' && strtolower($name) === 'base') { $baseVersion = $version; }
    $names[] = $name;
  }
  if ($rVersion === '' && $baseVersion !== '') { $rVersion = $baseVersion; }
  return [
    'r_version' => $rVersion,
    'platform' => $platform,
    'package_count' => count($names),
    'base_version' => $baseVersion,
    'packages' => $names,
  ];
}

function diagnosticSummaryByTech(string $tech, array $payload): array {
  return normalizeDiagnosticTech($tech) === 'r'
    ? diagnosticSummaryR($payload)
    : diagnosticSummaryPhp($payload);
}

function diagnosticSummary(array $payload): array {
  return diagnosticSummaryPhp($payload);
}

function diagnosticTechLabel(string $tech): string {
  return normalizeDiagnosticTech($tech) === 'r' ? 'R' : 'PHP';
}

function deleteDiagnosticFileByReference(string $reference): void {
  $ref = trim($reference);
  if ($ref === '') { return; }
  $relative = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $ref);
  $fullPath = vmDiagnosticProjectRoot() . DIRECTORY_SEPARATOR . ltrim($relative, DIRECTORY_SEPARATOR);
  if (is_file($fullPath)) { @unlink($fullPath); }
}

function loadVmDiagnosticEntry(array $vm, string $tech): array {
  $normalizedTech = normalizeDiagnosticTech($tech);
  $refCol = diagnosticRefColumnByTech($normalizedTech);
  $updatedCol = diagnosticUpdatedAtColumnByTech($normalizedTech);
  $reference = trim((string)($vm[$refCol] ?? ''));
  $updatedAt = trim((string)($vm[$updatedCol] ?? ''));
  $entry = [
    'tech' => $normalizedTech,
    'reference' => $reference,
    'filename' => $reference !== '' ? basename($reference) : '',
    'updated_at' => $updatedAt,
    'has_file' => false,
    'summary' => null,
    'json' => null,
  ];
  if ($reference === '') { return $entry; }

  $relative = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $reference);
  $fullPath = vmDiagnosticProjectRoot() . DIRECTORY_SEPARATOR . ltrim($relative, DIRECTORY_SEPARATOR);
  if (!is_file($fullPath)) { return $entry; }

  $jsonText = (string)file_get_contents($fullPath);
  $payload = decodeDiagnosticPayloadByTech($normalizedTech, $jsonText);
  $entry['filename'] = basename($fullPath);
  $entry['has_file'] = true;
  $entry['summary'] = diagnosticSummaryByTech($normalizedTech, $payload);
  $entry['json'] = $payload;
  return $entry;
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
  foreach ($row as $key => $value) {
    if (is_string($value)) { $row[$key] = normalizeUtf8Text($value); }
  }
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

function startAppSession(): void {
  if (session_status() === PHP_SESSION_ACTIVE) { return; }
  session_name('SEIPORTFOLIOSESSID');
  session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
  ]);
}

function roleRank(string $role): int {
  $map = ['leitura' => 1, 'edicao' => 2, 'admin' => 3];
  return $map[strtolower(trim($role))] ?? 0;
}

function roleAtLeast(string $role, string $required): bool {
  return roleRank($role) >= roleRank($required);
}

function normalizeRole(string $role): string {
  $value = strtolower(trim($role));
  if (!in_array($value, ['leitura', 'edicao', 'admin'], true)) { return 'leitura'; }
  return $value;
}

function publicUserPayload(array $row): array {
  return [
    'id' => (int)($row['id'] ?? 0),
    'username' => trim((string)($row['username'] ?? '')),
    'full_name' => normalizeUtf8Text(trim((string)($row['full_name'] ?? ''))),
    'role' => normalizeRole((string)($row['role'] ?? 'leitura')),
    'active' => (int)($row['active'] ?? 0),
  ];
}

function sessionAuthUser(): ?array {
  $raw = $_SESSION['auth_user'] ?? null;
  if (!is_array($raw)) { return null; }
  $user = publicUserPayload($raw);
  if ($user['id'] <= 0 || $user['active'] !== 1) { return null; }
  return $user;
}

function fetchUserByUsernameSqlite3(SQLite3 $db, string $username): ?array {
  $st = $db->prepare("SELECT id,username,password_hash,full_name,role,active FROM users WHERE lower(username)=lower(:username) LIMIT 1");
  $st->bindValue(':username', trim($username), SQLITE3_TEXT);
  $res = $st->execute();
  $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;
  return is_array($row) ? $row : null;
}

function fetchUserByUsernamePdo(PDO $db, string $username): ?array {
  $st = $db->prepare("SELECT id,username,password_hash,full_name,role,active FROM users WHERE lower(username)=lower(:username) LIMIT 1");
  $st->bindValue(':username', trim($username), PDO::PARAM_STR);
  $st->execute();
  $row = $st->fetch(PDO::FETCH_ASSOC);
  return is_array($row) ? $row : null;
}

function fetchUserByIdSqlite3(SQLite3 $db, int $id): ?array {
  $st = $db->prepare("SELECT id,username,password_hash,full_name,role,active FROM users WHERE id=:id LIMIT 1");
  $st->bindValue(':id', $id, SQLITE3_INTEGER);
  $res = $st->execute();
  $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;
  return is_array($row) ? $row : null;
}

function fetchUserByIdPdo(PDO $db, int $id): ?array {
  $st = $db->prepare("SELECT id,username,password_hash,full_name,role,active FROM users WHERE id=:id LIMIT 1");
  $st->bindValue(':id', $id, PDO::PARAM_INT);
  $st->execute();
  $row = $st->fetch(PDO::FETCH_ASSOC);
  return is_array($row) ? $row : null;
}

function updateUserPasswordSqlite3(SQLite3 $db, int $id, string $passwordHash): bool {
  $st = $db->prepare("UPDATE users SET password_hash=:password_hash, updated_at=datetime('now','localtime') WHERE id=:id");
  $st->bindValue(':password_hash', $passwordHash, SQLITE3_TEXT);
  $st->bindValue(':id', $id, SQLITE3_INTEGER);
  $res = $st->execute();
  if ($res) { return $db->changes() > 0; }
  return false;
}

function updateUserPasswordPdo(PDO $db, int $id, string $passwordHash): bool {
  $st = $db->prepare("UPDATE users SET password_hash=:password_hash, updated_at=datetime('now','localtime') WHERE id=:id");
  $st->bindValue(':password_hash', $passwordHash, PDO::PARAM_STR);
  $st->bindValue(':id', $id, PDO::PARAM_INT);
  $st->execute();
  return $st->rowCount() > 0;
}

function fetchUsersForBackupSqlite3(SQLite3 $db): array {
  $res = $db->query("SELECT id,username,password_hash,full_name,role,active,created_at,updated_at FROM users ORDER BY id ASC");
  $out = [];
  while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $out[] = $row;
  }
  return $out;
}

function fetchUsersForBackupPdo(PDO $db): array {
  return $db->query("SELECT id,username,password_hash,full_name,role,active,created_at,updated_at FROM users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
}

function csvEscapeValue(string $value): string {
  $needsQuotes = str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n") || str_contains($value, "\r");
  if (!$needsQuotes) { return $value; }
  return '"' . str_replace('"', '""', $value) . '"';
}

function csvBuild(array $headers, array $rows): string {
  $lines = [];
  $lines[] = implode(',', array_map(fn($v) => csvEscapeValue((string)$v), $headers));
  foreach ($rows as $row) {
    $line = [];
    foreach ($headers as $key) {
      $line[] = csvEscapeValue((string)($row[$key] ?? ''));
    }
    $lines[] = implode(',', $line);
  }
  return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
}

function sanitizeBackupRows(array $backup, string $section): array {
  $raw = $backup[$section] ?? [];
  if (!is_array($raw)) { return []; }

  $active = [];
  $archived = [];
  if (array_key_exists('active', $raw) || array_key_exists('archived', $raw)) {
    $active = is_array($raw['active'] ?? null) ? $raw['active'] : [];
    $archived = is_array($raw['archived'] ?? null) ? $raw['archived'] : [];
  } else {
    $active = $raw;
  }

  $out = [];
  foreach ($active as $row) {
    if (!is_array($row)) { continue; }
    $row['archived'] = 0;
    $row['archived_at'] = null;
    $out[] = $row;
  }
  foreach ($archived as $row) {
    if (!is_array($row)) { continue; }
    $row['archived'] = 1;
    if (!isset($row['archived_at']) || trim((string)$row['archived_at']) === '') {
      $row['archived_at'] = date('Y-m-d H:i:s');
    }
    $out[] = $row;
  }
  return $out;
}

function backupDiagnosticsFromVmRows(array $rows): array {
  $files = [];
  $seen = [];
  $root = vmDiagnosticProjectRoot();
  foreach ($rows as $row) {
    if (!is_array($row)) { continue; }
    $refs = diagnosticReferencesFromVmRow($row);
    foreach ($refs as $ref) {
      if (isset($seen[$ref])) { continue; }
      $seen[$ref] = true;
      $relative = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $ref);
      $fullPath = $root . DIRECTORY_SEPARATOR . ltrim($relative, DIRECTORY_SEPARATOR);
      if (!is_file($fullPath) || !is_readable($fullPath)) { continue; }
      $content = @file_get_contents($fullPath);
      if (!is_string($content)) { continue; }
      $files[] = [
        'reference' => $ref,
        'content_base64' => base64_encode($content),
      ];
    }
  }
  return $files;
}

function restoreBackupDiagnostics(array $files): void {
  if (!$files) { return; }
  $root = vmDiagnosticProjectRoot();
  foreach ($files as $item) {
    if (!is_array($item)) { continue; }
    $ref = trim((string)($item['reference'] ?? ''));
    $contentBase64 = trim((string)($item['content_base64'] ?? ''));
    if ($ref === '' || $contentBase64 === '') { continue; }
    if (!str_starts_with(str_replace('\\', '/', $ref), 'data/vm_diagnostics/')) { continue; }
    $decoded = base64_decode($contentBase64, true);
    if ($decoded === false) { continue; }
    $relative = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $ref);
    $fullPath = $root . DIRECTORY_SEPARATOR . ltrim($relative, DIRECTORY_SEPARATOR);
    $dir = dirname($fullPath);
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    if (!is_dir($dir)) { continue; }
    @file_put_contents($fullPath, $decoded);
  }
}

function sqlValueSqlite3($value): string {
  if ($value === null) { return 'NULL'; }
  if (is_bool($value)) { return $value ? '1' : '0'; }
  if (is_int($value) || is_float($value)) { return (string)$value; }
  return "'" . SQLite3::escapeString((string)$value) . "'";
}

function sqlValuePdo(PDO $db, $value): string {
  if ($value === null) { return 'NULL'; }
  if (is_bool($value)) { return $value ? '1' : '0'; }
  if (is_int($value) || is_float($value)) { return (string)$value; }
  $quoted = $db->quote((string)$value);
  return $quoted !== false ? $quoted : "''";
}

function handleApiRequest(): void {
  header('Content-Type: application/json; charset=utf-8');
  try {
    startAppSession();
    $db = db();
    $api = (string)($_GET['api'] ?? '');

    if ($api === 'auth-status') {
      $sessionUser = sessionAuthUser();
      if (!$sessionUser) {
        echo json_encode(['ok' => true, 'data' => ['authenticated' => false, 'user' => null]], JSON_UNESCAPED_UNICODE);
        return;
      }

      $freshUser = $db instanceof SQLite3
        ? fetchUserByIdSqlite3($db, (int)$sessionUser['id'])
        : fetchUserByIdPdo($db, (int)$sessionUser['id']);
      if (!is_array($freshUser) || (int)($freshUser['active'] ?? 0) !== 1) {
        unset($_SESSION['auth_user']);
        echo json_encode(['ok' => true, 'data' => ['authenticated' => false, 'user' => null]], JSON_UNESCAPED_UNICODE);
        return;
      }

      $public = publicUserPayload($freshUser);
      $_SESSION['auth_user'] = $public;
      echo json_encode(['ok' => true, 'data' => ['authenticated' => true, 'user' => $public]], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'login') {
      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
      $data = json_decode((string)file_get_contents('php://input'), true);
      if (!is_array($data)) { echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); return; }

      $username = trim((string)($data['username'] ?? ''));
      $password = (string)($data['password'] ?? '');
      if ($username === '' || $password === '') {
        echo json_encode(['ok'=>false,'error'=>'Usuario e senha sao obrigatorios.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      $user = $db instanceof SQLite3
        ? fetchUserByUsernameSqlite3($db, $username)
        : fetchUserByUsernamePdo($db, $username);
      if (!is_array($user) || (int)($user['active'] ?? 0) !== 1) {
        echo json_encode(['ok'=>false,'error'=>'Credenciais invalidas.'], JSON_UNESCAPED_UNICODE);
        return;
      }
      if (!password_verify($password, (string)($user['password_hash'] ?? ''))) {
        echo json_encode(['ok'=>false,'error'=>'Credenciais invalidas.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      session_regenerate_id(true);
      $public = publicUserPayload($user);
      $_SESSION['auth_user'] = $public;
      echo json_encode(['ok'=>true,'data'=>['authenticated'=>true,'user'=>$public]], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'logout') {
      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
      unset($_SESSION['auth_user']);
      echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
      return;
    }

    $publicActions = ['list', 'vm-list', 'db-list', 'archived-list'];
    $authUser = sessionAuthUser();
    if (!$authUser && !in_array($api, $publicActions, true)) {
      echo json_encode(['ok'=>false,'error'=>'Autenticacao necessaria.'], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'change-password') {
      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
      $data = json_decode((string)file_get_contents('php://input'), true);
      if (!is_array($data)) { echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); return; }

      $currentPassword = (string)($data['current_password'] ?? '');
      $newPassword = (string)($data['new_password'] ?? '');
      if ($currentPassword === '' || $newPassword === '') {
        echo json_encode(['ok'=>false,'error'=>'Senha atual e nova senha sao obrigatorias.'], JSON_UNESCAPED_UNICODE);
        return;
      }
      if (strlen($newPassword) < 8) {
        echo json_encode(['ok'=>false,'error'=>'A nova senha deve ter ao menos 8 caracteres.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      $userRow = $db instanceof SQLite3
        ? fetchUserByIdSqlite3($db, (int)$authUser['id'])
        : fetchUserByIdPdo($db, (int)$authUser['id']);
      if (!is_array($userRow) || (int)($userRow['active'] ?? 0) !== 1) {
        unset($_SESSION['auth_user']);
        echo json_encode(['ok'=>false,'error'=>'Usuario invalido.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      $currentHash = (string)($userRow['password_hash'] ?? '');
      if (!password_verify($currentPassword, $currentHash)) {
        echo json_encode(['ok'=>false,'error'=>'Senha atual invalida.'], JSON_UNESCAPED_UNICODE);
        return;
      }
      if (password_verify($newPassword, $currentHash)) {
        echo json_encode(['ok'=>false,'error'=>'A nova senha deve ser diferente da senha atual.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
      $updated = $db instanceof SQLite3
        ? updateUserPasswordSqlite3($db, (int)$userRow['id'], $newHash)
        : updateUserPasswordPdo($db, (int)$userRow['id'], $newHash);
      if (!$updated) {
        echo json_encode(['ok'=>false,'error'=>'Falha ao atualizar senha.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      session_regenerate_id(true);
      $_SESSION['auth_user'] = publicUserPayload($userRow);
      echo json_encode(['ok'=>true,'data'=>['message'=>'Senha atualizada com sucesso.']], JSON_UNESCAPED_UNICODE);
      return;
    }

    $adminOnlyActions = ['delete', 'vm-delete', 'backup-export', 'backup-restore'];
    if (in_array($api, $adminOnlyActions, true) && !roleAtLeast((string)$authUser['role'], 'admin')) {
      echo json_encode(['ok'=>false,'error'=>'Permissao insuficiente para esta acao.'], JSON_UNESCAPED_UNICODE);
      return;
    }

    $editActions = ['save', 'archive', 'restore', 'vm-save', 'vm-archive', 'vm-restore', 'db-save', 'db-delete', 'vm-diagnostic-save', 'vm-diagnostic-clear'];
    if (in_array($api, $editActions, true) && !roleAtLeast((string)$authUser['role'], 'edicao')) {
      echo json_encode(['ok'=>false,'error'=>'Perfil apenas leitura.'], JSON_UNESCAPED_UNICODE);
      return;
    }

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

    if ($api === 'export-csv') {
      $scope = strtolower(trim((string)($_GET['scope'] ?? 'systems')));
      if (!in_array($scope, ['systems', 'vms', 'databases'], true)) {
        echo json_encode(['ok'=>false,'error'=>'Escopo de exportacao invalido.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      $headers = [];
      $rows = [];
      if ($scope === 'systems') {
        $systems = $db instanceof SQLite3 ? fetchSystemsSqlite3($db, false) : fetchSystemsPdo($db, false);
        $headers = ['id','name','system_name','category','system_group','status','criticality','owner','url','url_homolog','vm_name','vm_ip','vm_homolog_name','vm_homolog_ip','vm_dev_name','vm_dev_ip','tech','description','notes','updated_at'];
        foreach ($systems as $item) {
          if (!is_array($item)) { continue; }
          $rows[] = [
            'id' => (int)($item['id'] ?? 0),
            'name' => (string)($item['name'] ?? ''),
            'system_name' => (string)($item['system_name'] ?? ''),
            'category' => (string)($item['category'] ?? ''),
            'system_group' => (string)($item['system_group'] ?? ''),
            'status' => (string)($item['status'] ?? ''),
            'criticality' => (string)($item['criticality'] ?? ''),
            'owner' => (string)($item['owner'] ?? ''),
            'url' => (string)($item['url'] ?? ''),
            'url_homolog' => (string)($item['url_homolog'] ?? ''),
            'vm_name' => (string)($item['vm_name'] ?? ''),
            'vm_ip' => (string)($item['vm_ip'] ?? ''),
            'vm_homolog_name' => (string)($item['vm_homolog_name'] ?? ''),
            'vm_homolog_ip' => (string)($item['vm_homolog_ip'] ?? ''),
            'vm_dev_name' => (string)($item['vm_dev_name'] ?? ''),
            'vm_dev_ip' => (string)($item['vm_dev_ip'] ?? ''),
            'tech' => is_array($item['tech'] ?? null) ? implode(', ', $item['tech']) : (string)($item['tech'] ?? ''),
            'description' => (string)($item['description'] ?? ''),
            'notes' => (string)($item['notes'] ?? ''),
            'updated_at' => (string)($item['updated_at'] ?? ''),
          ];
        }
      } elseif ($scope === 'vms') {
        $vms = $db instanceof SQLite3 ? listVmsSqlite3($db, false) : listVmsPdo($db, false);
        $headers = ['id','name','ip','vm_category','vm_type','vm_access','vm_administration','os_name','vcpus','ram','disk','vm_language','vm_tech','vm_instances','system_count','database_count','updated_at'];
        foreach ($vms as $item) {
          if (!is_array($item)) { continue; }
          $rows[] = [
            'id' => (int)($item['id'] ?? 0),
            'name' => (string)($item['name'] ?? ''),
            'ip' => (string)($item['ip'] ?? ''),
            'vm_category' => (string)($item['vm_category'] ?? ''),
            'vm_type' => (string)($item['vm_type'] ?? ''),
            'vm_access' => (string)($item['vm_access'] ?? ''),
            'vm_administration' => (string)($item['vm_administration'] ?? ''),
            'os_name' => (string)($item['os_name'] ?? ''),
            'vcpus' => (string)($item['vcpus'] ?? ''),
            'ram' => (string)($item['ram'] ?? ''),
            'disk' => (string)($item['disk'] ?? ''),
            'vm_language' => is_array($item['vm_language_list'] ?? null) ? implode(', ', $item['vm_language_list']) : (string)($item['vm_language'] ?? ''),
            'vm_tech' => is_array($item['vm_tech_list'] ?? null) ? implode(', ', $item['vm_tech_list']) : (string)($item['vm_tech'] ?? ''),
            'vm_instances' => is_array($item['vm_instances_list'] ?? null) ? json_encode($item['vm_instances_list'], JSON_UNESCAPED_UNICODE) : (string)($item['vm_instances'] ?? ''),
            'system_count' => (int)($item['system_count'] ?? 0),
            'database_count' => (int)($item['database_count'] ?? 0),
            'updated_at' => (string)($item['updated_at'] ?? ''),
          ];
        }
      } else {
        $databases = $db instanceof SQLite3 ? listDatabasesSqlite3($db, false) : listDatabasesPdo($db, false);
        $headers = ['id','system_name','db_name','db_user','vm_name','db_instance_name','db_instance_ip','vm_homolog_name','db_instance_homolog_name','db_instance_homolog_ip','notes','updated_at'];
        foreach ($databases as $item) {
          if (!is_array($item)) { continue; }
          $rows[] = [
            'id' => (int)($item['id'] ?? 0),
            'system_name' => (string)($item['system_name'] ?? ''),
            'db_name' => (string)($item['db_name'] ?? ''),
            'db_user' => (string)($item['db_user'] ?? ''),
            'vm_name' => (string)($item['vm_name'] ?? ''),
            'db_instance_name' => (string)($item['db_instance_name'] ?? ''),
            'db_instance_ip' => (string)($item['db_instance_ip'] ?? ''),
            'vm_homolog_name' => (string)($item['vm_homolog_name'] ?? ''),
            'db_instance_homolog_name' => (string)($item['db_instance_homolog_name'] ?? ''),
            'db_instance_homolog_ip' => (string)($item['db_instance_homolog_ip'] ?? ''),
            'notes' => (string)($item['notes'] ?? ''),
            'updated_at' => (string)($item['updated_at'] ?? ''),
          ];
        }
      }

      $file = 'sei_portfolio_' . $scope . '_' . date('Ymd_His') . '.csv';
      echo json_encode(['ok'=>true,'data'=>[
        'filename' => $file,
        'mime' => 'text/csv;charset=utf-8',
        'content' => csvBuild($headers, $rows),
      ]], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'backup-export') {
      $systemsActive = $db instanceof SQLite3 ? fetchSystemsSqlite3($db, false) : fetchSystemsPdo($db, false);
      $systemsArchived = $db instanceof SQLite3 ? fetchSystemsSqlite3($db, true) : fetchSystemsPdo($db, true);
      $vmsActive = $db instanceof SQLite3 ? listVmsSqlite3($db, false) : listVmsPdo($db, false);
      $vmsArchived = $db instanceof SQLite3 ? listVmsSqlite3($db, true) : listVmsPdo($db, true);
      $dbActive = $db instanceof SQLite3 ? listDatabasesSqlite3($db, false) : listDatabasesPdo($db, false);
      $dbArchived = $db instanceof SQLite3 ? listDatabasesSqlite3($db, true) : listDatabasesPdo($db, true);
      $users = $db instanceof SQLite3 ? fetchUsersForBackupSqlite3($db) : fetchUsersForBackupPdo($db);
      $diagnosticFiles = backupDiagnosticsFromVmRows(array_merge($vmsActive, $vmsArchived));

      $payload = [
        'meta' => [
          'app' => 'SEI Portifolio',
          'version' => 1,
          'exported_at' => date('c'),
        ],
        'systems' => ['active' => $systemsActive, 'archived' => $systemsArchived],
        'vms' => ['active' => $vmsActive, 'archived' => $vmsArchived],
        'databases' => ['active' => $dbActive, 'archived' => $dbArchived],
        'users' => $users,
        'diagnostic_files' => $diagnosticFiles,
      ];

      echo json_encode(['ok'=>true,'data'=>$payload], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'backup-restore') {
      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
      $data = json_decode((string)file_get_contents('php://input'), true);
      if (!is_array($data)) { echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); return; }
      $backup = is_array($data['backup'] ?? null) ? $data['backup'] : $data;

      $systemsRows = sanitizeBackupRows($backup, 'systems');
      $vmRows = sanitizeBackupRows($backup, 'vms');
      $databaseRows = sanitizeBackupRows($backup, 'databases');
      $usersRows = is_array($backup['users'] ?? null) ? $backup['users'] : [];
      $diagnosticFiles = is_array($backup['diagnostic_files'] ?? null) ? $backup['diagnostic_files'] : [];

      if (!$systemsRows && !$vmRows && !$databaseRows) {
        echo json_encode(['ok'=>false,'error'=>'Backup vazio ou invalido.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      if ($db instanceof SQLite3) {
        $db->exec('BEGIN IMMEDIATE');
        try {
          $db->exec('DELETE FROM system_databases');
          $db->exec('DELETE FROM systems');
          $db->exec('DELETE FROM virtual_machines');

          foreach ($vmRows as $row) {
            if (!is_array($row)) { continue; }
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) { continue; }
            $vmInstances = $row['vm_instances'] ?? ($row['vm_instances_list'] ?? '[]');
            if (is_array($vmInstances)) { $vmInstances = json_encode(normalizeVmInstancesValue($vmInstances), JSON_UNESCAPED_UNICODE); }
            $vmLanguage = $row['vm_language'] ?? ($row['vm_language_list'] ?? '');
            if (is_array($vmLanguage)) { $vmLanguage = implode(',', array_filter(array_map('trim', $vmLanguage))); }
            $vmTech = $row['vm_tech'] ?? ($row['vm_tech_list'] ?? '');
            if (is_array($vmTech)) { $vmTech = implode(',', array_filter(array_map('trim', $vmTech))); }
            $values = [
              sqlValueSqlite3($id),
              sqlValueSqlite3(normalizeUtf8Text(trim((string)($row['name'] ?? '')))),
              sqlValueSqlite3(trim((string)($row['ip'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['vm_category'] ?? 'Producao')) ?: 'Producao'),
              sqlValueSqlite3(trim((string)($row['vm_type'] ?? 'Sistemas')) ?: 'Sistemas'),
              sqlValueSqlite3(trim((string)($row['vm_access'] ?? 'Interno')) ?: 'Interno'),
              sqlValueSqlite3(trim((string)($row['vm_administration'] ?? 'SEI')) ?: 'SEI'),
              sqlValueSqlite3((string)$vmInstances),
              sqlValueSqlite3((string)$vmLanguage),
              sqlValueSqlite3((string)$vmTech),
              sqlValueSqlite3(trim((string)($row['diagnostic_json_ref'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['diagnostic_json_updated_at'] ?? '')) ?: null),
              sqlValueSqlite3(trim((string)($row['diagnostic_json_ref_r'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['diagnostic_json_updated_at_r'] ?? '')) ?: null),
              sqlValueSqlite3(trim((string)($row['os_name'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['os_version'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['vcpus'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['ram'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['disk'] ?? ''))),
              sqlValueSqlite3((int)($row['archived'] ?? 0) > 0 ? 1 : 0),
              sqlValueSqlite3(trim((string)($row['archived_at'] ?? '')) ?: null),
              sqlValueSqlite3(trim((string)($row['created_at'] ?? date('Y-m-d H:i:s')))),
              sqlValueSqlite3(trim((string)($row['updated_at'] ?? date('Y-m-d H:i:s')))),
            ];
            $db->exec("INSERT INTO virtual_machines(id,name,ip,vm_category,vm_type,vm_access,vm_administration,vm_instances,vm_language,vm_tech,diagnostic_json_ref,diagnostic_json_updated_at,diagnostic_json_ref_r,diagnostic_json_updated_at_r,os_name,os_version,vcpus,ram,disk,archived,archived_at,created_at,updated_at) VALUES(" . implode(',', $values) . ")");
          }

          foreach ($systemsRows as $row) {
            if (!is_array($row)) { continue; }
            $id = (int)($row['id'] ?? 0);
            $name = normalizeUtf8Text(trim((string)($row['name'] ?? '')));
            if ($id <= 0 || $name === '') { continue; }
            $tech = $row['tech'] ?? '';
            if (is_array($tech)) { $tech = implode(',', array_filter(array_map('trim', $tech))); }
            $values = [
              sqlValueSqlite3($id),
              sqlValueSqlite3($name),
              sqlValueSqlite3(trim((string)($row['system_name'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['system_group'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['ip'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['ip_homolog'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['vm'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['url_homolog'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['vm_homolog'] ?? ''))),
              sqlValueSqlite3((int)($row['vm_id'] ?? 0) > 0 ? (int)$row['vm_id'] : null),
              sqlValueSqlite3((int)($row['vm_homolog_id'] ?? 0) > 0 ? (int)$row['vm_homolog_id'] : null),
              sqlValueSqlite3((int)($row['vm_dev_id'] ?? 0) > 0 ? (int)$row['vm_dev_id'] : null),
              sqlValueSqlite3((int)($row['archived'] ?? 0) > 0 ? 1 : 0),
              sqlValueSqlite3(trim((string)($row['archived_at'] ?? '')) ?: null),
              sqlValueSqlite3(trim((string)($row['responsible_sector'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['responsible_coordinator'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['extension_number'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['email'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['support'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['support_contact'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['category'] ?? 'Outro')) ?: 'Outro'),
              sqlValueSqlite3(normalizeSystemStatus((string)($row['status'] ?? 'Ativo')) ?: 'Ativo'),
              sqlValueSqlite3(trim((string)($row['waf'] ?? ''))),
              sqlValueSqlite3((string)$tech),
              sqlValueSqlite3(trim((string)($row['url'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['description'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['owner'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['criticality'] ?? 'Media')) ?: 'Media'),
              sqlValueSqlite3(trim((string)($row['version'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['notes'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['created_at'] ?? date('Y-m-d H:i:s')))),
              sqlValueSqlite3(trim((string)($row['updated_at'] ?? date('Y-m-d H:i:s')))),
            ];
            $db->exec("INSERT INTO systems(id,name,system_name,system_group,ip,ip_homolog,vm,url_homolog,vm_homolog,vm_id,vm_homolog_id,vm_dev_id,archived,archived_at,responsible_sector,responsible_coordinator,extension_number,email,support,support_contact,category,status,waf,tech,url,description,owner,criticality,version,notes,created_at,updated_at) VALUES(" . implode(',', $values) . ")");
          }

          foreach ($databaseRows as $row) {
            if (!is_array($row)) { continue; }
            $id = (int)($row['id'] ?? 0);
            $systemId = (int)($row['system_id'] ?? 0);
            $dbName = trim((string)($row['db_name'] ?? ''));
            if ($id <= 0 || $systemId <= 0 || $dbName === '') { continue; }
            $values = [
              sqlValueSqlite3($id),
              sqlValueSqlite3($systemId),
              sqlValueSqlite3((int)($row['vm_id'] ?? 0) > 0 ? (int)$row['vm_id'] : null),
              sqlValueSqlite3((int)($row['vm_homolog_id'] ?? 0) > 0 ? (int)$row['vm_homolog_id'] : null),
              sqlValueSqlite3($dbName),
              sqlValueSqlite3(trim((string)($row['db_user'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['db_engine'] ?? '')) ?: 'SGBD'),
              sqlValueSqlite3(trim((string)($row['db_engine_version'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['db_engine_version_homolog'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['db_instance_name'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['db_instance_ip'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['db_instance_homolog_name'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['db_instance_homolog_ip'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['notes'] ?? ''))),
              sqlValueSqlite3((int)($row['archived'] ?? 0) > 0 ? 1 : 0),
              sqlValueSqlite3(trim((string)($row['archived_at'] ?? '')) ?: null),
              sqlValueSqlite3(trim((string)($row['created_at'] ?? date('Y-m-d H:i:s')))),
              sqlValueSqlite3(trim((string)($row['updated_at'] ?? date('Y-m-d H:i:s')))),
            ];
            $db->exec("INSERT INTO system_databases(id,system_id,vm_id,vm_homolog_id,db_name,db_user,db_engine,db_engine_version,db_engine_version_homolog,db_instance_name,db_instance_ip,db_instance_homolog_name,db_instance_homolog_ip,notes,archived,archived_at,created_at,updated_at) VALUES(" . implode(',', $values) . ")");
          }

          if ($usersRows) {
            $db->exec('DELETE FROM users');
            foreach ($usersRows as $row) {
              if (!is_array($row)) { continue; }
              $id = (int)($row['id'] ?? 0);
              $username = trim((string)($row['username'] ?? ''));
              $passwordHash = trim((string)($row['password_hash'] ?? ''));
              if ($id <= 0 || $username === '' || $passwordHash === '') { continue; }
              $values = [
                sqlValueSqlite3($id),
                sqlValueSqlite3($username),
                sqlValueSqlite3($passwordHash),
                sqlValueSqlite3(trim((string)($row['full_name'] ?? ''))),
                sqlValueSqlite3(normalizeRole((string)($row['role'] ?? 'leitura'))),
                sqlValueSqlite3((int)($row['active'] ?? 0) > 0 ? 1 : 0),
                sqlValueSqlite3(trim((string)($row['created_at'] ?? date('Y-m-d H:i:s')))),
                sqlValueSqlite3(trim((string)($row['updated_at'] ?? date('Y-m-d H:i:s')))),
              ];
              $db->exec("INSERT INTO users(id,username,password_hash,full_name,role,active,created_at,updated_at) VALUES(" . implode(',', $values) . ")");
            }
          }

          $db->exec('COMMIT');
        } catch (Throwable $e) {
          $db->exec('ROLLBACK');
          throw $e;
        }
      } else {
        $db->beginTransaction();
        try {
          $db->exec('DELETE FROM system_databases');
          $db->exec('DELETE FROM systems');
          $db->exec('DELETE FROM virtual_machines');

          foreach ($vmRows as $row) {
            if (!is_array($row)) { continue; }
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) { continue; }
            $vmInstances = $row['vm_instances'] ?? ($row['vm_instances_list'] ?? '[]');
            if (is_array($vmInstances)) { $vmInstances = json_encode(normalizeVmInstancesValue($vmInstances), JSON_UNESCAPED_UNICODE); }
            $vmLanguage = $row['vm_language'] ?? ($row['vm_language_list'] ?? '');
            if (is_array($vmLanguage)) { $vmLanguage = implode(',', array_filter(array_map('trim', $vmLanguage))); }
            $vmTech = $row['vm_tech'] ?? ($row['vm_tech_list'] ?? '');
            if (is_array($vmTech)) { $vmTech = implode(',', array_filter(array_map('trim', $vmTech))); }
            $values = [
              sqlValuePdo($db, $id),
              sqlValuePdo($db, normalizeUtf8Text(trim((string)($row['name'] ?? '')))),
              sqlValuePdo($db, trim((string)($row['ip'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['vm_category'] ?? 'Producao')) ?: 'Producao'),
              sqlValuePdo($db, trim((string)($row['vm_type'] ?? 'Sistemas')) ?: 'Sistemas'),
              sqlValuePdo($db, trim((string)($row['vm_access'] ?? 'Interno')) ?: 'Interno'),
              sqlValuePdo($db, trim((string)($row['vm_administration'] ?? 'SEI')) ?: 'SEI'),
              sqlValuePdo($db, (string)$vmInstances),
              sqlValuePdo($db, (string)$vmLanguage),
              sqlValuePdo($db, (string)$vmTech),
              sqlValuePdo($db, trim((string)($row['diagnostic_json_ref'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['diagnostic_json_updated_at'] ?? '')) ?: null),
              sqlValuePdo($db, trim((string)($row['diagnostic_json_ref_r'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['diagnostic_json_updated_at_r'] ?? '')) ?: null),
              sqlValuePdo($db, trim((string)($row['os_name'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['os_version'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['vcpus'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['ram'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['disk'] ?? ''))),
              sqlValuePdo($db, (int)($row['archived'] ?? 0) > 0 ? 1 : 0),
              sqlValuePdo($db, trim((string)($row['archived_at'] ?? '')) ?: null),
              sqlValuePdo($db, trim((string)($row['created_at'] ?? date('Y-m-d H:i:s')))),
              sqlValuePdo($db, trim((string)($row['updated_at'] ?? date('Y-m-d H:i:s')))),
            ];
            $db->exec("INSERT INTO virtual_machines(id,name,ip,vm_category,vm_type,vm_access,vm_administration,vm_instances,vm_language,vm_tech,diagnostic_json_ref,diagnostic_json_updated_at,diagnostic_json_ref_r,diagnostic_json_updated_at_r,os_name,os_version,vcpus,ram,disk,archived,archived_at,created_at,updated_at) VALUES(" . implode(',', $values) . ")");
          }

          foreach ($systemsRows as $row) {
            if (!is_array($row)) { continue; }
            $id = (int)($row['id'] ?? 0);
            $name = normalizeUtf8Text(trim((string)($row['name'] ?? '')));
            if ($id <= 0 || $name === '') { continue; }
            $tech = $row['tech'] ?? '';
            if (is_array($tech)) { $tech = implode(',', array_filter(array_map('trim', $tech))); }
            $values = [
              sqlValuePdo($db, $id),
              sqlValuePdo($db, $name),
              sqlValuePdo($db, trim((string)($row['system_name'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['system_group'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['ip'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['ip_homolog'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['vm'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['url_homolog'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['vm_homolog'] ?? ''))),
              sqlValuePdo($db, (int)($row['vm_id'] ?? 0) > 0 ? (int)$row['vm_id'] : null),
              sqlValuePdo($db, (int)($row['vm_homolog_id'] ?? 0) > 0 ? (int)$row['vm_homolog_id'] : null),
              sqlValuePdo($db, (int)($row['vm_dev_id'] ?? 0) > 0 ? (int)$row['vm_dev_id'] : null),
              sqlValuePdo($db, (int)($row['archived'] ?? 0) > 0 ? 1 : 0),
              sqlValuePdo($db, trim((string)($row['archived_at'] ?? '')) ?: null),
              sqlValuePdo($db, trim((string)($row['responsible_sector'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['responsible_coordinator'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['extension_number'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['email'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['support'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['support_contact'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['category'] ?? 'Outro')) ?: 'Outro'),
              sqlValuePdo($db, normalizeSystemStatus((string)($row['status'] ?? 'Ativo')) ?: 'Ativo'),
              sqlValuePdo($db, trim((string)($row['waf'] ?? ''))),
              sqlValuePdo($db, (string)$tech),
              sqlValuePdo($db, trim((string)($row['url'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['description'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['owner'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['criticality'] ?? 'Media')) ?: 'Media'),
              sqlValuePdo($db, trim((string)($row['version'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['notes'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['created_at'] ?? date('Y-m-d H:i:s')))),
              sqlValuePdo($db, trim((string)($row['updated_at'] ?? date('Y-m-d H:i:s')))),
            ];
            $db->exec("INSERT INTO systems(id,name,system_name,system_group,ip,ip_homolog,vm,url_homolog,vm_homolog,vm_id,vm_homolog_id,vm_dev_id,archived,archived_at,responsible_sector,responsible_coordinator,extension_number,email,support,support_contact,category,status,waf,tech,url,description,owner,criticality,version,notes,created_at,updated_at) VALUES(" . implode(',', $values) . ")");
          }

          foreach ($databaseRows as $row) {
            if (!is_array($row)) { continue; }
            $id = (int)($row['id'] ?? 0);
            $systemId = (int)($row['system_id'] ?? 0);
            $dbName = trim((string)($row['db_name'] ?? ''));
            if ($id <= 0 || $systemId <= 0 || $dbName === '') { continue; }
            $values = [
              sqlValuePdo($db, $id),
              sqlValuePdo($db, $systemId),
              sqlValuePdo($db, (int)($row['vm_id'] ?? 0) > 0 ? (int)$row['vm_id'] : null),
              sqlValuePdo($db, (int)($row['vm_homolog_id'] ?? 0) > 0 ? (int)$row['vm_homolog_id'] : null),
              sqlValuePdo($db, $dbName),
              sqlValuePdo($db, trim((string)($row['db_user'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['db_engine'] ?? '')) ?: 'SGBD'),
              sqlValuePdo($db, trim((string)($row['db_engine_version'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['db_engine_version_homolog'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['db_instance_name'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['db_instance_ip'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['db_instance_homolog_name'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['db_instance_homolog_ip'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['notes'] ?? ''))),
              sqlValuePdo($db, (int)($row['archived'] ?? 0) > 0 ? 1 : 0),
              sqlValuePdo($db, trim((string)($row['archived_at'] ?? '')) ?: null),
              sqlValuePdo($db, trim((string)($row['created_at'] ?? date('Y-m-d H:i:s')))),
              sqlValuePdo($db, trim((string)($row['updated_at'] ?? date('Y-m-d H:i:s')))),
            ];
            $db->exec("INSERT INTO system_databases(id,system_id,vm_id,vm_homolog_id,db_name,db_user,db_engine,db_engine_version,db_engine_version_homolog,db_instance_name,db_instance_ip,db_instance_homolog_name,db_instance_homolog_ip,notes,archived,archived_at,created_at,updated_at) VALUES(" . implode(',', $values) . ")");
          }

          if ($usersRows) {
            $db->exec('DELETE FROM users');
            foreach ($usersRows as $row) {
              if (!is_array($row)) { continue; }
              $id = (int)($row['id'] ?? 0);
              $username = trim((string)($row['username'] ?? ''));
              $passwordHash = trim((string)($row['password_hash'] ?? ''));
              if ($id <= 0 || $username === '' || $passwordHash === '') { continue; }
              $values = [
                sqlValuePdo($db, $id),
                sqlValuePdo($db, $username),
                sqlValuePdo($db, $passwordHash),
                sqlValuePdo($db, trim((string)($row['full_name'] ?? ''))),
                sqlValuePdo($db, normalizeRole((string)($row['role'] ?? 'leitura'))),
                sqlValuePdo($db, (int)($row['active'] ?? 0) > 0 ? 1 : 0),
                sqlValuePdo($db, trim((string)($row['created_at'] ?? date('Y-m-d H:i:s')))),
                sqlValuePdo($db, trim((string)($row['updated_at'] ?? date('Y-m-d H:i:s')))),
              ];
              $db->exec("INSERT INTO users(id,username,password_hash,full_name,role,active,created_at,updated_at) VALUES(" . implode(',', $values) . ")");
            }
          }

          $db->commit();
        } catch (Throwable $e) {
          $db->rollBack();
          throw $e;
        }
      }

      restoreBackupDiagnostics($diagnosticFiles);
      echo json_encode(['ok'=>true,'data'=>[
        'systems' => count($systemsRows),
        'vms' => count($vmRows),
        'databases' => count($databaseRows),
        'users' => count($usersRows),
      ]], JSON_UNESCAPED_UNICODE);
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
      $vmLanguage = is_array($data['vm_language'] ?? null) ? implode(',', array_filter(array_map('trim', $data['vm_language']))) : trim((string)($data['vm_language'] ?? ''));
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
          $st = $db->prepare("UPDATE virtual_machines SET name=:name, ip=:ip, vm_category=:vm_category, vm_type=:vm_type, vm_access=:vm_access, vm_administration=:vm_administration, vm_instances=:vm_instances, vm_language=:vm_language, vm_tech=:vm_tech, os_name=:os_name, os_version=:os_version, vcpus=:vcpus, ram=:ram, disk=:disk, updated_at=datetime('now','localtime') WHERE id=:id");
          $st->bindValue(':name', $name, SQLITE3_TEXT);
          $st->bindValue(':ip', $ip, SQLITE3_TEXT);
          $st->bindValue(':vm_category', $vmCategory, SQLITE3_TEXT);
          $st->bindValue(':vm_type', $vmType, SQLITE3_TEXT);
          $st->bindValue(':vm_access', $vmAccess, SQLITE3_TEXT);
          $st->bindValue(':vm_administration', $vmAdministration, SQLITE3_TEXT);
          $st->bindValue(':vm_instances', $vmInstances, SQLITE3_TEXT);
          $st->bindValue(':vm_language', $vmLanguage, SQLITE3_TEXT);
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
          $st = $db->prepare("UPDATE virtual_machines SET name=:name, ip=:ip, vm_category=:vm_category, vm_type=:vm_type, vm_access=:vm_access, vm_administration=:vm_administration, vm_instances=:vm_instances, vm_language=:vm_language, vm_tech=:vm_tech, os_name=:os_name, os_version=:os_version, vcpus=:vcpus, ram=:ram, disk=:disk, updated_at=datetime('now','localtime') WHERE id=:id");
          $st->bindValue(':name', $name, PDO::PARAM_STR);
          $st->bindValue(':ip', $ip, PDO::PARAM_STR);
          $st->bindValue(':vm_category', $vmCategory, PDO::PARAM_STR);
          $st->bindValue(':vm_type', $vmType, PDO::PARAM_STR);
          $st->bindValue(':vm_access', $vmAccess, PDO::PARAM_STR);
          $st->bindValue(':vm_administration', $vmAdministration, PDO::PARAM_STR);
          $st->bindValue(':vm_instances', $vmInstances, PDO::PARAM_STR);
          $st->bindValue(':vm_language', $vmLanguage, PDO::PARAM_STR);
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
          $st = $db->prepare("INSERT INTO virtual_machines(name,ip,vm_category,vm_type,vm_access,vm_administration,vm_instances,vm_language,vm_tech,os_name,os_version,vcpus,ram,disk) VALUES(:name,:ip,:vm_category,:vm_type,:vm_access,:vm_administration,:vm_instances,:vm_language,:vm_tech,:os_name,:os_version,:vcpus,:ram,:disk)");
          $st->bindValue(':name', $name, SQLITE3_TEXT);
          $st->bindValue(':ip', $ip, SQLITE3_TEXT);
          $st->bindValue(':vm_category', $vmCategory, SQLITE3_TEXT);
          $st->bindValue(':vm_type', $vmType, SQLITE3_TEXT);
          $st->bindValue(':vm_access', $vmAccess, SQLITE3_TEXT);
          $st->bindValue(':vm_administration', $vmAdministration, SQLITE3_TEXT);
          $st->bindValue(':vm_instances', $vmInstances, SQLITE3_TEXT);
          $st->bindValue(':vm_language', $vmLanguage, SQLITE3_TEXT);
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
          $st = $db->prepare("INSERT INTO virtual_machines(name,ip,vm_category,vm_type,vm_access,vm_administration,vm_instances,vm_language,vm_tech,os_name,os_version,vcpus,ram,disk) VALUES(:name,:ip,:vm_category,:vm_type,:vm_access,:vm_administration,:vm_instances,:vm_language,:vm_tech,:os_name,:os_version,:vcpus,:ram,:disk)");
          $st->bindValue(':name', $name, PDO::PARAM_STR);
          $st->bindValue(':ip', $ip, PDO::PARAM_STR);
          $st->bindValue(':vm_category', $vmCategory, PDO::PARAM_STR);
          $st->bindValue(':vm_type', $vmType, PDO::PARAM_STR);
          $st->bindValue(':vm_access', $vmAccess, PDO::PARAM_STR);
          $st->bindValue(':vm_administration', $vmAdministration, PDO::PARAM_STR);
          $st->bindValue(':vm_instances', $vmInstances, PDO::PARAM_STR);
          $st->bindValue(':vm_language', $vmLanguage, PDO::PARAM_STR);
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

      $phpEntry = null;
      $rEntry = null;
      try {
        $phpEntry = loadVmDiagnosticEntry($vm, 'php');
      } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'error'=>'Arquivo JSON referenciado invalido para PHP: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        return;
      }
      try {
        $rEntry = loadVmDiagnosticEntry($vm, 'r');
      } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'error'=>'Arquivo JSON referenciado invalido para R: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        return;
      }

      $supports = [
        'php' => vmSupportsDiagnosticTech($vm, 'php'),
        'r' => vmSupportsDiagnosticTech($vm, 'r'),
      ];

      echo json_encode([
        'ok'=>true,
        'data'=>[
          'vm_id'=>$id,
          'vm_name'=>trim((string)($vm['name'] ?? '')),
          'vm_label'=>vmLabelFromRow($vm),
          'vm_language'=>trim((string)($vm['vm_language'] ?? '')),
          'vm_language_list'=>is_array($vm['vm_language_list'] ?? null) ? $vm['vm_language_list'] : [],
          'vm_language_versions'=>is_array($vm['vm_language_versions'] ?? null) ? $vm['vm_language_versions'] : ['php'=>'','r'=>''],
          'vm_tech'=>trim((string)($vm['vm_tech'] ?? '')),
          'vm_tech_list'=>is_array($vm['vm_tech_list'] ?? null) ? $vm['vm_tech_list'] : [],
          'supports'=>$supports,
          'diagnostics'=>[
            'php'=>$phpEntry,
            'r'=>$rEntry,
          ],
          'has_any_file'=>($phpEntry['has_file'] ?? false) || ($rEntry['has_file'] ?? false),
          // Compatibilidade com consumidores antigos (camada PHP).
          'reference'=>$phpEntry['reference'] ?? '',
          'filename'=>$phpEntry['filename'] ?? '',
          'updated_at'=>$phpEntry['updated_at'] ?? '',
          'has_file'=>$phpEntry['has_file'] ?? false,
          'summary'=>$phpEntry['summary'] ?? null,
          'json'=>$phpEntry['json'] ?? null,
        ]
      ], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'vm-diagnostic-save') {
      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
      $data = json_decode((string)file_get_contents('php://input'), true);
      if (!is_array($data)) { echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); return; }

      $id = (int)($data['id'] ?? 0);
      $tech = normalizeDiagnosticTech((string)($data['tech'] ?? 'php'));
      $filename = sanitizeDiagnosticFilename((string)($data['filename'] ?? 'diagnostic.json'));
      $jsonText = (string)($data['json_text'] ?? '');
      if ($id <= 0) { echo json_encode(['ok'=>false,'error'=>'Invalid ID']); return; }
      if (trim($jsonText) === '') { echo json_encode(['ok'=>false,'error'=>'Conteudo JSON vazio.']); return; }

      $vm = $db instanceof SQLite3 ? fetchVmByIdSqlite3($db, $id) : fetchVmByIdPdo($db, $id);
      if (!$vm) { echo json_encode(['ok'=>false,'error'=>'Maquina nao encontrada']); return; }

      try {
        $payload = decodeDiagnosticPayloadByTech($tech, $jsonText);
      } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
        return;
      }

      $dir = ensureVmDiagnosticDir();
      $stamp = date('Ymd_His');
      $storedName = "vm_{$id}_{$tech}_{$stamp}_{$filename}";
      $fullPath = $dir . DIRECTORY_SEPARATOR . $storedName;
      $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
      if ($encoded === false || @file_put_contents($fullPath, $encoded) === false) {
        echo json_encode(['ok'=>false,'error'=>'Falha ao salvar arquivo de diagnostico.']);
        return;
      }

      $reference = 'data/vm_diagnostics/' . $storedName;
      $refCol = diagnosticRefColumnByTech($tech);
      $updatedAtCol = diagnosticUpdatedAtColumnByTech($tech);
      if ($db instanceof SQLite3) {
        $st = $db->prepare("UPDATE virtual_machines SET $refCol=:ref, $updatedAtCol=datetime('now','localtime'), updated_at=datetime('now','localtime') WHERE id=:id");
        $st->bindValue(':ref', $reference, SQLITE3_TEXT);
        $st->bindValue(':id', $id, SQLITE3_INTEGER);
        $st->execute();
        $vmUpdated = fetchVmByIdSqlite3($db, $id);
      } else {
        $st = $db->prepare("UPDATE virtual_machines SET $refCol=:ref, $updatedAtCol=datetime('now','localtime'), updated_at=datetime('now','localtime') WHERE id=:id");
        $st->bindValue(':ref', $reference, PDO::PARAM_STR);
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
        $vmUpdated = fetchVmByIdPdo($db, $id);
      }

      if (!$vmUpdated) { echo json_encode(['ok'=>false,'error'=>'Maquina nao encontrada']); return; }

      try {
        $entry = loadVmDiagnosticEntry($vmUpdated, $tech);
      } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'error'=>'Arquivo JSON salvo, mas invalido para ' . diagnosticTechLabel($tech) . ': ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        return;
      }

      echo json_encode([
        'ok'=>true,
        'data'=>[
          'tech'=>$tech,
          'vm'=>$vmUpdated,
          'vm_name'=>trim((string)($vmUpdated['name'] ?? '')),
          'vm_label'=>vmLabelFromRow($vmUpdated),
          'reference'=>$entry['reference'] ?? '',
          'filename'=>$entry['filename'] ?? '',
          'updated_at'=>$entry['updated_at'] ?? '',
          'summary'=>$entry['summary'] ?? null,
          'json'=>$entry['json'] ?? $payload,
          'diagnostic'=>$entry,
        ]
      ], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'vm-diagnostic-clear') {
      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
      $data = json_decode((string)file_get_contents('php://input'), true);
      if (!is_array($data)) { echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); return; }
      $id = (int)($data['id'] ?? 0);
      $tech = normalizeDiagnosticTech((string)($data['tech'] ?? 'php'));
      if ($id <= 0) { echo json_encode(['ok'=>false,'error'=>'Invalid ID']); return; }

      $vm = $db instanceof SQLite3 ? fetchVmByIdSqlite3($db, $id) : fetchVmByIdPdo($db, $id);
      if (!$vm) { echo json_encode(['ok'=>false,'error'=>'Maquina nao encontrada']); return; }

      $refCol = diagnosticRefColumnByTech($tech);
      $updatedAtCol = diagnosticUpdatedAtColumnByTech($tech);
      $ref = trim((string)($vm[$refCol] ?? ''));
      if ($ref !== '') { deleteDiagnosticFileByReference($ref); }

      if ($db instanceof SQLite3) {
        $st = $db->prepare("UPDATE virtual_machines SET $refCol='', $updatedAtCol=NULL, updated_at=datetime('now','localtime') WHERE id=:id");
        $st->bindValue(':id', $id, SQLITE3_INTEGER);
        $st->execute();
        $vmUpdated = fetchVmByIdSqlite3($db, $id);
      } else {
        $st = $db->prepare("UPDATE virtual_machines SET $refCol='', $updatedAtCol=NULL, updated_at=datetime('now','localtime') WHERE id=:id");
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
        $vmUpdated = fetchVmByIdPdo($db, $id);
      }

      if (!$vmUpdated) { echo json_encode(['ok'=>false,'error'=>'Maquina nao encontrada']); return; }
      echo json_encode([
        'ok'=>true,
        'data'=>[
          'tech'=>$tech,
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
        $inUse = (int)$db->querySingle("SELECT COUNT(*) FROM systems WHERE (vm_id=$id OR vm_homolog_id=$id OR vm_dev_id=$id) AND IFNULL(archived,0)=0");
        if ($inUse > 0) { echo json_encode(['ok'=>false,'error'=>'Maquina vinculada a sistemas ativos. Arquive os sistemas antes.']); return; }
        $dbInUse = (int)$db->querySingle("SELECT COUNT(*) FROM system_databases WHERE (vm_id=$id OR vm_homolog_id=$id) AND IFNULL(archived,0)=0");
        if ($dbInUse > 0) { echo json_encode(['ok'=>false,'error'=>'Maquina vinculada a bases ativas. Remova ou mova as bases antes.']); return; }
        $db->exec("UPDATE virtual_machines SET archived=1, archived_at=datetime('now','localtime') WHERE id=$id");
      } else {
        $stCheck = $db->prepare("SELECT COUNT(*) FROM systems WHERE (vm_id=:id OR vm_homolog_id=:id OR vm_dev_id=:id) AND IFNULL(archived,0)=0");
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
        $db->exec("UPDATE systems SET vm_dev_id=NULL WHERE vm_dev_id=$id");
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
        $stNullDev = $db->prepare("UPDATE systems SET vm_dev_id=NULL WHERE vm_dev_id=:id");
        $stNullDev->bindValue(':id', $id, PDO::PARAM_INT);
        $stNullDev->execute();
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
      $fields = ['name','system_name','vm_id','vm_homolog_id','vm_dev_id','category','system_group','status','url','url_homolog','description','owner','criticality','version','notes','responsible_sector','responsible_coordinator','extension_number','email','support','support_contact','analytics','ssl','waf','bundle','directory','size','repository','archived','archived_at'];
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


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

function normalizeSystemRow(array $row): array {
  $row['tech'] = $row['tech'] !== '' ? array_values(array_filter(array_map('trim', explode(',', (string)$row['tech'])))) : [];
  $row['vm_id'] = isset($row['vm_id']) && $row['vm_id'] !== null && (int)$row['vm_id'] > 0 ? (int)$row['vm_id'] : null;
  $row['vm_homolog_id'] = isset($row['vm_homolog_id']) && $row['vm_homolog_id'] !== null && (int)$row['vm_homolog_id'] > 0 ? (int)$row['vm_homolog_id'] : null;

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
    (SELECT COUNT(*) FROM system_databases d WHERE d.vm_id = vm.id AND IFNULL(d.archived,0)=0) AS database_count
  FROM virtual_machines vm
  WHERE IFNULL(vm.archived,0) = $flag
  ORDER BY vm.name COLLATE NOCASE";
  $res = $db->query($sql);
  $out = [];
  while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $row['id'] = (int)$row['id'];
    $row['vm_category'] = trim((string)($row['vm_category'] ?? '')) !== '' ? trim((string)$row['vm_category']) : 'Producao';
    $row['vm_tech'] = trim((string)($row['vm_tech'] ?? ''));
    $row['vm_tech_list'] = $row['vm_tech'] !== '' ? array_values(array_filter(array_map('trim', explode(',', $row['vm_tech'])))) : [];
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
    (SELECT COUNT(*) FROM system_databases d WHERE d.vm_id = vm.id AND IFNULL(d.archived,0)=0) AS database_count
  FROM virtual_machines vm
  WHERE IFNULL(vm.archived,0) = $flag
  ORDER BY vm.name COLLATE NOCASE";
  $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as &$row) {
    $row['id'] = (int)$row['id'];
    $row['vm_category'] = trim((string)($row['vm_category'] ?? '')) !== '' ? trim((string)$row['vm_category']) : 'Producao';
    $row['vm_tech'] = trim((string)($row['vm_tech'] ?? ''));
    $row['vm_tech_list'] = $row['vm_tech'] !== '' ? array_values(array_filter(array_map('trim', explode(',', $row['vm_tech'])))) : [];
    $row['prod_count'] = (int)$row['prod_count'];
    $row['hml_count'] = (int)$row['hml_count'];
    $row['system_count'] = (int)$row['system_count'];
    $row['database_count'] = (int)$row['database_count'];
  }
  unset($row);
  return $rows;
}

function fetchVmByIdSqlite3(SQLite3 $db, int $id): ?array {
  $st = $db->prepare("SELECT id,name,ip,vm_category,vm_tech,created_at,updated_at FROM virtual_machines WHERE id=:id");
  $st->bindValue(':id', $id, SQLITE3_INTEGER);
  $res = $st->execute();
  $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;
  if (!is_array($row)) { return null; }
  $row['id'] = (int)$row['id'];
  $row['vm_category'] = trim((string)($row['vm_category'] ?? '')) !== '' ? trim((string)$row['vm_category']) : 'Producao';
  $row['vm_tech'] = trim((string)($row['vm_tech'] ?? ''));
  $row['vm_tech_list'] = $row['vm_tech'] !== '' ? array_values(array_filter(array_map('trim', explode(',', $row['vm_tech'])))) : [];
  return $row;
}

function fetchVmByIdPdo(PDO $db, int $id): ?array {
  $st = $db->prepare("SELECT id,name,ip,vm_category,vm_tech,created_at,updated_at FROM virtual_machines WHERE id=:id");
  $st->bindValue(':id', $id, PDO::PARAM_INT);
  $st->execute();
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!is_array($row)) { return null; }
  $row['id'] = (int)$row['id'];
  $row['vm_category'] = trim((string)($row['vm_category'] ?? '')) !== '' ? trim((string)$row['vm_category']) : 'Producao';
  $row['vm_tech'] = trim((string)($row['vm_tech'] ?? ''));
  $row['vm_tech_list'] = $row['vm_tech'] !== '' ? array_values(array_filter(array_map('trim', explode(',', $row['vm_tech'])))) : [];
  return $row;
}

function databaseSelectSql(): string {
  return "SELECT
    d.*,
    s.name AS system_name,
    s.system_name AS system_alias,
    vm.name AS vm_name,
    vm.ip AS vm_ip
  FROM system_databases d
  LEFT JOIN systems s ON s.id = d.system_id
  LEFT JOIN virtual_machines vm ON vm.id = d.vm_id";
}

function normalizeDatabaseRow(array $row): array {
  $row['id'] = (int)($row['id'] ?? 0);
  $row['system_id'] = (int)($row['system_id'] ?? 0);
  $row['vm_id'] = isset($row['vm_id']) && $row['vm_id'] !== null && (int)$row['vm_id'] > 0 ? (int)$row['vm_id'] : null;
  $row['db_name'] = trim((string)($row['db_name'] ?? ''));
  $row['db_engine'] = trim((string)($row['db_engine'] ?? ''));
  $row['db_engine_version'] = trim((string)($row['db_engine_version'] ?? ''));
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
      $vmTech = is_array($data['vm_tech'] ?? null) ? implode(',', array_filter(array_map('trim', $data['vm_tech']))) : trim((string)($data['vm_tech'] ?? ''));
      if ($name === '' || $ip === '') { echo json_encode(['ok'=>false,'error'=>'Nome e IP sao obrigatorios']); return; }
      $allowedCategories = ['Producao','Homologacao','Desenvolvimento'];
      if (!in_array($vmCategory, $allowedCategories, true)) { $vmCategory = 'Producao'; }

      if (!empty($data['id'])) {
        $id = (int)$data['id'];
        if ($db instanceof SQLite3) {
          $st = $db->prepare("UPDATE virtual_machines SET name=:name, ip=:ip, vm_category=:vm_category, vm_tech=:vm_tech, updated_at=datetime('now','localtime') WHERE id=:id");
          $st->bindValue(':name', $name, SQLITE3_TEXT);
          $st->bindValue(':ip', $ip, SQLITE3_TEXT);
          $st->bindValue(':vm_category', $vmCategory, SQLITE3_TEXT);
          $st->bindValue(':vm_tech', $vmTech, SQLITE3_TEXT);
          $st->bindValue(':id', $id, SQLITE3_INTEGER);
          $st->execute();
          $row = fetchVmByIdSqlite3($db, $id);
        } else {
          $st = $db->prepare("UPDATE virtual_machines SET name=:name, ip=:ip, vm_category=:vm_category, vm_tech=:vm_tech, updated_at=datetime('now','localtime') WHERE id=:id");
          $st->bindValue(':name', $name, PDO::PARAM_STR);
          $st->bindValue(':ip', $ip, PDO::PARAM_STR);
          $st->bindValue(':vm_category', $vmCategory, PDO::PARAM_STR);
          $st->bindValue(':vm_tech', $vmTech, PDO::PARAM_STR);
          $st->bindValue(':id', $id, PDO::PARAM_INT);
          $st->execute();
          $row = fetchVmByIdPdo($db, $id);
        }
      } else {
        if ($db instanceof SQLite3) {
          $st = $db->prepare("INSERT INTO virtual_machines(name,ip,vm_category,vm_tech) VALUES(:name,:ip,:vm_category,:vm_tech)");
          $st->bindValue(':name', $name, SQLITE3_TEXT);
          $st->bindValue(':ip', $ip, SQLITE3_TEXT);
          $st->bindValue(':vm_category', $vmCategory, SQLITE3_TEXT);
          $st->bindValue(':vm_tech', $vmTech, SQLITE3_TEXT);
          $st->execute();
          $id = (int)$db->lastInsertRowID();
          $row = fetchVmByIdSqlite3($db, $id);
        } else {
          $st = $db->prepare("INSERT INTO virtual_machines(name,ip,vm_category,vm_tech) VALUES(:name,:ip,:vm_category,:vm_tech)");
          $st->bindValue(':name', $name, PDO::PARAM_STR);
          $st->bindValue(':ip', $ip, PDO::PARAM_STR);
          $st->bindValue(':vm_category', $vmCategory, PDO::PARAM_STR);
          $st->bindValue(':vm_tech', $vmTech, PDO::PARAM_STR);
          $st->execute();
          $id = (int)$db->lastInsertId();
          $row = fetchVmByIdPdo($db, $id);
        }
      }

      if (!$row) { echo json_encode(['ok'=>false,'error'=>'Maquina nao encontrada']); return; }
      echo json_encode(['ok'=>true,'data'=>$row], JSON_UNESCAPED_UNICODE);
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
        $dbInUse = (int)$db->querySingle("SELECT COUNT(*) FROM system_databases WHERE vm_id=$id AND IFNULL(archived,0)=0");
        if ($dbInUse > 0) { echo json_encode(['ok'=>false,'error'=>'Maquina vinculada a bases ativas. Remova ou mova as bases antes.']); return; }
        $db->exec("UPDATE virtual_machines SET archived=1, archived_at=datetime('now','localtime') WHERE id=$id");
      } else {
        $stCheck = $db->prepare("SELECT COUNT(*) FROM systems WHERE (vm_id=:id OR vm_homolog_id=:id) AND IFNULL(archived,0)=0");
        $stCheck->bindValue(':id', $id, PDO::PARAM_INT);
        $stCheck->execute();
        $inUse = (int)$stCheck->fetchColumn();
        if ($inUse > 0) { echo json_encode(['ok'=>false,'error'=>'Maquina vinculada a sistemas ativos. Arquive os sistemas antes.']); return; }
        $stCheckDb = $db->prepare("SELECT COUNT(*) FROM system_databases WHERE vm_id=:id AND IFNULL(archived,0)=0");
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
        $db->exec("UPDATE system_databases SET vm_id=NULL, updated_at=datetime('now','localtime') WHERE vm_id=$id");
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
        $stNullDbVm = $db->prepare("UPDATE system_databases SET vm_id=NULL, updated_at=datetime('now','localtime') WHERE vm_id=:id");
        $stNullDbVm->bindValue(':id', $id, PDO::PARAM_INT);
        $stNullDbVm->execute();
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
      $dbName = trim((string)($data['db_name'] ?? ''));
      $dbEngine = trim((string)($data['db_engine'] ?? ''));
      $dbEngineVersion = trim((string)($data['db_engine_version'] ?? ''));
      $notes = trim((string)($data['notes'] ?? ''));

      if ($systemId <= 0) { echo json_encode(['ok'=>false,'error'=>'Sistema obrigatorio']); return; }
      if ($vmId <= 0) { echo json_encode(['ok'=>false,'error'=>'Maquina obrigatoria']); return; }
      if ($dbName === '') { echo json_encode(['ok'=>false,'error'=>'Nome da base obrigatorio']); return; }
      if ($dbEngine === '') { echo json_encode(['ok'=>false,'error'=>'SGBD obrigatorio']); return; }

      if ($db instanceof SQLite3) {
        $systemExists = (int)$db->querySingle("SELECT COUNT(*) FROM systems WHERE id=$systemId AND IFNULL(archived,0)=0");
        if ($systemExists === 0) { echo json_encode(['ok'=>false,'error'=>'Sistema invalido ou arquivado']); return; }
        $vmExists = (int)$db->querySingle("SELECT COUNT(*) FROM virtual_machines WHERE id=$vmId AND IFNULL(archived,0)=0");
        if ($vmExists === 0) { echo json_encode(['ok'=>false,'error'=>'Maquina invalida ou arquivada']); return; }
      } else {
        $stSystem = $db->prepare("SELECT COUNT(*) FROM systems WHERE id=:id AND IFNULL(archived,0)=0");
        $stSystem->bindValue(':id', $systemId, PDO::PARAM_INT);
        $stSystem->execute();
        if ((int)$stSystem->fetchColumn() === 0) { echo json_encode(['ok'=>false,'error'=>'Sistema invalido ou arquivado']); return; }

        $stVm = $db->prepare("SELECT COUNT(*) FROM virtual_machines WHERE id=:id AND IFNULL(archived,0)=0");
        $stVm->bindValue(':id', $vmId, PDO::PARAM_INT);
        $stVm->execute();
        if ((int)$stVm->fetchColumn() === 0) { echo json_encode(['ok'=>false,'error'=>'Maquina invalida ou arquivada']); return; }
      }

      if (!empty($data['id'])) {
        $id = (int)$data['id'];
        if ($db instanceof SQLite3) {
          $st = $db->prepare("UPDATE system_databases
            SET system_id=:system_id, vm_id=:vm_id, db_name=:db_name, db_engine=:db_engine, db_engine_version=:db_engine_version, notes=:notes, archived=0, archived_at=NULL, updated_at=datetime('now','localtime')
            WHERE id=:id");
          $st->bindValue(':system_id', $systemId, SQLITE3_INTEGER);
          $st->bindValue(':vm_id', $vmId, SQLITE3_INTEGER);
          $st->bindValue(':db_name', $dbName, SQLITE3_TEXT);
          $st->bindValue(':db_engine', $dbEngine, SQLITE3_TEXT);
          $st->bindValue(':db_engine_version', $dbEngineVersion, SQLITE3_TEXT);
          $st->bindValue(':notes', $notes, SQLITE3_TEXT);
          $st->bindValue(':id', $id, SQLITE3_INTEGER);
          $st->execute();
          $row = fetchDatabaseByIdSqlite3($db, $id);
        } else {
          $st = $db->prepare("UPDATE system_databases
            SET system_id=:system_id, vm_id=:vm_id, db_name=:db_name, db_engine=:db_engine, db_engine_version=:db_engine_version, notes=:notes, archived=0, archived_at=NULL, updated_at=datetime('now','localtime')
            WHERE id=:id");
          $st->bindValue(':system_id', $systemId, PDO::PARAM_INT);
          $st->bindValue(':vm_id', $vmId, PDO::PARAM_INT);
          $st->bindValue(':db_name', $dbName, PDO::PARAM_STR);
          $st->bindValue(':db_engine', $dbEngine, PDO::PARAM_STR);
          $st->bindValue(':db_engine_version', $dbEngineVersion, PDO::PARAM_STR);
          $st->bindValue(':notes', $notes, PDO::PARAM_STR);
          $st->bindValue(':id', $id, PDO::PARAM_INT);
          $st->execute();
          $row = fetchDatabaseByIdPdo($db, $id);
        }
      } else {
        if ($db instanceof SQLite3) {
          $st = $db->prepare("INSERT INTO system_databases(system_id,vm_id,db_name,db_engine,db_engine_version,notes) VALUES(:system_id,:vm_id,:db_name,:db_engine,:db_engine_version,:notes)");
          $st->bindValue(':system_id', $systemId, SQLITE3_INTEGER);
          $st->bindValue(':vm_id', $vmId, SQLITE3_INTEGER);
          $st->bindValue(':db_name', $dbName, SQLITE3_TEXT);
          $st->bindValue(':db_engine', $dbEngine, SQLITE3_TEXT);
          $st->bindValue(':db_engine_version', $dbEngineVersion, SQLITE3_TEXT);
          $st->bindValue(':notes', $notes, SQLITE3_TEXT);
          $st->execute();
          $id = (int)$db->lastInsertRowID();
          $row = fetchDatabaseByIdSqlite3($db, $id);
        } else {
          $st = $db->prepare("INSERT INTO system_databases(system_id,vm_id,db_name,db_engine,db_engine_version,notes) VALUES(:system_id,:vm_id,:db_name,:db_engine,:db_engine_version,:notes)");
          $st->bindValue(':system_id', $systemId, PDO::PARAM_INT);
          $st->bindValue(':vm_id', $vmId, PDO::PARAM_INT);
          $st->bindValue(':db_name', $dbName, PDO::PARAM_STR);
          $st->bindValue(':db_engine', $dbEngine, PDO::PARAM_STR);
          $st->bindValue(':db_engine_version', $dbEngineVersion, PDO::PARAM_STR);
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
      $tech = is_array($data['tech'] ?? null) ? implode(',', array_filter(array_map('trim', $data['tech']))) : trim((string)($data['tech'] ?? ''));
      $fields = ['name','system_name','vm_id','vm_homolog_id','category','status','url','url_homolog','description','owner','criticality','version','notes','archived','archived_at'];
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

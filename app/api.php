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

function fetchSystemsSqlite3(SQLite3 $db): array {
  $res = $db->query(systemSelectSql() . " ORDER BY s.name COLLATE NOCASE");
  $out = [];
  while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $out[] = normalizeSystemRow($row);
  }
  return $out;
}

function fetchSystemsPdo(PDO $db): array {
  $rows = $db->query(systemSelectSql() . " ORDER BY s.name COLLATE NOCASE")->fetchAll(PDO::FETCH_ASSOC);
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
  $st->bindValue(':'.$field, trim((string)($data[$field] ?? '')), SQLITE3_TEXT);
}

function bindSystemFieldPdo(PDOStatement $st, string $field, array $data): void {
  if ($field === 'vm_id' || $field === 'vm_homolog_id') {
    $id = (int)($data[$field] ?? 0);
    if ($id > 0) { $st->bindValue(':'.$field, $id, PDO::PARAM_INT); }
    else { $st->bindValue(':'.$field, null, PDO::PARAM_NULL); }
    return;
  }
  $st->bindValue(':'.$field, trim((string)($data[$field] ?? '')), PDO::PARAM_STR);
}

function listVmsSqlite3(SQLite3 $db): array {
  $sql = "SELECT
    vm.*,
    (SELECT COUNT(*) FROM systems s WHERE s.vm_id = vm.id) AS prod_count,
    (SELECT COUNT(*) FROM systems s WHERE s.vm_homolog_id = vm.id) AS hml_count,
    (SELECT COUNT(*) FROM systems s WHERE s.vm_id = vm.id OR s.vm_homolog_id = vm.id) AS system_count
  FROM virtual_machines vm
  ORDER BY vm.name COLLATE NOCASE";
  $res = $db->query($sql);
  $out = [];
  while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $row['id'] = (int)$row['id'];
    $row['prod_count'] = (int)$row['prod_count'];
    $row['hml_count'] = (int)$row['hml_count'];
    $row['system_count'] = (int)$row['system_count'];
    $out[] = $row;
  }
  return $out;
}

function listVmsPdo(PDO $db): array {
  $sql = "SELECT
    vm.*,
    (SELECT COUNT(*) FROM systems s WHERE s.vm_id = vm.id) AS prod_count,
    (SELECT COUNT(*) FROM systems s WHERE s.vm_homolog_id = vm.id) AS hml_count,
    (SELECT COUNT(*) FROM systems s WHERE s.vm_id = vm.id OR s.vm_homolog_id = vm.id) AS system_count
  FROM virtual_machines vm
  ORDER BY vm.name COLLATE NOCASE";
  $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as &$row) {
    $row['id'] = (int)$row['id'];
    $row['prod_count'] = (int)$row['prod_count'];
    $row['hml_count'] = (int)$row['hml_count'];
    $row['system_count'] = (int)$row['system_count'];
  }
  unset($row);
  return $rows;
}

function fetchVmByIdSqlite3(SQLite3 $db, int $id): ?array {
  $st = $db->prepare("SELECT id,name,ip,created_at,updated_at FROM virtual_machines WHERE id=:id");
  $st->bindValue(':id', $id, SQLITE3_INTEGER);
  $res = $st->execute();
  $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;
  if (!is_array($row)) { return null; }
  $row['id'] = (int)$row['id'];
  return $row;
}

function fetchVmByIdPdo(PDO $db, int $id): ?array {
  $st = $db->prepare("SELECT id,name,ip,created_at,updated_at FROM virtual_machines WHERE id=:id");
  $st->bindValue(':id', $id, PDO::PARAM_INT);
  $st->execute();
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!is_array($row)) { return null; }
  $row['id'] = (int)$row['id'];
  return $row;
}

function handleApiRequest(): void {
  header('Content-Type: application/json; charset=utf-8');
  try {
    $db = db();
    $api = (string)($_GET['api'] ?? '');

    if ($api === 'list') {
      $out = $db instanceof SQLite3 ? fetchSystemsSqlite3($db) : fetchSystemsPdo($db);
      echo json_encode(['ok'=>true,'data'=>$out], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'vm-list') {
      $out = $db instanceof SQLite3 ? listVmsSqlite3($db) : listVmsPdo($db);
      echo json_encode(['ok'=>true,'data'=>$out], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'vm-save') {
      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
      $data = json_decode((string)file_get_contents('php://input'), true);
      if (!is_array($data)) { echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); return; }

      $name = trim((string)($data['name'] ?? ''));
      $ip = trim((string)($data['ip'] ?? ''));
      if ($name === '' || $ip === '') { echo json_encode(['ok'=>false,'error'=>'Nome e IP sao obrigatorios']); return; }

      if (!empty($data['id'])) {
        $id = (int)$data['id'];
        if ($db instanceof SQLite3) {
          $st = $db->prepare("UPDATE virtual_machines SET name=:name, ip=:ip, updated_at=datetime('now','localtime') WHERE id=:id");
          $st->bindValue(':name', $name, SQLITE3_TEXT);
          $st->bindValue(':ip', $ip, SQLITE3_TEXT);
          $st->bindValue(':id', $id, SQLITE3_INTEGER);
          $st->execute();
          $row = fetchVmByIdSqlite3($db, $id);
        } else {
          $st = $db->prepare("UPDATE virtual_machines SET name=:name, ip=:ip, updated_at=datetime('now','localtime') WHERE id=:id");
          $st->bindValue(':name', $name, PDO::PARAM_STR);
          $st->bindValue(':ip', $ip, PDO::PARAM_STR);
          $st->bindValue(':id', $id, PDO::PARAM_INT);
          $st->execute();
          $row = fetchVmByIdPdo($db, $id);
        }
      } else {
        if ($db instanceof SQLite3) {
          $st = $db->prepare("INSERT INTO virtual_machines(name,ip) VALUES(:name,:ip)");
          $st->bindValue(':name', $name, SQLITE3_TEXT);
          $st->bindValue(':ip', $ip, SQLITE3_TEXT);
          $st->execute();
          $id = (int)$db->lastInsertRowID();
          $row = fetchVmByIdSqlite3($db, $id);
        } else {
          $st = $db->prepare("INSERT INTO virtual_machines(name,ip) VALUES(:name,:ip)");
          $st->bindValue(':name', $name, PDO::PARAM_STR);
          $st->bindValue(':ip', $ip, PDO::PARAM_STR);
          $st->execute();
          $id = (int)$db->lastInsertId();
          $row = fetchVmByIdPdo($db, $id);
        }
      }

      if (!$row) { echo json_encode(['ok'=>false,'error'=>'Maquina nao encontrada']); return; }
      echo json_encode(['ok'=>true,'data'=>$row], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'vm-delete') {
      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
      $data = json_decode((string)file_get_contents('php://input'), true);
      $id = (int)($data['id'] ?? 0);
      if ($id <= 0) { echo json_encode(['ok'=>false,'error'=>'Invalid ID']); return; }

      if ($db instanceof SQLite3) {
        $inUse = (int)$db->querySingle("SELECT COUNT(*) FROM systems WHERE vm_id=$id OR vm_homolog_id=$id");
        if ($inUse > 0) { echo json_encode(['ok'=>false,'error'=>'Maquina vinculada a sistemas. Remova os vinculos antes de excluir.']); return; }
        $db->exec("DELETE FROM virtual_machines WHERE id=$id");
      } else {
        $stCheck = $db->prepare("SELECT COUNT(*) FROM systems WHERE vm_id=:id OR vm_homolog_id=:id");
        $stCheck->bindValue(':id', $id, PDO::PARAM_INT);
        $stCheck->execute();
        $inUse = (int)$stCheck->fetchColumn();
        if ($inUse > 0) { echo json_encode(['ok'=>false,'error'=>'Maquina vinculada a sistemas. Remova os vinculos antes de excluir.']); return; }
        $st = $db->prepare("DELETE FROM virtual_machines WHERE id=:id");
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
      $fields = ['name','system_name','vm_id','vm_homolog_id','category','status','url','url_homolog','description','owner','criticality','version','notes'];
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
        $db->exec("DELETE FROM systems WHERE id=$id");
      } else {
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

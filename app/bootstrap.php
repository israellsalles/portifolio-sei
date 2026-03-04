<?php

$projectRoot = __DIR__ . DIRECTORY_SEPARATOR . '..';
$legacyDb = $projectRoot . DIRECTORY_SEPARATOR . 'sysportfolio.db';
$dir = $projectRoot . DIRECTORY_SEPARATOR . 'data';
if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
if (!is_dir($dir)) {
  $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sysportfolio';
  if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
}

$targetDb = $dir . DIRECTORY_SEPARATOR . 'sysportfolio.db';
if (file_exists($legacyDb)) {
  $needsMigration = !file_exists($targetDb) || @filemtime($legacyDb) > @filemtime($targetDb);
  if ($needsMigration) { @copy($legacyDb, $targetDb); }
}

define('DB_PATH', $targetDb);

function ensureSystemColumnsSqlite3(SQLite3 $db): void {
  $required = [
    'system_name' => "TEXT DEFAULT ''",
    'ip' => "TEXT DEFAULT ''",
    'ip_homolog' => "TEXT DEFAULT ''",
    'vm' => "TEXT DEFAULT ''",
    'url_homolog' => "TEXT DEFAULT ''",
    'vm_homolog' => "TEXT DEFAULT ''",
    'vm_id' => "INTEGER DEFAULT NULL",
    'vm_homolog_id' => "INTEGER DEFAULT NULL",
  ];
  $existing = [];
  $res = $db->query('PRAGMA table_info(systems)');
  while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $existing[] = (string)($row['name'] ?? '');
  }
  foreach ($required as $col => $spec) {
    if (!in_array($col, $existing, true)) {
      $db->exec("ALTER TABLE systems ADD COLUMN $col $spec");
    }
  }
}

function ensureSystemColumnsPdo(PDO $db): void {
  $required = [
    'system_name' => "TEXT DEFAULT ''",
    'ip' => "TEXT DEFAULT ''",
    'ip_homolog' => "TEXT DEFAULT ''",
    'vm' => "TEXT DEFAULT ''",
    'url_homolog' => "TEXT DEFAULT ''",
    'vm_homolog' => "TEXT DEFAULT ''",
    'vm_id' => "INTEGER DEFAULT NULL",
    'vm_homolog_id' => "INTEGER DEFAULT NULL",
  ];
  $existing = [];
  $rows = $db->query('PRAGMA table_info(systems)')->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as $row) {
    $existing[] = (string)($row['name'] ?? '');
  }
  foreach ($required as $col => $spec) {
    if (!in_array($col, $existing, true)) {
      $db->exec("ALTER TABLE systems ADD COLUMN $col $spec");
    }
  }
}

function ensureVmTableSqlite3(SQLite3 $db): void {
  $db->exec("CREATE TABLE IF NOT EXISTS virtual_machines (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    ip TEXT DEFAULT '',
    created_at TEXT DEFAULT (datetime('now','localtime')),
    updated_at TEXT DEFAULT (datetime('now','localtime'))
  )");
}

function ensureVmTablePdo(PDO $db): void {
  $db->exec("CREATE TABLE IF NOT EXISTS virtual_machines (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    ip TEXT DEFAULT '',
    created_at TEXT DEFAULT (datetime('now','localtime')),
    updated_at TEXT DEFAULT (datetime('now','localtime'))
  )");
}

function findOrCreateVmIdSqlite3(SQLite3 $db, string $name, string $ip): ?int {
  $name = trim($name);
  $ip = trim($ip);
  if ($name === '' && $ip === '') { return null; }
  if ($name === '') { $name = 'vm-' . preg_replace('/[^a-z0-9]+/i', '-', $ip); }

  $sel = $db->prepare("SELECT id FROM virtual_machines WHERE lower(name)=lower(:name) AND IFNULL(ip,'')=:ip LIMIT 1");
  $sel->bindValue(':name', $name, SQLITE3_TEXT);
  $sel->bindValue(':ip', $ip, SQLITE3_TEXT);
  $res = $sel->execute();
  $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;
  if (is_array($row) && isset($row['id'])) { return (int)$row['id']; }

  $ins = $db->prepare("INSERT INTO virtual_machines(name,ip,created_at,updated_at) VALUES(:name,:ip,datetime('now','localtime'),datetime('now','localtime'))");
  $ins->bindValue(':name', $name, SQLITE3_TEXT);
  $ins->bindValue(':ip', $ip, SQLITE3_TEXT);
  $ins->execute();
  return (int)$db->lastInsertRowID();
}

function findOrCreateVmIdPdo(PDO $db, string $name, string $ip): ?int {
  $name = trim($name);
  $ip = trim($ip);
  if ($name === '' && $ip === '') { return null; }
  if ($name === '') { $name = 'vm-' . preg_replace('/[^a-z0-9]+/i', '-', $ip); }

  $sel = $db->prepare("SELECT id FROM virtual_machines WHERE lower(name)=lower(:name) AND IFNULL(ip,'')=:ip LIMIT 1");
  $sel->bindValue(':name', $name, PDO::PARAM_STR);
  $sel->bindValue(':ip', $ip, PDO::PARAM_STR);
  $sel->execute();
  $row = $sel->fetch(PDO::FETCH_ASSOC);
  if (is_array($row) && isset($row['id'])) { return (int)$row['id']; }

  $ins = $db->prepare("INSERT INTO virtual_machines(name,ip,created_at,updated_at) VALUES(:name,:ip,datetime('now','localtime'),datetime('now','localtime'))");
  $ins->bindValue(':name', $name, PDO::PARAM_STR);
  $ins->bindValue(':ip', $ip, PDO::PARAM_STR);
  $ins->execute();
  return (int)$db->lastInsertId();
}

function migrateLegacyVmLinksSqlite3(SQLite3 $db): void {
  $res = $db->query("SELECT id, vm_id, vm_homolog_id, vm, ip, vm_homolog, ip_homolog FROM systems");
  while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $currentVmId = (int)($row['vm_id'] ?? 0);
    $currentVmHomologId = (int)($row['vm_homolog_id'] ?? 0);

    $vmId = $currentVmId > 0 ? $currentVmId : findOrCreateVmIdSqlite3($db, (string)($row['vm'] ?? ''), (string)($row['ip'] ?? ''));
    $vmHomologId = $currentVmHomologId > 0 ? $currentVmHomologId : findOrCreateVmIdSqlite3($db, (string)($row['vm_homolog'] ?? ''), (string)($row['ip_homolog'] ?? ''));

    if (($vmId ?? 0) === $currentVmId && ($vmHomologId ?? 0) === $currentVmHomologId) { continue; }

    $up = $db->prepare("UPDATE systems SET vm_id=:vm_id, vm_homolog_id=:vm_homolog_id WHERE id=:id");
    if ($vmId === null) { $up->bindValue(':vm_id', null, SQLITE3_NULL); }
    else { $up->bindValue(':vm_id', $vmId, SQLITE3_INTEGER); }
    if ($vmHomologId === null) { $up->bindValue(':vm_homolog_id', null, SQLITE3_NULL); }
    else { $up->bindValue(':vm_homolog_id', $vmHomologId, SQLITE3_INTEGER); }
    $up->bindValue(':id', (int)$row['id'], SQLITE3_INTEGER);
    $up->execute();
  }
}

function migrateLegacyVmLinksPdo(PDO $db): void {
  $rows = $db->query("SELECT id, vm_id, vm_homolog_id, vm, ip, vm_homolog, ip_homolog FROM systems")->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as $row) {
    $currentVmId = (int)($row['vm_id'] ?? 0);
    $currentVmHomologId = (int)($row['vm_homolog_id'] ?? 0);

    $vmId = $currentVmId > 0 ? $currentVmId : findOrCreateVmIdPdo($db, (string)($row['vm'] ?? ''), (string)($row['ip'] ?? ''));
    $vmHomologId = $currentVmHomologId > 0 ? $currentVmHomologId : findOrCreateVmIdPdo($db, (string)($row['vm_homolog'] ?? ''), (string)($row['ip_homolog'] ?? ''));

    if (($vmId ?? 0) === $currentVmId && ($vmHomologId ?? 0) === $currentVmHomologId) { continue; }

    $up = $db->prepare("UPDATE systems SET vm_id=:vm_id, vm_homolog_id=:vm_homolog_id WHERE id=:id");
    if ($vmId === null) { $up->bindValue(':vm_id', null, PDO::PARAM_NULL); }
    else { $up->bindValue(':vm_id', $vmId, PDO::PARAM_INT); }
    if ($vmHomologId === null) { $up->bindValue(':vm_homolog_id', null, PDO::PARAM_NULL); }
    else { $up->bindValue(':vm_homolog_id', $vmHomologId, PDO::PARAM_INT); }
    $up->bindValue(':id', (int)$row['id'], PDO::PARAM_INT);
    $up->execute();
  }
}

function db() {
  if (!is_writable(dirname(DB_PATH)) && !file_exists(DB_PATH)) {
    throw new RuntimeException('DB directory without write permission: ' . dirname(DB_PATH));
  }

  if (class_exists('SQLite3')) {
    $db = new SQLite3(DB_PATH);
    $db->enableExceptions(true);
    $db->exec("CREATE TABLE IF NOT EXISTS systems (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      system_name TEXT DEFAULT '',
      ip TEXT DEFAULT '',
      ip_homolog TEXT DEFAULT '',
      vm TEXT DEFAULT '',
      url_homolog TEXT DEFAULT '',
      vm_homolog TEXT DEFAULT '',
      vm_id INTEGER DEFAULT NULL,
      vm_homolog_id INTEGER DEFAULT NULL,
      category TEXT DEFAULT 'Outro',
      status TEXT DEFAULT 'Ativo',
      tech TEXT DEFAULT '',
      url TEXT DEFAULT '',
      description TEXT DEFAULT '',
      owner TEXT DEFAULT '',
      criticality TEXT DEFAULT 'Media',
      version TEXT DEFAULT '',
      notes TEXT DEFAULT '',
      created_at TEXT DEFAULT (datetime('now','localtime')),
      updated_at TEXT DEFAULT (datetime('now','localtime'))
    )");
    ensureSystemColumnsSqlite3($db);
    ensureVmTableSqlite3($db);
    migrateLegacyVmLinksSqlite3($db);
    $count = (int)$db->querySingle('SELECT COUNT(*) FROM systems');
    if ($count === 0) {
      $db->exec("INSERT INTO systems(name,category,status,tech,description,owner,criticality,version) VALUES
        ('GeoNetwork','GIS','Ativo','GeoNetwork,Java','Catalogo de metadados geoespaciais','TI/Geo','Alta','4.2.x'),
        ('OJS','Publicacao','Ativo','OJS,PHP','Sistema de gestao de periodicos cientificos','Editorial','Alta','3.3.x'),
        ('ODK Central','Coleta de Dados','Ativo','ODK Central,Docker','Plataforma de coleta de dados','Campo','Media','v2023.x')");
    }
    return $db;
  }

  if (extension_loaded('pdo_sqlite')) {
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("CREATE TABLE IF NOT EXISTS systems (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      system_name TEXT DEFAULT '',
      ip TEXT DEFAULT '',
      ip_homolog TEXT DEFAULT '',
      vm TEXT DEFAULT '',
      url_homolog TEXT DEFAULT '',
      vm_homolog TEXT DEFAULT '',
      vm_id INTEGER DEFAULT NULL,
      vm_homolog_id INTEGER DEFAULT NULL,
      category TEXT DEFAULT 'Outro',
      status TEXT DEFAULT 'Ativo',
      tech TEXT DEFAULT '',
      url TEXT DEFAULT '',
      description TEXT DEFAULT '',
      owner TEXT DEFAULT '',
      criticality TEXT DEFAULT 'Media',
      version TEXT DEFAULT '',
      notes TEXT DEFAULT '',
      created_at TEXT DEFAULT (datetime('now','localtime')),
      updated_at TEXT DEFAULT (datetime('now','localtime'))
    )");
    ensureSystemColumnsPdo($db);
    ensureVmTablePdo($db);
    migrateLegacyVmLinksPdo($db);
    $count = (int)$db->query("SELECT COUNT(*) FROM systems")->fetchColumn();
    if ($count === 0) {
      $db->exec("INSERT INTO systems(name,category,status,tech,description,owner,criticality,version) VALUES
        ('GeoNetwork','GIS','Ativo','GeoNetwork,Java','Catalogo de metadados geoespaciais','TI/Geo','Alta','4.2.x'),
        ('OJS','Publicacao','Ativo','OJS,PHP','Sistema de gestao de periodicos cientificos','Editorial','Alta','3.3.x'),
        ('ODK Central','Coleta de Dados','Ativo','ODK Central,Docker','Plataforma de coleta de dados','Campo','Media','v2023.x')");
    }
    return $db;
  }

  throw new RuntimeException('SQLite indisponivel neste PHP. Habilite sqlite3 ou pdo_sqlite no php.ini.');
}

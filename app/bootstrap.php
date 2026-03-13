<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'constants.php';

$projectRoot = __DIR__ . DIRECTORY_SEPARATOR . '..';
$dbFileName = 'bd_sei_catalogosistema.db';
$legacyDbFileName = 'sysportfolio.db';
$legacyDb = $projectRoot . DIRECTORY_SEPARATOR . $legacyDbFileName;
$dir = $projectRoot . DIRECTORY_SEPARATOR . 'data';
if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
if (!is_dir($dir)) {
  $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bd_sei_catalogosistema';
  if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
}

$targetDb = $dir . DIRECTORY_SEPARATOR . $dbFileName;
$legacyCandidates = [
  $legacyDb,
  $dir . DIRECTORY_SEPARATOR . $legacyDbFileName,
];
foreach ($legacyCandidates as $legacyCandidate) {
  if (!file_exists($legacyCandidate)) { continue; }
  $sameFile = realpath($legacyCandidate) === realpath($targetDb);
  if ($sameFile) { continue; }
  $needsMigration = !file_exists($targetDb) || @filemtime($legacyCandidate) > @filemtime($targetDb);
  if ($needsMigration) { @copy($legacyCandidate, $targetDb); }
}

define('DB_PATH', $targetDb);

function ensureSystemColumnsSqlite3(SQLite3 $db): void {
  $required = [
    'system_name' => "TEXT DEFAULT ''",
    'system_group' => "TEXT DEFAULT ''",
    'system_access' => "TEXT DEFAULT 'Interno'",
    'ip' => "TEXT DEFAULT ''",
    'ip_homolog' => "TEXT DEFAULT ''",
    'vm' => "TEXT DEFAULT ''",
    'url_homolog' => "TEXT DEFAULT ''",
    'vm_homolog' => "TEXT DEFAULT ''",
    'vm_id' => "INTEGER DEFAULT NULL",
    'vm_homolog_id' => "INTEGER DEFAULT NULL",
    'vm_dev_id' => "INTEGER DEFAULT NULL",
    'archived' => "INTEGER DEFAULT 0",
    'archived_at' => "TEXT DEFAULT NULL",
    'responsible_sector' => "TEXT DEFAULT ''",
    'responsible_coordinator' => "TEXT DEFAULT ''",
    'extension_number' => "TEXT DEFAULT ''",
    'email' => "TEXT DEFAULT ''",
    'support' => "TEXT DEFAULT ''",
    'support_contact' => "TEXT DEFAULT ''",
    'analytics' => "TEXT DEFAULT ''",
    'ssl' => "TEXT DEFAULT ''",
    'waf' => "TEXT DEFAULT ''",
    'bundle' => "TEXT DEFAULT ''",
    'directory' => "TEXT DEFAULT ''",
    'size' => "TEXT DEFAULT ''",
    'repository' => "TEXT DEFAULT ''",
    'target_version' => "TEXT DEFAULT ''",
    'app_server' => "TEXT DEFAULT ''",
    'web_server' => "TEXT DEFAULT ''",
    'containerization' => "INTEGER DEFAULT 0",
    'container_tool' => "TEXT DEFAULT ''",
    'runtime_port' => "TEXT DEFAULT ''",
    'php_required_extensions' => "TEXT DEFAULT ''",
    'php_recommended_extensions' => "TEXT DEFAULT ''",
    'php_required_libraries' => "TEXT DEFAULT ''",
    'php_required_ini' => "TEXT DEFAULT ''",
    'r_required_packages' => "TEXT DEFAULT ''",
    'doc_installation_ref' => "TEXT DEFAULT ''",
    'doc_installation_updated_at' => "TEXT DEFAULT NULL",
    'doc_maintenance_ref' => "TEXT DEFAULT ''",
    'doc_maintenance_updated_at' => "TEXT DEFAULT NULL",
    'doc_security_ref' => "TEXT DEFAULT ''",
    'doc_security_updated_at' => "TEXT DEFAULT NULL",
    'doc_manual_ref' => "TEXT DEFAULT ''",
    'doc_manual_updated_at' => "TEXT DEFAULT NULL",
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
    'system_group' => "TEXT DEFAULT ''",
    'system_access' => "TEXT DEFAULT 'Interno'",
    'ip' => "TEXT DEFAULT ''",
    'ip_homolog' => "TEXT DEFAULT ''",
    'vm' => "TEXT DEFAULT ''",
    'url_homolog' => "TEXT DEFAULT ''",
    'vm_homolog' => "TEXT DEFAULT ''",
    'vm_id' => "INTEGER DEFAULT NULL",
    'vm_homolog_id' => "INTEGER DEFAULT NULL",
    'vm_dev_id' => "INTEGER DEFAULT NULL",
    'archived' => "INTEGER DEFAULT 0",
    'archived_at' => "TEXT DEFAULT NULL",
    'responsible_sector' => "TEXT DEFAULT ''",
    'responsible_coordinator' => "TEXT DEFAULT ''",
    'extension_number' => "TEXT DEFAULT ''",
    'email' => "TEXT DEFAULT ''",
    'support' => "TEXT DEFAULT ''",
    'support_contact' => "TEXT DEFAULT ''",
    'analytics' => "TEXT DEFAULT ''",
    'ssl' => "TEXT DEFAULT ''",
    'waf' => "TEXT DEFAULT ''",
    'bundle' => "TEXT DEFAULT ''",
    'directory' => "TEXT DEFAULT ''",
    'size' => "TEXT DEFAULT ''",
    'repository' => "TEXT DEFAULT ''",
    'target_version' => "TEXT DEFAULT ''",
    'app_server' => "TEXT DEFAULT ''",
    'web_server' => "TEXT DEFAULT ''",
    'containerization' => "INTEGER DEFAULT 0",
    'container_tool' => "TEXT DEFAULT ''",
    'runtime_port' => "TEXT DEFAULT ''",
    'php_required_extensions' => "TEXT DEFAULT ''",
    'php_recommended_extensions' => "TEXT DEFAULT ''",
    'php_required_libraries' => "TEXT DEFAULT ''",
    'php_required_ini' => "TEXT DEFAULT ''",
    'r_required_packages' => "TEXT DEFAULT ''",
    'doc_installation_ref' => "TEXT DEFAULT ''",
    'doc_installation_updated_at' => "TEXT DEFAULT NULL",
    'doc_maintenance_ref' => "TEXT DEFAULT ''",
    'doc_maintenance_updated_at' => "TEXT DEFAULT NULL",
    'doc_security_ref' => "TEXT DEFAULT ''",
    'doc_security_updated_at' => "TEXT DEFAULT NULL",
    'doc_manual_ref' => "TEXT DEFAULT ''",
    'doc_manual_updated_at' => "TEXT DEFAULT NULL",
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

function normalizeLegacyStatusValuesSqlite3(SQLite3 $db): void {
  $db->exec("UPDATE systems SET status='Manutenção' WHERE status IN ('ManutenÃƒÂ§ÃƒÂ£o','ManutenÃ§Ã£o')");
  $db->exec("UPDATE systems SET status='Implantação' WHERE status IN ('ImplantaÃƒÂ§ÃƒÂ£o','ImplantaÃ§Ã£o')");
}

function normalizeLegacyStatusValuesPdo(PDO $db): void {
  $db->exec("UPDATE systems SET status='Manutenção' WHERE status IN ('ManutenÃƒÂ§ÃƒÂ£o','ManutenÃ§Ã£o')");
  $db->exec("UPDATE systems SET status='Implantação' WHERE status IN ('ImplantaÃƒÂ§ÃƒÂ£o','ImplantaÃ§Ã£o')");
}

function ensureVmTableSqlite3(SQLite3 $db): void {
  $db->exec("CREATE TABLE IF NOT EXISTS virtual_machines (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    ip TEXT DEFAULT '',
    public_ip TEXT DEFAULT '',
    vm_category TEXT DEFAULT 'Producao',
    vm_type TEXT DEFAULT 'Sistemas',
    vm_access TEXT DEFAULT 'Interno',
    vm_administration TEXT DEFAULT 'SEI',
    vm_instances TEXT DEFAULT '',
    vm_language TEXT DEFAULT '',
    vm_target_version TEXT DEFAULT '',
    vm_app_server TEXT DEFAULT '',
    vm_web_server TEXT DEFAULT '',
    vm_containerization INTEGER DEFAULT 0,
    vm_container_tool TEXT DEFAULT '',
    vm_runtime_port TEXT DEFAULT '',
    vm_tech TEXT DEFAULT '',
    diagnostic_json_ref TEXT DEFAULT '',
    diagnostic_json_updated_at TEXT DEFAULT NULL,
    diagnostic_json_ref_r TEXT DEFAULT '',
    diagnostic_json_updated_at_r TEXT DEFAULT NULL,
    os_name TEXT DEFAULT '',
    os_version TEXT DEFAULT '',
    vcpus TEXT DEFAULT '',
    ram TEXT DEFAULT '',
    disk TEXT DEFAULT '',
    archived INTEGER DEFAULT 0,
    archived_at TEXT DEFAULT NULL,
    created_at TEXT DEFAULT (datetime('now','localtime')),
    updated_at TEXT DEFAULT (datetime('now','localtime'))
  )");
  $res = $db->query('PRAGMA table_info(virtual_machines)');
  $existing = [];
  while ($row = $res->fetchArray(SQLITE3_ASSOC)) { $existing[] = (string)($row['name'] ?? ''); }
  if (!in_array('vm_category', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN vm_category TEXT DEFAULT 'Producao'"); }
  if (!in_array('public_ip', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN public_ip TEXT DEFAULT ''"); }
  if (!in_array('vm_type', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN vm_type TEXT DEFAULT 'Sistemas'"); }
  if (!in_array('vm_access', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN vm_access TEXT DEFAULT 'Interno'"); }
  if (!in_array('vm_administration', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN vm_administration TEXT DEFAULT 'SEI'"); }
  if (!in_array('vm_instances', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN vm_instances TEXT DEFAULT ''"); }
  if (!in_array('vm_language', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN vm_language TEXT DEFAULT ''"); }
  if (!in_array('vm_target_version', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN vm_target_version TEXT DEFAULT ''"); }
  if (!in_array('vm_app_server', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN vm_app_server TEXT DEFAULT ''"); }
  if (!in_array('vm_web_server', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN vm_web_server TEXT DEFAULT ''"); }
  if (!in_array('vm_containerization', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN vm_containerization INTEGER DEFAULT 0"); }
  if (!in_array('vm_container_tool', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN vm_container_tool TEXT DEFAULT ''"); }
  if (!in_array('vm_runtime_port', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN vm_runtime_port TEXT DEFAULT ''"); }
  if (!in_array('vm_tech', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN vm_tech TEXT DEFAULT ''"); }
  if (!in_array('diagnostic_json_ref', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN diagnostic_json_ref TEXT DEFAULT ''"); }
  if (!in_array('diagnostic_json_updated_at', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN diagnostic_json_updated_at TEXT DEFAULT NULL"); }
  if (!in_array('diagnostic_json_ref_r', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN diagnostic_json_ref_r TEXT DEFAULT ''"); }
  if (!in_array('diagnostic_json_updated_at_r', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN diagnostic_json_updated_at_r TEXT DEFAULT NULL"); }
  if (!in_array('os_name', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN os_name TEXT DEFAULT ''"); }
  if (!in_array('os_version', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN os_version TEXT DEFAULT ''"); }
  if (!in_array('vcpus', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN vcpus TEXT DEFAULT ''"); }
  if (!in_array('ram', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN ram TEXT DEFAULT ''"); }
  if (!in_array('disk', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN disk TEXT DEFAULT ''"); }
  if (!in_array('archived', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN archived INTEGER DEFAULT 0"); }
  if (!in_array('archived_at', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN archived_at TEXT DEFAULT NULL"); }
}

function ensureVmTablePdo(PDO $db): void {
  $db->exec("CREATE TABLE IF NOT EXISTS virtual_machines (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    ip TEXT DEFAULT '',
    public_ip TEXT DEFAULT '',
    vm_category TEXT DEFAULT 'Producao',
    vm_type TEXT DEFAULT 'Sistemas',
    vm_access TEXT DEFAULT 'Interno',
    vm_administration TEXT DEFAULT 'SEI',
    vm_instances TEXT DEFAULT '',
    vm_language TEXT DEFAULT '',
    vm_target_version TEXT DEFAULT '',
    vm_app_server TEXT DEFAULT '',
    vm_web_server TEXT DEFAULT '',
    vm_containerization INTEGER DEFAULT 0,
    vm_container_tool TEXT DEFAULT '',
    vm_runtime_port TEXT DEFAULT '',
    vm_tech TEXT DEFAULT '',
    diagnostic_json_ref TEXT DEFAULT '',
    diagnostic_json_updated_at TEXT DEFAULT NULL,
    diagnostic_json_ref_r TEXT DEFAULT '',
    diagnostic_json_updated_at_r TEXT DEFAULT NULL,
    os_name TEXT DEFAULT '',
    os_version TEXT DEFAULT '',
    vcpus TEXT DEFAULT '',
    ram TEXT DEFAULT '',
    disk TEXT DEFAULT '',
    archived INTEGER DEFAULT 0,
    archived_at TEXT DEFAULT NULL,
    created_at TEXT DEFAULT (datetime('now','localtime')),
    updated_at TEXT DEFAULT (datetime('now','localtime'))
  )");
  $rows = $db->query('PRAGMA table_info(virtual_machines)')->fetchAll(PDO::FETCH_ASSOC);
  $existing = [];
  foreach ($rows as $row) { $existing[] = (string)($row['name'] ?? ''); }
  if (!in_array('vm_category', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN vm_category TEXT DEFAULT 'Producao'"); }
  if (!in_array('public_ip', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN public_ip TEXT DEFAULT ''"); }
  if (!in_array('vm_type', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN vm_type TEXT DEFAULT 'Sistemas'"); }
  if (!in_array('vm_access', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN vm_access TEXT DEFAULT 'Interno'"); }
  if (!in_array('vm_administration', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN vm_administration TEXT DEFAULT 'SEI'"); }
  if (!in_array('vm_instances', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN vm_instances TEXT DEFAULT ''"); }
  if (!in_array('vm_language', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN vm_language TEXT DEFAULT ''"); }
  if (!in_array('vm_target_version', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN vm_target_version TEXT DEFAULT ''"); }
  if (!in_array('vm_app_server', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN vm_app_server TEXT DEFAULT ''"); }
  if (!in_array('vm_web_server', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN vm_web_server TEXT DEFAULT ''"); }
  if (!in_array('vm_containerization', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN vm_containerization INTEGER DEFAULT 0"); }
  if (!in_array('vm_container_tool', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN vm_container_tool TEXT DEFAULT ''"); }
  if (!in_array('vm_runtime_port', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN vm_runtime_port TEXT DEFAULT ''"); }
  if (!in_array('vm_tech', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN vm_tech TEXT DEFAULT ''"); }
  if (!in_array('diagnostic_json_ref', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN diagnostic_json_ref TEXT DEFAULT ''"); }
  if (!in_array('diagnostic_json_updated_at', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN diagnostic_json_updated_at TEXT DEFAULT NULL"); }
  if (!in_array('diagnostic_json_ref_r', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN diagnostic_json_ref_r TEXT DEFAULT ''"); }
  if (!in_array('diagnostic_json_updated_at_r', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN diagnostic_json_updated_at_r TEXT DEFAULT NULL"); }
  if (!in_array('os_name', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN os_name TEXT DEFAULT ''"); }
  if (!in_array('os_version', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN os_version TEXT DEFAULT ''"); }
  if (!in_array('vcpus', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN vcpus TEXT DEFAULT ''"); }
  if (!in_array('ram', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN ram TEXT DEFAULT ''"); }
  if (!in_array('disk', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN disk TEXT DEFAULT ''"); }
  if (!in_array('archived', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN archived INTEGER DEFAULT 0"); }
  if (!in_array('archived_at', $existing, true)) { $db->exec("ALTER TABLE virtual_machines ADD COLUMN archived_at TEXT DEFAULT NULL"); }
}

function ensureDatabaseTableSqlite3(SQLite3 $db): void {
  $db->exec("CREATE TABLE IF NOT EXISTS system_databases (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    system_id INTEGER NOT NULL,
    vm_id INTEGER DEFAULT NULL,
    vm_homolog_id INTEGER DEFAULT NULL,
    db_name TEXT NOT NULL,
    db_user TEXT DEFAULT '',
    db_engine TEXT NOT NULL,
    db_engine_version TEXT DEFAULT '',
    db_engine_version_homolog TEXT DEFAULT '',
    db_instance_name TEXT DEFAULT '',
    db_instance_ip TEXT DEFAULT '',
    db_instance_port TEXT DEFAULT '',
    db_instance_homolog_name TEXT DEFAULT '',
    db_instance_homolog_ip TEXT DEFAULT '',
    db_instance_homolog_port TEXT DEFAULT '',
    notes TEXT DEFAULT '',
    archived INTEGER DEFAULT 0,
    archived_at TEXT DEFAULT NULL,
    created_at TEXT DEFAULT (datetime('now','localtime')),
    updated_at TEXT DEFAULT (datetime('now','localtime'))
  )");

  $res = $db->query('PRAGMA table_info(system_databases)');
  $existing = [];
  while ($row = $res->fetchArray(SQLITE3_ASSOC)) { $existing[] = (string)($row['name'] ?? ''); }

  if (!in_array('vm_homolog_id', $existing, true)) { $db->exec("ALTER TABLE system_databases ADD COLUMN vm_homolog_id INTEGER DEFAULT NULL"); }
  if (!in_array('db_user', $existing, true)) { $db->exec("ALTER TABLE system_databases ADD COLUMN db_user TEXT DEFAULT ''"); }
  if (!in_array('db_engine_version', $existing, true)) { $db->exec("ALTER TABLE system_databases ADD COLUMN db_engine_version TEXT DEFAULT ''"); }
  if (!in_array('db_engine_version_homolog', $existing, true)) { $db->exec("ALTER TABLE system_databases ADD COLUMN db_engine_version_homolog TEXT DEFAULT ''"); }
  if (!in_array('db_instance_name', $existing, true)) { $db->exec("ALTER TABLE system_databases ADD COLUMN db_instance_name TEXT DEFAULT ''"); }
  if (!in_array('db_instance_ip', $existing, true)) { $db->exec("ALTER TABLE system_databases ADD COLUMN db_instance_ip TEXT DEFAULT ''"); }
  if (!in_array('db_instance_port', $existing, true)) { $db->exec("ALTER TABLE system_databases ADD COLUMN db_instance_port TEXT DEFAULT ''"); }
  if (!in_array('db_instance_homolog_name', $existing, true)) { $db->exec("ALTER TABLE system_databases ADD COLUMN db_instance_homolog_name TEXT DEFAULT ''"); }
  if (!in_array('db_instance_homolog_ip', $existing, true)) { $db->exec("ALTER TABLE system_databases ADD COLUMN db_instance_homolog_ip TEXT DEFAULT ''"); }
  if (!in_array('db_instance_homolog_port', $existing, true)) { $db->exec("ALTER TABLE system_databases ADD COLUMN db_instance_homolog_port TEXT DEFAULT ''"); }
  if (!in_array('notes', $existing, true)) { $db->exec("ALTER TABLE system_databases ADD COLUMN notes TEXT DEFAULT ''"); }
  if (!in_array('archived', $existing, true)) { $db->exec("ALTER TABLE system_databases ADD COLUMN archived INTEGER DEFAULT 0"); }
  if (!in_array('archived_at', $existing, true)) { $db->exec("ALTER TABLE system_databases ADD COLUMN archived_at TEXT DEFAULT NULL"); }
  if (!in_array('updated_at', $existing, true)) { $db->exec("ALTER TABLE system_databases ADD COLUMN updated_at TEXT DEFAULT (datetime('now','localtime'))"); }

  $db->exec("CREATE INDEX IF NOT EXISTS idx_system_databases_system_id ON system_databases(system_id)");
  $db->exec("CREATE INDEX IF NOT EXISTS idx_system_databases_vm_id ON system_databases(vm_id)");
  $db->exec("CREATE INDEX IF NOT EXISTS idx_system_databases_vm_homolog_id ON system_databases(vm_homolog_id)");
}

function ensureDatabaseTablePdo(PDO $db): void {
  $db->exec("CREATE TABLE IF NOT EXISTS system_databases (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    system_id INTEGER NOT NULL,
    vm_id INTEGER DEFAULT NULL,
    vm_homolog_id INTEGER DEFAULT NULL,
    db_name TEXT NOT NULL,
    db_user TEXT DEFAULT '',
    db_engine TEXT NOT NULL,
    db_engine_version TEXT DEFAULT '',
    db_engine_version_homolog TEXT DEFAULT '',
    db_instance_name TEXT DEFAULT '',
    db_instance_ip TEXT DEFAULT '',
    db_instance_port TEXT DEFAULT '',
    db_instance_homolog_name TEXT DEFAULT '',
    db_instance_homolog_ip TEXT DEFAULT '',
    db_instance_homolog_port TEXT DEFAULT '',
    notes TEXT DEFAULT '',
    archived INTEGER DEFAULT 0,
    archived_at TEXT DEFAULT NULL,
    created_at TEXT DEFAULT (datetime('now','localtime')),
    updated_at TEXT DEFAULT (datetime('now','localtime'))
  )");

  $rows = $db->query('PRAGMA table_info(system_databases)')->fetchAll(PDO::FETCH_ASSOC);
  $existing = [];
  foreach ($rows as $row) { $existing[] = (string)($row['name'] ?? ''); }

  if (!in_array('vm_homolog_id', $existing, true)) { $db->exec("ALTER TABLE system_databases ADD COLUMN vm_homolog_id INTEGER DEFAULT NULL"); }
  if (!in_array('db_user', $existing, true)) { $db->exec("ALTER TABLE system_databases ADD COLUMN db_user TEXT DEFAULT ''"); }
  if (!in_array('db_engine_version', $existing, true)) { $db->exec("ALTER TABLE system_databases ADD COLUMN db_engine_version TEXT DEFAULT ''"); }
  if (!in_array('db_engine_version_homolog', $existing, true)) { $db->exec("ALTER TABLE system_databases ADD COLUMN db_engine_version_homolog TEXT DEFAULT ''"); }
  if (!in_array('db_instance_name', $existing, true)) { $db->exec("ALTER TABLE system_databases ADD COLUMN db_instance_name TEXT DEFAULT ''"); }
  if (!in_array('db_instance_ip', $existing, true)) { $db->exec("ALTER TABLE system_databases ADD COLUMN db_instance_ip TEXT DEFAULT ''"); }
  if (!in_array('db_instance_port', $existing, true)) { $db->exec("ALTER TABLE system_databases ADD COLUMN db_instance_port TEXT DEFAULT ''"); }
  if (!in_array('db_instance_homolog_name', $existing, true)) { $db->exec("ALTER TABLE system_databases ADD COLUMN db_instance_homolog_name TEXT DEFAULT ''"); }
  if (!in_array('db_instance_homolog_ip', $existing, true)) { $db->exec("ALTER TABLE system_databases ADD COLUMN db_instance_homolog_ip TEXT DEFAULT ''"); }
  if (!in_array('db_instance_homolog_port', $existing, true)) { $db->exec("ALTER TABLE system_databases ADD COLUMN db_instance_homolog_port TEXT DEFAULT ''"); }
  if (!in_array('notes', $existing, true)) { $db->exec("ALTER TABLE system_databases ADD COLUMN notes TEXT DEFAULT ''"); }
  if (!in_array('archived', $existing, true)) { $db->exec("ALTER TABLE system_databases ADD COLUMN archived INTEGER DEFAULT 0"); }
  if (!in_array('archived_at', $existing, true)) { $db->exec("ALTER TABLE system_databases ADD COLUMN archived_at TEXT DEFAULT NULL"); }
  if (!in_array('updated_at', $existing, true)) { $db->exec("ALTER TABLE system_databases ADD COLUMN updated_at TEXT DEFAULT (datetime('now','localtime'))"); }

  $db->exec("CREATE INDEX IF NOT EXISTS idx_system_databases_system_id ON system_databases(system_id)");
  $db->exec("CREATE INDEX IF NOT EXISTS idx_system_databases_vm_id ON system_databases(vm_id)");
  $db->exec("CREATE INDEX IF NOT EXISTS idx_system_databases_vm_homolog_id ON system_databases(vm_homolog_id)");
}

function ensureTicketsTableSqlite3(SQLite3 $db): void {
  $db->exec("CREATE TABLE IF NOT EXISTS tickets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    target_type TEXT NOT NULL DEFAULT 'system',
    system_id INTEGER DEFAULT NULL,
    vm_id INTEGER DEFAULT NULL,
    ticket_number TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    created_at TEXT DEFAULT (datetime('now','localtime')),
    updated_at TEXT DEFAULT (datetime('now','localtime')),
    FOREIGN KEY(system_id) REFERENCES systems(id) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY(vm_id) REFERENCES virtual_machines(id) ON UPDATE CASCADE ON DELETE CASCADE
  )");

  $res = $db->query('PRAGMA table_info(tickets)');
  $existing = [];
  while ($row = $res->fetchArray(SQLITE3_ASSOC)) { $existing[] = (string)($row['name'] ?? ''); }
  if (!in_array('target_type', $existing, true)) { $db->exec("ALTER TABLE tickets ADD COLUMN target_type TEXT NOT NULL DEFAULT 'system'"); }
  if (!in_array('system_id', $existing, true)) { $db->exec("ALTER TABLE tickets ADD COLUMN system_id INTEGER DEFAULT NULL"); }
  if (!in_array('vm_id', $existing, true)) { $db->exec("ALTER TABLE tickets ADD COLUMN vm_id INTEGER DEFAULT NULL"); }
  if (!in_array('ticket_number', $existing, true)) { $db->exec("ALTER TABLE tickets ADD COLUMN ticket_number TEXT NOT NULL DEFAULT ''"); }
  if (!in_array('description', $existing, true)) { $db->exec("ALTER TABLE tickets ADD COLUMN description TEXT NOT NULL DEFAULT ''"); }
  if (!in_array('created_at', $existing, true)) { $db->exec("ALTER TABLE tickets ADD COLUMN created_at TEXT DEFAULT (datetime('now','localtime'))"); }
  if (!in_array('updated_at', $existing, true)) { $db->exec("ALTER TABLE tickets ADD COLUMN updated_at TEXT DEFAULT (datetime('now','localtime'))"); }

  $db->exec("CREATE INDEX IF NOT EXISTS idx_tickets_target_type ON tickets(target_type)");
  $db->exec("CREATE INDEX IF NOT EXISTS idx_tickets_system_id ON tickets(system_id)");
  $db->exec("CREATE INDEX IF NOT EXISTS idx_tickets_vm_id ON tickets(vm_id)");
  $db->exec("CREATE INDEX IF NOT EXISTS idx_tickets_created_at ON tickets(created_at)");
}

function ensureTicketsTablePdo(PDO $db): void {
  $db->exec("CREATE TABLE IF NOT EXISTS tickets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    target_type TEXT NOT NULL DEFAULT 'system',
    system_id INTEGER DEFAULT NULL,
    vm_id INTEGER DEFAULT NULL,
    ticket_number TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    created_at TEXT DEFAULT (datetime('now','localtime')),
    updated_at TEXT DEFAULT (datetime('now','localtime')),
    FOREIGN KEY(system_id) REFERENCES systems(id) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY(vm_id) REFERENCES virtual_machines(id) ON UPDATE CASCADE ON DELETE CASCADE
  )");

  $rows = $db->query('PRAGMA table_info(tickets)')->fetchAll(PDO::FETCH_ASSOC);
  $existing = [];
  foreach ($rows as $row) { $existing[] = (string)($row['name'] ?? ''); }
  if (!in_array('target_type', $existing, true)) { $db->exec("ALTER TABLE tickets ADD COLUMN target_type TEXT NOT NULL DEFAULT 'system'"); }
  if (!in_array('system_id', $existing, true)) { $db->exec("ALTER TABLE tickets ADD COLUMN system_id INTEGER DEFAULT NULL"); }
  if (!in_array('vm_id', $existing, true)) { $db->exec("ALTER TABLE tickets ADD COLUMN vm_id INTEGER DEFAULT NULL"); }
  if (!in_array('ticket_number', $existing, true)) { $db->exec("ALTER TABLE tickets ADD COLUMN ticket_number TEXT NOT NULL DEFAULT ''"); }
  if (!in_array('description', $existing, true)) { $db->exec("ALTER TABLE tickets ADD COLUMN description TEXT NOT NULL DEFAULT ''"); }
  if (!in_array('created_at', $existing, true)) { $db->exec("ALTER TABLE tickets ADD COLUMN created_at TEXT DEFAULT (datetime('now','localtime'))"); }
  if (!in_array('updated_at', $existing, true)) { $db->exec("ALTER TABLE tickets ADD COLUMN updated_at TEXT DEFAULT (datetime('now','localtime'))"); }

  $db->exec("CREATE INDEX IF NOT EXISTS idx_tickets_target_type ON tickets(target_type)");
  $db->exec("CREATE INDEX IF NOT EXISTS idx_tickets_system_id ON tickets(system_id)");
  $db->exec("CREATE INDEX IF NOT EXISTS idx_tickets_vm_id ON tickets(vm_id)");
  $db->exec("CREATE INDEX IF NOT EXISTS idx_tickets_created_at ON tickets(created_at)");
}

function expectedSystemsForeignKeys(): array {
  return [
    'vm_id' => [
      'table' => 'virtual_machines',
      'to' => 'id',
      'on_update' => 'CASCADE',
      'on_delete' => 'SET NULL',
    ],
    'vm_homolog_id' => [
      'table' => 'virtual_machines',
      'to' => 'id',
      'on_update' => 'CASCADE',
      'on_delete' => 'SET NULL',
    ],
    'vm_dev_id' => [
      'table' => 'virtual_machines',
      'to' => 'id',
      'on_update' => 'CASCADE',
      'on_delete' => 'SET NULL',
    ],
  ];
}

function expectedSystemDatabasesForeignKeys(): array {
  return [
    'system_id' => [
      'table' => 'systems',
      'to' => 'id',
      'on_update' => 'CASCADE',
      'on_delete' => 'CASCADE',
    ],
    'vm_id' => [
      'table' => 'virtual_machines',
      'to' => 'id',
      'on_update' => 'CASCADE',
      'on_delete' => 'SET NULL',
    ],
    'vm_homolog_id' => [
      'table' => 'virtual_machines',
      'to' => 'id',
      'on_update' => 'CASCADE',
      'on_delete' => 'SET NULL',
    ],
  ];
}

function hasExpectedForeignKeys(array $actual, array $expected): bool {
  foreach ($expected as $from => $spec) {
    if (!isset($actual[$from])) { return false; }
    $row = $actual[$from];
    if (($row['table'] ?? '') !== strtolower((string)$spec['table'])) { return false; }
    if (($row['to'] ?? '') !== strtolower((string)$spec['to'])) { return false; }
    if (strtoupper((string)($row['on_update'] ?? '')) !== strtoupper((string)$spec['on_update'])) { return false; }
    if (strtoupper((string)($row['on_delete'] ?? '')) !== strtoupper((string)$spec['on_delete'])) { return false; }
  }
  return true;
}

function systemsForeignKeyMapSqlite3(SQLite3 $db): array {
  $map = [];
  $res = $db->query('PRAGMA foreign_key_list(systems)');
  while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $from = strtolower(trim((string)($row['from'] ?? '')));
    if ($from === '') { continue; }
    $map[$from] = [
      'table' => strtolower(trim((string)($row['table'] ?? ''))),
      'to' => strtolower(trim((string)($row['to'] ?? ''))),
      'on_update' => strtoupper(trim((string)($row['on_update'] ?? ''))),
      'on_delete' => strtoupper(trim((string)($row['on_delete'] ?? ''))),
    ];
  }
  return $map;
}

function systemDatabasesForeignKeyMapSqlite3(SQLite3 $db): array {
  $map = [];
  $res = $db->query('PRAGMA foreign_key_list(system_databases)');
  while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $from = strtolower(trim((string)($row['from'] ?? '')));
    if ($from === '') { continue; }
    $map[$from] = [
      'table' => strtolower(trim((string)($row['table'] ?? ''))),
      'to' => strtolower(trim((string)($row['to'] ?? ''))),
      'on_update' => strtoupper(trim((string)($row['on_update'] ?? ''))),
      'on_delete' => strtoupper(trim((string)($row['on_delete'] ?? ''))),
    ];
  }
  return $map;
}

function systemsForeignKeyMapPdo(PDO $db): array {
  $map = [];
  $rows = $db->query('PRAGMA foreign_key_list(systems)')->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as $row) {
    $from = strtolower(trim((string)($row['from'] ?? '')));
    if ($from === '') { continue; }
    $map[$from] = [
      'table' => strtolower(trim((string)($row['table'] ?? ''))),
      'to' => strtolower(trim((string)($row['to'] ?? ''))),
      'on_update' => strtoupper(trim((string)($row['on_update'] ?? ''))),
      'on_delete' => strtoupper(trim((string)($row['on_delete'] ?? ''))),
    ];
  }
  return $map;
}

function systemDatabasesForeignKeyMapPdo(PDO $db): array {
  $map = [];
  $rows = $db->query('PRAGMA foreign_key_list(system_databases)')->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as $row) {
    $from = strtolower(trim((string)($row['from'] ?? '')));
    if ($from === '') { continue; }
    $map[$from] = [
      'table' => strtolower(trim((string)($row['table'] ?? ''))),
      'to' => strtolower(trim((string)($row['to'] ?? ''))),
      'on_update' => strtoupper(trim((string)($row['on_update'] ?? ''))),
      'on_delete' => strtoupper(trim((string)($row['on_delete'] ?? ''))),
    ];
  }
  return $map;
}

function systemsColumnsForRelationalMigration(): array {
  return [
    'id','name','system_name','system_group','system_access','ip','ip_homolog','vm','url_homolog','vm_homolog',
    'vm_id','vm_homolog_id','vm_dev_id','archived','archived_at',
    'responsible_sector','responsible_coordinator','extension_number','email','support','support_contact',
    'analytics','ssl','waf','bundle','directory','size','repository',
    'target_version','app_server','web_server','containerization','container_tool','runtime_port',
    'php_required_extensions','php_recommended_extensions','php_required_libraries','php_required_ini','r_required_packages',
    'doc_installation_ref','doc_installation_updated_at','doc_maintenance_ref','doc_maintenance_updated_at',
    'doc_security_ref','doc_security_updated_at','doc_manual_ref','doc_manual_updated_at',
    'category','status','tech','url','description','owner','criticality','version','notes',
    'created_at','updated_at',
  ];
}

function systemDatabasesColumnsForRelationalMigration(): array {
  return [
    'id','system_id','vm_id','vm_homolog_id','db_name','db_user','db_engine',
    'db_engine_version','db_engine_version_homolog',
    'db_instance_name','db_instance_ip','db_instance_port','db_instance_homolog_name','db_instance_homolog_ip','db_instance_homolog_port',
    'notes','archived','archived_at','created_at','updated_at',
  ];
}

function createSystemsTableWithForeignKeysSql(): string {
  return "CREATE TABLE systems (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    system_name TEXT DEFAULT '',
    system_group TEXT DEFAULT '',
    system_access TEXT DEFAULT 'Interno',
    ip TEXT DEFAULT '',
    ip_homolog TEXT DEFAULT '',
    vm TEXT DEFAULT '',
    url_homolog TEXT DEFAULT '',
    vm_homolog TEXT DEFAULT '',
    vm_id INTEGER DEFAULT NULL,
    vm_homolog_id INTEGER DEFAULT NULL,
    vm_dev_id INTEGER DEFAULT NULL,
    archived INTEGER DEFAULT 0,
    archived_at TEXT DEFAULT NULL,
    responsible_sector TEXT DEFAULT '',
    responsible_coordinator TEXT DEFAULT '',
    extension_number TEXT DEFAULT '',
    email TEXT DEFAULT '',
    support TEXT DEFAULT '',
    support_contact TEXT DEFAULT '',
    analytics TEXT DEFAULT '',
    ssl TEXT DEFAULT '',
    waf TEXT DEFAULT '',
    bundle TEXT DEFAULT '',
    directory TEXT DEFAULT '',
    size TEXT DEFAULT '',
    repository TEXT DEFAULT '',
    target_version TEXT DEFAULT '',
    app_server TEXT DEFAULT '',
    web_server TEXT DEFAULT '',
    containerization INTEGER DEFAULT 0,
    container_tool TEXT DEFAULT '',
    runtime_port TEXT DEFAULT '',
    php_required_extensions TEXT DEFAULT '',
    php_recommended_extensions TEXT DEFAULT '',
    php_required_libraries TEXT DEFAULT '',
    php_required_ini TEXT DEFAULT '',
    r_required_packages TEXT DEFAULT '',
    doc_installation_ref TEXT DEFAULT '',
    doc_installation_updated_at TEXT DEFAULT NULL,
    doc_maintenance_ref TEXT DEFAULT '',
    doc_maintenance_updated_at TEXT DEFAULT NULL,
    doc_security_ref TEXT DEFAULT '',
    doc_security_updated_at TEXT DEFAULT NULL,
    doc_manual_ref TEXT DEFAULT '',
    doc_manual_updated_at TEXT DEFAULT NULL,
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
    updated_at TEXT DEFAULT (datetime('now','localtime')),
    FOREIGN KEY(vm_id) REFERENCES virtual_machines(id) ON UPDATE CASCADE ON DELETE SET NULL,
    FOREIGN KEY(vm_homolog_id) REFERENCES virtual_machines(id) ON UPDATE CASCADE ON DELETE SET NULL,
    FOREIGN KEY(vm_dev_id) REFERENCES virtual_machines(id) ON UPDATE CASCADE ON DELETE SET NULL
  )";
}

function createSystemDatabasesTableWithForeignKeysSql(): string {
  return "CREATE TABLE system_databases (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    system_id INTEGER NOT NULL,
    vm_id INTEGER DEFAULT NULL,
    vm_homolog_id INTEGER DEFAULT NULL,
    db_name TEXT NOT NULL,
    db_user TEXT DEFAULT '',
    db_engine TEXT NOT NULL,
    db_engine_version TEXT DEFAULT '',
    db_engine_version_homolog TEXT DEFAULT '',
    db_instance_name TEXT DEFAULT '',
    db_instance_ip TEXT DEFAULT '',
    db_instance_port TEXT DEFAULT '',
    db_instance_homolog_name TEXT DEFAULT '',
    db_instance_homolog_ip TEXT DEFAULT '',
    db_instance_homolog_port TEXT DEFAULT '',
    notes TEXT DEFAULT '',
    archived INTEGER DEFAULT 0,
    archived_at TEXT DEFAULT NULL,
    created_at TEXT DEFAULT (datetime('now','localtime')),
    updated_at TEXT DEFAULT (datetime('now','localtime')),
    FOREIGN KEY(system_id) REFERENCES systems(id) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY(vm_id) REFERENCES virtual_machines(id) ON UPDATE CASCADE ON DELETE SET NULL,
    FOREIGN KEY(vm_homolog_id) REFERENCES virtual_machines(id) ON UPDATE CASCADE ON DELETE SET NULL
  )";
}

function normalizeRelationalDataSqlite3(SQLite3 $db): void {
  $db->exec("UPDATE systems SET vm_id=NULL WHERE vm_id IS NOT NULL AND (vm_id <= 0 OR vm_id NOT IN (SELECT id FROM virtual_machines))");
  $db->exec("UPDATE systems SET vm_homolog_id=NULL WHERE vm_homolog_id IS NOT NULL AND (vm_homolog_id <= 0 OR vm_homolog_id NOT IN (SELECT id FROM virtual_machines))");
  $db->exec("UPDATE systems SET vm_dev_id=NULL WHERE vm_dev_id IS NOT NULL AND (vm_dev_id <= 0 OR vm_dev_id NOT IN (SELECT id FROM virtual_machines))");
  $db->exec("UPDATE system_databases SET vm_id=NULL WHERE vm_id IS NOT NULL AND (vm_id <= 0 OR vm_id NOT IN (SELECT id FROM virtual_machines))");
  $db->exec("UPDATE system_databases SET vm_homolog_id=NULL WHERE vm_homolog_id IS NOT NULL AND (vm_homolog_id <= 0 OR vm_homolog_id NOT IN (SELECT id FROM virtual_machines))");
  $db->exec("DELETE FROM system_databases WHERE system_id IS NULL OR system_id NOT IN (SELECT id FROM systems)");
}

function normalizeRelationalDataPdo(PDO $db): void {
  $db->exec("UPDATE systems SET vm_id=NULL WHERE vm_id IS NOT NULL AND (vm_id <= 0 OR vm_id NOT IN (SELECT id FROM virtual_machines))");
  $db->exec("UPDATE systems SET vm_homolog_id=NULL WHERE vm_homolog_id IS NOT NULL AND (vm_homolog_id <= 0 OR vm_homolog_id NOT IN (SELECT id FROM virtual_machines))");
  $db->exec("UPDATE systems SET vm_dev_id=NULL WHERE vm_dev_id IS NOT NULL AND (vm_dev_id <= 0 OR vm_dev_id NOT IN (SELECT id FROM virtual_machines))");
  $db->exec("UPDATE system_databases SET vm_id=NULL WHERE vm_id IS NOT NULL AND (vm_id <= 0 OR vm_id NOT IN (SELECT id FROM virtual_machines))");
  $db->exec("UPDATE system_databases SET vm_homolog_id=NULL WHERE vm_homolog_id IS NOT NULL AND (vm_homolog_id <= 0 OR vm_homolog_id NOT IN (SELECT id FROM virtual_machines))");
  $db->exec("DELETE FROM system_databases WHERE system_id IS NULL OR system_id NOT IN (SELECT id FROM systems)");
}

function ensureRelationalIndexesSqlite3(SQLite3 $db): void {
  $db->exec("CREATE INDEX IF NOT EXISTS idx_systems_vm_id ON systems(vm_id)");
  $db->exec("CREATE INDEX IF NOT EXISTS idx_systems_vm_homolog_id ON systems(vm_homolog_id)");
  $db->exec("CREATE INDEX IF NOT EXISTS idx_systems_vm_dev_id ON systems(vm_dev_id)");
  $db->exec("CREATE INDEX IF NOT EXISTS idx_system_databases_system_id ON system_databases(system_id)");
  $db->exec("CREATE INDEX IF NOT EXISTS idx_system_databases_vm_id ON system_databases(vm_id)");
  $db->exec("CREATE INDEX IF NOT EXISTS idx_system_databases_vm_homolog_id ON system_databases(vm_homolog_id)");
}

function ensureRelationalIndexesPdo(PDO $db): void {
  $db->exec("CREATE INDEX IF NOT EXISTS idx_systems_vm_id ON systems(vm_id)");
  $db->exec("CREATE INDEX IF NOT EXISTS idx_systems_vm_homolog_id ON systems(vm_homolog_id)");
  $db->exec("CREATE INDEX IF NOT EXISTS idx_systems_vm_dev_id ON systems(vm_dev_id)");
  $db->exec("CREATE INDEX IF NOT EXISTS idx_system_databases_system_id ON system_databases(system_id)");
  $db->exec("CREATE INDEX IF NOT EXISTS idx_system_databases_vm_id ON system_databases(vm_id)");
  $db->exec("CREATE INDEX IF NOT EXISTS idx_system_databases_vm_homolog_id ON system_databases(vm_homolog_id)");
}

function ensureRelationalSchemaSqlite3(SQLite3 $db): void {
  $hasSystemsFks = hasExpectedForeignKeys(systemsForeignKeyMapSqlite3($db), expectedSystemsForeignKeys());
  $hasDatabasesFks = hasExpectedForeignKeys(systemDatabasesForeignKeyMapSqlite3($db), expectedSystemDatabasesForeignKeys());

  if ($hasSystemsFks && $hasDatabasesFks) {
    ensureRelationalIndexesSqlite3($db);
    return;
  }

  normalizeRelationalDataSqlite3($db);
  $systemCols = implode(',', systemsColumnsForRelationalMigration());
  $dbCols = implode(',', systemDatabasesColumnsForRelationalMigration());

  $db->exec('PRAGMA foreign_keys = OFF');
  $db->exec('BEGIN IMMEDIATE');
  try {
    $db->exec('DROP TABLE IF EXISTS systems_legacy_fkless');
    $db->exec('DROP TABLE IF EXISTS system_databases_legacy_fkless');

    $db->exec('ALTER TABLE systems RENAME TO systems_legacy_fkless');
    $db->exec(createSystemsTableWithForeignKeysSql());
    $db->exec("INSERT INTO systems ($systemCols) SELECT $systemCols FROM systems_legacy_fkless");
    $db->exec('DROP TABLE systems_legacy_fkless');

    $db->exec('ALTER TABLE system_databases RENAME TO system_databases_legacy_fkless');
    $db->exec(createSystemDatabasesTableWithForeignKeysSql());
    $db->exec("INSERT INTO system_databases ($dbCols) SELECT $dbCols FROM system_databases_legacy_fkless");
    $db->exec('DROP TABLE system_databases_legacy_fkless');

    ensureRelationalIndexesSqlite3($db);
    $db->exec('COMMIT');
  } catch (Throwable $e) {
    $db->exec('ROLLBACK');
    throw $e;
  } finally {
    $db->exec('PRAGMA foreign_keys = ON');
  }

  $fkCheck = $db->query('PRAGMA foreign_key_check');
  if ($fkCheck && $fkCheck->fetchArray(SQLITE3_ASSOC)) {
    throw new RuntimeException('Falha ao validar integridade referencial apos migracao de FOREIGN KEY.');
  }
}

function ensureRelationalSchemaPdo(PDO $db): void {
  $hasSystemsFks = hasExpectedForeignKeys(systemsForeignKeyMapPdo($db), expectedSystemsForeignKeys());
  $hasDatabasesFks = hasExpectedForeignKeys(systemDatabasesForeignKeyMapPdo($db), expectedSystemDatabasesForeignKeys());

  if ($hasSystemsFks && $hasDatabasesFks) {
    ensureRelationalIndexesPdo($db);
    return;
  }

  normalizeRelationalDataPdo($db);
  $systemCols = implode(',', systemsColumnsForRelationalMigration());
  $dbCols = implode(',', systemDatabasesColumnsForRelationalMigration());

  $db->exec('PRAGMA foreign_keys = OFF');
  try {
    $db->beginTransaction();

    $db->exec('DROP TABLE IF EXISTS systems_legacy_fkless');
    $db->exec('DROP TABLE IF EXISTS system_databases_legacy_fkless');

    $db->exec('ALTER TABLE systems RENAME TO systems_legacy_fkless');
    $db->exec(createSystemsTableWithForeignKeysSql());
    $db->exec("INSERT INTO systems ($systemCols) SELECT $systemCols FROM systems_legacy_fkless");
    $db->exec('DROP TABLE systems_legacy_fkless');

    $db->exec('ALTER TABLE system_databases RENAME TO system_databases_legacy_fkless');
    $db->exec(createSystemDatabasesTableWithForeignKeysSql());
    $db->exec("INSERT INTO system_databases ($dbCols) SELECT $dbCols FROM system_databases_legacy_fkless");
    $db->exec('DROP TABLE system_databases_legacy_fkless');

    ensureRelationalIndexesPdo($db);
    $db->commit();
  } catch (Throwable $e) {
    if ($db->inTransaction()) { $db->rollBack(); }
    throw $e;
  } finally {
    $db->exec('PRAGMA foreign_keys = ON');
  }

  $fkCheck = $db->query('PRAGMA foreign_key_check')->fetchAll(PDO::FETCH_ASSOC);
  if (!empty($fkCheck)) {
    throw new RuntimeException('Falha ao validar integridade referencial apos migracao de FOREIGN KEY.');
  }
}

function ensureUsersTableSqlite3(SQLite3 $db): void {
  $db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    full_name TEXT DEFAULT '',
    role TEXT NOT NULL DEFAULT 'edicao',
    active INTEGER DEFAULT 1,
    created_at TEXT DEFAULT (datetime('now','localtime')),
    updated_at TEXT DEFAULT (datetime('now','localtime'))
  )");
  $db->exec("CREATE INDEX IF NOT EXISTS idx_users_username ON users(username)");

  $db->exec("CREATE TABLE IF NOT EXISTS login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip TEXT NOT NULL,
    attempted_at TEXT DEFAULT (datetime('now','localtime'))
  )");
  $db->exec("CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON login_attempts(ip)");

  $count = (int)$db->querySingle('SELECT COUNT(*) FROM users');
  if ($count === 0) {
    $adminPass  = getenv('SEI_ADMIN_PASSWORD')  ?: bin2hex(random_bytes(12));
    $editorPass = getenv('SEI_EDITOR_PASSWORD') ?: bin2hex(random_bytes(12));
    if (!getenv('SEI_ADMIN_PASSWORD'))  { error_log("[SEI bootstrap] Senha inicial admin: $adminPass"); }
    if (!getenv('SEI_EDITOR_PASSWORD')) { error_log("[SEI bootstrap] Senha inicial editor: $editorPass"); }

    $seed = [
      ['username' => 'admin',  'password' => $adminPass,  'full_name' => 'Administrador', 'role' => ROLE_ADMIN],
      ['username' => 'editor', 'password' => $editorPass, 'full_name' => 'Editor',        'role' => ROLE_EDICAO],
    ];

    foreach ($seed as $user) {
      $st = $db->prepare("INSERT INTO users(username,password_hash,full_name,role,active,created_at,updated_at) VALUES(:username,:password_hash,:full_name,:role,1,datetime('now','localtime'),datetime('now','localtime'))");
      $st->bindValue(':username', $user['username'], SQLITE3_TEXT);
      $st->bindValue(':password_hash', password_hash($user['password'], PASSWORD_DEFAULT), SQLITE3_TEXT);
      $st->bindValue(':full_name', $user['full_name'], SQLITE3_TEXT);
      $st->bindValue(':role', $user['role'], SQLITE3_TEXT);
      $st->execute();
    }
  }

}

function ensureUsersTablePdo(PDO $db): void {
  $db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    full_name TEXT DEFAULT '',
    role TEXT NOT NULL DEFAULT 'edicao',
    active INTEGER DEFAULT 1,
    created_at TEXT DEFAULT (datetime('now','localtime')),
    updated_at TEXT DEFAULT (datetime('now','localtime'))
  )");
  $db->exec("CREATE INDEX IF NOT EXISTS idx_users_username ON users(username)");

  $db->exec("CREATE TABLE IF NOT EXISTS login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip TEXT NOT NULL,
    attempted_at TEXT DEFAULT (datetime('now','localtime'))
  )");
  $db->exec("CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON login_attempts(ip)");

  $count = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
  if ($count === 0) {
    $adminPass  = getenv('SEI_ADMIN_PASSWORD')  ?: bin2hex(random_bytes(12));
    $editorPass = getenv('SEI_EDITOR_PASSWORD') ?: bin2hex(random_bytes(12));
    if (!getenv('SEI_ADMIN_PASSWORD'))  { error_log("[SEI bootstrap] Senha inicial admin: $adminPass"); }
    if (!getenv('SEI_EDITOR_PASSWORD')) { error_log("[SEI bootstrap] Senha inicial editor: $editorPass"); }

    $seed = [
      ['username' => 'admin',  'password' => $adminPass,  'full_name' => 'Administrador', 'role' => ROLE_ADMIN],
      ['username' => 'editor', 'password' => $editorPass, 'full_name' => 'Editor',        'role' => ROLE_EDICAO],
    ];

    $st = $db->prepare("INSERT INTO users(username,password_hash,full_name,role,active,created_at,updated_at) VALUES(:username,:password_hash,:full_name,:role,1,datetime('now','localtime'),datetime('now','localtime'))");
    foreach ($seed as $user) {
      $st->bindValue(':username', $user['username'], PDO::PARAM_STR);
      $st->bindValue(':password_hash', password_hash($user['password'], PASSWORD_DEFAULT), PDO::PARAM_STR);
      $st->bindValue(':full_name', $user['full_name'], PDO::PARAM_STR);
      $st->bindValue(':role', $user['role'], PDO::PARAM_STR);
      $st->execute();
    }
  }

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
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec("CREATE TABLE IF NOT EXISTS systems (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      system_name TEXT DEFAULT '',
      system_group TEXT DEFAULT '',
      system_access TEXT DEFAULT 'Interno',
      ip TEXT DEFAULT '',
      ip_homolog TEXT DEFAULT '',
      vm TEXT DEFAULT '',
      url_homolog TEXT DEFAULT '',
      vm_homolog TEXT DEFAULT '',
      vm_id INTEGER DEFAULT NULL,
      vm_homolog_id INTEGER DEFAULT NULL,
      vm_dev_id INTEGER DEFAULT NULL,
      archived INTEGER DEFAULT 0,
      archived_at TEXT DEFAULT NULL,
      responsible_sector TEXT DEFAULT '',
      responsible_coordinator TEXT DEFAULT '',
      extension_number TEXT DEFAULT '',
      email TEXT DEFAULT '',
      support TEXT DEFAULT '',
      support_contact TEXT DEFAULT '',
      category TEXT DEFAULT 'Outro',
      status TEXT DEFAULT 'Ativo',
      waf TEXT DEFAULT '',
      tech TEXT DEFAULT '',
      target_version TEXT DEFAULT '',
      app_server TEXT DEFAULT '',
      web_server TEXT DEFAULT '',
      containerization INTEGER DEFAULT 0,
      container_tool TEXT DEFAULT '',
      runtime_port TEXT DEFAULT '',
      php_required_extensions TEXT DEFAULT '',
      php_recommended_extensions TEXT DEFAULT '',
      php_required_libraries TEXT DEFAULT '',
      php_required_ini TEXT DEFAULT '',
      r_required_packages TEXT DEFAULT '',
      doc_installation_ref TEXT DEFAULT '',
      doc_installation_updated_at TEXT DEFAULT NULL,
      doc_maintenance_ref TEXT DEFAULT '',
      doc_maintenance_updated_at TEXT DEFAULT NULL,
      doc_security_ref TEXT DEFAULT '',
      doc_security_updated_at TEXT DEFAULT NULL,
      doc_manual_ref TEXT DEFAULT '',
      doc_manual_updated_at TEXT DEFAULT NULL,
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
    normalizeLegacyStatusValuesSqlite3($db);
    ensureVmTableSqlite3($db);
    ensureDatabaseTableSqlite3($db);
    ensureUsersTableSqlite3($db);
    migrateLegacyVmLinksSqlite3($db);
    ensureRelationalSchemaSqlite3($db);
    ensureTicketsTableSqlite3($db);
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
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec("CREATE TABLE IF NOT EXISTS systems (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      system_name TEXT DEFAULT '',
      system_group TEXT DEFAULT '',
      system_access TEXT DEFAULT 'Interno',
      ip TEXT DEFAULT '',
      ip_homolog TEXT DEFAULT '',
      vm TEXT DEFAULT '',
      url_homolog TEXT DEFAULT '',
      vm_homolog TEXT DEFAULT '',
      vm_id INTEGER DEFAULT NULL,
      vm_homolog_id INTEGER DEFAULT NULL,
      vm_dev_id INTEGER DEFAULT NULL,
      archived INTEGER DEFAULT 0,
      archived_at TEXT DEFAULT NULL,
      responsible_sector TEXT DEFAULT '',
      responsible_coordinator TEXT DEFAULT '',
      extension_number TEXT DEFAULT '',
      email TEXT DEFAULT '',
      support TEXT DEFAULT '',
      support_contact TEXT DEFAULT '',
      category TEXT DEFAULT 'Outro',
      status TEXT DEFAULT 'Ativo',
      waf TEXT DEFAULT '',
      tech TEXT DEFAULT '',
      target_version TEXT DEFAULT '',
      app_server TEXT DEFAULT '',
      web_server TEXT DEFAULT '',
      containerization INTEGER DEFAULT 0,
      container_tool TEXT DEFAULT '',
      runtime_port TEXT DEFAULT '',
      php_required_extensions TEXT DEFAULT '',
      php_recommended_extensions TEXT DEFAULT '',
      php_required_libraries TEXT DEFAULT '',
      php_required_ini TEXT DEFAULT '',
      r_required_packages TEXT DEFAULT '',
      doc_installation_ref TEXT DEFAULT '',
      doc_installation_updated_at TEXT DEFAULT NULL,
      doc_maintenance_ref TEXT DEFAULT '',
      doc_maintenance_updated_at TEXT DEFAULT NULL,
      doc_security_ref TEXT DEFAULT '',
      doc_security_updated_at TEXT DEFAULT NULL,
      doc_manual_ref TEXT DEFAULT '',
      doc_manual_updated_at TEXT DEFAULT NULL,
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
    normalizeLegacyStatusValuesPdo($db);
    ensureVmTablePdo($db);
    ensureDatabaseTablePdo($db);
    ensureUsersTablePdo($db);
    migrateLegacyVmLinksPdo($db);
    ensureRelationalSchemaPdo($db);
    ensureTicketsTablePdo($db);
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

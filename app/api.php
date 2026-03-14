<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'constants.php';

function systemSelectSql(): string {
  return "SELECT
    s.*,
    vm.name AS vm_name,
    vm.ip AS vm_ip,
    vm.public_ip AS vm_public_ip,
    vmh.name AS vm_homolog_name,
    vmh.ip AS vm_homolog_ip,
    vmh.public_ip AS vm_homolog_public_ip,
    vmd.name AS vm_dev_name,
    vmd.ip AS vm_dev_ip,
    vmd.public_ip AS vm_dev_public_ip
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

function normalizeSystemDocType(string $raw): string {
  $value = strtolower(trim($raw));
  if ($value === 'maintenance' || $value === 'manutencao' || $value === 'atualizacao') { return 'maintenance'; }
  if ($value === 'security' || $value === 'seguranca') { return 'security'; }
  if ($value === 'manual' || $value === 'procedures' || $value === 'procedimento' || $value === 'procedimentos') { return 'manual'; }
  return $value === 'installation' || $value === 'instalacao' ? 'installation' : '';
}

function systemDocFieldMap(string $docType): ?array {
  $type = normalizeSystemDocType($docType);
  $map = [
    'installation' => ['ref' => 'doc_installation_ref', 'updated_at' => 'doc_installation_updated_at', 'label' => 'Instalacao'],
    'maintenance' => ['ref' => 'doc_maintenance_ref', 'updated_at' => 'doc_maintenance_updated_at', 'label' => 'Manutencao'],
    'security' => ['ref' => 'doc_security_ref', 'updated_at' => 'doc_security_updated_at', 'label' => 'Seguranca'],
    'manual' => ['ref' => 'doc_manual_ref', 'updated_at' => 'doc_manual_updated_at', 'label' => 'Manual'],
  ];
  return $map[$type] ?? null;
}

function systemDocAllFieldMaps(): array {
  $types = ['installation', 'maintenance', 'security', 'manual'];
  $out = [];
  foreach ($types as $type) {
    $entry = systemDocFieldMap($type);
    if ($entry !== null) { $out[$type] = $entry; }
  }
  return $out;
}

function systemDocProjectRoot(): string {
  $root = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');
  return $root !== false ? $root : (__DIR__ . DIRECTORY_SEPARATOR . '..');
}

function systemDocDir(): string {
  return systemDocProjectRoot() . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'system_docs';
}

function ensureSystemDocDir(): string {
  $dir = systemDocDir();
  if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
    throw new RuntimeException('Nao foi possivel criar diretorio de documentos dos sistemas');
  }
  if (!is_writable($dir)) {
    throw new RuntimeException('Diretorio de documentos dos sistemas sem permissao de escrita');
  }
  return $dir;
}

function sanitizeSystemDocFilename(string $filename): string {
  $name = trim($filename);
  if ($name === '') { $name = 'documento.pdf'; }
  $name = str_replace(['\\', '/'], '-', $name);
  $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?? 'documento.pdf';
  if ($name === '' || $name === '.' || $name === '..') { $name = 'documento.pdf'; }
  if (!str_ends_with(strtolower($name), '.pdf')) { $name .= '.pdf'; }
  return $name;
}

function deleteSystemDocFileByReference(string $reference): void {
  $ref = trim($reference);
  if ($ref === '') { return; }
  $relative = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $ref);
  $fullPath = systemDocProjectRoot() . DIRECTORY_SEPARATOR . ltrim($relative, DIRECTORY_SEPARATOR);
  if (is_file($fullPath)) { @unlink($fullPath); }
}

function systemDocReferencesFromSystemRow(array $row): array {
  $refs = [];
  foreach (systemDocAllFieldMaps() as $map) {
    $ref = trim((string)($row[$map['ref']] ?? ''));
    if ($ref === '') { continue; }
    $refs[$ref] = $ref;
  }
  return array_values($refs);
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

function normalizeVmIpListValue($raw): array {
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

function packVmIpListValue($raw): string {
  return implode(', ', normalizeVmIpListValue($raw));
}

function firstVmIpValue($raw): string {
  $ips = normalizeVmIpListValue($raw);
  return $ips[0] ?? '';
}

function hostFromUrlText(string $rawUrl): string {
  $value = trim($rawUrl);
  if ($value === '') { return ''; }
  $candidate = preg_match('#^[a-z][a-z0-9+.-]*://#i', $value) === 1 ? $value : ('https://' . $value);
  $parts = @parse_url($candidate);
  if (!is_array($parts)) { return ''; }
  $host = strtolower(trim((string)($parts['host'] ?? '')));
  if ($host === '' || preg_match('/[^a-z0-9.\-]/i', $host) === 1) { return ''; }
  return $host;
}

function isPublicIpAddress(string $ip): bool {
  $value = trim($ip);
  if ($value === '') { return false; }
  $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
  return filter_var($value, FILTER_VALIDATE_IP, $flags) !== false;
}

function resolveHostPublicIps(string $host): array {
  $target = strtolower(trim($host));
  if ($target === '') { return []; }
  $out = [];
  $seen = [];

  $addIp = static function(string $ip) use (&$out, &$seen): void {
    $value = trim($ip);
    if ($value === '' || !isPublicIpAddress($value)) { return; }
    $key = strtolower($value);
    if (isset($seen[$key])) { return; }
    $seen[$key] = true;
    $out[] = $value;
  };

  if (function_exists('dns_get_record')) {
    $flags = 0;
    if (defined('DNS_A')) { $flags |= DNS_A; }
    if (defined('DNS_AAAA')) { $flags |= DNS_AAAA; }
    if ($flags === 0) { $flags = DNS_A; }
    $records = @dns_get_record($target, $flags);
    if (is_array($records)) {
      foreach ($records as $record) {
        if (!is_array($record)) { continue; }
        $addIp((string)($record['ip'] ?? ''));
        $addIp((string)($record['ipv6'] ?? ''));
      }
    }
  }

  if (!$out && function_exists('gethostbynamel')) {
    $fallback = @gethostbynamel($target);
    if (is_array($fallback)) {
      foreach ($fallback as $ip) {
        $addIp((string)$ip);
      }
    }
  }

  if (!$out) {
    foreach (resolveHostPublicIpsViaDoh($target) as $ip) {
      $addIp((string)$ip);
    }
  }

  return $out;
}

function resolveHostInternalIps(string $host): array {
  $target = strtolower(trim($host));
  if ($target === '') { return []; }
  $out = [];
  $seen = [];

  $addIp = static function(string $ip) use (&$out, &$seen): void {
    $value = trim($ip);
    if ($value === '') { return; }
    if (filter_var($value, FILTER_VALIDATE_IP) === false) { return; }
    $key = strtolower($value);
    if (isset($seen[$key])) { return; }
    $seen[$key] = true;
    $out[] = $value;
  };

  if (function_exists('dns_get_record')) {
    $flags = 0;
    if (defined('DNS_A')) { $flags |= DNS_A; }
    if (defined('DNS_AAAA')) { $flags |= DNS_AAAA; }
    if ($flags === 0) { $flags = DNS_A; }
    $records = @dns_get_record($target, $flags);
    if (is_array($records)) {
      foreach ($records as $record) {
        if (!is_array($record)) { continue; }
        $addIp((string)($record['ip'] ?? ''));
        $addIp((string)($record['ipv6'] ?? ''));
      }
    }
  }

  if (!$out && function_exists('gethostbynamel')) {
    $fallback = @gethostbynamel($target);
    if (is_array($fallback)) {
      foreach ($fallback as $ip) {
        $addIp((string)$ip);
      }
    }
  }

  return $out;
}

function resolveHostPublicIpsViaDoh(string $host): array {
  $target = strtolower(trim($host));
  if ($target === '') { return []; }
  $out = [];
  $seen = [];

  $add = static function(string $ip) use (&$out, &$seen): void {
    $value = trim($ip);
    if ($value === '' || !isPublicIpAddress($value)) { return; }
    $key = strtolower($value);
    if (isset($seen[$key])) { return; }
    $seen[$key] = true;
    $out[] = $value;
  };

  $providers = [
    'https://dns.google/resolve',
    'https://cloudflare-dns.com/dns-query',
  ];
  $types = ['A', 'AAAA'];

  foreach ($providers as $baseUrl) {
    foreach ($types as $type) {
      $url = $baseUrl . '?name=' . rawurlencode($target) . '&type=' . rawurlencode($type);
      $json = httpGetJson($url);
      if (!is_array($json)) { continue; }
      $answers = $json['Answer'] ?? [];
      if (!is_array($answers)) { continue; }
      foreach ($answers as $answer) {
        if (!is_array($answer)) { continue; }
        $add((string)($answer['data'] ?? ''));
      }
    }
    if ($out) { break; }
  }

  return $out;
}

function httpGetJson(string $url): ?array {
  $target = trim($url);
  if ($target === '') { return null; }
  $body = '';

  if (function_exists('curl_init')) {
    $ch = curl_init($target);
    if ($ch === false) { return null; }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/dns-json, application/json']);
    curl_setopt($ch, CURLOPT_USERAGENT, 'SEI-Catalog/1.0');
    $resp = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (is_string($resp) && $status >= 200 && $status < 300) { $body = $resp; }
  }

  if ($body === '' && filter_var($target, FILTER_VALIDATE_URL)) {
    $ctx = stream_context_create([
      'http' => [
        'method' => 'GET',
        'timeout' => 8,
        'header' => "Accept: application/dns-json, application/json\r\nUser-Agent: SEI-Catalog/1.0\r\n",
      ],
    ]);
    $resp = @file_get_contents($target, false, $ctx);
    if (is_string($resp) && $resp !== '') { $body = $resp; }
  }

  if ($body === '') { return null; }
  $decoded = json_decode($body, true);
  return is_array($decoded) ? $decoded : null;
}

function parseSslTargetKey(string $raw): ?array {
  $value = strtolower(trim($raw));
  if ($value === '') { return null; }
  if (preg_match('/^([a-z0-9.-]+)(?::([0-9]{1,5}))?$/i', $value, $m) !== 1) { return null; }
  $host = trim((string)($m[1] ?? ''));
  if ($host === '' || preg_match('/[^a-z0-9.-]/i', $host) === 1) { return null; }
  $port = isset($m[2]) && $m[2] !== '' ? (int)$m[2] : 443;
  if ($port < 1 || $port > 65535) { return null; }
  return ['key' => $host . ':' . $port, 'host' => $host, 'port' => $port];
}

function sslCertificateExpiryTimestamp(string $host, int $port = 443): ?int {
  if (!function_exists('stream_socket_client') || !function_exists('stream_context_create')) { return null; }
  $hostname = strtolower(trim($host));
  if ($hostname === '') { return null; }
  if ($port < 1 || $port > 65535) { return null; }

  $context = stream_context_create([
    'ssl' => [
      'capture_peer_cert' => true,
      'verify_peer' => false,
      'verify_peer_name' => false,
      'allow_self_signed' => true,
      'SNI_enabled' => true,
      'peer_name' => $hostname,
    ],
  ]);

  $errno = 0;
  $errstr = '';
  $socket = @stream_socket_client(
    'ssl://' . $hostname . ':' . $port,
    $errno,
    $errstr,
    8,
    STREAM_CLIENT_CONNECT,
    $context
  );
  if (!is_resource($socket)) { return null; }

  $params = stream_context_get_params($socket);
  @fclose($socket);
  $cert = $params['options']['ssl']['peer_certificate'] ?? null;
  if (!is_resource($cert) && !is_object($cert)) { return null; }

  if (!function_exists('openssl_x509_parse')) { return null; }
  $parsed = @openssl_x509_parse($cert);
  if (!is_array($parsed)) { return null; }
  $validTo = (int)($parsed['validTo_time_t'] ?? 0);
  return $validTo > 0 ? $validTo : null;
}

function sslValidityLabelByTimestamp(?int $timestamp): string {
  if ($timestamp === null || $timestamp <= 0) { return '-'; }
  $now = time();
  $date = date('d/m/Y', $timestamp);
  if ($timestamp < $now) { return 'Expirado em ' . $date; }
  $days = (int)floor(($timestamp - $now) / 86400);
  return $date . ' (' . $days . ' dias)';
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
    $port = trim((string)($item['port'] ?? $item['instance_port'] ?? $item['db_port'] ?? ''));
    if ($port === '' && preg_match('/^(.+):(\d{1,5})$/', $ip, $parts)) {
      $candidateIp = trim((string)($parts[1] ?? ''));
      $candidatePort = trim((string)($parts[2] ?? ''));
      if (filter_var($candidateIp, FILTER_VALIDATE_IP)) {
        $ip = $candidateIp;
        $port = $candidatePort;
      }
    }
    if ($port !== '') {
      if (!preg_match('/^\d+$/', $port)) { continue; }
      $portInt = (int)$port;
      if ($portInt < 1 || $portInt > 65535) { continue; }
      $port = (string)$portInt;
    }
    if ($name === '' && $ip === '') { continue; }
    if ($name === '') { $name = 'Instancia ' . (count($out) + 1); }
    if ($ip === '') { continue; }
    $key = strtolower($name) . '|' . strtolower($ip) . '|' . $port;
    if (isset($seen[$key])) { continue; }
    $seen[$key] = true;
    $out[] = ['name' => $name, 'ip' => $ip, 'port' => $port];
  }
  return $out;
}

function vmInstancesWithFallback(array $vm): array {
  $instances = normalizeVmInstancesValue($vm['vm_instances_list'] ?? ($vm['vm_instances'] ?? ''));
  if ($instances) { return $instances; }
  $ips = normalizeVmIpListValue($vm['ip'] ?? '');
  if (!$ips) { return []; }
  $out = [];
  foreach ($ips as $ip) {
    $out[] = ['name' => 'Instancia principal', 'ip' => $ip, 'port' => ''];
  }
  return $out;
}

function resolveVmInstance(array $vm, string $name, string $ip, string $port = ''): ?array {
  $instances = vmInstancesWithFallback($vm);
  if (!$instances) { return null; }
  $targetName = trim($name);
  $targetIp = trim($ip);
  $targetPort = trim($port);
  if ($targetPort !== '') {
    if (!preg_match('/^\d+$/', $targetPort)) { return null; }
    $targetPortInt = (int)$targetPort;
    if ($targetPortInt < 1 || $targetPortInt > 65535) { return null; }
    $targetPort = (string)$targetPortInt;
  }

  if ($targetIp !== '') {
    foreach ($instances as $instance) {
      $instIp = trim((string)($instance['ip'] ?? ''));
      $instName = trim((string)($instance['name'] ?? ''));
      $instPort = trim((string)($instance['port'] ?? ''));
      if ($instIp !== $targetIp) { continue; }
      if ($targetName !== '' && strtolower($instName) !== strtolower($targetName)) { continue; }
      if ($targetPort !== '' && $instPort !== $targetPort) { continue; }
      return ['name' => $instName, 'ip' => $instIp, 'port' => $instPort];
    }
  }

  if ($targetName !== '') {
    foreach ($instances as $instance) {
      $instName = trim((string)($instance['name'] ?? ''));
      $instIp = trim((string)($instance['ip'] ?? ''));
      $instPort = trim((string)($instance['port'] ?? ''));
      if (strtolower($instName) === strtolower($targetName)) {
        if ($targetPort !== '' && $instPort !== $targetPort) { continue; }
        return ['name' => $instName, 'ip' => $instIp, 'port' => $instPort];
      }
    }
  }

  if ($targetName === '' && $targetIp === '' && $targetPort === '') { return $instances[0]; }
  return null;
}

function normalizeSystemRow(array $row): array {
  foreach ($row as $key => $value) {
    if (is_string($value)) { $row[$key] = normalizeUtf8Text($value); }
  }
  $systemAccessRaw = trim((string)($row['system_access'] ?? ''));
  $row['system_access'] = stripos($systemAccessRaw, 'extern') !== false ? 'Externo' : 'Interno';
  $row['tech'] = $row['tech'] !== '' ? array_values(array_filter(array_map('trim', explode(',', (string)$row['tech'])))) : [];
  $row['target_version'] = trim((string)($row['target_version'] ?? ''));
  $row['app_server'] = trim((string)($row['app_server'] ?? ''));
  $row['web_server'] = trim((string)($row['web_server'] ?? ''));
  $row['containerization'] = boolFromMixed($row['containerization'] ?? 0) ? 1 : 0;
  $row['container_tool'] = trim((string)($row['container_tool'] ?? ''));
  if ((int)$row['containerization'] <= 0) { $row['container_tool'] = ''; }
  $row['runtime_port'] = trim((string)($row['runtime_port'] ?? ''));
  $row['php_required_extensions'] = trim((string)($row['php_required_extensions'] ?? ''));
  $row['php_recommended_extensions'] = trim((string)($row['php_recommended_extensions'] ?? ''));
  $row['php_required_libraries'] = trim((string)($row['php_required_libraries'] ?? ''));
  $row['php_required_ini'] = normalizeIniRequirementText((string)($row['php_required_ini'] ?? ''));
  $row['r_required_packages'] = trim((string)($row['r_required_packages'] ?? ''));
  foreach (systemDocAllFieldMaps() as $type => $map) {
    $row[$map['ref']] = trim((string)($row[$map['ref']] ?? ''));
    $row[$map['updated_at']] = trim((string)($row[$map['updated_at']] ?? ''));
  }
  $row['php_required_extensions_list'] = splitCsvList($row['php_required_extensions']);
  $row['php_recommended_extensions_list'] = splitCsvList($row['php_recommended_extensions']);
  $row['php_required_libraries_list'] = splitCsvList($row['php_required_libraries']);
  $row['php_required_ini_list'] = splitIniRequirementLines($row['php_required_ini']);
  $row['r_required_packages_list'] = splitCsvList($row['r_required_packages']);
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
  if (($row['vm_public_ip'] ?? '') === '') { $row['vm_public_ip'] = (string)($row['public_ip'] ?? ''); }
  if (($row['vm_homolog_public_ip'] ?? '') === '') { $row['vm_homolog_public_ip'] = (string)($row['public_ip_homolog'] ?? ''); }
  if (($row['vm_dev_name'] ?? '') === '') { $row['vm_dev_name'] = (string)($row['vm_dev'] ?? ''); }
  if (($row['vm_dev_ip'] ?? '') === '') { $row['vm_dev_ip'] = (string)($row['ip_dev'] ?? ''); }
  if (($row['vm_dev_public_ip'] ?? '') === '') { $row['vm_dev_public_ip'] = (string)($row['public_ip_dev'] ?? ''); }
  $row['vm_ip'] = firstVmIpValue((string)($row['vm_ip'] ?? ''));
  $row['vm_homolog_ip'] = firstVmIpValue((string)($row['vm_homolog_ip'] ?? ''));
  $row['vm_dev_ip'] = firstVmIpValue((string)($row['vm_dev_ip'] ?? ''));
  $row['vm_public_ip'] = firstVmIpValue((string)($row['vm_public_ip'] ?? ''));
  $row['vm_homolog_public_ip'] = firstVmIpValue((string)($row['vm_homolog_public_ip'] ?? ''));
  $row['vm_dev_public_ip'] = firstVmIpValue((string)($row['vm_dev_public_ip'] ?? ''));

  $row['system_documents'] = [];
  foreach (systemDocAllFieldMaps() as $type => $map) {
    $reference = trim((string)($row[$map['ref']] ?? ''));
    $updatedAt = trim((string)($row[$map['updated_at']] ?? ''));
    $row['system_documents'][$type] = [
      'type' => $type,
      'label' => (string)$map['label'],
      'reference' => $reference,
      'filename' => $reference !== '' ? basename($reference) : '',
      'updated_at' => $updatedAt,
      'has_file' => $reference !== '',
      'url' => (int)($row['id'] ?? 0) > 0 && $reference !== ''
        ? ('?api=system-doc-view&id=' . (int)$row['id'] . '&doc_type=' . rawurlencode($type))
        : '',
    ];
  }

  return $row;
}

function fetchSystemsSqlite3(SQLite3 $db, bool $archived=false): array {
  $flag = $archived ? 1 : 0;
  $res = $db->query(systemSelectSql() . " WHERE IFNULL(s.archived,0)=$flag ORDER BY s.name COLLATE NOCASE");
  $out = [];
  $vmCache = [];
  $vmLoader = function (?int $id) use ($db, &$vmCache): ?array {
    $vmId = (int)($id ?? 0);
    if ($vmId <= 0) { return null; }
    if (array_key_exists($vmId, $vmCache)) { return $vmCache[$vmId]; }
    $vmCache[$vmId] = fetchVmByIdSqlite3($db, $vmId);
    return $vmCache[$vmId];
  };
  while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $normalized = normalizeSystemRow($row);
    $out[] = enrichSystemRowWithPhpCompatibility($normalized, $vmLoader);
  }
  return $out;
}

function fetchSystemsPdo(PDO $db, bool $archived=false): array {
  $flag = $archived ? 1 : 0;
  $rows = $db->query(systemSelectSql() . " WHERE IFNULL(s.archived,0)=$flag ORDER BY s.name COLLATE NOCASE")->fetchAll(PDO::FETCH_ASSOC);
  $vmCache = [];
  $vmLoader = function (?int $id) use ($db, &$vmCache): ?array {
    $vmId = (int)($id ?? 0);
    if ($vmId <= 0) { return null; }
    if (array_key_exists($vmId, $vmCache)) { return $vmCache[$vmId]; }
    $vmCache[$vmId] = fetchVmByIdPdo($db, $vmId);
    return $vmCache[$vmId];
  };
  foreach ($rows as &$row) {
    $normalized = normalizeSystemRow($row);
    $row = enrichSystemRowWithPhpCompatibility($normalized, $vmLoader);
  }
  unset($row);
  return $rows;
}

function fetchSystemByIdSqlite3(SQLite3 $db, int $id): ?array {
  $st = $db->prepare(systemSelectSql() . " WHERE s.id=:id LIMIT 1");
  $st->bindValue(':id', $id, SQLITE3_INTEGER);
  $res = $st->execute();
  $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;
  if (!is_array($row)) { return null; }
  $normalized = normalizeSystemRow($row);
  $vmCache = [];
  $vmLoader = function (?int $vmId) use ($db, &$vmCache): ?array {
    $idInt = (int)($vmId ?? 0);
    if ($idInt <= 0) { return null; }
    if (array_key_exists($idInt, $vmCache)) { return $vmCache[$idInt]; }
    $vmCache[$idInt] = fetchVmByIdSqlite3($db, $idInt);
    return $vmCache[$idInt];
  };
  return enrichSystemRowWithPhpCompatibility($normalized, $vmLoader);
}

function fetchSystemByIdPdo(PDO $db, int $id): ?array {
  $st = $db->prepare(systemSelectSql() . " WHERE s.id=:id LIMIT 1");
  $st->bindValue(':id', $id, PDO::PARAM_INT);
  $st->execute();
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!is_array($row)) { return null; }
  $normalized = normalizeSystemRow($row);
  $vmCache = [];
  $vmLoader = function (?int $vmId) use ($db, &$vmCache): ?array {
    $idInt = (int)($vmId ?? 0);
    if ($idInt <= 0) { return null; }
    if (array_key_exists($idInt, $vmCache)) { return $vmCache[$idInt]; }
    $vmCache[$idInt] = fetchVmByIdPdo($db, $idInt);
    return $vmCache[$idInt];
  };
  return enrichSystemRowWithPhpCompatibility($normalized, $vmLoader);
}

function bindSystemFieldSqlite3(SQLite3Stmt $st, string $field, array $data): void {
  if ($field === 'vm_id' || $field === 'vm_homolog_id' || $field === 'vm_dev_id') {
    $id = (int)($data[$field] ?? 0);
    if ($id > 0) { $st->bindValue(':'.$field, $id, SQLITE3_INTEGER); }
    else { $st->bindValue(':'.$field, null, SQLITE3_NULL); }
    return;
  }
  if ($field === 'containerization') {
    $st->bindValue(':'.$field, boolFromMixed($data[$field] ?? 0) ? 1 : 0, SQLITE3_INTEGER);
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
  if ($field === 'containerization') {
    $st->bindValue(':'.$field, boolFromMixed($data[$field] ?? 0) ? 1 : 0, PDO::PARAM_INT);
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

function normalizeCsvListValue($raw): string {
  $tokens = [];
  if (is_array($raw)) {
    foreach ($raw as $entry) {
      $parts = preg_split('/[\r\n,;]+/', (string)$entry) ?: [];
      foreach ($parts as $part) {
        $value = trim((string)$part);
        if ($value !== '') { $tokens[] = $value; }
      }
    }
  } else {
    $parts = preg_split('/[\r\n,;]+/', (string)$raw) ?: [];
    foreach ($parts as $part) {
      $value = trim((string)$part);
      if ($value !== '') { $tokens[] = $value; }
    }
  }

  $seen = [];
  $out = [];
  foreach ($tokens as $token) {
    $key = strtolower($token);
    if (isset($seen[$key])) { continue; }
    $seen[$key] = true;
    $out[] = $token;
  }
  return implode(',', $out);
}

function mergeCsvListValues($primary, $extra): string {
  return normalizeCsvListValue([
    normalizeCsvListValue($primary),
    normalizeCsvListValue($extra),
  ]);
}

function splitIniRequirementLines(string $raw): array {
  $value = str_replace("\r", "\n", trim($raw));
  if ($value === '') { return []; }
  return array_values(array_filter(array_map('trim', explode("\n", $value))));
}

function normalizeIniRequirementText(string $raw): string {
  return implode("\n", splitIniRequirementLines($raw));
}

function parseIniRequirementLine(string $line): ?array {
  $raw = trim($line);
  if ($raw === '') { return null; }
  if (!preg_match('/^([A-Za-z0-9_.-]+)\s*(>=|<=|!=|=|>|<)?\s*(.*)$/', $raw, $m)) {
    return null;
  }
  $directive = trim((string)($m[1] ?? ''));
  if ($directive === '') { return null; }
  $operator = trim((string)($m[2] ?? ''));
  $expected = trim((string)($m[3] ?? ''));
  if ($operator === '' && $expected !== '') { $operator = '='; }
  if ($operator === '') { $operator = 'exists'; }
  return [
    'raw' => $raw,
    'directive' => $directive,
    'directive_key' => strtolower($directive),
    'operator' => $operator,
    'expected' => $expected,
  ];
}

function parseIniRequirements(string $raw): array {
  $out = [];
  foreach (splitIniRequirementLines($raw) as $line) {
    $rule = parseIniRequirementLine($line);
    if ($rule !== null) { $out[] = $rule; }
  }
  return $out;
}

function iniValueToNumber(string $value): ?float {
  $normalized = trim($value);
  if ($normalized === '') { return null; }
  if (!preg_match('/^\s*([+-]?\d+(?:\.\d+)?)\s*([KMGTP]?)(?:B)?\s*$/i', $normalized, $m)) {
    return null;
  }
  $number = (float)($m[1] ?? 0);
  $unit = strtoupper(trim((string)($m[2] ?? '')));
  $factor = 1.0;
  if ($unit === 'K') { $factor = 1024.0; }
  elseif ($unit === 'M') { $factor = 1024.0 * 1024.0; }
  elseif ($unit === 'G') { $factor = 1024.0 * 1024.0 * 1024.0; }
  elseif ($unit === 'T') { $factor = 1024.0 * 1024.0 * 1024.0 * 1024.0; }
  elseif ($unit === 'P') { $factor = 1024.0 * 1024.0 * 1024.0 * 1024.0 * 1024.0; }
  return $number * $factor;
}

function normalizeIniScalar(string $value): string {
  $normalized = strtolower(trim($value));
  if (in_array($normalized, ['on', 'yes', 'true'], true)) { return '1'; }
  if (in_array($normalized, ['off', 'no', 'false'], true)) { return '0'; }
  return $normalized;
}

function compareIniRequirementValues(string $actual, string $expected, string $operator): bool {
  $op = trim($operator);
  if ($op === 'exists') { return trim($actual) !== ''; }
  $actualNum = iniValueToNumber($actual);
  $expectedNum = iniValueToNumber($expected);
  if ($actualNum !== null && $expectedNum !== null && in_array($op, ['>','>=','<','<='], true)) {
    if ($op === '>') { return $actualNum > $expectedNum; }
    if ($op === '>=') { return $actualNum >= $expectedNum; }
    if ($op === '<') { return $actualNum < $expectedNum; }
    return $actualNum <= $expectedNum;
  }

  $actualNorm = normalizeIniScalar($actual);
  $expectedNorm = normalizeIniScalar($expected);
  if ($op === '=' || $op === '==') { return $actualNorm === $expectedNorm; }
  if ($op === '!=') { return $actualNorm !== $expectedNorm; }

  if ($op === '>') { return strcmp($actualNorm, $expectedNorm) > 0; }
  if ($op === '>=') { return strcmp($actualNorm, $expectedNorm) >= 0; }
  if ($op === '<') { return strcmp($actualNorm, $expectedNorm) < 0; }
  if ($op === '<=') { return strcmp($actualNorm, $expectedNorm) <= 0; }
  return false;
}

function phpRequirementsFromSystemRow(array $row): array {
  $requiredRaw = splitCsvList((string)($row['php_required_extensions'] ?? ''));
  $recommendedRaw = splitCsvList((string)($row['php_recommended_extensions'] ?? ''));
  $requiredExtensions = [];
  $seenRequired = [];
  foreach (array_merge($requiredRaw, $recommendedRaw) as $ext) {
    $name = trim((string)$ext);
    if ($name === '') { continue; }
    $key = strtolower($name);
    if (isset($seenRequired[$key])) { continue; }
    $seenRequired[$key] = true;
    $requiredExtensions[] = $name;
  }
  $recommendedExtensions = [];
  $requiredLibraries = [];
  $iniText = normalizeIniRequirementText((string)($row['php_required_ini'] ?? ''));
  $iniRules = parseIniRequirements($iniText);
  $hasRequirements = count($requiredExtensions) > 0 || count($iniRules) > 0;
  return [
    'required_extensions' => $requiredExtensions,
    'recommended_extensions' => $recommendedExtensions,
    'required_libraries' => $requiredLibraries,
    'ini_text' => $iniText,
    'ini_rules' => $iniRules,
    'has_requirements' => $hasRequirements,
  ];
}

function phpExtensionsMapFromPayload(array $payload): array {
  $map = [];
  foreach (($payload['extensions'] ?? []) as $ext) {
    if (!is_array($ext)) { continue; }
    $name = trim((string)($ext['name'] ?? ''));
    if ($name === '') { continue; }
    $map[strtolower($name)] = $name;
  }
  return $map;
}

function phpIniMapFromPayload(array $payload): array {
  $map = [];
  foreach (($payload['ini'] ?? []) as $item) {
    if (!is_array($item)) { continue; }
    $directive = trim((string)($item['directive'] ?? ''));
    if ($directive === '') { continue; }
    $map[strtolower($directive)] = trim((string)($item['local_value'] ?? ''));
  }
  return $map;
}

function evaluateSystemPhpCompatibilityForVm(array $requirements, ?array $vm, string $environment): array {
  $base = [
    'environment' => $environment,
    'vm_id' => (int)($vm['id'] ?? 0),
    'vm_name' => $vm ? vmLabelFromRow($vm) : '',
    'status' => 'not_applicable',
    'label' => 'Sem requisitos PHP',
    'missing_required_extensions' => [],
    'missing_recommended_extensions' => [],
    'unverified_libraries' => [],
    'unmet_ini_rules' => [],
    'notes' => [],
  ];

  if (!($requirements['has_requirements'] ?? false)) { return $base; }

  if (!$vm || (int)($vm['id'] ?? 0) <= 0) {
    $base['status'] = 'no_vm';
    $base['label'] = 'Sem VM vinculada';
    $base['notes'][] = 'Ambiente sem VM vinculada.';
    return $base;
  }

  if (!vmSupportsDiagnosticTech($vm, 'php')) {
    $base['status'] = 'incompatible';
    $base['label'] = 'Incompatível';
    $base['notes'][] = 'VM sem tecnologia PHP cadastrada.';
    return $base;
  }

  try {
    $entry = loadVmDiagnosticEntry($vm, 'php');
  } catch (Throwable $e) {
    $base['status'] = 'warning';
    $base['label'] = 'Diagnóstico inválido';
    $base['notes'][] = 'JSON de diagnóstico PHP inválido para esta VM.';
    return $base;
  }

  if (!is_array($entry) || !($entry['has_file'] ?? false) || !is_array($entry['json'] ?? null)) {
    $base['status'] = 'warning';
    $base['label'] = 'Sem diagnóstico';
    $base['notes'][] = 'VM sem arquivo de diagnóstico PHP para validação automática.';
    return $base;
  }

  $payload = $entry['json'];
  $extensionsMap = phpExtensionsMapFromPayload($payload);
  $iniMap = phpIniMapFromPayload($payload);

  $missingRequired = [];
  foreach (($requirements['required_extensions'] ?? []) as $ext) {
    $name = trim((string)$ext);
    if ($name === '') { continue; }
    if (!isset($extensionsMap[strtolower($name)])) { $missingRequired[] = $name; }
  }

  $missingRecommended = [];
  foreach (($requirements['recommended_extensions'] ?? []) as $ext) {
    $name = trim((string)$ext);
    if ($name === '') { continue; }
    if (!isset($extensionsMap[strtolower($name)])) { $missingRecommended[] = $name; }
  }

  $unmetIni = [];
  foreach (($requirements['ini_rules'] ?? []) as $rule) {
    if (!is_array($rule)) { continue; }
    $directiveKey = strtolower(trim((string)($rule['directive_key'] ?? '')));
    $directive = trim((string)($rule['directive'] ?? ''));
    if ($directiveKey === '' || $directive === '') { continue; }
    $actual = (string)($iniMap[$directiveKey] ?? '');
    $operator = (string)($rule['operator'] ?? 'exists');
    $expected = (string)($rule['expected'] ?? '');
    $ok = compareIniRequirementValues($actual, $expected, $operator);
    if ($ok) { continue; }
    $unmetIni[] = [
      'directive' => $directive,
      'operator' => $operator,
      'expected' => $expected,
      'actual' => $actual,
      'raw' => (string)($rule['raw'] ?? $directive),
    ];
  }

  $base['missing_required_extensions'] = $missingRequired;
  $base['missing_recommended_extensions'] = $missingRecommended;
  $base['unverified_libraries'] = array_values(array_filter(array_map('trim', $requirements['required_libraries'] ?? [])));
  $base['unmet_ini_rules'] = $unmetIni;

  if (count($missingRequired) > 0 || count($unmetIni) > 0) {
    $base['status'] = 'incompatible';
    $base['label'] = 'Incompatível';
    return $base;
  }

  if (count($missingRecommended) > 0 || count($base['unverified_libraries']) > 0) {
    $base['status'] = 'warning';
    $base['label'] = 'Parcialmente compatível';
    if (count($base['unverified_libraries']) > 0) {
      $base['notes'][] = 'Bibliotecas/pacotes requeridos não são verificáveis automaticamente via diagnóstico.';
    }
    return $base;
  }

  $base['status'] = 'compatible';
  $base['label'] = 'Compatível';
  return $base;
}

function systemPhpCompatibilityLabel(string $status): string {
  $map = [
    'compatible' => 'Compatível',
    'warning' => 'Parcial',
    'incompatible' => 'Incompatível',
    'no_vm' => 'Sem VM',
    'not_applicable' => 'N/A',
  ];
  return $map[$status] ?? 'N/A';
}

function buildSystemPhpCompatibility(array $row, callable $vmLoader): array {
  $requirements = phpRequirementsFromSystemRow($row);
  $environments = [
    evaluateSystemPhpCompatibilityForVm($requirements, $vmLoader((int)($row['vm_id'] ?? 0)), 'Producao'),
    evaluateSystemPhpCompatibilityForVm($requirements, $vmLoader((int)($row['vm_homolog_id'] ?? 0)), 'Homologacao'),
    evaluateSystemPhpCompatibilityForVm($requirements, $vmLoader((int)($row['vm_dev_id'] ?? 0)), 'Desenvolvimento'),
  ];

  $statuses = array_map(fn($item) => (string)($item['status'] ?? 'not_applicable'), $environments);
  $overall = 'not_applicable';
  if ($requirements['has_requirements']) {
    if (in_array('incompatible', $statuses, true)) { $overall = 'incompatible'; }
    elseif (in_array('warning', $statuses, true)) { $overall = 'warning'; }
    elseif (in_array('compatible', $statuses, true)) { $overall = 'compatible'; }
    elseif (in_array('no_vm', $statuses, true)) { $overall = 'no_vm'; }
    else { $overall = 'warning'; }
  }

  $issues = 0;
  foreach ($environments as $env) {
    if (!is_array($env)) { continue; }
    $issues += count($env['missing_required_extensions'] ?? []);
    $issues += count($env['unmet_ini_rules'] ?? []);
    $issues += count($env['missing_recommended_extensions'] ?? []);
  }

  return [
    'has_requirements' => (bool)($requirements['has_requirements'] ?? false),
    'status' => $overall,
    'label' => systemPhpCompatibilityLabel($overall),
    'issues' => $issues,
    'environments' => $environments,
  ];
}

function rRequirementsFromSystemRow(array $row): array {
  $requiredPackages = splitCsvList((string)($row['r_required_packages'] ?? ''));
  return [
    'required_packages' => $requiredPackages,
    'has_requirements' => count($requiredPackages) > 0,
  ];
}

function rPackagesMapFromPayload(array $payload): array {
  $map = [];
  foreach (($payload['packages'] ?? []) as $pkg) {
    if (!is_array($pkg)) { continue; }
    $name = trim((string)($pkg['Package'] ?? $pkg['package'] ?? $pkg['name'] ?? ''));
    if ($name === '') { continue; }
    $version = trim((string)($pkg['Version'] ?? $pkg['version'] ?? ''));
    $map[strtolower($name)] = ['name' => $name, 'version' => $version];
  }
  return $map;
}

function evaluateSystemRCompatibilityForVm(array $requirements, ?array $vm, string $environment): array {
  $base = [
    'environment' => $environment,
    'vm_id' => (int)($vm['id'] ?? 0),
    'vm_name' => $vm ? vmLabelFromRow($vm) : '',
    'status' => 'not_applicable',
    'label' => 'Sem requisitos R',
    'missing_required_packages' => [],
    'notes' => [],
  ];

  if (!($requirements['has_requirements'] ?? false)) { return $base; }

  if (!$vm || (int)($vm['id'] ?? 0) <= 0) {
    $base['status'] = 'no_vm';
    $base['label'] = 'Sem VM vinculada';
    $base['notes'][] = 'Ambiente sem VM vinculada.';
    return $base;
  }

  if (!vmSupportsDiagnosticTech($vm, 'r')) {
    $base['status'] = 'incompatible';
    $base['label'] = 'Incompatível';
    $base['notes'][] = 'VM sem tecnologia R cadastrada.';
    return $base;
  }

  try {
    $entry = loadVmDiagnosticEntry($vm, 'r');
  } catch (Throwable $e) {
    $base['status'] = 'warning';
    $base['label'] = 'Diagnóstico inválido';
    $base['notes'][] = 'JSON de diagnóstico R inválido para esta VM.';
    return $base;
  }

  if (!is_array($entry) || !($entry['has_file'] ?? false) || !is_array($entry['json'] ?? null)) {
    $base['status'] = 'warning';
    $base['label'] = 'Sem diagnóstico';
    $base['notes'][] = 'VM sem arquivo de diagnóstico R para validação automática.';
    return $base;
  }

  $packagesMap = rPackagesMapFromPayload($entry['json']);
  $missingRequired = [];
  foreach (($requirements['required_packages'] ?? []) as $pkg) {
    $name = trim((string)$pkg);
    if ($name === '') { continue; }
    if (!isset($packagesMap[strtolower($name)])) { $missingRequired[] = $name; }
  }
  $base['missing_required_packages'] = $missingRequired;

  if (count($missingRequired) > 0) {
    $base['status'] = 'incompatible';
    $base['label'] = 'Incompatível';
    return $base;
  }

  $base['status'] = 'compatible';
  $base['label'] = 'Compatível';
  return $base;
}

function systemRCompatibilityLabel(string $status): string {
  $map = [
    'compatible' => 'Compatível',
    'warning' => 'Parcial',
    'incompatible' => 'Incompatível',
    'no_vm' => 'Sem VM',
    'not_applicable' => 'N/A',
  ];
  return $map[$status] ?? 'N/A';
}

function buildSystemRCompatibility(array $row, callable $vmLoader): array {
  $requirements = rRequirementsFromSystemRow($row);
  $environments = [
    evaluateSystemRCompatibilityForVm($requirements, $vmLoader((int)($row['vm_id'] ?? 0)), 'Producao'),
    evaluateSystemRCompatibilityForVm($requirements, $vmLoader((int)($row['vm_homolog_id'] ?? 0)), 'Homologacao'),
    evaluateSystemRCompatibilityForVm($requirements, $vmLoader((int)($row['vm_dev_id'] ?? 0)), 'Desenvolvimento'),
  ];

  $statuses = array_map(fn($item) => (string)($item['status'] ?? 'not_applicable'), $environments);
  $overall = 'not_applicable';
  if ($requirements['has_requirements']) {
    if (in_array('incompatible', $statuses, true)) { $overall = 'incompatible'; }
    elseif (in_array('warning', $statuses, true)) { $overall = 'warning'; }
    elseif (in_array('compatible', $statuses, true)) { $overall = 'compatible'; }
    elseif (in_array('no_vm', $statuses, true)) { $overall = 'no_vm'; }
    else { $overall = 'warning'; }
  }

  $issues = 0;
  foreach ($environments as $env) {
    if (!is_array($env)) { continue; }
    $issues += count($env['missing_required_packages'] ?? []);
  }

  return [
    'has_requirements' => (bool)($requirements['has_requirements'] ?? false),
    'status' => $overall,
    'label' => systemRCompatibilityLabel($overall),
    'issues' => $issues,
    'environments' => $environments,
  ];
}

function enrichSystemRowWithPhpCompatibility(array $row, callable $vmLoader): array {
  $phpRequirements = phpRequirementsFromSystemRow($row);
  $row['php_required_extensions'] = implode(',', $phpRequirements['required_extensions']);
  $row['php_recommended_extensions'] = implode(',', $phpRequirements['recommended_extensions']);
  $row['php_required_libraries'] = implode(',', $phpRequirements['required_libraries']);
  $row['php_required_ini'] = (string)($phpRequirements['ini_text'] ?? '');
  $row['php_required_extensions_list'] = $phpRequirements['required_extensions'];
  $row['php_recommended_extensions_list'] = $phpRequirements['recommended_extensions'];
  $row['php_required_libraries_list'] = $phpRequirements['required_libraries'];
  $row['php_required_ini_list'] = array_map(fn($rule) => (string)($rule['raw'] ?? ''), $phpRequirements['ini_rules'] ?? []);
  $row['php_requirements'] = [
    'required_extensions' => $phpRequirements['required_extensions'],
    'recommended_extensions' => $phpRequirements['recommended_extensions'],
    'required_libraries' => $phpRequirements['required_libraries'],
    'required_ini' => $row['php_required_ini_list'],
    'has_requirements' => (bool)($phpRequirements['has_requirements'] ?? false),
  ];

  $rRequirements = rRequirementsFromSystemRow($row);
  $row['r_required_packages'] = implode(',', $rRequirements['required_packages']);
  $row['r_required_packages_list'] = $rRequirements['required_packages'];
  $row['r_requirements'] = [
    'required_packages' => $rRequirements['required_packages'],
    'has_requirements' => (bool)($rRequirements['has_requirements'] ?? false),
  ];

  $row['php_compatibility'] = buildSystemPhpCompatibility($row, $vmLoader);
  $row['r_compatibility'] = buildSystemRCompatibility($row, $vmLoader);
  return $row;
}

function normalizePortListValue($raw, ?string &$error = null): string {
  $error = null;
  $tokens = [];
  if (is_array($raw)) {
    foreach ($raw as $entry) {
      $parts = preg_split('/[\r\n,;\s]+/', (string)$entry) ?: [];
      foreach ($parts as $part) {
        $value = trim((string)$part);
        if ($value !== '') { $tokens[] = $value; }
      }
    }
  } else {
    $parts = preg_split('/[\r\n,;\s]+/', (string)$raw) ?: [];
    foreach ($parts as $part) {
      $value = trim((string)$part);
      if ($value !== '') { $tokens[] = $value; }
    }
  }

  $seen = [];
  $out = [];
  foreach ($tokens as $token) {
    if (!preg_match('/^\d+$/', $token)) {
      $error = "Porta de execucao invalida: {$token}.";
      return '';
    }
    $port = (int)$token;
    if ($port < 1 || $port > 65535) {
      $error = "Porta de execucao fora da faixa: {$token}.";
      return '';
    }
    $normalized = (string)$port;
    if (isset($seen[$normalized])) { continue; }
    $seen[$normalized] = true;
    $out[] = $normalized;
  }

  return implode(',', $out);
}

function normalizeSinglePortValue($raw, ?string &$error = null, string $label = 'Porta da instancia'): string {
  $error = null;
  $value = trim((string)$raw);
  if ($value === '') { return ''; }
  if (!preg_match('/^\d+$/', $value)) {
    $error = "{$label} invalida: {$value}.";
    return '';
  }
  $port = (int)$value;
  if ($port < 1 || $port > 65535) {
    $error = "{$label} fora da faixa: {$value}.";
    return '';
  }
  return (string)$port;
}

function boolFromMixed($value): bool {
  if (is_bool($value)) { return $value; }
  if (is_int($value) || is_float($value)) { return ((int)$value) !== 0; }
  $normalized = strtolower(trim((string)$value));
  if ($normalized === '') { return false; }
  return in_array($normalized, ['1', 'true', 'sim', 'yes', 'on'], true);
}

function vmTechListFromRow(array $row): array {
  if (is_array($row['vm_tech_list'] ?? null)) {
    return array_values(array_filter(array_map(fn($v) => trim((string)$v), $row['vm_tech_list'])));
  }
  $appServer = splitCsvList((string)($row['vm_app_server'] ?? ''));
  if ($appServer) { return $appServer; }
  return splitCsvList((string)($row['vm_tech'] ?? ''));
}

function vmAppServerListFromRow(array $row): array {
  if (is_array($row['vm_app_server_list'] ?? null)) {
    return array_values(array_filter(array_map(fn($v) => trim((string)$v), $row['vm_app_server_list'])));
  }
  $list = splitCsvList((string)($row['vm_app_server'] ?? ''));
  if ($list) { return $list; }
  return splitCsvList((string)($row['vm_tech'] ?? ''));
}

function vmWebServerListFromRow(array $row): array {
  if (is_array($row['vm_web_server_list'] ?? null)) {
    return array_values(array_filter(array_map(fn($v) => trim((string)$v), $row['vm_web_server_list'])));
  }
  return splitCsvList((string)($row['vm_web_server'] ?? ''));
}

function vmContainerToolListFromRow(array $row): array {
  if (is_array($row['vm_container_tool_list'] ?? null)) {
    return array_values(array_filter(array_map(fn($v) => trim((string)$v), $row['vm_container_tool_list'])));
  }
  return splitCsvList((string)($row['vm_container_tool'] ?? ''));
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
    $row['ip'] = packVmIpListValue((string)($row['ip'] ?? ''));
    $row['ip_list'] = normalizeVmIpListValue($row['ip']);
    $row['public_ip'] = packVmIpListValue((string)($row['public_ip'] ?? ''));
    $row['public_ip_list'] = normalizeVmIpListValue($row['public_ip']);
    $row['vm_category'] = trim((string)($row['vm_category'] ?? '')) !== '' ? trim((string)$row['vm_category']) : 'Producao';
    $row['vm_type'] = trim((string)($row['vm_type'] ?? '')) !== '' ? trim((string)$row['vm_type']) : 'Sistemas';
    $row['vm_access'] = trim((string)($row['vm_access'] ?? '')) !== '' ? trim((string)$row['vm_access']) : 'Interno';
    $row['vm_administration'] = trim((string)($row['vm_administration'] ?? '')) !== '' ? trim((string)$row['vm_administration']) : 'SEI';
    $row['vm_instances'] = trim((string)($row['vm_instances'] ?? ''));
    $row['vm_instances_list'] = normalizeVmInstancesValue($row['vm_instances']);
    $row['vm_language'] = trim((string)($row['vm_language'] ?? ''));
    $row['vm_language_list'] = vmLanguageListFromRow($row);
    $row['vm_target_version'] = trim((string)($row['vm_target_version'] ?? ''));
    $row['vm_tech'] = trim((string)($row['vm_tech'] ?? ''));
    $row['vm_app_server'] = trim((string)($row['vm_app_server'] ?? ''));
    if ($row['vm_app_server'] === '' && $row['vm_tech'] !== '') { $row['vm_app_server'] = $row['vm_tech']; }
    $row['vm_app_server_list'] = vmAppServerListFromRow($row);
    $row['vm_web_server'] = trim((string)($row['vm_web_server'] ?? ''));
    $row['vm_web_server_list'] = vmWebServerListFromRow($row);
    $row['vm_containerization'] = boolFromMixed($row['vm_containerization'] ?? 0) ? 1 : 0;
    $row['vm_container_tool'] = trim((string)($row['vm_container_tool'] ?? ''));
    $row['vm_container_tool_list'] = $row['vm_containerization'] > 0 ? vmContainerToolListFromRow($row) : [];
    $row['vm_runtime_port'] = trim((string)($row['vm_runtime_port'] ?? ''));
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
    $row['ip'] = packVmIpListValue((string)($row['ip'] ?? ''));
    $row['ip_list'] = normalizeVmIpListValue($row['ip']);
    $row['public_ip'] = packVmIpListValue((string)($row['public_ip'] ?? ''));
    $row['public_ip_list'] = normalizeVmIpListValue($row['public_ip']);
    $row['vm_category'] = trim((string)($row['vm_category'] ?? '')) !== '' ? trim((string)$row['vm_category']) : 'Producao';
    $row['vm_type'] = trim((string)($row['vm_type'] ?? '')) !== '' ? trim((string)$row['vm_type']) : 'Sistemas';
    $row['vm_access'] = trim((string)($row['vm_access'] ?? '')) !== '' ? trim((string)$row['vm_access']) : 'Interno';
    $row['vm_administration'] = trim((string)($row['vm_administration'] ?? '')) !== '' ? trim((string)$row['vm_administration']) : 'SEI';
    $row['vm_instances'] = trim((string)($row['vm_instances'] ?? ''));
    $row['vm_instances_list'] = normalizeVmInstancesValue($row['vm_instances']);
    $row['vm_language'] = trim((string)($row['vm_language'] ?? ''));
    $row['vm_language_list'] = vmLanguageListFromRow($row);
    $row['vm_target_version'] = trim((string)($row['vm_target_version'] ?? ''));
    $row['vm_tech'] = trim((string)($row['vm_tech'] ?? ''));
    $row['vm_app_server'] = trim((string)($row['vm_app_server'] ?? ''));
    if ($row['vm_app_server'] === '' && $row['vm_tech'] !== '') { $row['vm_app_server'] = $row['vm_tech']; }
    $row['vm_app_server_list'] = vmAppServerListFromRow($row);
    $row['vm_web_server'] = trim((string)($row['vm_web_server'] ?? ''));
    $row['vm_web_server_list'] = vmWebServerListFromRow($row);
    $row['vm_containerization'] = boolFromMixed($row['vm_containerization'] ?? 0) ? 1 : 0;
    $row['vm_container_tool'] = trim((string)($row['vm_container_tool'] ?? ''));
    $row['vm_container_tool_list'] = $row['vm_containerization'] > 0 ? vmContainerToolListFromRow($row) : [];
    $row['vm_runtime_port'] = trim((string)($row['vm_runtime_port'] ?? ''));
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
  $st = $db->prepare("SELECT id,name,ip,public_ip,vm_category,vm_type,vm_access,vm_administration,vm_instances,vm_language,vm_target_version,vm_app_server,vm_web_server,vm_containerization,vm_container_tool,vm_runtime_port,vm_tech,diagnostic_json_ref,diagnostic_json_updated_at,diagnostic_json_ref_r,diagnostic_json_updated_at_r,os_name,os_version,vcpus,ram,disk,created_at,updated_at FROM virtual_machines WHERE id=:id");
  $st->bindValue(':id', $id, SQLITE3_INTEGER);
  $res = $st->execute();
  $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;
  if (!is_array($row)) { return null; }
  foreach ($row as $key => $value) {
    if (is_string($value)) { $row[$key] = normalizeUtf8Text($value); }
  }
  $row['id'] = (int)$row['id'];
  $row['ip'] = packVmIpListValue((string)($row['ip'] ?? ''));
  $row['ip_list'] = normalizeVmIpListValue($row['ip']);
  $row['public_ip'] = packVmIpListValue((string)($row['public_ip'] ?? ''));
  $row['public_ip_list'] = normalizeVmIpListValue($row['public_ip']);
  $row['vm_category'] = trim((string)($row['vm_category'] ?? '')) !== '' ? trim((string)$row['vm_category']) : 'Producao';
  $row['vm_type'] = trim((string)($row['vm_type'] ?? '')) !== '' ? trim((string)$row['vm_type']) : 'Sistemas';
  $row['vm_access'] = trim((string)($row['vm_access'] ?? '')) !== '' ? trim((string)$row['vm_access']) : 'Interno';
  $row['vm_administration'] = trim((string)($row['vm_administration'] ?? '')) !== '' ? trim((string)$row['vm_administration']) : 'SEI';
  $row['vm_instances'] = trim((string)($row['vm_instances'] ?? ''));
  $row['vm_instances_list'] = normalizeVmInstancesValue($row['vm_instances']);
  $row['vm_language'] = trim((string)($row['vm_language'] ?? ''));
  $row['vm_language_list'] = vmLanguageListFromRow($row);
  $row['vm_target_version'] = trim((string)($row['vm_target_version'] ?? ''));
  $row['vm_tech'] = trim((string)($row['vm_tech'] ?? ''));
  $row['vm_app_server'] = trim((string)($row['vm_app_server'] ?? ''));
  if ($row['vm_app_server'] === '' && $row['vm_tech'] !== '') { $row['vm_app_server'] = $row['vm_tech']; }
  $row['vm_app_server_list'] = vmAppServerListFromRow($row);
  $row['vm_web_server'] = trim((string)($row['vm_web_server'] ?? ''));
  $row['vm_web_server_list'] = vmWebServerListFromRow($row);
  $row['vm_containerization'] = boolFromMixed($row['vm_containerization'] ?? 0) ? 1 : 0;
  $row['vm_container_tool'] = trim((string)($row['vm_container_tool'] ?? ''));
  $row['vm_container_tool_list'] = $row['vm_containerization'] > 0 ? vmContainerToolListFromRow($row) : [];
  $row['vm_runtime_port'] = trim((string)($row['vm_runtime_port'] ?? ''));
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
  $st = $db->prepare("SELECT id,name,ip,public_ip,vm_category,vm_type,vm_access,vm_administration,vm_instances,vm_language,vm_target_version,vm_app_server,vm_web_server,vm_containerization,vm_container_tool,vm_runtime_port,vm_tech,diagnostic_json_ref,diagnostic_json_updated_at,diagnostic_json_ref_r,diagnostic_json_updated_at_r,os_name,os_version,vcpus,ram,disk,created_at,updated_at FROM virtual_machines WHERE id=:id");
  $st->bindValue(':id', $id, PDO::PARAM_INT);
  $st->execute();
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!is_array($row)) { return null; }
  foreach ($row as $key => $value) {
    if (is_string($value)) { $row[$key] = normalizeUtf8Text($value); }
  }
  $row['id'] = (int)$row['id'];
  $row['ip'] = packVmIpListValue((string)($row['ip'] ?? ''));
  $row['ip_list'] = normalizeVmIpListValue($row['ip']);
  $row['public_ip'] = packVmIpListValue((string)($row['public_ip'] ?? ''));
  $row['public_ip_list'] = normalizeVmIpListValue($row['public_ip']);
  $row['vm_category'] = trim((string)($row['vm_category'] ?? '')) !== '' ? trim((string)$row['vm_category']) : 'Producao';
  $row['vm_type'] = trim((string)($row['vm_type'] ?? '')) !== '' ? trim((string)$row['vm_type']) : 'Sistemas';
  $row['vm_access'] = trim((string)($row['vm_access'] ?? '')) !== '' ? trim((string)$row['vm_access']) : 'Interno';
  $row['vm_administration'] = trim((string)($row['vm_administration'] ?? '')) !== '' ? trim((string)$row['vm_administration']) : 'SEI';
  $row['vm_instances'] = trim((string)($row['vm_instances'] ?? ''));
  $row['vm_instances_list'] = normalizeVmInstancesValue($row['vm_instances']);
  $row['vm_language'] = trim((string)($row['vm_language'] ?? ''));
  $row['vm_language_list'] = vmLanguageListFromRow($row);
  $row['vm_target_version'] = trim((string)($row['vm_target_version'] ?? ''));
  $row['vm_tech'] = trim((string)($row['vm_tech'] ?? ''));
  $row['vm_app_server'] = trim((string)($row['vm_app_server'] ?? ''));
  if ($row['vm_app_server'] === '' && $row['vm_tech'] !== '') { $row['vm_app_server'] = $row['vm_tech']; }
  $row['vm_app_server_list'] = vmAppServerListFromRow($row);
  $row['vm_web_server'] = trim((string)($row['vm_web_server'] ?? ''));
  $row['vm_web_server_list'] = vmWebServerListFromRow($row);
  $row['vm_containerization'] = boolFromMixed($row['vm_containerization'] ?? 0) ? 1 : 0;
  $row['vm_container_tool'] = trim((string)($row['vm_container_tool'] ?? ''));
  $row['vm_container_tool_list'] = $row['vm_containerization'] > 0 ? vmContainerToolListFromRow($row) : [];
  $row['vm_runtime_port'] = trim((string)($row['vm_runtime_port'] ?? ''));
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
  $row['ip'] = packVmIpListValue((string)($row['ip'] ?? ''));
  $row['ip_list'] = normalizeVmIpListValue($row['ip']);
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
  $row['ip'] = packVmIpListValue((string)($row['ip'] ?? ''));
  $row['ip_list'] = normalizeVmIpListValue($row['ip']);
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
  $ip = packVmIpListValue((string)($vm['ip'] ?? ''));
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
  $row['db_instance_port'] = trim((string)($row['db_instance_port'] ?? ''));
  $row['db_instance_homolog_name'] = trim((string)($row['db_instance_homolog_name'] ?? ''));
  $row['db_instance_homolog_ip'] = trim((string)($row['db_instance_homolog_ip'] ?? ''));
  $row['db_instance_homolog_port'] = trim((string)($row['db_instance_homolog_port'] ?? ''));
  if ($row['db_instance_ip'] === '') { $row['db_instance_ip'] = firstVmIpValue((string)($row['vm_ip'] ?? '')); }
  if ($row['db_instance_homolog_ip'] === '') { $row['db_instance_homolog_ip'] = firstVmIpValue((string)($row['vm_homolog_ip'] ?? '')); }
  if ($row['db_instance_ip'] === '') { $row['db_instance_port'] = ''; }
  if ($row['db_instance_homolog_ip'] === '') { $row['db_instance_homolog_port'] = ''; }
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

function normalizeTicketTargetType(string $raw): string {
  return strtolower(trim($raw)) === 'vm' ? 'vm' : 'system';
}

function ticketSelectSql(): string {
  return "SELECT
    t.*,
    s.name AS system_name_ref,
    vm.name AS vm_name_ref
  FROM tickets t
  LEFT JOIN systems s ON s.id = t.system_id
  LEFT JOIN virtual_machines vm ON vm.id = t.vm_id";
}

function normalizeTicketRow(array $row): array {
  foreach ($row as $key => $value) {
    if (is_string($value)) { $row[$key] = normalizeUtf8Text($value); }
  }
  $row['id'] = (int)($row['id'] ?? 0);
  $row['target_type'] = normalizeTicketTargetType((string)($row['target_type'] ?? 'system'));
  $row['system_id'] = isset($row['system_id']) && $row['system_id'] !== null && (int)$row['system_id'] > 0 ? (int)$row['system_id'] : null;
  $row['vm_id'] = isset($row['vm_id']) && $row['vm_id'] !== null && (int)$row['vm_id'] > 0 ? (int)$row['vm_id'] : null;
  $row['ticket_number'] = trim((string)($row['ticket_number'] ?? ''));
  $row['description'] = trim((string)($row['description'] ?? ''));
  $row['created_at'] = trim((string)($row['created_at'] ?? ''));
  $row['updated_at'] = trim((string)($row['updated_at'] ?? ''));
  $systemName = trim((string)($row['system_name_ref'] ?? ''));
  $vmName = trim((string)($row['vm_name_ref'] ?? ''));
  $row['target_name'] = $row['target_type'] === 'vm' ? ($vmName !== '' ? $vmName : '-') : ($systemName !== '' ? $systemName : '-');
  return $row;
}

function listTicketsSqlite3(SQLite3 $db): array {
  $res = $db->query(ticketSelectSql() . " ORDER BY t.created_at DESC, t.id DESC");
  $out = [];
  while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $out[] = normalizeTicketRow($row);
  }
  return $out;
}

function listTicketsPdo(PDO $db): array {
  $rows = $db->query(ticketSelectSql() . " ORDER BY t.created_at DESC, t.id DESC")->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as &$row) { $row = normalizeTicketRow($row); }
  unset($row);
  return $rows;
}

function fetchTicketByIdSqlite3(SQLite3 $db, int $id): ?array {
  $st = $db->prepare(ticketSelectSql() . " WHERE t.id=:id LIMIT 1");
  $st->bindValue(':id', $id, SQLITE3_INTEGER);
  $res = $st->execute();
  $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;
  if (!is_array($row)) { return null; }
  return normalizeTicketRow($row);
}

function fetchTicketByIdPdo(PDO $db, int $id): ?array {
  $st = $db->prepare(ticketSelectSql() . " WHERE t.id=:id LIMIT 1");
  $st->bindValue(':id', $id, PDO::PARAM_INT);
  $st->execute();
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!is_array($row)) { return null; }
  return normalizeTicketRow($row);
}

function fetchActiveSystemNameSqlite3(SQLite3 $db, int $id): ?string {
  if ($id <= 0) { return null; }
  $st = $db->prepare("SELECT name FROM systems WHERE id=:id AND IFNULL(archived,0)=0 LIMIT 1");
  $st->bindValue(':id', $id, SQLITE3_INTEGER);
  $res = $st->execute();
  $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;
  if (!is_array($row)) { return null; }
  $name = trim((string)($row['name'] ?? ''));
  return $name !== '' ? $name : null;
}

function fetchActiveSystemNamePdo(PDO $db, int $id): ?string {
  if ($id <= 0) { return null; }
  $st = $db->prepare("SELECT name FROM systems WHERE id=:id AND IFNULL(archived,0)=0 LIMIT 1");
  $st->bindValue(':id', $id, PDO::PARAM_INT);
  $st->execute();
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!is_array($row)) { return null; }
  $name = trim((string)($row['name'] ?? ''));
  return $name !== '' ? $name : null;
}

function fetchActiveVmNameSqlite3(SQLite3 $db, int $id): ?string {
  if ($id <= 0) { return null; }
  $st = $db->prepare("SELECT name FROM virtual_machines WHERE id=:id AND IFNULL(archived,0)=0 LIMIT 1");
  $st->bindValue(':id', $id, SQLITE3_INTEGER);
  $res = $st->execute();
  $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;
  if (!is_array($row)) { return null; }
  $name = trim((string)($row['name'] ?? ''));
  return $name !== '' ? $name : null;
}

function fetchActiveVmNamePdo(PDO $db, int $id): ?string {
  if ($id <= 0) { return null; }
  $st = $db->prepare("SELECT name FROM virtual_machines WHERE id=:id AND IFNULL(archived,0)=0 LIMIT 1");
  $st->bindValue(':id', $id, PDO::PARAM_INT);
  $st->execute();
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!is_array($row)) { return null; }
  $name = trim((string)($row['name'] ?? ''));
  return $name !== '' ? $name : null;
}

function startAppSession(): void {
  if (session_status() === PHP_SESSION_ACTIVE) { return; }
  session_name('SEIPORTFOLIOSESSID');
  session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure'   => !empty($_SERVER['HTTPS']),
  ]);
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
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
    'role' => normalizeRole((string)($row['role'] ?? 'edicao')),
    'active' => (int)($row['active'] ?? 0),
  ];
}

function sessionAuthUser(): ?array {
  $raw = $_SESSION['auth_user'] ?? null;
  if (!is_array($raw)) { return null; }
  $user = publicUserPayload($raw);
  if ($user['id'] <= 0 || $user['active'] !== 1) { return null; }
  // Perfil legado de leitura nao deve manter sessao autenticada.
  if (normalizeRole((string)($user['role'] ?? '')) === 'leitura') { return null; }
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

function listUsersSqlite3(SQLite3 $db): array {
  $res = $db->query("SELECT id,username,full_name,role,active,created_at,updated_at FROM users ORDER BY username COLLATE NOCASE");
  $out = [];
  while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    if (!is_array($row)) { continue; }
    $out[] = [
      'id' => (int)($row['id'] ?? 0),
      'username' => trim((string)($row['username'] ?? '')),
      'full_name' => normalizeUtf8Text(trim((string)($row['full_name'] ?? ''))),
      'role' => normalizeRole((string)($row['role'] ?? 'edicao')),
      'active' => (int)($row['active'] ?? 0),
      'created_at' => trim((string)($row['created_at'] ?? '')),
      'updated_at' => trim((string)($row['updated_at'] ?? '')),
    ];
  }
  return $out;
}

function listUsersPdo(PDO $db): array {
  $rows = $db->query("SELECT id,username,full_name,role,active,created_at,updated_at FROM users ORDER BY username COLLATE NOCASE")->fetchAll(PDO::FETCH_ASSOC);
  $out = [];
  foreach ($rows as $row) {
    if (!is_array($row)) { continue; }
    $out[] = [
      'id' => (int)($row['id'] ?? 0),
      'username' => trim((string)($row['username'] ?? '')),
      'full_name' => normalizeUtf8Text(trim((string)($row['full_name'] ?? ''))),
      'role' => normalizeRole((string)($row['role'] ?? 'edicao')),
      'active' => (int)($row['active'] ?? 0),
      'created_at' => trim((string)($row['created_at'] ?? '')),
      'updated_at' => trim((string)($row['updated_at'] ?? '')),
    ];
  }
  return $out;
}

function fetchUserByUsernameExcludingIdSqlite3(SQLite3 $db, string $username, int $excludeId = 0): ?array {
  $name = trim($username);
  if ($name === '') { return null; }
  if ($excludeId > 0) {
    $st = $db->prepare("SELECT id,username,password_hash,full_name,role,active FROM users WHERE lower(username)=lower(:username) AND id<>:id LIMIT 1");
    $st->bindValue(':id', $excludeId, SQLITE3_INTEGER);
  } else {
    $st = $db->prepare("SELECT id,username,password_hash,full_name,role,active FROM users WHERE lower(username)=lower(:username) LIMIT 1");
  }
  $st->bindValue(':username', $name, SQLITE3_TEXT);
  $res = $st->execute();
  $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;
  return is_array($row) ? $row : null;
}

function fetchUserByUsernameExcludingIdPdo(PDO $db, string $username, int $excludeId = 0): ?array {
  $name = trim($username);
  if ($name === '') { return null; }
  if ($excludeId > 0) {
    $st = $db->prepare("SELECT id,username,password_hash,full_name,role,active FROM users WHERE lower(username)=lower(:username) AND id<>:id LIMIT 1");
    $st->bindValue(':id', $excludeId, PDO::PARAM_INT);
  } else {
    $st = $db->prepare("SELECT id,username,password_hash,full_name,role,active FROM users WHERE lower(username)=lower(:username) LIMIT 1");
  }
  $st->bindValue(':username', $name, PDO::PARAM_STR);
  $st->execute();
  $row = $st->fetch(PDO::FETCH_ASSOC);
  return is_array($row) ? $row : null;
}

function countActiveAdminsSqlite3(SQLite3 $db): int {
  return (int)$db->querySingle("SELECT COUNT(*) FROM users WHERE lower(role)='admin' AND IFNULL(active,0)=1");
}

function countActiveAdminsPdo(PDO $db): int {
  return (int)$db->query("SELECT COUNT(*) FROM users WHERE lower(role)='admin' AND IFNULL(active,0)=1")->fetchColumn();
}

function insertUserSqlite3(SQLite3 $db, string $username, string $passwordHash, string $fullName, string $role, int $active): int {
  $st = $db->prepare("INSERT INTO users(username,password_hash,full_name,role,active,created_at,updated_at) VALUES(:username,:password_hash,:full_name,:role,:active,datetime('now','localtime'),datetime('now','localtime'))");
  $st->bindValue(':username', trim($username), SQLITE3_TEXT);
  $st->bindValue(':password_hash', $passwordHash, SQLITE3_TEXT);
  $st->bindValue(':full_name', normalizeUtf8Text(trim($fullName)), SQLITE3_TEXT);
  $st->bindValue(':role', normalizeRole($role), SQLITE3_TEXT);
  $st->bindValue(':active', $active > 0 ? 1 : 0, SQLITE3_INTEGER);
  $res = $st->execute();
  if (!$res) { return 0; }
  return (int)$db->lastInsertRowID();
}

function insertUserPdo(PDO $db, string $username, string $passwordHash, string $fullName, string $role, int $active): int {
  $st = $db->prepare("INSERT INTO users(username,password_hash,full_name,role,active,created_at,updated_at) VALUES(:username,:password_hash,:full_name,:role,:active,datetime('now','localtime'),datetime('now','localtime'))");
  $st->bindValue(':username', trim($username), PDO::PARAM_STR);
  $st->bindValue(':password_hash', $passwordHash, PDO::PARAM_STR);
  $st->bindValue(':full_name', normalizeUtf8Text(trim($fullName)), PDO::PARAM_STR);
  $st->bindValue(':role', normalizeRole($role), PDO::PARAM_STR);
  $st->bindValue(':active', $active > 0 ? 1 : 0, PDO::PARAM_INT);
  $st->execute();
  return (int)$db->lastInsertId();
}

function updateUserProfileSqlite3(SQLite3 $db, int $id, string $username, string $fullName, string $role, int $active, ?string $passwordHash = null): bool {
  if ($passwordHash === null) {
    $st = $db->prepare("UPDATE users SET username=:username,full_name=:full_name,role=:role,active=:active,updated_at=datetime('now','localtime') WHERE id=:id");
  } else {
    $st = $db->prepare("UPDATE users SET username=:username,password_hash=:password_hash,full_name=:full_name,role=:role,active=:active,updated_at=datetime('now','localtime') WHERE id=:id");
    $st->bindValue(':password_hash', $passwordHash, SQLITE3_TEXT);
  }
  $st->bindValue(':username', trim($username), SQLITE3_TEXT);
  $st->bindValue(':full_name', normalizeUtf8Text(trim($fullName)), SQLITE3_TEXT);
  $st->bindValue(':role', normalizeRole($role), SQLITE3_TEXT);
  $st->bindValue(':active', $active > 0 ? 1 : 0, SQLITE3_INTEGER);
  $st->bindValue(':id', $id, SQLITE3_INTEGER);
  $res = $st->execute();
  return (bool)$res;
}

function updateUserProfilePdo(PDO $db, int $id, string $username, string $fullName, string $role, int $active, ?string $passwordHash = null): bool {
  if ($passwordHash === null) {
    $st = $db->prepare("UPDATE users SET username=:username,full_name=:full_name,role=:role,active=:active,updated_at=datetime('now','localtime') WHERE id=:id");
  } else {
    $st = $db->prepare("UPDATE users SET username=:username,password_hash=:password_hash,full_name=:full_name,role=:role,active=:active,updated_at=datetime('now','localtime') WHERE id=:id");
    $st->bindValue(':password_hash', $passwordHash, PDO::PARAM_STR);
  }
  $st->bindValue(':username', trim($username), PDO::PARAM_STR);
  $st->bindValue(':full_name', normalizeUtf8Text(trim($fullName)), PDO::PARAM_STR);
  $st->bindValue(':role', normalizeRole($role), PDO::PARAM_STR);
  $st->bindValue(':active', $active > 0 ? 1 : 0, PDO::PARAM_INT);
  $st->bindValue(':id', $id, PDO::PARAM_INT);
  $st->execute();
  return true;
}

function deleteUserByIdSqlite3(SQLite3 $db, int $id): bool {
  $st = $db->prepare("DELETE FROM users WHERE id=:id");
  $st->bindValue(':id', $id, SQLITE3_INTEGER);
  $res = $st->execute();
  if (!$res) { return false; }
  return $db->changes() > 0;
}

function deleteUserByIdPdo(PDO $db, int $id): bool {
  $st = $db->prepare("DELETE FROM users WHERE id=:id");
  $st->bindValue(':id', $id, PDO::PARAM_INT);
  $st->execute();
  return $st->rowCount() > 0;
}

function viewOnlySystemRows(array $rows): array {
  $out = [];
  foreach ($rows as $row) {
    if (!is_array($row)) { continue; }
    $out[] = [
      'id' => (int)($row['id'] ?? 0),
      'name' => normalizeUtf8Text(trim((string)($row['name'] ?? ''))),
      'url' => implode("\n", normalizeUrlListValue((string)($row['url'] ?? ''))),
      'url_list' => normalizeUrlListValue((string)($row['url'] ?? '')),
      'category' => normalizeUtf8Text(trim((string)($row['category'] ?? ''))),
      'system_group' => normalizeUtf8Text(trim((string)($row['system_group'] ?? ''))),
      'criticality' => normalizeUtf8Text(trim((string)($row['criticality'] ?? ''))),
      'description' => normalizeUtf8Text(trim((string)($row['description'] ?? ''))),
      'notes' => normalizeUtf8Text(trim((string)($row['notes'] ?? ''))),
      'status' => normalizeSystemStatus((string)($row['status'] ?? 'Ativo')),
      'owner' => normalizeUtf8Text(trim((string)($row['owner'] ?? ''))),
      'responsible_sector' => normalizeUtf8Text(trim((string)($row['responsible_sector'] ?? ''))),
      'responsible_coordinator' => normalizeUtf8Text(trim((string)($row['responsible_coordinator'] ?? ''))),
      'extension_number' => normalizeUtf8Text(trim((string)($row['extension_number'] ?? ''))),
      'email' => normalizeUtf8Text(trim((string)($row['email'] ?? ''))),
      'support' => normalizeUtf8Text(trim((string)($row['support'] ?? ''))),
      'support_contact' => normalizeUtf8Text(trim((string)($row['support_contact'] ?? ''))),
      'created_at' => trim((string)($row['created_at'] ?? '')),
      'updated_at' => trim((string)($row['updated_at'] ?? '')),
    ];
  }
  return $out;
}

function csvEscapeValue(string $value, string $delimiter=','): string {
  $needsQuotes = str_contains($value, $delimiter) || str_contains($value, '"') || str_contains($value, "\n") || str_contains($value, "\r");
  if (!$needsQuotes) { return $value; }
  return '"' . str_replace('"', '""', $value) . '"';
}

function csvBuild(array $headers, array $rows, string $delimiter=','): string {
  $lines = [];
  $lines[] = implode($delimiter, array_map(fn($v) => csvEscapeValue((string)$v, $delimiter), $headers));
  foreach ($rows as $row) {
    $line = [];
    foreach ($headers as $key) {
      $line[] = csvEscapeValue((string)($row[$key] ?? ''), $delimiter);
    }
    $lines[] = implode($delimiter, $line);
  }
  return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
}

function normalizeVmCsvHeaderKey(string $value): string {
  $text = str_replace("\xEF\xBB\xBF", '', trim($value));
  $text = normalizeUtf8Text($text);
  if ($text === '') { return ''; }
  $text = strtr($text, [
    'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
    'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
    'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
    'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
    'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
    'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
    'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
    'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
    'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
    'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
    'Ç' => 'C', 'ç' => 'c',
    'Ñ' => 'N', 'ñ' => 'n'
  ]);
  if (function_exists('iconv')) {
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    if (is_string($converted) && $converted !== '') { $text = $converted; }
  }
  $text = strtolower($text);
  $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? $text;
  $text = preg_replace('/\s+/', ' ', $text) ?? $text;
  return trim($text);
}

function normalizeVmCsvAdministration(string $value): string {
  $raw = trim($value);
  if ($raw === '') { return ''; }
  $key = normalizeVmCsvHeaderKey($raw);
  if (str_contains($key, 'prodeb')) { return 'PRODEB'; }
  if (str_contains($key, 'sei')) { return 'SEI'; }
  return strtoupper($raw);
}

function normalizeVmCsvResourceText(string $value): string {
  $raw = trim($value);
  if ($raw === '') { return ''; }
  if (preg_match('/[a-z]/i', $raw) === 1) { return $raw; }
  return $raw;
}

function normalizeVmCsvVcpuText(string $value): string {
  $raw = trim($value);
  if ($raw === '') { return ''; }
  $raw = str_replace(',', '.', $raw);
  if (!preg_match('/^[0-9]+(?:\.[0-9]+)?$/', $raw)) { return trim($value); }
  $number = (float)$raw;
  if (abs($number - round($number)) < 0.00001) { return (string)((int)round($number)); }
  return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
}

function vmCsvMachinesHeadersMap(): array {
  return [
    'nome de servidor' => 'name',
    'nome do servidor' => 'name',
    'administracao' => 'vm_administration',
    'sistema operacional' => 'os_name',
    'endereco ip' => 'ip',
    'vcpu' => 'vcpus',
    'memoria gb' => 'ram_csv',
    'storage gb' => 'disk_csv',
  ];
}

function parseVmMachinesCsvContent(string $csvContent): array {
  $lines = preg_split('/\r\n|\n|\r/', str_replace("\xEF\xBB\xBF", '', $csvContent)) ?: [];
  $lineNumber = 0;
  $headerLine = '';
  foreach ($lines as $line) {
    $lineNumber++;
    if (trim((string)$line) === '') { continue; }
    $headerLine = (string)$line;
    break;
  }
  if ($headerLine === '') {
    throw new RuntimeException('CSV vazio.');
  }

  $rawHeaders = str_getcsv($headerLine, ';');
  $headerMap = vmCsvMachinesHeadersMap();
  $indexToField = [];
  foreach ($rawHeaders as $index => $rawHeader) {
    $normalized = normalizeVmCsvHeaderKey((string)$rawHeader);
    if ($normalized === '') { continue; }
    $field = $headerMap[$normalized] ?? null;
    if ($field !== null) {
      $indexToField[(int)$index] = $field;
    }
  }
  $required = ['name', 'vm_administration', 'os_name', 'ip', 'vcpus', 'ram_csv', 'disk_csv'];
  foreach ($required as $field) {
    if (!in_array($field, $indexToField, true)) {
      throw new RuntimeException('Cabeçalho CSV inválido. Use o modelo exportado pela aba Máquinas.');
    }
  }

  $rows = [];
  $seenNames = [];
  for ($i = $lineNumber; $i < count($lines); $i++) {
    $line = (string)($lines[$i] ?? '');
    $rowNumber = $i + 1;
    if (trim($line) === '') { continue; }
    $columns = str_getcsv($line, ';');
    $parsed = [
      'row_number' => $rowNumber,
      'name' => '',
      'vm_administration' => '',
      'os_name' => '',
      'ip' => '',
      'vcpus' => '',
      'ram_csv' => '',
      'disk_csv' => '',
    ];
    foreach ($indexToField as $index => $field) {
      $parsed[$field] = trim((string)($columns[$index] ?? ''));
    }
    $parsed['name'] = trim((string)$parsed['name']);
    $parsed['vm_administration'] = normalizeVmCsvAdministration((string)$parsed['vm_administration']);
    $parsed['os_name'] = trim((string)$parsed['os_name']);
    $parsed['ip'] = packVmIpListValue((string)$parsed['ip']);
    $parsed['vcpus'] = normalizeVmCsvVcpuText((string)$parsed['vcpus']);
    $parsed['ram_csv'] = normalizeVmCsvResourceText((string)$parsed['ram_csv']);
    $parsed['disk_csv'] = normalizeVmCsvResourceText((string)$parsed['disk_csv']);

    $nameKey = strtolower($parsed['name']);
    if ($nameKey !== '') {
      if (isset($seenNames[$nameKey])) {
        $parsed['row_error'] = 'Nome de servidor duplicado no CSV.';
      } else {
        $seenNames[$nameKey] = true;
      }
    }
    $rows[] = $parsed;
  }
  return $rows;
}

function vmCsvMachinesDiskExportValue(string $raw): string {
  $text = trim($raw);
  if ($text === '') { return ''; }
  if (preg_match('/^\s*([0-9][0-9\.,]*)\s*gb\s*$/i', $text, $match) === 1) {
    return trim((string)($match[1] ?? ''));
  }
  return $text;
}

function vmCsvPreviewPayload(array $parsedRows, array $existingVms): array {
  $prepared = [];
  foreach ($existingVms as $vm) {
    if (!is_array($vm)) { continue; }
    $id = (int)($vm['id'] ?? 0);
    if ($id <= 0) { continue; }
    $name = trim((string)($vm['name'] ?? ''));
    $ips = normalizeVmIpListValue((string)($vm['ip'] ?? ''));
    $prepared[$id] = [
      'id' => $id,
      'name' => $name,
      'name_key' => strtolower($name),
      'ip' => implode(', ', $ips),
      'ip_keys' => array_values(array_unique(array_map('strtolower', $ips))),
      'vm_administration' => trim((string)($vm['vm_administration'] ?? '')),
      'os_name' => trim((string)($vm['os_name'] ?? '')),
      'vcpus' => trim((string)($vm['vcpus'] ?? '')),
      'ram_csv' => vmCsvMachinesDiskExportValue((string)($vm['ram'] ?? '')),
      'disk_csv' => vmCsvMachinesDiskExportValue((string)($vm['disk'] ?? '')),
      'archived' => (int)($vm['archived'] ?? 0) > 0 ? 1 : 0,
    ];
  }

  $nameIndex = [];
  $ipIndex = [];
  foreach ($prepared as $vm) {
    $nameKey = (string)($vm['name_key'] ?? '');
    if ($nameKey !== '') {
      if (!isset($nameIndex[$nameKey])) { $nameIndex[$nameKey] = []; }
      $nameIndex[$nameKey][$vm['id']] = $vm;
    }
    foreach (($vm['ip_keys'] ?? []) as $ipKey) {
      if ($ipKey === '') { continue; }
      if (!isset($ipIndex[$ipKey])) { $ipIndex[$ipKey] = []; }
      $ipIndex[$ipKey][$vm['id']] = $vm;
    }
  }

  $pickCandidate = static function(array $candidates): array {
    if (!$candidates) { return ['vm' => null, 'ambiguous' => false]; }
    $candidates = array_values($candidates);
    $active = array_values(array_filter($candidates, static fn($vm) => (int)($vm['archived'] ?? 0) === 0));
    $pool = $active ?: $candidates;
    if (count($pool) > 1) { return ['vm' => null, 'ambiguous' => true]; }
    return ['vm' => $pool[0], 'ambiguous' => false];
  };

  $items = [];
  $applyRows = [];
  $summary = ['rows_total' => count($parsedRows), 'update' => 0, 'create' => 0, 'skip' => 0, 'error' => 0];

  foreach ($parsedRows as $row) {
    if (!is_array($row)) { continue; }
    $name = trim((string)($row['name'] ?? ''));
    $ip = packVmIpListValue((string)($row['ip'] ?? ''));
    $rowIps = normalizeVmIpListValue($ip);
    $admin = normalizeVmCsvAdministration((string)($row['vm_administration'] ?? ''));
    $effectiveAdmin = $admin;
    $vmCategory = 'Producao';
    $vmType = 'Sistemas';
    $vmAccess = 'Interno';
    $osName = trim((string)($row['os_name'] ?? ''));
    $vcpus = normalizeVmCsvVcpuText((string)($row['vcpus'] ?? ''));
    $ramCsv = normalizeVmCsvResourceText((string)($row['ram_csv'] ?? ''));
    $diskCsv = normalizeVmCsvResourceText((string)($row['disk_csv'] ?? ''));

    $item = [
      'row_number' => (int)($row['row_number'] ?? 0),
      'action' => 'skip',
      'reason' => '',
      'name' => $name,
      'match_by' => '',
      'changed_fields' => [],
      'next' => [
        'name' => $name,
        'ip' => $ip,
        'vm_category' => $vmCategory,
        'vm_type' => $vmType,
        'vm_access' => $vmAccess,
        'vm_administration' => $admin,
        'os_name' => $osName,
        'vcpus' => $vcpus,
        'ram_csv' => $ramCsv,
        'disk_csv' => $diskCsv,
      ],
      'current' => null,
    ];

    $rowError = trim((string)($row['row_error'] ?? ''));
    if ($rowError !== '') {
      $item['action'] = 'error';
      $item['reason'] = $rowError;
      $summary['error']++;
      $items[] = $item;
      continue;
    }

    if ($name === '' || $ip === '') {
      $item['action'] = 'error';
      $item['reason'] = 'Nome do servidor e Endereço IP são obrigatórios.';
      $summary['error']++;
      $items[] = $item;
      continue;
    }

    $nameCandidates = $nameIndex[strtolower($name)] ?? [];
    $ipCandidatesMap = [];
    foreach ($rowIps as $rowIp) {
      $ipKey = strtolower($rowIp);
      foreach (($ipIndex[$ipKey] ?? []) as $vmId => $vmCandidate) {
        $ipCandidatesMap[$vmId] = $vmCandidate;
      }
    }
    $ipCandidates = array_values($ipCandidatesMap);

    $pickedByName = $pickCandidate(array_values($nameCandidates));
    $pickedByIp = $pickCandidate($ipCandidates);

    if ($pickedByName['ambiguous']) {
      $item['action'] = 'error';
      $item['reason'] = 'Mais de uma máquina encontrada com este nome.';
      $summary['error']++;
      $items[] = $item;
      continue;
    }
    if (!$pickedByName['vm'] && $pickedByIp['ambiguous']) {
      $item['action'] = 'error';
      $item['reason'] = 'Mais de uma máquina encontrada com este IP.';
      $summary['error']++;
      $items[] = $item;
      continue;
    }

    $existing = null;
    $matchBy = '';
    if (is_array($pickedByName['vm'] ?? null)) {
      $existing = $pickedByName['vm'];
      $matchBy = 'name';
      if (is_array($pickedByIp['vm'] ?? null) && (int)($pickedByIp['vm']['id'] ?? 0) !== (int)($existing['id'] ?? 0)) {
        $item['action'] = 'error';
        $item['reason'] = 'Conflito: nome e IP apontam para máquinas diferentes.';
        $summary['error']++;
        $items[] = $item;
        continue;
      }
    } elseif (is_array($pickedByIp['vm'] ?? null)) {
      $existing = $pickedByIp['vm'];
      $matchBy = 'ip';
    }

    if (is_array($existing)) {
      $current = [
        'id' => (int)($existing['id'] ?? 0),
        'name' => trim((string)($existing['name'] ?? '')),
        'ip' => packVmIpListValue((string)($existing['ip'] ?? '')),
        'vm_administration' => trim((string)($existing['vm_administration'] ?? '')),
        'os_name' => trim((string)($existing['os_name'] ?? '')),
        'vcpus' => trim((string)($existing['vcpus'] ?? '')),
        'ram_csv' => vmCsvMachinesDiskExportValue((string)($existing['ram_csv'] ?? '')),
        'disk_csv' => vmCsvMachinesDiskExportValue((string)($existing['disk_csv'] ?? '')),
      ];
      if (!in_array($effectiveAdmin, ['SEI', 'PRODEB'], true)) {
        $effectiveAdmin = in_array((string)$current['vm_administration'], ['SEI', 'PRODEB'], true)
          ? (string)$current['vm_administration']
          : 'SEI';
      }
      $item['match_by'] = $matchBy;
      $item['next']['vm_administration'] = $effectiveAdmin;
      $item['current'] = $current;
      $changedFields = [];
      foreach (['name','ip','vm_administration','os_name','vcpus','ram_csv','disk_csv'] as $field) {
        if ((string)($current[$field] ?? '') !== (string)($item['next'][$field] ?? '')) {
          $changedFields[] = $field;
        }
      }
      if (!$changedFields) {
        $item['action'] = 'skip';
        $item['reason'] = $matchBy === 'ip' ? 'Sem alterações (identificada por IP).' : 'Sem alterações.';
        $summary['skip']++;
      } else {
        $item['action'] = 'update';
        $item['changed_fields'] = $changedFields;
        $summary['update']++;
        $applyRows[] = [
          'row_number' => (int)($row['row_number'] ?? 0),
          'action' => 'update',
          'id' => (int)$current['id'],
          'name' => $name,
          'ip' => $ip,
          'vm_administration' => $effectiveAdmin,
          'os_name' => $osName,
          'vcpus' => $vcpus,
          'ram_csv' => $ramCsv,
          'disk_csv' => $diskCsv,
        ];
      }
    } else {
      if (!in_array($effectiveAdmin, ['SEI', 'PRODEB'], true)) { $effectiveAdmin = 'SEI'; }
      $item['next']['vm_administration'] = $effectiveAdmin;
      $item['action'] = 'create';
      $summary['create']++;
      $applyRows[] = [
        'row_number' => (int)($row['row_number'] ?? 0),
        'action' => 'create',
        'id' => 0,
        'name' => $name,
        'ip' => $ip,
        'vm_category' => $vmCategory,
        'vm_type' => $vmType,
        'vm_access' => $vmAccess,
        'vm_administration' => $effectiveAdmin,
        'os_name' => $osName,
        'vcpus' => $vcpus,
        'ram_csv' => $ramCsv,
        'disk_csv' => $diskCsv,
      ];
    }

    $items[] = $item;
  }

  return [
    'summary' => $summary,
    'items' => $items,
    'apply_rows' => $applyRows,
  ];
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
  $baseDir = vmDiagnosticDir();
  if (!is_dir($baseDir) && !@mkdir($baseDir, 0775, true) && !is_dir($baseDir)) { return; }
  $baseReal = realpath($baseDir);
  if ($baseReal === false) { return; }

  foreach ($files as $item) {
    if (!is_array($item)) { continue; }
    $ref = trim((string)($item['reference'] ?? ''));
    $contentBase64 = trim((string)($item['content_base64'] ?? ''));
    if ($ref === '' || $contentBase64 === '') { continue; }
    $normalizedRef = str_replace('\\', '/', $ref);
    if (!preg_match('#^data/vm_diagnostics/([A-Za-z0-9._-]+\.json)$#i', $normalizedRef, $match)) { continue; }
    $filename = trim((string)($match[1] ?? ''));
    if ($filename === '' || $filename === '.' || $filename === '..') { continue; }
    $decoded = base64_decode($contentBase64, true);
    if ($decoded === false) { continue; }
    $fullPath = $baseReal . DIRECTORY_SEPARATOR . $filename;
    @file_put_contents($fullPath, $decoded);
  }
}

function backupSystemDocsFromSystemRows(array $rows): array {
  $files = [];
  $seen = [];
  $root = systemDocProjectRoot();
  foreach ($rows as $row) {
    if (!is_array($row)) { continue; }
    $refs = systemDocReferencesFromSystemRow($row);
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

function restoreBackupSystemDocs(array $files): void {
  if (!$files) { return; }
  $baseDir = systemDocDir();
  if (!is_dir($baseDir) && !@mkdir($baseDir, 0775, true) && !is_dir($baseDir)) { return; }
  $baseReal = realpath($baseDir);
  if ($baseReal === false) { return; }

  foreach ($files as $item) {
    if (!is_array($item)) { continue; }
    $ref = trim((string)($item['reference'] ?? ''));
    $contentBase64 = trim((string)($item['content_base64'] ?? ''));
    if ($ref === '' || $contentBase64 === '') { continue; }
    $normalizedRef = str_replace('\\', '/', $ref);
    if (!preg_match('#^data/system_docs/([A-Za-z0-9._-]+\.pdf)$#i', $normalizedRef, $match)) { continue; }
    $filename = trim((string)($match[1] ?? ''));
    if ($filename === '' || $filename === '.' || $filename === '..') { continue; }
    $decoded = base64_decode($contentBase64, true);
    if ($decoded === false) { continue; }
    $fullPath = $baseReal . DIRECTORY_SEPARATOR . $filename;
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

    // Validacao CSRF para todos os endpoints POST, exceto login (protegido por rate limiting)
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $api !== 'login') {
      $clientToken  = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
      $sessionToken = $_SESSION['csrf_token'] ?? '';
      if ($sessionToken === '' || !hash_equals($sessionToken, $clientToken)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Token de seguranca invalido. Recarregue a pagina.'], JSON_UNESCAPED_UNICODE);
        return;
      }
    }

    // Limpeza periodica de tentativas de login expiradas (~1% das requisicoes)
    if (mt_rand(1, 100) === 1) {
      try {
        $db instanceof SQLite3
          ? $db->exec("DELETE FROM login_attempts WHERE attempted_at < datetime('now','-3600 seconds','localtime')")
          : $db->exec("DELETE FROM login_attempts WHERE attempted_at < datetime('now','-3600 seconds','localtime')");
      } catch (\Throwable $e) { /* tabela pode nao existir em bancos antigos */ }
    }

    if ($api === 'auth-status') {
      $sessionUser = sessionAuthUser();
      if (!$sessionUser) {
        unset($_SESSION['auth_user']);
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
      if (normalizeRole((string)($freshUser['role'] ?? '')) === 'leitura') {
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

      // Rate limiting por IP
      $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
      try {
        if ($db instanceof SQLite3) {
          $escapedIp = SQLite3::escapeString($clientIp);
          $attempts = (int)$db->querySingle(
            "SELECT COUNT(*) FROM login_attempts WHERE ip='$escapedIp' AND attempted_at >= datetime('now','-" . LOGIN_WINDOW_SECS . " seconds','localtime')"
          );
        } else {
          $stCount = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip=:ip AND attempted_at >= datetime('now','-" . LOGIN_WINDOW_SECS . " seconds','localtime')");
          $stCount->bindValue(':ip', $clientIp, PDO::PARAM_STR);
          $stCount->execute();
          $attempts = (int)$stCount->fetchColumn();
        }
        if ($attempts >= LOGIN_MAX_ATTEMPTS) {
          http_response_code(429);
          echo json_encode(['ok'=>false,'error'=>'Muitas tentativas de login. Aguarde alguns minutos.'], JSON_UNESCAPED_UNICODE);
          return;
        }
        // Registrar tentativa
        if ($db instanceof SQLite3) {
          $db->exec("INSERT INTO login_attempts(ip) VALUES('" . SQLite3::escapeString($clientIp) . "')");
        } else {
          $stIns = $db->prepare("INSERT INTO login_attempts(ip) VALUES(:ip)");
          $stIns->bindValue(':ip', $clientIp, PDO::PARAM_STR);
          $stIns->execute();
        }
      } catch (\Throwable $e) { /* tabela pode nao existir em bancos antigos, ignorar */ }

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
      if (normalizeRole((string)($user['role'] ?? '')) === 'leitura') {
        echo json_encode(['ok'=>false,'error'=>'Perfil sem permissao de login.'], JSON_UNESCAPED_UNICODE);
        return;
      }
      if (!password_verify($password, (string)($user['password_hash'] ?? ''))) {
        echo json_encode(['ok'=>false,'error'=>'Credenciais invalidas.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      // Login bem-sucedido: limpar tentativas do IP
      try {
        if ($db instanceof SQLite3) {
          $db->exec("DELETE FROM login_attempts WHERE ip='" . SQLite3::escapeString($clientIp) . "'");
        } else {
          $stClean = $db->prepare("DELETE FROM login_attempts WHERE ip=:ip");
          $stClean->bindValue(':ip', $clientIp, PDO::PARAM_STR);
          $stClean->execute();
        }
      } catch (\Throwable $e) { /* ignorar */ }

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

    $authUser = sessionAuthUser();
    if ($api === 'list') {
      $out = $db instanceof SQLite3 ? fetchSystemsSqlite3($db, false) : fetchSystemsPdo($db, false);
      if (!$authUser || normalizeRole((string)($authUser['role'] ?? '')) === 'leitura') {
        $out = viewOnlySystemRows($out);
      }
      echo json_encode(['ok'=>true,'data'=>$out], JSON_UNESCAPED_UNICODE);
      return;
    }

    if (!$authUser) {
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

    $adminOnlyActions = ['delete', 'vm-delete', 'backup-export', 'backup-restore', 'user-list', 'user-save', 'user-delete'];
    if (in_array($api, $adminOnlyActions, true) && !roleAtLeast((string)$authUser['role'], 'admin')) {
      echo json_encode(['ok'=>false,'error'=>'Permissao insuficiente para esta acao.'], JSON_UNESCAPED_UNICODE);
      return;
    }

    $editActions = ['save', 'archive', 'restore', 'vm-save', 'vm-archive', 'vm-restore', 'db-save', 'db-delete', 'vm-diagnostic-save', 'vm-diagnostic-clear', 'ticket-save', 'ticket-update', 'ticket-delete', 'system-doc-upload', 'system-doc-delete', 'vm-csv-export', 'vm-csv-import-preview', 'vm-csv-import-apply', 'export-csv'];
    if (in_array($api, $editActions, true) && !roleAtLeast((string)$authUser['role'], 'edicao')) {
      echo json_encode(['ok'=>false,'error'=>'Perfil apenas leitura.'], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'user-list') {
      $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
      if (!in_array($method, ['GET', 'HEAD'], true)) {
        echo json_encode(['ok'=>false,'error'=>'Invalid method']);
        return;
      }
      $users = $db instanceof SQLite3 ? listUsersSqlite3($db) : listUsersPdo($db);
      echo json_encode(['ok'=>true,'data'=>$users], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'user-save') {
      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
      $data = json_decode((string)file_get_contents('php://input'), true);
      if (!is_array($data)) { echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); return; }

      $id = (int)($data['id'] ?? 0);
      $isCreate = $id <= 0;
      $username = trim((string)($data['username'] ?? ''));
      $fullName = normalizeUtf8Text(trim((string)($data['full_name'] ?? '')));
      $role = normalizeRole((string)($data['role'] ?? 'edicao'));
      if (!in_array($role, ['edicao', 'admin'], true)) { $role = 'edicao'; }
      $active = boolFromMixed($data['active'] ?? 1) ? 1 : 0;
      $newPassword = (string)($data['new_password'] ?? '');

      if ($username === '') {
        echo json_encode(['ok'=>false,'error'=>'Usuario e obrigatorio.'], JSON_UNESCAPED_UNICODE);
        return;
      }
      if (preg_match('/^[a-zA-Z0-9._-]{3,64}$/', $username) !== 1) {
        echo json_encode(['ok'=>false,'error'=>'Usuario invalido. Use de 3 a 64 caracteres: letras, numeros, ponto, traco e underscore.'], JSON_UNESCAPED_UNICODE);
        return;
      }
      if ($isCreate && $newPassword === '') {
        echo json_encode(['ok'=>false,'error'=>'Senha e obrigatoria para novo usuario.'], JSON_UNESCAPED_UNICODE);
        return;
      }
      if ($newPassword !== '' && strlen($newPassword) < 8) {
        echo json_encode(['ok'=>false,'error'=>'A senha deve ter ao menos 8 caracteres.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      $duplicated = $db instanceof SQLite3
        ? fetchUserByUsernameExcludingIdSqlite3($db, $username, max(0, $id))
        : fetchUserByUsernameExcludingIdPdo($db, $username, max(0, $id));
      if (is_array($duplicated)) {
        echo json_encode(['ok'=>false,'error'=>'Ja existe um usuario com este login.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      if ($isCreate) {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $newId = $db instanceof SQLite3
          ? insertUserSqlite3($db, $username, $passwordHash, $fullName, $role, $active)
          : insertUserPdo($db, $username, $passwordHash, $fullName, $role, $active);
        if ($newId <= 0) {
          echo json_encode(['ok'=>false,'error'=>'Falha ao criar usuario.'], JSON_UNESCAPED_UNICODE);
          return;
        }
        $created = $db instanceof SQLite3 ? fetchUserByIdSqlite3($db, $newId) : fetchUserByIdPdo($db, $newId);
        if (!is_array($created)) {
          echo json_encode(['ok'=>false,'error'=>'Usuario criado, mas falha ao carregar retorno.'], JSON_UNESCAPED_UNICODE);
          return;
        }
        echo json_encode(['ok'=>true,'data'=>publicUserPayload($created)], JSON_UNESCAPED_UNICODE);
        return;
      }

      $current = $db instanceof SQLite3 ? fetchUserByIdSqlite3($db, $id) : fetchUserByIdPdo($db, $id);
      if (!is_array($current)) {
        echo json_encode(['ok'=>false,'error'=>'Usuario nao encontrado.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      $currentRole = normalizeRole((string)($current['role'] ?? 'edicao'));
      $currentActive = (int)($current['active'] ?? 0) > 0 ? 1 : 0;
      $isSelf = (int)($authUser['id'] ?? 0) === $id;
      if ($isSelf && $active !== 1) {
        echo json_encode(['ok'=>false,'error'=>'Nao e permitido desativar o proprio usuario.'], JSON_UNESCAPED_UNICODE);
        return;
      }
      if ($isSelf && $role !== $currentRole) {
        echo json_encode(['ok'=>false,'error'=>'Nao e permitido alterar o proprio perfil.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      $activeAdmins = $db instanceof SQLite3 ? countActiveAdminsSqlite3($db) : countActiveAdminsPdo($db);
      $wasActiveAdmin = ($currentRole === 'admin' && $currentActive === 1);
      $willBeActiveAdmin = ($role === 'admin' && $active === 1);
      if ($wasActiveAdmin && !$willBeActiveAdmin && $activeAdmins <= 1) {
        echo json_encode(['ok'=>false,'error'=>'Nao e permitido remover o ultimo admin ativo.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      $passwordHash = null;
      if ($newPassword !== '') {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
      }

      $updated = $db instanceof SQLite3
        ? updateUserProfileSqlite3($db, $id, $username, $fullName, $role, $active, $passwordHash)
        : updateUserProfilePdo($db, $id, $username, $fullName, $role, $active, $passwordHash);
      if (!$updated) {
        echo json_encode(['ok'=>false,'error'=>'Falha ao atualizar usuario.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      $fresh = $db instanceof SQLite3 ? fetchUserByIdSqlite3($db, $id) : fetchUserByIdPdo($db, $id);
      if (!is_array($fresh)) {
        echo json_encode(['ok'=>false,'error'=>'Usuario atualizado, mas falha ao carregar retorno.'], JSON_UNESCAPED_UNICODE);
        return;
      }
      if ($isSelf) {
        $_SESSION['auth_user'] = publicUserPayload($fresh);
      }

      echo json_encode(['ok'=>true,'data'=>publicUserPayload($fresh)], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'user-delete') {
      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
      $data = json_decode((string)file_get_contents('php://input'), true);
      if (!is_array($data)) { echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); return; }
      $id = (int)($data['id'] ?? 0);
      if ($id <= 0) {
        echo json_encode(['ok'=>false,'error'=>'Usuario invalido.'], JSON_UNESCAPED_UNICODE);
        return;
      }
      if ($id === (int)($authUser['id'] ?? 0)) {
        echo json_encode(['ok'=>false,'error'=>'Nao e permitido excluir o proprio usuario.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      $target = $db instanceof SQLite3 ? fetchUserByIdSqlite3($db, $id) : fetchUserByIdPdo($db, $id);
      if (!is_array($target)) {
        echo json_encode(['ok'=>false,'error'=>'Usuario nao encontrado.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      $targetRole = normalizeRole((string)($target['role'] ?? 'edicao'));
      $targetActive = (int)($target['active'] ?? 0) > 0 ? 1 : 0;
      if ($targetRole === 'admin' && $targetActive === 1) {
        $activeAdmins = $db instanceof SQLite3 ? countActiveAdminsSqlite3($db) : countActiveAdminsPdo($db);
        if ($activeAdmins <= 1) {
          echo json_encode(['ok'=>false,'error'=>'Nao e permitido excluir o ultimo admin ativo.'], JSON_UNESCAPED_UNICODE);
          return;
        }
      }

      $deleted = $db instanceof SQLite3 ? deleteUserByIdSqlite3($db, $id) : deleteUserByIdPdo($db, $id);
      if (!$deleted) {
        echo json_encode(['ok'=>false,'error'=>'Falha ao excluir usuario.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
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

    if ($api === 'ticket-list') {
      $out = $db instanceof SQLite3 ? listTicketsSqlite3($db) : listTicketsPdo($db);
      echo json_encode(['ok'=>true,'data'=>$out], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'dns-public-ip-resolve') {
      $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
      $data = [];
      if ($method === 'POST') {
        $data = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($data)) { echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); return; }
      } elseif ($method === 'GET') {
        $data = ['hosts' => (string)($_GET['hosts'] ?? '')];
      } else {
        echo json_encode(['ok'=>false,'error'=>'Invalid method']);
        return;
      }

      $rawHosts = [];
      if (is_array($data['hosts'] ?? null)) {
        $rawHosts = $data['hosts'];
      } elseif (array_key_exists('hosts', $data)) {
        $rawHosts = preg_split('/[\r\n,; ]+/', (string)$data['hosts']) ?: [];
      }

      $out = [];
      $seen = [];
      $maxHosts = 150;
      foreach ($rawHosts as $entry) {
        if (count($out) >= $maxHosts) { break; }
        $candidate = trim((string)$entry);
        if ($candidate === '') { continue; }
        $host = hostFromUrlText($candidate);
        if ($host === '' && preg_match('/^[a-z0-9.-]+$/i', $candidate) === 1) { $host = strtolower($candidate); }
        if ($host === '' || isset($seen[$host])) { continue; }
        $seen[$host] = true;
        $ips = resolveHostPublicIps($host);
        $out[$host] = $ips ? implode(', ', $ips) : '-';
      }

      echo json_encode(['ok'=>true,'data'=>$out], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'dns-ssl-validity-resolve') {
      $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
      $data = [];
      if ($method === 'POST') {
        $data = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($data)) { echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); return; }
      } elseif ($method === 'GET') {
        $data = ['targets' => (string)($_GET['targets'] ?? '')];
      } else {
        echo json_encode(['ok'=>false,'error'=>'Invalid method']);
        return;
      }

      $rawTargets = [];
      if (is_array($data['targets'] ?? null)) {
        $rawTargets = $data['targets'];
      } elseif (array_key_exists('targets', $data)) {
        $rawTargets = preg_split('/[\r\n,; ]+/', (string)$data['targets']) ?: [];
      }

      $out = [];
      $seen = [];
      $maxTargets = 150;
      foreach ($rawTargets as $entry) {
        if (count($out) >= $maxTargets) { break; }
        $parsed = parseSslTargetKey((string)$entry);
        if ($parsed === null) { continue; }
        $key = (string)$parsed['key'];
        if (isset($seen[$key])) { continue; }
        $seen[$key] = true;
        $timestamp = sslCertificateExpiryTimestamp((string)$parsed['host'], (int)$parsed['port']);
        $out[$key] = sslValidityLabelByTimestamp($timestamp);
      }

      echo json_encode(['ok'=>true,'data'=>$out], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'dns-internal-ip-resolve') {
      $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
      $data = [];
      if ($method === 'POST') {
        $data = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($data)) { echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); return; }
      } elseif ($method === 'GET') {
        $data = ['hosts' => (string)($_GET['hosts'] ?? '')];
      } else {
        echo json_encode(['ok'=>false,'error'=>'Invalid method']);
        return;
      }

      $rawHosts = [];
      if (is_array($data['hosts'] ?? null)) {
        $rawHosts = $data['hosts'];
      } elseif (array_key_exists('hosts', $data)) {
        $rawHosts = preg_split('/[\r\n,; ]+/', (string)$data['hosts']) ?: [];
      }

      $out = [];
      $seen = [];
      $maxHosts = 150;
      foreach ($rawHosts as $entry) {
        if (count($out) >= $maxHosts) { break; }
        $candidate = trim((string)$entry);
        if ($candidate === '') { continue; }
        $host = hostFromUrlText($candidate);
        if ($host === '' && preg_match('/^[a-z0-9.-]+$/i', $candidate) === 1) { $host = strtolower($candidate); }
        if ($host === '' || isset($seen[$host])) { continue; }
        $seen[$host] = true;
        $ips = resolveHostInternalIps($host);
        $out[$host] = $ips ? implode(', ', $ips) : '-';
      }

      echo json_encode(['ok'=>true,'data'=>$out], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'system-doc-view') {
      $id = (int)($_GET['id'] ?? 0);
      $docType = normalizeSystemDocType((string)($_GET['doc_type'] ?? ''));
      $docMap = systemDocFieldMap($docType);
      if ($id <= 0 || $docMap === null) {
        echo json_encode(['ok'=>false,'error'=>'Parametros invalidos.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      $row = $db instanceof SQLite3 ? fetchSystemByIdSqlite3($db, $id) : fetchSystemByIdPdo($db, $id);
      if (!$row) {
        echo json_encode(['ok'=>false,'error'=>'Sistema nao encontrado.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      $reference = trim((string)($row[$docMap['ref']] ?? ''));
      if ($reference === '') {
        echo json_encode(['ok'=>false,'error'=>'Documento nao cadastrado.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      $relative = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $reference);
      $fullPath = systemDocProjectRoot() . DIRECTORY_SEPARATOR . ltrim($relative, DIRECTORY_SEPARATOR);
      if (!is_file($fullPath) || !is_readable($fullPath)) {
        echo json_encode(['ok'=>false,'error'=>'Arquivo de documento nao encontrado.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      $filename = basename($fullPath);
      if (function_exists('header_remove')) { @header_remove('Content-Type'); }
      header('Content-Type: application/pdf');
      header('Content-Length: ' . (string)filesize($fullPath));
      header('Content-Disposition: inline; filename="' . rawurlencode($filename) . '"');
      readfile($fullPath);
      return;
    }

    if ($api === 'system-php-compat-get') {
      $id = (int)($_GET['id'] ?? 0);
      if ($id <= 0) {
        echo json_encode(['ok'=>false,'error'=>'Invalid ID'], JSON_UNESCAPED_UNICODE);
        return;
      }
      $row = $db instanceof SQLite3 ? fetchSystemByIdSqlite3($db, $id) : fetchSystemByIdPdo($db, $id);
      if (!$row) {
        echo json_encode(['ok'=>false,'error'=>'Sistema nao encontrado'], JSON_UNESCAPED_UNICODE);
        return;
      }
      echo json_encode(['ok'=>true,'data'=>$row], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'system-r-compat-get') {
      $id = (int)($_GET['id'] ?? 0);
      if ($id <= 0) {
        echo json_encode(['ok'=>false,'error'=>'Invalid ID'], JSON_UNESCAPED_UNICODE);
        return;
      }
      $row = $db instanceof SQLite3 ? fetchSystemByIdSqlite3($db, $id) : fetchSystemByIdPdo($db, $id);
      if (!$row) {
        echo json_encode(['ok'=>false,'error'=>'Sistema nao encontrado'], JSON_UNESCAPED_UNICODE);
        return;
      }
      echo json_encode(['ok'=>true,'data'=>$row], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'vm-csv-export') {
      $vms = $db instanceof SQLite3 ? listVmsSqlite3($db, false) : listVmsPdo($db, false);
      $headers = ['Nome de Servidor','Administração','Sistema Operacional','Endereço IP','vCPU','Memória (GB)','Storage (GB)'];
      $rows = [];
      foreach ($vms as $vm) {
        if (!is_array($vm)) { continue; }
        $rows[] = [
          'Nome de Servidor' => trim((string)($vm['name'] ?? '')),
          'Administração' => trim((string)($vm['vm_administration'] ?? '')),
          'Sistema Operacional' => trim((string)($vm['os_name'] ?? '')),
          'Endereço IP' => packVmIpListValue((string)($vm['ip'] ?? '')),
          'vCPU' => trim((string)($vm['vcpus'] ?? '')),
          'Memória (GB)' => vmCsvMachinesDiskExportValue((string)($vm['ram'] ?? '')),
          'Storage (GB)' => vmCsvMachinesDiskExportValue((string)($vm['disk'] ?? '')),
        ];
      }
      $file = 'maquinas_' . date('Ymd_His') . '.csv';
      echo json_encode(['ok'=>true,'data'=>[
        'filename' => $file,
        'mime' => 'text/csv;charset=utf-8',
        'content' => csvBuild($headers, $rows, ';'),
      ]], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'vm-csv-import-preview') {
      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
      $data = json_decode((string)file_get_contents('php://input'), true);
      if (!is_array($data)) { echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); return; }
      $csvContent = (string)($data['csv_content'] ?? '');
      if (trim($csvContent) === '') {
        echo json_encode(['ok'=>false,'error'=>'CSV vazio.'], JSON_UNESCAPED_UNICODE);
        return;
      }
      try {
        $parsedRows = parseVmMachinesCsvContent($csvContent);
      } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
        return;
      }
      $existingActive = $db instanceof SQLite3 ? listVmsSqlite3($db, false) : listVmsPdo($db, false);
      $existingArchived = $db instanceof SQLite3 ? listVmsSqlite3($db, true) : listVmsPdo($db, true);
      $preview = vmCsvPreviewPayload(array_values($parsedRows), array_merge($existingActive, $existingArchived));
      echo json_encode(['ok'=>true,'data'=>$preview], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'vm-csv-import-apply') {
      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
      $data = json_decode((string)file_get_contents('php://input'), true);
      if (!is_array($data)) { echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); return; }
      $rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
      if (!$rows) {
        echo json_encode(['ok'=>false,'error'=>'Nenhuma linha para atualizar.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      $summary = ['updated' => 0, 'created' => 0, 'skipped' => 0];
      $applyRow = function(array $row) use (&$summary, $db) {
        $action = strtolower(trim((string)($row['action'] ?? '')));
        if (!in_array($action, ['update', 'create'], true)) {
          $summary['skipped']++;
          return;
        }

        $name = trim((string)($row['name'] ?? ''));
        $ip = packVmIpListValue((string)($row['ip'] ?? ''));
        if ($name === '' || $ip === '') {
          $summary['skipped']++;
          return;
        }

        $admin = normalizeVmCsvAdministration((string)($row['vm_administration'] ?? ''));
        $vmCategory = trim((string)($row['vm_category'] ?? ''));
        $vmType = trim((string)($row['vm_type'] ?? ''));
        $vmAccess = trim((string)($row['vm_access'] ?? ''));
        $osName = trim((string)($row['os_name'] ?? ''));
        $vcpus = normalizeVmCsvVcpuText((string)($row['vcpus'] ?? ''));
        $ramCsv = normalizeVmCsvResourceText((string)($row['ram_csv'] ?? ''));
        $diskCsv = normalizeVmCsvResourceText((string)($row['disk_csv'] ?? ''));
        $ram = $ramCsv !== '' && preg_match('/[a-z]/i', $ramCsv) !== 1 ? ($ramCsv . ' GB') : $ramCsv;
        $disk = $diskCsv !== '' && preg_match('/[a-z]/i', $diskCsv) !== 1 ? ($diskCsv . ' GB') : $diskCsv;
        if (!in_array($vmCategory, ['Producao', 'Homologacao', 'Desenvolvimento'], true)) { $vmCategory = 'Producao'; }
        if (!in_array($vmType, ['Sistemas', 'SGBD'], true)) { $vmType = 'Sistemas'; }
        if (!in_array($vmAccess, ['Interno', 'Externo'], true)) { $vmAccess = 'Interno'; }

        $id = (int)($row['id'] ?? 0);
        $current = null;
        if ($id > 0) {
          $current = $db instanceof SQLite3 ? fetchVmByIdSqlite3($db, $id) : fetchVmByIdPdo($db, $id);
        }

        if ($current !== null) {
          if (!in_array($admin, ['SEI', 'PRODEB'], true)) {
            $admin = trim((string)($current['vm_administration'] ?? ''));
            if (!in_array($admin, ['SEI', 'PRODEB'], true)) { $admin = 'SEI'; }
          }
          if ($db instanceof SQLite3) {
            $st = $db->prepare("UPDATE virtual_machines SET name=:name, ip=:ip, vm_administration=:vm_administration, os_name=:os_name, vcpus=:vcpus, ram=:ram, disk=:disk, updated_at=datetime('now','localtime') WHERE id=:id");
            $st->bindValue(':name', $name, SQLITE3_TEXT);
            $st->bindValue(':ip', $ip, SQLITE3_TEXT);
            $st->bindValue(':vm_administration', $admin, SQLITE3_TEXT);
            $st->bindValue(':os_name', $osName, SQLITE3_TEXT);
            $st->bindValue(':vcpus', $vcpus, SQLITE3_TEXT);
            $st->bindValue(':ram', $ram, SQLITE3_TEXT);
            $st->bindValue(':disk', $disk, SQLITE3_TEXT);
            $st->bindValue(':id', (int)$current['id'], SQLITE3_INTEGER);
            $st->execute();
          } else {
            $st = $db->prepare("UPDATE virtual_machines SET name=:name, ip=:ip, vm_administration=:vm_administration, os_name=:os_name, vcpus=:vcpus, ram=:ram, disk=:disk, updated_at=datetime('now','localtime') WHERE id=:id");
            $st->bindValue(':name', $name, PDO::PARAM_STR);
            $st->bindValue(':ip', $ip, PDO::PARAM_STR);
            $st->bindValue(':vm_administration', $admin, PDO::PARAM_STR);
            $st->bindValue(':os_name', $osName, PDO::PARAM_STR);
            $st->bindValue(':vcpus', $vcpus, PDO::PARAM_STR);
            $st->bindValue(':ram', $ram, PDO::PARAM_STR);
            $st->bindValue(':disk', $disk, PDO::PARAM_STR);
            $st->bindValue(':id', (int)$current['id'], PDO::PARAM_INT);
            $st->execute();
          }
          $summary['updated']++;
          return;
        }

        if (!in_array($admin, ['SEI', 'PRODEB'], true)) { $admin = 'SEI'; }
        if ($db instanceof SQLite3) {
          $st = $db->prepare("INSERT INTO virtual_machines(name,ip,vm_category,vm_type,vm_access,vm_administration,os_name,vcpus,ram,disk) VALUES(:name,:ip,:vm_category,:vm_type,:vm_access,:vm_administration,:os_name,:vcpus,:ram,:disk)");
          $st->bindValue(':name', $name, SQLITE3_TEXT);
          $st->bindValue(':ip', $ip, SQLITE3_TEXT);
          $st->bindValue(':vm_category', $vmCategory, SQLITE3_TEXT);
          $st->bindValue(':vm_type', $vmType, SQLITE3_TEXT);
          $st->bindValue(':vm_access', $vmAccess, SQLITE3_TEXT);
          $st->bindValue(':vm_administration', $admin, SQLITE3_TEXT);
          $st->bindValue(':os_name', $osName, SQLITE3_TEXT);
          $st->bindValue(':vcpus', $vcpus, SQLITE3_TEXT);
          $st->bindValue(':ram', $ram, SQLITE3_TEXT);
          $st->bindValue(':disk', $disk, SQLITE3_TEXT);
          $st->execute();
        } else {
          $st = $db->prepare("INSERT INTO virtual_machines(name,ip,vm_category,vm_type,vm_access,vm_administration,os_name,vcpus,ram,disk) VALUES(:name,:ip,:vm_category,:vm_type,:vm_access,:vm_administration,:os_name,:vcpus,:ram,:disk)");
          $st->bindValue(':name', $name, PDO::PARAM_STR);
          $st->bindValue(':ip', $ip, PDO::PARAM_STR);
          $st->bindValue(':vm_category', $vmCategory, PDO::PARAM_STR);
          $st->bindValue(':vm_type', $vmType, PDO::PARAM_STR);
          $st->bindValue(':vm_access', $vmAccess, PDO::PARAM_STR);
          $st->bindValue(':vm_administration', $admin, PDO::PARAM_STR);
          $st->bindValue(':os_name', $osName, PDO::PARAM_STR);
          $st->bindValue(':vcpus', $vcpus, PDO::PARAM_STR);
          $st->bindValue(':ram', $ram, PDO::PARAM_STR);
          $st->bindValue(':disk', $disk, PDO::PARAM_STR);
          $st->execute();
        }
        $summary['created']++;
      };

      try {
        if ($db instanceof SQLite3) {
          $db->exec('BEGIN IMMEDIATE');
          foreach ($rows as $row) {
            if (!is_array($row)) { $summary['skipped']++; continue; }
            $applyRow($row);
          }
          $db->exec('COMMIT');
        } else {
          $db->beginTransaction();
          foreach ($rows as $row) {
            if (!is_array($row)) { $summary['skipped']++; continue; }
            $applyRow($row);
          }
          $db->commit();
        }
      } catch (Throwable $e) {
        if ($db instanceof SQLite3) { $db->exec('ROLLBACK'); }
        elseif ($db->inTransaction()) { $db->rollBack(); }
        echo json_encode(['ok'=>false,'error'=>'Falha ao aplicar importação: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        return;
      }

      echo json_encode(['ok'=>true,'data'=>['summary'=>$summary]], JSON_UNESCAPED_UNICODE);
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
        $headers = ['id','name','system_name','category','system_group','system_access','status','criticality','owner','url','url_homolog','vm_name','vm_ip','vm_homolog_name','vm_homolog_ip','vm_dev_name','vm_dev_ip','tech','target_version','app_server','web_server','containerization','container_tool','runtime_port','php_required_extensions','php_recommended_extensions','php_required_libraries','php_required_ini','r_required_packages','description','notes','updated_at'];
        foreach ($systems as $item) {
          if (!is_array($item)) { continue; }
          $rows[] = [
            'id' => (int)($item['id'] ?? 0),
            'name' => (string)($item['name'] ?? ''),
            'system_name' => (string)($item['system_name'] ?? ''),
            'category' => (string)($item['category'] ?? ''),
            'system_group' => (string)($item['system_group'] ?? ''),
            'system_access' => (string)($item['system_access'] ?? 'Interno'),
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
            'target_version' => (string)($item['target_version'] ?? ''),
            'app_server' => (string)($item['app_server'] ?? ''),
            'web_server' => (string)($item['web_server'] ?? ''),
            'containerization' => (int)($item['containerization'] ?? 0) > 0 ? 1 : 0,
            'container_tool' => (string)($item['container_tool'] ?? ''),
            'runtime_port' => (string)($item['runtime_port'] ?? ''),
            'php_required_extensions' => (string)($item['php_required_extensions'] ?? ''),
            'php_recommended_extensions' => (string)($item['php_recommended_extensions'] ?? ''),
            'php_required_libraries' => (string)($item['php_required_libraries'] ?? ''),
            'php_required_ini' => (string)($item['php_required_ini'] ?? ''),
            'r_required_packages' => (string)($item['r_required_packages'] ?? ''),
            'description' => (string)($item['description'] ?? ''),
            'notes' => (string)($item['notes'] ?? ''),
            'updated_at' => (string)($item['updated_at'] ?? ''),
          ];
        }
      } elseif ($scope === 'vms') {
        $vms = $db instanceof SQLite3 ? listVmsSqlite3($db, false) : listVmsPdo($db, false);
        $headers = ['id','name','ip','public_ip','vm_category','vm_type','vm_access','vm_administration','os_name','vcpus','ram','disk','vm_language','vm_target_version','vm_app_server','vm_web_server','vm_containerization','vm_container_tool','vm_runtime_port','vm_tech','vm_instances','system_count','database_count','updated_at'];
        foreach ($vms as $item) {
          if (!is_array($item)) { continue; }
          $rows[] = [
            'id' => (int)($item['id'] ?? 0),
            'name' => (string)($item['name'] ?? ''),
            'ip' => (string)($item['ip'] ?? ''),
            'public_ip' => (string)($item['public_ip'] ?? ''),
            'vm_category' => (string)($item['vm_category'] ?? ''),
            'vm_type' => (string)($item['vm_type'] ?? ''),
            'vm_access' => (string)($item['vm_access'] ?? ''),
            'vm_administration' => (string)($item['vm_administration'] ?? ''),
            'os_name' => (string)($item['os_name'] ?? ''),
            'vcpus' => (string)($item['vcpus'] ?? ''),
            'ram' => (string)($item['ram'] ?? ''),
            'disk' => (string)($item['disk'] ?? ''),
            'vm_language' => is_array($item['vm_language_list'] ?? null) ? implode(', ', $item['vm_language_list']) : (string)($item['vm_language'] ?? ''),
            'vm_target_version' => (string)($item['vm_target_version'] ?? ''),
            'vm_app_server' => is_array($item['vm_app_server_list'] ?? null) ? implode(', ', $item['vm_app_server_list']) : (string)($item['vm_app_server'] ?? ''),
            'vm_web_server' => is_array($item['vm_web_server_list'] ?? null) ? implode(', ', $item['vm_web_server_list']) : (string)($item['vm_web_server'] ?? ''),
            'vm_containerization' => (int)($item['vm_containerization'] ?? 0) > 0 ? 1 : 0,
            'vm_container_tool' => is_array($item['vm_container_tool_list'] ?? null) ? implode(', ', $item['vm_container_tool_list']) : (string)($item['vm_container_tool'] ?? ''),
            'vm_runtime_port' => (string)($item['vm_runtime_port'] ?? ''),
            'vm_tech' => is_array($item['vm_tech_list'] ?? null) ? implode(', ', $item['vm_tech_list']) : (string)($item['vm_tech'] ?? ''),
            'vm_instances' => is_array($item['vm_instances_list'] ?? null) ? json_encode($item['vm_instances_list'], JSON_UNESCAPED_UNICODE) : (string)($item['vm_instances'] ?? ''),
            'system_count' => (int)($item['system_count'] ?? 0),
            'database_count' => (int)($item['database_count'] ?? 0),
            'updated_at' => (string)($item['updated_at'] ?? ''),
          ];
        }
      } else {
        $databases = $db instanceof SQLite3 ? listDatabasesSqlite3($db, false) : listDatabasesPdo($db, false);
        $headers = ['id','system_name','db_name','db_user','vm_name','db_instance_name','db_instance_ip','db_instance_port','vm_homolog_name','db_instance_homolog_name','db_instance_homolog_ip','db_instance_homolog_port','notes','updated_at'];
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
            'db_instance_port' => (string)($item['db_instance_port'] ?? ''),
            'vm_homolog_name' => (string)($item['vm_homolog_name'] ?? ''),
            'db_instance_homolog_name' => (string)($item['db_instance_homolog_name'] ?? ''),
            'db_instance_homolog_ip' => (string)($item['db_instance_homolog_ip'] ?? ''),
            'db_instance_homolog_port' => (string)($item['db_instance_homolog_port'] ?? ''),
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
      $systemDocFiles = backupSystemDocsFromSystemRows(array_merge($systemsActive, $systemsArchived));

      $payload = [
        'meta' => [
          'app' => 'Catálogo de Sistemas SEI',
          'version' => 1,
          'exported_at' => date('c'),
        ],
        'systems' => ['active' => $systemsActive, 'archived' => $systemsArchived],
        'vms' => ['active' => $vmsActive, 'archived' => $vmsArchived],
        'databases' => ['active' => $dbActive, 'archived' => $dbArchived],
        'users' => $users,
        'diagnostic_files' => $diagnosticFiles,
        'system_doc_files' => $systemDocFiles,
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
      $systemDocFiles = is_array($backup['system_doc_files'] ?? null) ? $backup['system_doc_files'] : [];

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
            $vmTargetVersion = trim((string)($row['vm_target_version'] ?? ''));
            $vmAppServer = $row['vm_app_server'] ?? ($row['vm_app_server_list'] ?? '');
            if (is_array($vmAppServer)) { $vmAppServer = implode(',', array_filter(array_map('trim', $vmAppServer))); }
            if ((string)$vmAppServer === '' && (string)$vmTech !== '') { $vmAppServer = (string)$vmTech; }
            $vmWebServer = $row['vm_web_server'] ?? ($row['vm_web_server_list'] ?? '');
            if (is_array($vmWebServer)) { $vmWebServer = implode(',', array_filter(array_map('trim', $vmWebServer))); }
            $vmContainerization = boolFromMixed($row['vm_containerization'] ?? 0) ? 1 : 0;
            $vmContainerTool = $row['vm_container_tool'] ?? ($row['vm_container_tool_list'] ?? '');
            if (is_array($vmContainerTool)) { $vmContainerTool = implode(',', array_filter(array_map('trim', $vmContainerTool))); }
            if ($vmContainerization === 0) { $vmContainerTool = ''; }
            $vmRuntimePort = trim((string)($row['vm_runtime_port'] ?? ''));
            $values = [
              sqlValueSqlite3($id),
              sqlValueSqlite3(normalizeUtf8Text(trim((string)($row['name'] ?? '')))),
              sqlValueSqlite3(packVmIpListValue((string)($row['ip'] ?? ''))),
              sqlValueSqlite3(packVmIpListValue((string)($row['public_ip'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['vm_category'] ?? 'Producao')) ?: 'Producao'),
              sqlValueSqlite3(trim((string)($row['vm_type'] ?? 'Sistemas')) ?: 'Sistemas'),
              sqlValueSqlite3(trim((string)($row['vm_access'] ?? 'Interno')) ?: 'Interno'),
              sqlValueSqlite3(trim((string)($row['vm_administration'] ?? 'SEI')) ?: 'SEI'),
              sqlValueSqlite3((string)$vmInstances),
              sqlValueSqlite3((string)$vmLanguage),
              sqlValueSqlite3((string)$vmTargetVersion),
              sqlValueSqlite3((string)$vmAppServer),
              sqlValueSqlite3((string)$vmWebServer),
              sqlValueSqlite3((int)$vmContainerization > 0 ? 1 : 0),
              sqlValueSqlite3((string)$vmContainerTool),
              sqlValueSqlite3((string)$vmRuntimePort),
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
            $db->exec("INSERT INTO virtual_machines(id,name,ip,public_ip,vm_category,vm_type,vm_access,vm_administration,vm_instances,vm_language,vm_target_version,vm_app_server,vm_web_server,vm_containerization,vm_container_tool,vm_runtime_port,vm_tech,diagnostic_json_ref,diagnostic_json_updated_at,diagnostic_json_ref_r,diagnostic_json_updated_at_r,os_name,os_version,vcpus,ram,disk,archived,archived_at,created_at,updated_at) VALUES(" . implode(',', $values) . ")");
          }

          foreach ($systemsRows as $row) {
            if (!is_array($row)) { continue; }
            $id = (int)($row['id'] ?? 0);
            $name = normalizeUtf8Text(trim((string)($row['name'] ?? '')));
            if ($id <= 0 || $name === '') { continue; }
            $tech = $row['tech'] ?? '';
            if (is_array($tech)) { $tech = implode(',', array_filter(array_map('trim', $tech))); }
            $phpRequiredExtensions = mergeCsvListValues(
              $row['php_required_extensions'] ?? ($row['php_required_extensions_list'] ?? ''),
              $row['php_recommended_extensions'] ?? ($row['php_recommended_extensions_list'] ?? '')
            );
            $phpRecommendedExtensions = '';
            $phpRequiredLibraries = '';
            $phpRequiredIni = normalizeIniRequirementText((string)($row['php_required_ini'] ?? ''));
            $rRequiredPackages = normalizeCsvListValue($row['r_required_packages'] ?? ($row['r_required_packages_list'] ?? ''));
            if ($phpRequiredIni === '' && is_array($row['php_required_ini_list'] ?? null)) {
              $phpRequiredIni = normalizeIniRequirementText(implode("\n", array_map(fn($entry) => trim((string)$entry), $row['php_required_ini_list'])));
            }
            $values = [
              sqlValueSqlite3($id),
              sqlValueSqlite3($name),
              sqlValueSqlite3(trim((string)($row['system_name'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['system_group'] ?? ''))),
              sqlValueSqlite3(stripos(trim((string)($row['system_access'] ?? '')), 'extern') !== false ? 'Externo' : 'Interno'),
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
              sqlValueSqlite3(trim((string)($row['analytics'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['ssl'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['waf'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['bundle'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['directory'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['size'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['repository'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['target_version'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['app_server'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['web_server'] ?? ''))),
              sqlValueSqlite3(boolFromMixed($row['containerization'] ?? 0) ? 1 : 0),
              sqlValueSqlite3(boolFromMixed($row['containerization'] ?? 0) ? trim((string)($row['container_tool'] ?? '')) : ''),
              sqlValueSqlite3(trim((string)($row['runtime_port'] ?? ''))),
              sqlValueSqlite3($phpRequiredExtensions),
              sqlValueSqlite3($phpRecommendedExtensions),
              sqlValueSqlite3($phpRequiredLibraries),
              sqlValueSqlite3($phpRequiredIni),
              sqlValueSqlite3($rRequiredPackages),
              sqlValueSqlite3(trim((string)($row['doc_installation_ref'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['doc_installation_updated_at'] ?? '')) ?: null),
              sqlValueSqlite3(trim((string)($row['doc_maintenance_ref'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['doc_maintenance_updated_at'] ?? '')) ?: null),
              sqlValueSqlite3(trim((string)($row['doc_security_ref'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['doc_security_updated_at'] ?? '')) ?: null),
              sqlValueSqlite3(trim((string)($row['doc_manual_ref'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['doc_manual_updated_at'] ?? '')) ?: null),
              sqlValueSqlite3(trim((string)($row['category'] ?? 'Outro')) ?: 'Outro'),
              sqlValueSqlite3(normalizeSystemStatus((string)($row['status'] ?? 'Ativo')) ?: 'Ativo'),
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
            $db->exec("INSERT INTO systems(id,name,system_name,system_group,system_access,ip,ip_homolog,vm,url_homolog,vm_homolog,vm_id,vm_homolog_id,vm_dev_id,archived,archived_at,responsible_sector,responsible_coordinator,extension_number,email,support,support_contact,analytics,ssl,waf,bundle,directory,size,repository,target_version,app_server,web_server,containerization,container_tool,runtime_port,php_required_extensions,php_recommended_extensions,php_required_libraries,php_required_ini,r_required_packages,doc_installation_ref,doc_installation_updated_at,doc_maintenance_ref,doc_maintenance_updated_at,doc_security_ref,doc_security_updated_at,doc_manual_ref,doc_manual_updated_at,category,status,tech,url,description,owner,criticality,version,notes,created_at,updated_at) VALUES(" . implode(',', $values) . ")");
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
              sqlValueSqlite3(trim((string)($row['db_instance_port'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['db_instance_homolog_name'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['db_instance_homolog_ip'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['db_instance_homolog_port'] ?? ''))),
              sqlValueSqlite3(trim((string)($row['notes'] ?? ''))),
              sqlValueSqlite3((int)($row['archived'] ?? 0) > 0 ? 1 : 0),
              sqlValueSqlite3(trim((string)($row['archived_at'] ?? '')) ?: null),
              sqlValueSqlite3(trim((string)($row['created_at'] ?? date('Y-m-d H:i:s')))),
              sqlValueSqlite3(trim((string)($row['updated_at'] ?? date('Y-m-d H:i:s')))),
            ];
            $db->exec("INSERT INTO system_databases(id,system_id,vm_id,vm_homolog_id,db_name,db_user,db_engine,db_engine_version,db_engine_version_homolog,db_instance_name,db_instance_ip,db_instance_port,db_instance_homolog_name,db_instance_homolog_ip,db_instance_homolog_port,notes,archived,archived_at,created_at,updated_at) VALUES(" . implode(',', $values) . ")");
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
                sqlValueSqlite3(normalizeRole((string)($row['role'] ?? 'edicao'))),
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
            $vmTargetVersion = trim((string)($row['vm_target_version'] ?? ''));
            $vmAppServer = $row['vm_app_server'] ?? ($row['vm_app_server_list'] ?? '');
            if (is_array($vmAppServer)) { $vmAppServer = implode(',', array_filter(array_map('trim', $vmAppServer))); }
            if ((string)$vmAppServer === '' && (string)$vmTech !== '') { $vmAppServer = (string)$vmTech; }
            $vmWebServer = $row['vm_web_server'] ?? ($row['vm_web_server_list'] ?? '');
            if (is_array($vmWebServer)) { $vmWebServer = implode(',', array_filter(array_map('trim', $vmWebServer))); }
            $vmContainerization = boolFromMixed($row['vm_containerization'] ?? 0) ? 1 : 0;
            $vmContainerTool = $row['vm_container_tool'] ?? ($row['vm_container_tool_list'] ?? '');
            if (is_array($vmContainerTool)) { $vmContainerTool = implode(',', array_filter(array_map('trim', $vmContainerTool))); }
            if ($vmContainerization === 0) { $vmContainerTool = ''; }
            $vmRuntimePort = trim((string)($row['vm_runtime_port'] ?? ''));
            $values = [
              sqlValuePdo($db, $id),
              sqlValuePdo($db, normalizeUtf8Text(trim((string)($row['name'] ?? '')))),
              sqlValuePdo($db, packVmIpListValue((string)($row['ip'] ?? ''))),
              sqlValuePdo($db, packVmIpListValue((string)($row['public_ip'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['vm_category'] ?? 'Producao')) ?: 'Producao'),
              sqlValuePdo($db, trim((string)($row['vm_type'] ?? 'Sistemas')) ?: 'Sistemas'),
              sqlValuePdo($db, trim((string)($row['vm_access'] ?? 'Interno')) ?: 'Interno'),
              sqlValuePdo($db, trim((string)($row['vm_administration'] ?? 'SEI')) ?: 'SEI'),
              sqlValuePdo($db, (string)$vmInstances),
              sqlValuePdo($db, (string)$vmLanguage),
              sqlValuePdo($db, (string)$vmTargetVersion),
              sqlValuePdo($db, (string)$vmAppServer),
              sqlValuePdo($db, (string)$vmWebServer),
              sqlValuePdo($db, (int)$vmContainerization > 0 ? 1 : 0),
              sqlValuePdo($db, (string)$vmContainerTool),
              sqlValuePdo($db, (string)$vmRuntimePort),
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
            $db->exec("INSERT INTO virtual_machines(id,name,ip,public_ip,vm_category,vm_type,vm_access,vm_administration,vm_instances,vm_language,vm_target_version,vm_app_server,vm_web_server,vm_containerization,vm_container_tool,vm_runtime_port,vm_tech,diagnostic_json_ref,diagnostic_json_updated_at,diagnostic_json_ref_r,diagnostic_json_updated_at_r,os_name,os_version,vcpus,ram,disk,archived,archived_at,created_at,updated_at) VALUES(" . implode(',', $values) . ")");
          }

          foreach ($systemsRows as $row) {
            if (!is_array($row)) { continue; }
            $id = (int)($row['id'] ?? 0);
            $name = normalizeUtf8Text(trim((string)($row['name'] ?? '')));
            if ($id <= 0 || $name === '') { continue; }
            $tech = $row['tech'] ?? '';
            if (is_array($tech)) { $tech = implode(',', array_filter(array_map('trim', $tech))); }
            $phpRequiredExtensions = mergeCsvListValues(
              $row['php_required_extensions'] ?? ($row['php_required_extensions_list'] ?? ''),
              $row['php_recommended_extensions'] ?? ($row['php_recommended_extensions_list'] ?? '')
            );
            $phpRecommendedExtensions = '';
            $phpRequiredLibraries = '';
            $phpRequiredIni = normalizeIniRequirementText((string)($row['php_required_ini'] ?? ''));
            $rRequiredPackages = normalizeCsvListValue($row['r_required_packages'] ?? ($row['r_required_packages_list'] ?? ''));
            if ($phpRequiredIni === '' && is_array($row['php_required_ini_list'] ?? null)) {
              $phpRequiredIni = normalizeIniRequirementText(implode("\n", array_map(fn($entry) => trim((string)$entry), $row['php_required_ini_list'])));
            }
            $values = [
              sqlValuePdo($db, $id),
              sqlValuePdo($db, $name),
              sqlValuePdo($db, trim((string)($row['system_name'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['system_group'] ?? ''))),
              sqlValuePdo($db, stripos(trim((string)($row['system_access'] ?? '')), 'extern') !== false ? 'Externo' : 'Interno'),
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
              sqlValuePdo($db, trim((string)($row['analytics'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['ssl'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['waf'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['bundle'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['directory'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['size'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['repository'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['target_version'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['app_server'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['web_server'] ?? ''))),
              sqlValuePdo($db, boolFromMixed($row['containerization'] ?? 0) ? 1 : 0),
              sqlValuePdo($db, boolFromMixed($row['containerization'] ?? 0) ? trim((string)($row['container_tool'] ?? '')) : ''),
              sqlValuePdo($db, trim((string)($row['runtime_port'] ?? ''))),
              sqlValuePdo($db, $phpRequiredExtensions),
              sqlValuePdo($db, $phpRecommendedExtensions),
              sqlValuePdo($db, $phpRequiredLibraries),
              sqlValuePdo($db, $phpRequiredIni),
              sqlValuePdo($db, $rRequiredPackages),
              sqlValuePdo($db, trim((string)($row['doc_installation_ref'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['doc_installation_updated_at'] ?? '')) ?: null),
              sqlValuePdo($db, trim((string)($row['doc_maintenance_ref'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['doc_maintenance_updated_at'] ?? '')) ?: null),
              sqlValuePdo($db, trim((string)($row['doc_security_ref'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['doc_security_updated_at'] ?? '')) ?: null),
              sqlValuePdo($db, trim((string)($row['doc_manual_ref'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['doc_manual_updated_at'] ?? '')) ?: null),
              sqlValuePdo($db, trim((string)($row['category'] ?? 'Outro')) ?: 'Outro'),
              sqlValuePdo($db, normalizeSystemStatus((string)($row['status'] ?? 'Ativo')) ?: 'Ativo'),
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
            $db->exec("INSERT INTO systems(id,name,system_name,system_group,system_access,ip,ip_homolog,vm,url_homolog,vm_homolog,vm_id,vm_homolog_id,vm_dev_id,archived,archived_at,responsible_sector,responsible_coordinator,extension_number,email,support,support_contact,analytics,ssl,waf,bundle,directory,size,repository,target_version,app_server,web_server,containerization,container_tool,runtime_port,php_required_extensions,php_recommended_extensions,php_required_libraries,php_required_ini,r_required_packages,doc_installation_ref,doc_installation_updated_at,doc_maintenance_ref,doc_maintenance_updated_at,doc_security_ref,doc_security_updated_at,doc_manual_ref,doc_manual_updated_at,category,status,tech,url,description,owner,criticality,version,notes,created_at,updated_at) VALUES(" . implode(',', $values) . ")");
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
              sqlValuePdo($db, trim((string)($row['db_instance_port'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['db_instance_homolog_name'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['db_instance_homolog_ip'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['db_instance_homolog_port'] ?? ''))),
              sqlValuePdo($db, trim((string)($row['notes'] ?? ''))),
              sqlValuePdo($db, (int)($row['archived'] ?? 0) > 0 ? 1 : 0),
              sqlValuePdo($db, trim((string)($row['archived_at'] ?? '')) ?: null),
              sqlValuePdo($db, trim((string)($row['created_at'] ?? date('Y-m-d H:i:s')))),
              sqlValuePdo($db, trim((string)($row['updated_at'] ?? date('Y-m-d H:i:s')))),
            ];
            $db->exec("INSERT INTO system_databases(id,system_id,vm_id,vm_homolog_id,db_name,db_user,db_engine,db_engine_version,db_engine_version_homolog,db_instance_name,db_instance_ip,db_instance_port,db_instance_homolog_name,db_instance_homolog_ip,db_instance_homolog_port,notes,archived,archived_at,created_at,updated_at) VALUES(" . implode(',', $values) . ")");
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
                sqlValuePdo($db, normalizeRole((string)($row['role'] ?? 'edicao'))),
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
      restoreBackupSystemDocs($systemDocFiles);
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
      $ipList = normalizeVmIpListValue($data['ip'] ?? '');
      $ip = implode(', ', $ipList);
      $publicIpList = normalizeVmIpListValue($data['public_ip'] ?? '');
      $publicIp = implode(', ', $publicIpList);
      $vmCategory = trim((string)($data['vm_category'] ?? ''));
      $vmType = trim((string)($data['vm_type'] ?? ''));
      $vmAccess = trim((string)($data['vm_access'] ?? ''));
      $vmAdministration = trim((string)($data['vm_administration'] ?? ''));
      $vmInstancesList = normalizeVmInstancesValue($data['vm_instances'] ?? []);
      $vmInstances = json_encode($vmInstancesList, JSON_UNESCAPED_UNICODE);
      if (!is_string($vmInstances)) { $vmInstances = '[]'; }
      $vmLanguage = is_array($data['vm_language'] ?? null) ? implode(',', array_filter(array_map('trim', $data['vm_language']))) : trim((string)($data['vm_language'] ?? ''));
      $vmTech = is_array($data['vm_tech'] ?? null) ? implode(',', array_filter(array_map('trim', $data['vm_tech']))) : trim((string)($data['vm_tech'] ?? ''));
      $hasVmTargetVersion = array_key_exists('vm_target_version', $data);
      $hasVmContainerization = array_key_exists('vm_containerization', $data);
      $vmTargetVersion = trim((string)($data['vm_target_version'] ?? ''));
      $vmAppServer = is_array($data['vm_app_server'] ?? null) ? implode(',', array_filter(array_map('trim', $data['vm_app_server']))) : trim((string)($data['vm_app_server'] ?? ''));
      $vmWebServer = is_array($data['vm_web_server'] ?? null) ? implode(',', array_filter(array_map('trim', $data['vm_web_server']))) : trim((string)($data['vm_web_server'] ?? ''));
      $vmContainerization = boolFromMixed($data['vm_containerization'] ?? 0) ? 1 : 0;
      $vmContainerTool = is_array($data['vm_container_tool'] ?? null) ? implode(',', array_filter(array_map('trim', $data['vm_container_tool']))) : trim((string)($data['vm_container_tool'] ?? ''));
      $vmRuntimePort = trim((string)($data['vm_runtime_port'] ?? ''));
      if ($vmAppServer === '' && $vmTech !== '') { $vmAppServer = $vmTech; }
      if ($vmTech === '' && $vmAppServer !== '') { $vmTech = $vmAppServer; }
      if (!$hasVmContainerization) {
        $vmContainerization = $vmContainerTool !== '' ? 1 : 0;
      }
      if ($vmContainerization === 0) { $vmContainerTool = ''; }
      $vmRuntimePortError = null;
      $vmRuntimePort = normalizePortListValue($vmRuntimePort, $vmRuntimePortError);
      if ($vmRuntimePortError !== null) {
        echo json_encode(['ok'=>false,'error'=>$vmRuntimePortError], JSON_UNESCAPED_UNICODE);
        return;
      }
      $osName = trim((string)($data['os_name'] ?? ''));
      $osVersion = trim((string)($data['os_version'] ?? ''));
      $vcpus = trim((string)($data['vcpus'] ?? ''));
      $ram = trim((string)($data['ram'] ?? ''));
      $disk = trim((string)($data['disk'] ?? ''));
      if ($name === '' || count($ipList) === 0) { echo json_encode(['ok'=>false,'error'=>'Nome e ao menos um IP sao obrigatorios']); return; }
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
      if (count($vmInstancesList) > 0) {
        $allowedIps = array_flip(array_map(static fn($entry) => strtolower((string)$entry), $ipList));
        foreach ($vmInstancesList as $instance) {
          $instanceIp = trim((string)($instance['ip'] ?? ''));
          if ($instanceIp === '') { continue; }
          if (!isset($allowedIps[strtolower($instanceIp)])) {
            echo json_encode(['ok'=>false,'error'=>'As instancias SGBD devem usar IP cadastrado na maquina.'], JSON_UNESCAPED_UNICODE);
            return;
          }
        }
      }

      if (!empty($data['id'])) {
        $id = (int)$data['id'];
        $currentVm = $db instanceof SQLite3 ? fetchVmByIdSqlite3($db, $id) : fetchVmByIdPdo($db, $id);
        if (!$currentVm) { echo json_encode(['ok'=>false,'error'=>'Maquina nao encontrada']); return; }
        if (!$hasVmTargetVersion) {
          $vmTargetVersion = trim((string)($currentVm['vm_target_version'] ?? ''));
        }
        if (!$hasVmContainerization) {
          if ($vmContainerTool === '') {
            $vmContainerization = boolFromMixed($currentVm['vm_containerization'] ?? 0) ? 1 : 0;
          } else {
            $vmContainerization = 1;
          }
          if ($vmContainerization === 0) { $vmContainerTool = ''; }
        }

        if ($db instanceof SQLite3) {
          $st = $db->prepare("UPDATE virtual_machines SET name=:name, ip=:ip, public_ip=:public_ip, vm_category=:vm_category, vm_type=:vm_type, vm_access=:vm_access, vm_administration=:vm_administration, vm_instances=:vm_instances, vm_language=:vm_language, vm_target_version=:vm_target_version, vm_app_server=:vm_app_server, vm_web_server=:vm_web_server, vm_containerization=:vm_containerization, vm_container_tool=:vm_container_tool, vm_runtime_port=:vm_runtime_port, vm_tech=:vm_tech, os_name=:os_name, os_version=:os_version, vcpus=:vcpus, ram=:ram, disk=:disk, updated_at=datetime('now','localtime') WHERE id=:id");
          $st->bindValue(':name', $name, SQLITE3_TEXT);
          $st->bindValue(':ip', $ip, SQLITE3_TEXT);
          $st->bindValue(':public_ip', $publicIp, SQLITE3_TEXT);
          $st->bindValue(':vm_category', $vmCategory, SQLITE3_TEXT);
          $st->bindValue(':vm_type', $vmType, SQLITE3_TEXT);
          $st->bindValue(':vm_access', $vmAccess, SQLITE3_TEXT);
          $st->bindValue(':vm_administration', $vmAdministration, SQLITE3_TEXT);
          $st->bindValue(':vm_instances', $vmInstances, SQLITE3_TEXT);
          $st->bindValue(':vm_language', $vmLanguage, SQLITE3_TEXT);
          $st->bindValue(':vm_target_version', $vmTargetVersion, SQLITE3_TEXT);
          $st->bindValue(':vm_app_server', $vmAppServer, SQLITE3_TEXT);
          $st->bindValue(':vm_web_server', $vmWebServer, SQLITE3_TEXT);
          $st->bindValue(':vm_containerization', $vmContainerization, SQLITE3_INTEGER);
          $st->bindValue(':vm_container_tool', $vmContainerTool, SQLITE3_TEXT);
          $st->bindValue(':vm_runtime_port', $vmRuntimePort, SQLITE3_TEXT);
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
          $st = $db->prepare("UPDATE virtual_machines SET name=:name, ip=:ip, public_ip=:public_ip, vm_category=:vm_category, vm_type=:vm_type, vm_access=:vm_access, vm_administration=:vm_administration, vm_instances=:vm_instances, vm_language=:vm_language, vm_target_version=:vm_target_version, vm_app_server=:vm_app_server, vm_web_server=:vm_web_server, vm_containerization=:vm_containerization, vm_container_tool=:vm_container_tool, vm_runtime_port=:vm_runtime_port, vm_tech=:vm_tech, os_name=:os_name, os_version=:os_version, vcpus=:vcpus, ram=:ram, disk=:disk, updated_at=datetime('now','localtime') WHERE id=:id");
          $st->bindValue(':name', $name, PDO::PARAM_STR);
          $st->bindValue(':ip', $ip, PDO::PARAM_STR);
          $st->bindValue(':public_ip', $publicIp, PDO::PARAM_STR);
          $st->bindValue(':vm_category', $vmCategory, PDO::PARAM_STR);
          $st->bindValue(':vm_type', $vmType, PDO::PARAM_STR);
          $st->bindValue(':vm_access', $vmAccess, PDO::PARAM_STR);
          $st->bindValue(':vm_administration', $vmAdministration, PDO::PARAM_STR);
          $st->bindValue(':vm_instances', $vmInstances, PDO::PARAM_STR);
          $st->bindValue(':vm_language', $vmLanguage, PDO::PARAM_STR);
          $st->bindValue(':vm_target_version', $vmTargetVersion, PDO::PARAM_STR);
          $st->bindValue(':vm_app_server', $vmAppServer, PDO::PARAM_STR);
          $st->bindValue(':vm_web_server', $vmWebServer, PDO::PARAM_STR);
          $st->bindValue(':vm_containerization', $vmContainerization, PDO::PARAM_INT);
          $st->bindValue(':vm_container_tool', $vmContainerTool, PDO::PARAM_STR);
          $st->bindValue(':vm_runtime_port', $vmRuntimePort, PDO::PARAM_STR);
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
          $st = $db->prepare("INSERT INTO virtual_machines(name,ip,public_ip,vm_category,vm_type,vm_access,vm_administration,vm_instances,vm_language,vm_target_version,vm_app_server,vm_web_server,vm_containerization,vm_container_tool,vm_runtime_port,vm_tech,os_name,os_version,vcpus,ram,disk) VALUES(:name,:ip,:public_ip,:vm_category,:vm_type,:vm_access,:vm_administration,:vm_instances,:vm_language,:vm_target_version,:vm_app_server,:vm_web_server,:vm_containerization,:vm_container_tool,:vm_runtime_port,:vm_tech,:os_name,:os_version,:vcpus,:ram,:disk)");
          $st->bindValue(':name', $name, SQLITE3_TEXT);
          $st->bindValue(':ip', $ip, SQLITE3_TEXT);
          $st->bindValue(':public_ip', $publicIp, SQLITE3_TEXT);
          $st->bindValue(':vm_category', $vmCategory, SQLITE3_TEXT);
          $st->bindValue(':vm_type', $vmType, SQLITE3_TEXT);
          $st->bindValue(':vm_access', $vmAccess, SQLITE3_TEXT);
          $st->bindValue(':vm_administration', $vmAdministration, SQLITE3_TEXT);
          $st->bindValue(':vm_instances', $vmInstances, SQLITE3_TEXT);
          $st->bindValue(':vm_language', $vmLanguage, SQLITE3_TEXT);
          $st->bindValue(':vm_target_version', $vmTargetVersion, SQLITE3_TEXT);
          $st->bindValue(':vm_app_server', $vmAppServer, SQLITE3_TEXT);
          $st->bindValue(':vm_web_server', $vmWebServer, SQLITE3_TEXT);
          $st->bindValue(':vm_containerization', $vmContainerization, SQLITE3_INTEGER);
          $st->bindValue(':vm_container_tool', $vmContainerTool, SQLITE3_TEXT);
          $st->bindValue(':vm_runtime_port', $vmRuntimePort, SQLITE3_TEXT);
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
          $st = $db->prepare("INSERT INTO virtual_machines(name,ip,public_ip,vm_category,vm_type,vm_access,vm_administration,vm_instances,vm_language,vm_target_version,vm_app_server,vm_web_server,vm_containerization,vm_container_tool,vm_runtime_port,vm_tech,os_name,os_version,vcpus,ram,disk) VALUES(:name,:ip,:public_ip,:vm_category,:vm_type,:vm_access,:vm_administration,:vm_instances,:vm_language,:vm_target_version,:vm_app_server,:vm_web_server,:vm_containerization,:vm_container_tool,:vm_runtime_port,:vm_tech,:os_name,:os_version,:vcpus,:ram,:disk)");
          $st->bindValue(':name', $name, PDO::PARAM_STR);
          $st->bindValue(':ip', $ip, PDO::PARAM_STR);
          $st->bindValue(':public_ip', $publicIp, PDO::PARAM_STR);
          $st->bindValue(':vm_category', $vmCategory, PDO::PARAM_STR);
          $st->bindValue(':vm_type', $vmType, PDO::PARAM_STR);
          $st->bindValue(':vm_access', $vmAccess, PDO::PARAM_STR);
          $st->bindValue(':vm_administration', $vmAdministration, PDO::PARAM_STR);
          $st->bindValue(':vm_instances', $vmInstances, PDO::PARAM_STR);
          $st->bindValue(':vm_language', $vmLanguage, PDO::PARAM_STR);
          $st->bindValue(':vm_target_version', $vmTargetVersion, PDO::PARAM_STR);
          $st->bindValue(':vm_app_server', $vmAppServer, PDO::PARAM_STR);
          $st->bindValue(':vm_web_server', $vmWebServer, PDO::PARAM_STR);
          $st->bindValue(':vm_containerization', $vmContainerization, PDO::PARAM_INT);
          $st->bindValue(':vm_container_tool', $vmContainerTool, PDO::PARAM_STR);
          $st->bindValue(':vm_runtime_port', $vmRuntimePort, PDO::PARAM_STR);
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
          'vm_target_version'=>trim((string)($vm['vm_target_version'] ?? '')),
          'vm_app_server'=>trim((string)($vm['vm_app_server'] ?? '')),
          'vm_app_server_list'=>is_array($vm['vm_app_server_list'] ?? null) ? $vm['vm_app_server_list'] : [],
          'vm_web_server'=>trim((string)($vm['vm_web_server'] ?? '')),
          'vm_web_server_list'=>is_array($vm['vm_web_server_list'] ?? null) ? $vm['vm_web_server_list'] : [],
          'vm_containerization'=>(int)($vm['vm_containerization'] ?? 0) > 0 ? 1 : 0,
          'vm_container_tool'=>trim((string)($vm['vm_container_tool'] ?? '')),
          'vm_container_tool_list'=>is_array($vm['vm_container_tool_list'] ?? null) ? $vm['vm_container_tool_list'] : [],
          'vm_runtime_port'=>trim((string)($vm['vm_runtime_port'] ?? '')),
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
        $db->exec("UPDATE system_databases SET vm_id=NULL, db_instance_name='', db_instance_ip='', db_instance_port='', updated_at=datetime('now','localtime') WHERE vm_id=$id");
        $db->exec("UPDATE system_databases SET vm_homolog_id=NULL, db_instance_homolog_name='', db_instance_homolog_ip='', db_instance_homolog_port='', updated_at=datetime('now','localtime') WHERE vm_homolog_id=$id");
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
        $stNullDbVm = $db->prepare("UPDATE system_databases SET vm_id=NULL, db_instance_name='', db_instance_ip='', db_instance_port='', updated_at=datetime('now','localtime') WHERE vm_id=:id");
        $stNullDbVm->bindValue(':id', $id, PDO::PARAM_INT);
        $stNullDbVm->execute();
        $stNullDbVmHml = $db->prepare("UPDATE system_databases SET vm_homolog_id=NULL, db_instance_homolog_name='', db_instance_homolog_ip='', db_instance_homolog_port='', updated_at=datetime('now','localtime') WHERE vm_homolog_id=:id");
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
      $dbInstancePort = trim((string)($data['db_instance_port'] ?? ''));
      $dbInstanceHomologName = trim((string)($data['db_instance_homolog_name'] ?? ''));
      $dbInstanceHomologIp = trim((string)($data['db_instance_homolog_ip'] ?? ''));
      $dbInstanceHomologPort = trim((string)($data['db_instance_homolog_port'] ?? ''));
      $notes = trim((string)($data['notes'] ?? ''));

      if ($systemId <= 0) { echo json_encode(['ok'=>false,'error'=>'Sistema obrigatorio']); return; }
      if ($vmId <= 0) { echo json_encode(['ok'=>false,'error'=>'Maquina obrigatoria']); return; }
      if ($dbName === '') { echo json_encode(['ok'=>false,'error'=>'Nome da base obrigatorio']); return; }
      $dbInstancePortError = null;
      $dbInstancePort = normalizeSinglePortValue($dbInstancePort, $dbInstancePortError, 'Porta da instancia');
      if ($dbInstancePortError !== null) {
        echo json_encode(['ok'=>false,'error'=>$dbInstancePortError], JSON_UNESCAPED_UNICODE);
        return;
      }
      $dbInstanceHomologPortError = null;
      $dbInstanceHomologPort = normalizeSinglePortValue($dbInstanceHomologPort, $dbInstanceHomologPortError, 'Porta da instancia de homologacao');
      if ($dbInstanceHomologPortError !== null) {
        echo json_encode(['ok'=>false,'error'=>$dbInstanceHomologPortError], JSON_UNESCAPED_UNICODE);
        return;
      }

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
      $selectedInstance = resolveVmInstance($vmRow, $dbInstanceName, $dbInstanceIp, $dbInstancePort);
      if (!$selectedInstance) {
        echo json_encode(['ok'=>false,'error'=>'Instancia SGBD invalida para a maquina selecionada.']);
        return;
      }

      $selectedHomologInstance = ['name' => '', 'ip' => '', 'port' => ''];
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
        $resolvedHomolog = resolveVmInstance($vmHomologRow, $dbInstanceHomologName, $dbInstanceHomologIp, $dbInstanceHomologPort);
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
            SET system_id=:system_id, vm_id=:vm_id, vm_homolog_id=:vm_homolog_id, db_name=:db_name, db_user=:db_user, db_engine=:db_engine, db_engine_version=:db_engine_version, db_engine_version_homolog=:db_engine_version_homolog, db_instance_name=:db_instance_name, db_instance_ip=:db_instance_ip, db_instance_port=:db_instance_port, db_instance_homolog_name=:db_instance_homolog_name, db_instance_homolog_ip=:db_instance_homolog_ip, db_instance_homolog_port=:db_instance_homolog_port, notes=:notes, archived=0, archived_at=NULL, updated_at=datetime('now','localtime')
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
          $st->bindValue(':db_instance_port', (string)($selectedInstance['port'] ?? ''), SQLITE3_TEXT);
          $st->bindValue(':db_instance_homolog_name', (string)($selectedHomologInstance['name'] ?? ''), SQLITE3_TEXT);
          $st->bindValue(':db_instance_homolog_ip', (string)($selectedHomologInstance['ip'] ?? ''), SQLITE3_TEXT);
          $st->bindValue(':db_instance_homolog_port', (string)($selectedHomologInstance['port'] ?? ''), SQLITE3_TEXT);
          $st->bindValue(':notes', $notes, SQLITE3_TEXT);
          $st->bindValue(':id', $id, SQLITE3_INTEGER);
          $st->execute();
          $row = fetchDatabaseByIdSqlite3($db, $id);
        } else {
          $st = $db->prepare("UPDATE system_databases
            SET system_id=:system_id, vm_id=:vm_id, vm_homolog_id=:vm_homolog_id, db_name=:db_name, db_user=:db_user, db_engine=:db_engine, db_engine_version=:db_engine_version, db_engine_version_homolog=:db_engine_version_homolog, db_instance_name=:db_instance_name, db_instance_ip=:db_instance_ip, db_instance_port=:db_instance_port, db_instance_homolog_name=:db_instance_homolog_name, db_instance_homolog_ip=:db_instance_homolog_ip, db_instance_homolog_port=:db_instance_homolog_port, notes=:notes, archived=0, archived_at=NULL, updated_at=datetime('now','localtime')
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
          $st->bindValue(':db_instance_port', (string)($selectedInstance['port'] ?? ''), PDO::PARAM_STR);
          $st->bindValue(':db_instance_homolog_name', (string)($selectedHomologInstance['name'] ?? ''), PDO::PARAM_STR);
          $st->bindValue(':db_instance_homolog_ip', (string)($selectedHomologInstance['ip'] ?? ''), PDO::PARAM_STR);
          $st->bindValue(':db_instance_homolog_port', (string)($selectedHomologInstance['port'] ?? ''), PDO::PARAM_STR);
          $st->bindValue(':notes', $notes, PDO::PARAM_STR);
          $st->bindValue(':id', $id, PDO::PARAM_INT);
          $st->execute();
          $row = fetchDatabaseByIdPdo($db, $id);
        }
      } else {
        if ($db instanceof SQLite3) {
          $st = $db->prepare("INSERT INTO system_databases(system_id,vm_id,vm_homolog_id,db_name,db_user,db_engine,db_engine_version,db_engine_version_homolog,db_instance_name,db_instance_ip,db_instance_port,db_instance_homolog_name,db_instance_homolog_ip,db_instance_homolog_port,notes) VALUES(:system_id,:vm_id,:vm_homolog_id,:db_name,:db_user,:db_engine,:db_engine_version,:db_engine_version_homolog,:db_instance_name,:db_instance_ip,:db_instance_port,:db_instance_homolog_name,:db_instance_homolog_ip,:db_instance_homolog_port,:notes)");
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
          $st->bindValue(':db_instance_port', (string)($selectedInstance['port'] ?? ''), SQLITE3_TEXT);
          $st->bindValue(':db_instance_homolog_name', (string)($selectedHomologInstance['name'] ?? ''), SQLITE3_TEXT);
          $st->bindValue(':db_instance_homolog_ip', (string)($selectedHomologInstance['ip'] ?? ''), SQLITE3_TEXT);
          $st->bindValue(':db_instance_homolog_port', (string)($selectedHomologInstance['port'] ?? ''), SQLITE3_TEXT);
          $st->bindValue(':notes', $notes, SQLITE3_TEXT);
          $st->execute();
          $id = (int)$db->lastInsertRowID();
          $row = fetchDatabaseByIdSqlite3($db, $id);
        } else {
          $st = $db->prepare("INSERT INTO system_databases(system_id,vm_id,vm_homolog_id,db_name,db_user,db_engine,db_engine_version,db_engine_version_homolog,db_instance_name,db_instance_ip,db_instance_port,db_instance_homolog_name,db_instance_homolog_ip,db_instance_homolog_port,notes) VALUES(:system_id,:vm_id,:vm_homolog_id,:db_name,:db_user,:db_engine,:db_engine_version,:db_engine_version_homolog,:db_instance_name,:db_instance_ip,:db_instance_port,:db_instance_homolog_name,:db_instance_homolog_ip,:db_instance_homolog_port,:notes)");
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
          $st->bindValue(':db_instance_port', (string)($selectedInstance['port'] ?? ''), PDO::PARAM_STR);
          $st->bindValue(':db_instance_homolog_name', (string)($selectedHomologInstance['name'] ?? ''), PDO::PARAM_STR);
          $st->bindValue(':db_instance_homolog_ip', (string)($selectedHomologInstance['ip'] ?? ''), PDO::PARAM_STR);
          $st->bindValue(':db_instance_homolog_port', (string)($selectedHomologInstance['port'] ?? ''), PDO::PARAM_STR);
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

    if ($api === 'ticket-save') {
      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
      $data = json_decode((string)file_get_contents('php://input'), true);
      if (!is_array($data)) { echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); return; }

      $targetType = normalizeTicketTargetType((string)($data['target_type'] ?? 'system'));
      $ticketNumber = trim((string)($data['ticket_number'] ?? ''));
      $description = trim((string)($data['description'] ?? ''));
      $systemId = (int)($data['system_id'] ?? 0);
      $vmId = (int)($data['vm_id'] ?? 0);

      if ($ticketNumber === '') {
        echo json_encode(['ok'=>false,'error'=>'Numero do chamado e obrigatorio.'], JSON_UNESCAPED_UNICODE);
        return;
      }
      if ($description === '') {
        echo json_encode(['ok'=>false,'error'=>'Descricao do chamado e obrigatoria.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      if ($targetType === 'system') {
        if ($systemId <= 0) {
          echo json_encode(['ok'=>false,'error'=>'Selecione um sistema valido.'], JSON_UNESCAPED_UNICODE);
          return;
        }
        if (($db instanceof SQLite3 ? fetchActiveSystemNameSqlite3($db, $systemId) : fetchActiveSystemNamePdo($db, $systemId)) === null) {
          echo json_encode(['ok'=>false,'error'=>'Sistema invalido ou arquivado.'], JSON_UNESCAPED_UNICODE);
          return;
        }
        $vmId = 0;
      } else {
        if ($vmId <= 0) {
          echo json_encode(['ok'=>false,'error'=>'Selecione uma maquina valida.'], JSON_UNESCAPED_UNICODE);
          return;
        }
        if (($db instanceof SQLite3 ? fetchActiveVmNameSqlite3($db, $vmId) : fetchActiveVmNamePdo($db, $vmId)) === null) {
          echo json_encode(['ok'=>false,'error'=>'Maquina invalida ou arquivada.'], JSON_UNESCAPED_UNICODE);
          return;
        }
        $systemId = 0;
      }

      if ($db instanceof SQLite3) {
        $st = $db->prepare("INSERT INTO tickets(target_type,system_id,vm_id,ticket_number,description,created_at,updated_at) VALUES(:target_type,:system_id,:vm_id,:ticket_number,:description,datetime('now','localtime'),datetime('now','localtime'))");
        $st->bindValue(':target_type', $targetType, SQLITE3_TEXT);
        if ($systemId > 0) { $st->bindValue(':system_id', $systemId, SQLITE3_INTEGER); }
        else { $st->bindValue(':system_id', null, SQLITE3_NULL); }
        if ($vmId > 0) { $st->bindValue(':vm_id', $vmId, SQLITE3_INTEGER); }
        else { $st->bindValue(':vm_id', null, SQLITE3_NULL); }
        $st->bindValue(':ticket_number', $ticketNumber, SQLITE3_TEXT);
        $st->bindValue(':description', $description, SQLITE3_TEXT);
        $st->execute();
        $id = (int)$db->lastInsertRowID();
        $row = fetchTicketByIdSqlite3($db, $id);
      } else {
        $st = $db->prepare("INSERT INTO tickets(target_type,system_id,vm_id,ticket_number,description,created_at,updated_at) VALUES(:target_type,:system_id,:vm_id,:ticket_number,:description,datetime('now','localtime'),datetime('now','localtime'))");
        $st->bindValue(':target_type', $targetType, PDO::PARAM_STR);
        if ($systemId > 0) { $st->bindValue(':system_id', $systemId, PDO::PARAM_INT); }
        else { $st->bindValue(':system_id', null, PDO::PARAM_NULL); }
        if ($vmId > 0) { $st->bindValue(':vm_id', $vmId, PDO::PARAM_INT); }
        else { $st->bindValue(':vm_id', null, PDO::PARAM_NULL); }
        $st->bindValue(':ticket_number', $ticketNumber, PDO::PARAM_STR);
        $st->bindValue(':description', $description, PDO::PARAM_STR);
        $st->execute();
        $id = (int)$db->lastInsertId();
        $row = fetchTicketByIdPdo($db, $id);
      }

      if (!$row) { echo json_encode(['ok'=>false,'error'=>'Chamado nao encontrado.'], JSON_UNESCAPED_UNICODE); return; }
      echo json_encode(['ok'=>true,'data'=>$row], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'ticket-update') {
      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
      $data = json_decode((string)file_get_contents('php://input'), true);
      if (!is_array($data)) { echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); return; }

      $id = (int)($data['id'] ?? 0);
      if ($id <= 0) {
        echo json_encode(['ok'=>false,'error'=>'ID do chamado invalido.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      $existing = $db instanceof SQLite3 ? fetchTicketByIdSqlite3($db, $id) : fetchTicketByIdPdo($db, $id);
      if (!$existing) {
        echo json_encode(['ok'=>false,'error'=>'Chamado nao encontrado.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      $targetType = normalizeTicketTargetType((string)($data['target_type'] ?? $existing['target_type'] ?? 'system'));
      $ticketNumber = trim((string)($data['ticket_number'] ?? ''));
      $description = trim((string)($data['description'] ?? ''));
      $systemId = (int)($data['system_id'] ?? 0);
      $vmId = (int)($data['vm_id'] ?? 0);

      if ($ticketNumber === '') {
        echo json_encode(['ok'=>false,'error'=>'Numero do chamado e obrigatorio.'], JSON_UNESCAPED_UNICODE);
        return;
      }
      if ($description === '') {
        echo json_encode(['ok'=>false,'error'=>'Descricao do chamado e obrigatoria.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      if ($targetType === 'system') {
        if ($systemId <= 0) {
          echo json_encode(['ok'=>false,'error'=>'Selecione um sistema valido.'], JSON_UNESCAPED_UNICODE);
          return;
        }
        if (($db instanceof SQLite3 ? fetchActiveSystemNameSqlite3($db, $systemId) : fetchActiveSystemNamePdo($db, $systemId)) === null) {
          echo json_encode(['ok'=>false,'error'=>'Sistema invalido ou arquivado.'], JSON_UNESCAPED_UNICODE);
          return;
        }
        $vmId = 0;
      } else {
        if ($vmId <= 0) {
          echo json_encode(['ok'=>false,'error'=>'Selecione uma maquina valida.'], JSON_UNESCAPED_UNICODE);
          return;
        }
        if (($db instanceof SQLite3 ? fetchActiveVmNameSqlite3($db, $vmId) : fetchActiveVmNamePdo($db, $vmId)) === null) {
          echo json_encode(['ok'=>false,'error'=>'Maquina invalida ou arquivada.'], JSON_UNESCAPED_UNICODE);
          return;
        }
        $systemId = 0;
      }

      if ($db instanceof SQLite3) {
        $st = $db->prepare("UPDATE tickets SET target_type=:target_type, system_id=:system_id, vm_id=:vm_id, ticket_number=:ticket_number, description=:description, updated_at=datetime('now','localtime') WHERE id=:id");
        $st->bindValue(':target_type', $targetType, SQLITE3_TEXT);
        if ($systemId > 0) { $st->bindValue(':system_id', $systemId, SQLITE3_INTEGER); }
        else { $st->bindValue(':system_id', null, SQLITE3_NULL); }
        if ($vmId > 0) { $st->bindValue(':vm_id', $vmId, SQLITE3_INTEGER); }
        else { $st->bindValue(':vm_id', null, SQLITE3_NULL); }
        $st->bindValue(':ticket_number', $ticketNumber, SQLITE3_TEXT);
        $st->bindValue(':description', $description, SQLITE3_TEXT);
        $st->bindValue(':id', $id, SQLITE3_INTEGER);
        $st->execute();
        $row = fetchTicketByIdSqlite3($db, $id);
      } else {
        $st = $db->prepare("UPDATE tickets SET target_type=:target_type, system_id=:system_id, vm_id=:vm_id, ticket_number=:ticket_number, description=:description, updated_at=datetime('now','localtime') WHERE id=:id");
        $st->bindValue(':target_type', $targetType, PDO::PARAM_STR);
        if ($systemId > 0) { $st->bindValue(':system_id', $systemId, PDO::PARAM_INT); }
        else { $st->bindValue(':system_id', null, PDO::PARAM_NULL); }
        if ($vmId > 0) { $st->bindValue(':vm_id', $vmId, PDO::PARAM_INT); }
        else { $st->bindValue(':vm_id', null, PDO::PARAM_NULL); }
        $st->bindValue(':ticket_number', $ticketNumber, PDO::PARAM_STR);
        $st->bindValue(':description', $description, PDO::PARAM_STR);
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
        $row = fetchTicketByIdPdo($db, $id);
      }

      if (!$row) { echo json_encode(['ok'=>false,'error'=>'Chamado nao encontrado.'], JSON_UNESCAPED_UNICODE); return; }
      echo json_encode(['ok'=>true,'data'=>$row], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'ticket-delete') {
      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
      $data = json_decode((string)file_get_contents('php://input'), true);
      if (!is_array($data)) { echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); return; }

      $id = (int)($data['id'] ?? 0);
      if ($id <= 0) {
        echo json_encode(['ok'=>false,'error'=>'ID do chamado invalido.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      $existing = $db instanceof SQLite3 ? fetchTicketByIdSqlite3($db, $id) : fetchTicketByIdPdo($db, $id);
      if (!$existing) {
        echo json_encode(['ok'=>false,'error'=>'Chamado nao encontrado.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      if ($db instanceof SQLite3) {
        $st = $db->prepare("DELETE FROM tickets WHERE id=:id");
        $st->bindValue(':id', $id, SQLITE3_INTEGER);
        $st->execute();
      } else {
        $st = $db->prepare("DELETE FROM tickets WHERE id=:id");
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
      }

      echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'system-doc-upload') {
      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
      $data = json_decode((string)file_get_contents('php://input'), true);
      if (!is_array($data)) { echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); return; }

      $systemId = (int)($data['system_id'] ?? 0);
      $docType = normalizeSystemDocType((string)($data['doc_type'] ?? ''));
      $docMap = systemDocFieldMap($docType);
      $filename = trim((string)($data['filename'] ?? ''));
      $contentBase64 = trim((string)($data['content_base64'] ?? ''));
      if ($systemId <= 0 || $docMap === null) {
        echo json_encode(['ok'=>false,'error'=>'Parametros invalidos para upload do documento.'], JSON_UNESCAPED_UNICODE);
        return;
      }
      if ($contentBase64 === '') {
        echo json_encode(['ok'=>false,'error'=>'Conteudo do PDF nao informado.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      if (preg_match('#^data:application/pdf;base64,#i', $contentBase64) === 1) {
        $contentBase64 = preg_replace('#^data:application/pdf;base64,#i', '', $contentBase64) ?? '';
      }
      $decoded = base64_decode($contentBase64, true);
      if ($decoded === false || $decoded === '') {
        echo json_encode(['ok'=>false,'error'=>'Arquivo PDF invalido (base64).'], JSON_UNESCAPED_UNICODE);
        return;
      }
      if (strncmp($decoded, '%PDF', 4) !== 0) {
        echo json_encode(['ok'=>false,'error'=>'O arquivo enviado nao parece ser um PDF valido.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      $current = $db instanceof SQLite3 ? fetchSystemByIdSqlite3($db, $systemId) : fetchSystemByIdPdo($db, $systemId);
      if (!$current) {
        echo json_encode(['ok'=>false,'error'=>'Sistema nao encontrado.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      $safeName = sanitizeSystemDocFilename($filename !== '' ? $filename : ($docType . '.pdf'));
      $dir = ensureSystemDocDir();
      $stamp = date('Ymd_His');
      $storedName = "system_{$systemId}_{$docType}_{$stamp}_{$safeName}";
      $fullPath = $dir . DIRECTORY_SEPARATOR . $storedName;
      if (@file_put_contents($fullPath, $decoded) === false) {
        echo json_encode(['ok'=>false,'error'=>'Falha ao gravar arquivo PDF.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      $reference = 'data/system_docs/' . $storedName;
      $updatedAt = date('Y-m-d H:i:s');
      $oldReference = trim((string)($current[$docMap['ref']] ?? ''));
      if ($db instanceof SQLite3) {
        $st = $db->prepare("UPDATE systems SET {$docMap['ref']}=:ref, {$docMap['updated_at']}=:doc_updated_at, updated_at=datetime('now','localtime') WHERE id=:id");
        $st->bindValue(':ref', $reference, SQLITE3_TEXT);
        $st->bindValue(':doc_updated_at', $updatedAt, SQLITE3_TEXT);
        $st->bindValue(':id', $systemId, SQLITE3_INTEGER);
        $st->execute();
        $row = fetchSystemByIdSqlite3($db, $systemId);
      } else {
        $st = $db->prepare("UPDATE systems SET {$docMap['ref']}=:ref, {$docMap['updated_at']}=:doc_updated_at, updated_at=datetime('now','localtime') WHERE id=:id");
        $st->bindValue(':ref', $reference, PDO::PARAM_STR);
        $st->bindValue(':doc_updated_at', $updatedAt, PDO::PARAM_STR);
        $st->bindValue(':id', $systemId, PDO::PARAM_INT);
        $st->execute();
        $row = fetchSystemByIdPdo($db, $systemId);
      }

      if ($oldReference !== '' && $oldReference !== $reference) {
        deleteSystemDocFileByReference($oldReference);
      }
      if (!$row) { echo json_encode(['ok'=>false,'error'=>'Sistema nao encontrado apos upload.'], JSON_UNESCAPED_UNICODE); return; }
      echo json_encode(['ok'=>true,'data'=>$row], JSON_UNESCAPED_UNICODE);
      return;
    }

    if ($api === 'system-doc-delete') {
      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
      $data = json_decode((string)file_get_contents('php://input'), true);
      if (!is_array($data)) { echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); return; }

      $systemId = (int)($data['system_id'] ?? 0);
      $docType = normalizeSystemDocType((string)($data['doc_type'] ?? ''));
      $docMap = systemDocFieldMap($docType);
      if ($systemId <= 0 || $docMap === null) {
        echo json_encode(['ok'=>false,'error'=>'Parametros invalidos para exclusao do documento.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      $current = $db instanceof SQLite3 ? fetchSystemByIdSqlite3($db, $systemId) : fetchSystemByIdPdo($db, $systemId);
      if (!$current) {
        echo json_encode(['ok'=>false,'error'=>'Sistema nao encontrado.'], JSON_UNESCAPED_UNICODE);
        return;
      }

      $oldReference = trim((string)($current[$docMap['ref']] ?? ''));
      if ($db instanceof SQLite3) {
        $st = $db->prepare("UPDATE systems SET {$docMap['ref']}='', {$docMap['updated_at']}=NULL, updated_at=datetime('now','localtime') WHERE id=:id");
        $st->bindValue(':id', $systemId, SQLITE3_INTEGER);
        $st->execute();
        $row = fetchSystemByIdSqlite3($db, $systemId);
      } else {
        $st = $db->prepare("UPDATE systems SET {$docMap['ref']}='', {$docMap['updated_at']}=NULL, updated_at=datetime('now','localtime') WHERE id=:id");
        $st->bindValue(':id', $systemId, PDO::PARAM_INT);
        $st->execute();
        $row = fetchSystemByIdPdo($db, $systemId);
      }

      if ($oldReference !== '') {
        deleteSystemDocFileByReference($oldReference);
      }
      if (!$row) { echo json_encode(['ok'=>false,'error'=>'Sistema nao encontrado apos exclusao do documento.'], JSON_UNESCAPED_UNICODE); return; }
      echo json_encode(['ok'=>true,'data'=>$row], JSON_UNESCAPED_UNICODE);
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
      $data['target_version'] = trim((string)($data['target_version'] ?? ''));
      $data['app_server'] = trim((string)($data['app_server'] ?? ''));
      $data['web_server'] = trim((string)($data['web_server'] ?? ''));
      $systemAccess = trim((string)($data['system_access'] ?? ''));
      $data['system_access'] = strcasecmp($systemAccess, 'Externo') === 0 ? 'Externo' : 'Interno';
      $data['containerization'] = boolFromMixed($data['containerization'] ?? 0) ? 1 : 0;
      $data['container_tool'] = trim((string)($data['container_tool'] ?? ''));
      if ((int)$data['containerization'] <= 0) { $data['container_tool'] = ''; }
      $runtimePortError = null;
      $data['runtime_port'] = normalizePortListValue($data['runtime_port'] ?? '', $runtimePortError);
      $data['php_required_extensions'] = mergeCsvListValues(
        $data['php_required_extensions'] ?? '',
        $data['php_recommended_extensions'] ?? ''
      );
      $data['php_recommended_extensions'] = '';
      $data['php_required_libraries'] = '';
      if (is_array($data['php_required_ini'] ?? null)) {
        $data['php_required_ini'] = normalizeIniRequirementText(implode("\n", array_map(fn($entry) => trim((string)$entry), $data['php_required_ini'])));
      } else {
        $data['php_required_ini'] = normalizeIniRequirementText((string)($data['php_required_ini'] ?? ''));
      }
      $data['r_required_packages'] = normalizeCsvListValue($data['r_required_packages'] ?? '');
      if ($runtimePortError !== null) {
        echo json_encode(['ok'=>false,'error'=>$runtimePortError], JSON_UNESCAPED_UNICODE);
        return;
      }

      $fields = ['name','system_name','vm_id','vm_homolog_id','vm_dev_id','category','system_group','system_access','status','url','url_homolog','description','owner','criticality','version','notes','responsible_sector','responsible_coordinator','extension_number','email','support','support_contact','analytics','ssl','waf','bundle','directory','size','repository','target_version','app_server','web_server','containerization','container_tool','runtime_port','php_required_extensions','php_recommended_extensions','php_required_libraries','php_required_ini','r_required_packages','archived','archived_at'];
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
      $systemRow = $db instanceof SQLite3 ? fetchSystemByIdSqlite3($db, $id) : fetchSystemByIdPdo($db, $id);
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
      if (is_array($systemRow)) {
        foreach (systemDocReferencesFromSystemRow($systemRow) as $reference) {
          deleteSystemDocFileByReference((string)$reference);
        }
      }
      echo json_encode(['ok'=>true]);
      return;
    }

    echo json_encode(['ok'=>false,'error'=>'Unknown action']);
  } catch (Throwable $e) {
    $status = http_response_code();
    if (!is_int($status) || $status < 400) {
      http_response_code(500);
    }
    error_log('[SEI API] ' . $e->getMessage());
    echo json_encode(['ok'=>false,'error'=>'Erro interno no servidor.'], JSON_UNESCAPED_UNICODE);
  }
}

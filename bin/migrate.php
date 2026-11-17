<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
try {
 $pdo = database($config);
 if ($pdo->query("SELECT GET_LOCK('sya_migrations', 10)")->fetchColumn() != 1) { throw new RuntimeException('Migration lock unavailable'); }
 try {
  foreach (glob(dirname(__DIR__) . '/database/migrations/*.sql') as $file) {
   $version = basename($file); $sql = file_get_contents($file); $checksum = hash('sha256', $sql);
   // Bootstrap only the ledger; MySQL DDL implicitly commits, so each migration must be restart-safe.
   $pdo->exec(file_get_contents(dirname(__DIR__) . '/database/migrations/001_foundation.sql'));
   $check = $pdo->prepare('SELECT checksum FROM schema_migrations WHERE version = ?'); $check->execute([$version]);
   $old = $check->fetchColumn();
   if ($old !== false) { if (!hash_equals($old, $checksum)) { throw new RuntimeException('Applied migration changed'); } echo "Already applied: $version\n"; continue; }
   $pdo->exec($sql);
   $insert = $pdo->prepare('INSERT INTO schema_migrations (version, checksum) VALUES (?, ?)'); $insert->execute([$version, $checksum]);
   echo "Applied: $version\n";
  }
  echo 'Database connection verified; schema version: ' . $pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn() . "\n";
 } finally { $pdo->query("SELECT RELEASE_LOCK('sya_migrations')"); }
} catch (Throwable $error) { fwrite(STDERR, "Database migration failed. Check local configuration and database access.\n"); exit(1); }

<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
function check(bool $condition, string $message): void { if (!$condition) { throw new RuntimeException($message); } echo "PASS: $message\n"; }
$pdo = database($config);
check($pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES) === false, 'Native prepared statements');
$stmt = $pdo->prepare('SELECT ? AS value');
$value = "Isioma & Ayo — Ọrẹ́ '; DROP TABLE schema_migrations; --";
$stmt->execute([$value]);
check($stmt->fetchColumn() === $value, 'UTF-8 and SQL metacharacters remain literal');
check((int)$pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn() === 1, 'Exactly one migration recorded');
check($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) === ['schema_migrations'], 'Only essential foundation ledger exists');

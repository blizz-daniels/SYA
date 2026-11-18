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
check((int)$pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn() === 5, 'Exactly five migrations recorded');
$expected = ['cms_categories','media_collections','media_assets','cms_items','cms_revisions','cms_events','admin_settings', 'administrators', 'audition_appointments', 'audition_callbacks', 'audition_events', 'audition_notifications', 'audition_sessions', 'audition_slots', 'audit_log', 'auth_rate_limits', 'evaluation_rubric_versions', 'evaluation_rubrics', 'evaluations', 'recruitment_applications', 'recruitment_attachments', 'recruitment_delivery_log', 'recruitment_events', 'recruitment_form_templates', 'recruitment_form_versions', 'recruitment_roles', 'recruitment_tracking_challenges', 'schema_migrations', 'system_state'];
$actual = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
sort($expected); sort($actual);
check($actual === $expected, 'Only Milestone 1–5 tables exist');

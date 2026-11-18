<?php
declare(strict_types=1);
return [
 'name' => 'See You Around', 'environment' => 'production', 'base_url' => '', 'debug' => false,
 'database' => ['host' => '127.0.0.1', 'port' => 3306, 'name' => 'see_you_around', 'username' => 'sya', 'password' => ''],
 'session' => ['name' => 'sya_session', 'idle_seconds' => 1800, 'secure' => true, 'same_site' => 'Lax'],
 'uploads' => ['max_bytes' => 2097152, 'allowed_types' => ['image/jpeg','image/png','image/webp','application/pdf']],
 'recruitment' => ['privacy_approved' => false, 'uploads_enabled' => false, 'privacy_notice' => '', 'privacy_contact' => '', 'notice_version' => '1'],
 'features' => ['recruitment' => false, 'newsletter' => false, 'contact' => false],
 'email' => ['provider' => 'resend', 'api_key' => '', 'from_email' => '', 'from_name' => 'See You Around', 'delivery_verified' => false],
 'storage' => ['private_path' => dirname(__DIR__) . '/storage/private', 'access_verified' => false],
 'security' => ['https_required' => true, 'app_key' => '', 'setup_token_hash' => ''], 'pagination_limit' => 20,
 'social_links' => [], // Optional approved ['label'=>'Instagram','url'=>'https://...'] entries; no tracking widgets.
 'communications' => ['privacy_approved' => false, 'notice' => '', 'notice_version' => '1', 'privacy_contact' => '', 'rights_enabled' => false, 'response_days' => 30, 'contact_retention_days' => 180],
 'backups' => ['retention_days' => 7, 'reminder_days' => 14],
 'tracking' => ['code_lifetime_seconds' => 600], 'retention' => ['unsuccessful_applicant_months' => 12],
];

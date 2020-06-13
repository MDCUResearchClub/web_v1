<?php

// @codingStandardsIgnoreFile

/**
 * @file
 * Drupal site-specific configuration file.
 */

$settings['config_sync_directory'] = '../config/sync';

$settings['hash_salt'] = file_get_contents('/var/run/drupal_hash_salt');

$settings['update_free_access'] = FALSE;

$settings['reverse_proxy'] = TRUE;
$settings['reverse_proxy_addresses'] = [$_SERVER['REMOTE_ADDR']];

$settings['file_private_path'] = '/var/www/html/files';

$settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.yml';

$settings['trusted_host_patterns'] = [
  '^mdcuresearchclub\.thew\.pro$',
  '^.+\.mdcuresearchclub\.thew\.pro$',
];

$settings['file_scan_ignore_directories'] = [
  'node_modules',
  'bower_components',
];

$settings['entity_update_batch_size'] = 50;

$settings['entity_update_backup'] = TRUE;

$settings['migrate_node_migrate_type_classic'] = FALSE;

$databases['default']['default'] = array (
  'database' => 'postgres',
  'username' => 'postgres',
  'password' => file_get_contents('/var/run/postgres_password'),
  'prefix' => '',
  'host' => 'postgres',
  'port' => '5432',
  'namespace' => 'Drupal\\Core\\Database\\Driver\\pgsql',
  'driver' => 'pgsql',
);

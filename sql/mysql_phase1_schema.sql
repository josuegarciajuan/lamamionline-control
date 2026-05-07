-- Fase 1 · Esquema inicial MySQL para migracion del CRM
-- Auditoria rehecha y cerrada sobre el runtime real el 2026-04-04.
-- El runtime activo usa el directorio data/ y se conserva el prefijo crm_
-- para no colisionar con tablas previas de telefonosbd.

CREATE DATABASE IF NOT EXISTS telefonosbd
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE telefonosbd;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS crm_settings (
  id VARCHAR(32) NOT NULL PRIMARY KEY,
  updated_at DATETIME NULL,
  payload_json JSON NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_users (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  username VARCHAR(191) NOT NULL,
  name VARCHAR(191) NULL,
  password VARCHAR(255) NULL,
  password_hash VARCHAR(255) NULL,
  raw_json JSON NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  KEY idx_crm_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_agenda (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  nombre VARCHAR(191) NULL,
  telefono VARCHAR(32) NULL,
  telefono_norm VARCHAR(16) NULL,
  observaciones TEXT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  raw_json JSON NULL,
  KEY idx_crm_agenda_telefono_norm (telefono_norm),
  KEY idx_crm_agenda_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_gastos (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  descripcion VARCHAR(255) NULL,
  cantidad DECIMAL(12,2) NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  raw_json JSON NULL,
  KEY idx_crm_gastos_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_interesadas (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  telefono VARCHAR(32) NULL,
  telefono_norm VARCHAR(16) NULL,
  movil_origen VARCHAR(32) NULL,
  movil_origen_norm VARCHAR(16) NULL,
  estado VARCHAR(64) NULL,
  observaciones TEXT NULL,
  cliente_id VARCHAR(64) NULL,
  convertida_at DATETIME NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  raw_json JSON NULL,
  KEY idx_crm_interesadas_telefono_norm (telefono_norm),
  KEY idx_crm_interesadas_estado (estado),
  KEY idx_crm_interesadas_cliente_id (cliente_id),
  KEY idx_crm_interesadas_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_clientes (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  nombre VARCHAR(191) NULL,
  telefono VARCHAR(32) NULL,
  telefono_norm VARCHAR(16) NULL,
  estado VARCHAR(64) NULL,
  fecha_alta DATE NULL,
  fecha_baja DATE NULL,
  localidad VARCHAR(191) NULL,
  provincia VARCHAR(191) NULL,
  zona VARCHAR(191) NULL,
  servicios TEXT NULL,
  tarifas TEXT NULL,
  precio_alta DECIMAL(12,2) NULL,
  modo_pago VARCHAR(64) NULL,
  ubicacion_maps TEXT NULL,
  notas TEXT NULL,
  source_interesada_id VARCHAR(64) NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  raw_json JSON NULL,
  KEY idx_crm_clientes_telefono_norm (telefono_norm),
  KEY idx_crm_clientes_estado (estado),
  KEY idx_crm_clientes_source_interesada_id (source_interesada_id),
  KEY idx_crm_clientes_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_leads (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  cliente_id VARCHAR(64) NULL,
  cliente_nombre VARCHAR(191) NULL,
  bot_id VARCHAR(64) NULL,
  bot_nombre VARCHAR(191) NULL,
  fecha_hora VARCHAR(32) NULL,
  precio_lead DECIMAL(12,2) NULL,
  observaciones TEXT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  raw_json JSON NULL,
  KEY idx_crm_leads_cliente_id (cliente_id),
  KEY idx_crm_leads_bot_id (bot_id),
  KEY idx_crm_leads_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_telefonos (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  nombre VARCHAR(191) NULL,
  tfono VARCHAR(32) NULL,
  telefono_norm VARCHAR(16) NULL,
  compania VARCHAR(191) NULL,
  pin VARCHAR(64) NULL,
  uso VARCHAR(64) NULL,
  destacamos_id VARCHAR(64) NULL,
  waha TINYINT(1) NOT NULL DEFAULT 0,
  waha_port VARCHAR(32) NULL,
  notas TEXT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  raw_json JSON NULL,
  KEY idx_crm_telefonos_telefono_norm (telefono_norm),
  KEY idx_crm_telefonos_uso (uso),
  KEY idx_crm_telefonos_waha_port (waha_port)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_bots (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  cliente_id VARCHAR(64) NULL,
  nombre_bot VARCHAR(191) NULL,
  estado VARCHAR(64) NULL,
  telefono_bot VARCHAR(32) NULL,
  telefono_norm VARCHAR(16) NULL,
  server_ip VARCHAR(64) NULL,
  ubicacion_maps TEXT NULL,
  servicios TEXT NULL,
  tarifas TEXT NULL,
  zona VARCHAR(191) NULL,
  bot_mode VARCHAR(64) NULL,
  generated_assets JSON NULL,
  waha_port VARCHAR(32) NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  raw_json JSON NULL,
  KEY idx_crm_bots_cliente_id (cliente_id),
  KEY idx_crm_bots_estado (estado),
  KEY idx_crm_bots_telefono_norm (telefono_norm)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_lamamibot (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  nombre_bot VARCHAR(191) NULL,
  estado VARCHAR(64) NULL,
  girlsconf_base_url TEXT NULL,
  girlsconf_json_path TEXT NULL,
  generated_assets JSON NULL,
  clientas_ids JSON NULL,
  telefonos_ids JSON NULL,
  last_sync_at DATETIME NULL,
  last_sync_summary TEXT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  raw_json JSON NULL,
  KEY idx_crm_lamamibot_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_eurekas (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  descripcion TEXT NULL,
  estado VARCHAR(64) NULL,
  prompt_codex LONGTEXT NULL,
  prompt_generated_at DATETIME NULL,
  source VARCHAR(64) NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  raw_json JSON NULL,
  KEY idx_crm_eurekas_estado (estado),
  KEY idx_crm_eurekas_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_casawasap_contactos (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  nombre VARCHAR(191) NULL,
  telefono VARCHAR(32) NULL,
  telefono_norm VARCHAR(16) NULL,
  estado VARCHAR(64) NULL,
  modo VARCHAR(64) NULL,
  precio DECIMAL(12,2) NULL,
  periodicidad_cobro VARCHAR(64) NULL,
  quien_lo_trae VARCHAR(191) NULL,
  notas TEXT NULL,
  cliente_at DATETIME NULL,
  baja_at DATETIME NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  raw_json JSON NULL,
  KEY idx_crm_casawasap_contactos_telefono_norm (telefono_norm),
  KEY idx_crm_casawasap_contactos_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_casawasap_pagos (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  cliente_id VARCHAR(64) NULL,
  cliente_nombre VARCHAR(191) NULL,
  fecha_hora DATETIME NULL,
  importe DECIMAL(12,2) NULL,
  observaciones TEXT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  raw_json JSON NULL,
  KEY idx_crm_casawasap_pagos_cliente_id (cliente_id),
  KEY idx_crm_casawasap_pagos_fecha_hora (fecha_hora),
  KEY idx_crm_casawasap_pagos_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_jostal_interesadas (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  telefono VARCHAR(32) NULL,
  telefono_norm VARCHAR(16) NULL,
  estado VARCHAR(64) NULL,
  fecha DATE NULL,
  interesada_en VARCHAR(191) NULL,
  observaciones TEXT NULL,
  clienta_id VARCHAR(64) NULL,
  convertida_at DATETIME NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  raw_json JSON NULL,
  KEY idx_crm_jostal_interesadas_telefono_norm (telefono_norm),
  KEY idx_crm_jostal_interesadas_estado (estado),
  KEY idx_crm_jostal_interesadas_clienta_id (clienta_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_jostal_clientas (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  nombre VARCHAR(191) NULL,
  telefono VARCHAR(32) NULL,
  telefono_norm VARCHAR(16) NULL,
  modo VARCHAR(64) NULL,
  observaciones TEXT NULL,
  periodos_estancia JSON NULL,
  source_interesada_id VARCHAR(64) NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  raw_json JSON NULL,
  KEY idx_crm_jostal_clientas_telefono_norm (telefono_norm),
  KEY idx_crm_jostal_clientas_modo (modo),
  KEY idx_crm_jostal_clientas_source_interesada_id (source_interesada_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_jostal_leads (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  clienta_id VARCHAR(64) NULL,
  clienta_nombre VARCHAR(191) NULL,
  observacion TEXT NULL,
  precio DECIMAL(12,2) NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  raw_json JSON NULL,
  KEY idx_crm_jostal_leads_clienta_id (clienta_id),
  KEY idx_crm_jostal_leads_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_jostal_ventas (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  clienta_id VARCHAR(64) NULL,
  descripcion TEXT NULL,
  precio DECIMAL(12,2) NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  raw_json JSON NULL,
  KEY idx_crm_jostal_ventas_clienta_id (clienta_id),
  KEY idx_crm_jostal_ventas_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_avisos_runs (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  started_at DATETIME NULL,
  finished_at DATETIME NULL,
  send_whatsapp TINYINT(1) NOT NULL DEFAULT 0,
  engines JSON NULL,
  summary JSON NULL,
  raw_json JSON NULL,
  KEY idx_crm_avisos_runs_started_at (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_avisos (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  source_key VARCHAR(191) NULL,
  title VARCHAR(255) NULL,
  message TEXT NULL,
  severity VARCHAR(32) NULL,
  status VARCHAR(32) NULL,
  engine VARCHAR(64) NULL,
  auto_resolve TINYINT(1) NOT NULL DEFAULT 0,
  occurrences INT NOT NULL DEFAULT 0,
  meta_json JSON NULL,
  last_eval_action VARCHAR(64) NULL,
  last_run_id VARCHAR(64) NULL,
  scheduled_for DATETIME NULL,
  activated_at DATETIME NULL,
  last_seen_at DATETIME NULL,
  read_at DATETIME NULL,
  dismissed_at DATETIME NULL,
  resolved_at DATETIME NULL,
  whatsapp_sent_at DATETIME NULL,
  whatsapp_last_attempt_at DATETIME NULL,
  whatsapp_last_error TEXT NULL,
  whatsapp_last_log LONGTEXT NULL,
  whatsapp_last_result TEXT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  raw_json JSON NULL,
  KEY idx_crm_avisos_status (status),
  KEY idx_crm_avisos_severity (severity),
  KEY idx_crm_avisos_source_key (source_key),
  KEY idx_crm_avisos_last_run_id (last_run_id),
  KEY idx_crm_avisos_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_comercial_settings (
  id VARCHAR(32) NOT NULL PRIMARY KEY,
  global_daily_target INT NULL,
  waha_host VARCHAR(255) NULL,
  waha_api_key VARCHAR(255) NULL,
  waha_session VARCHAR(64) NULL,
  typing_pre_min_sec INT NULL,
  typing_pre_max_sec INT NULL,
  typing_min_sec INT NULL,
  typing_max_sec INT NULL,
  typing_jitter_sec INT NULL,
  curl_timeout_sec INT NULL,
  ban_fail_streak_warning INT NULL,
  ban_fail_streak_pause INT NULL,
  ban_fail_ratio_warning DECIMAL(8,4) NULL,
  ban_fail_ratio_pause DECIMAL(8,4) NULL,
  ban_window_size INT NULL,
  cooldown_minutes_warning INT NULL,
  cooldown_minutes_pause INT NULL,
  auto_followup_enabled TINYINT(1) NOT NULL DEFAULT 0,
  auto_pause_enabled TINYINT(1) NOT NULL DEFAULT 0,
  notify_only_after_second_reply TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NULL,
  payload_json JSON NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_comercial_processes (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  slug VARCHAR(64) NULL,
  nombre VARCHAR(191) NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  priority INT NOT NULL DEFAULT 0,
  source_type VARCHAR(64) NULL,
  source_mysql_host VARCHAR(255) NULL,
  source_mysql_db VARCHAR(191) NULL,
  source_mysql_user VARCHAR(191) NULL,
  source_mysql_pass VARCHAR(191) NULL,
  source_mysql_query LONGTEXT NULL,
  source_phone_field VARCHAR(191) NULL,
  source_queue_files JSON NULL,
  positive_keywords JSON NULL,
  negative_keywords JSON NULL,
  message_templates JSON NULL,
  followup_templates JSON NULL,
  auto_create_lead TINYINT(1) NOT NULL DEFAULT 0,
  auto_followup TINYINT(1) NOT NULL DEFAULT 0,
  daily_target_absolute INT NULL,
  daily_target_percent DECIMAL(8,2) NULL,
  min_interval_seconds INT NULL,
  max_interval_seconds INT NULL,
  window_start_hour INT NULL,
  window_end_hour INT NULL,
  last_run_at DATETIME NULL,
  next_run_at DATETIME NULL,
  last_error TEXT NULL,
  last_result TEXT NULL,
  last_line_id VARCHAR(64) NULL,
  last_target_phone VARCHAR(32) NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  raw_json JSON NULL,
  KEY idx_crm_comercial_processes_slug (slug),
  KEY idx_crm_comercial_processes_enabled (enabled),
  KEY idx_crm_comercial_processes_next_run_at (next_run_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_comercial_process_lines (
  process_id VARCHAR(64) NOT NULL,
  line_id VARCHAR(64) NOT NULL,
  created_at DATETIME NULL,
  PRIMARY KEY (process_id, line_id),
  KEY idx_crm_comercial_process_lines_line_id (line_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_comercial_runtime (
  scope_key VARCHAR(191) NOT NULL PRIMARY KEY,
  updated_at DATETIME NULL,
  payload_json JSON NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_comercial_line_state (
  line_id VARCHAR(64) NOT NULL PRIMARY KEY,
  status VARCHAR(64) NULL,
  consecutive_failures INT NOT NULL DEFAULT 0,
  cooldown_until DATETIME NULL,
  effective_power_factor DECIMAL(8,4) NULL,
  adaptive_power_factor DECIMAL(8,4) NULL,
  health_status VARCHAR(64) NULL,
  health_error TEXT NULL,
  health_http_code INT NULL,
  health_session_status VARCHAR(64) NULL,
  last_error TEXT NULL,
  last_http_code INT NULL,
  last_success_at DATETIME NULL,
  last_failure_at DATETIME NULL,
  last_health_check_at DATETIME NULL,
  last_health_ok_at DATETIME NULL,
  last_health_failure_at DATETIME NULL,
  stable_since_at DATETIME NULL,
  last_ban_at DATETIME NULL,
  last_power_raise_at DATETIME NULL,
  last_power_drop_at DATETIME NULL,
  rolling_window JSON NULL,
  updated_at DATETIME NULL,
  raw_json JSON NULL,
  KEY idx_crm_comercial_line_state_status (status),
  KEY idx_crm_comercial_line_state_cooldown_until (cooldown_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_comercial_daily_stats (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  stat_date DATE NULL,
  process_id VARCHAR(64) NULL,
  line_id VARCHAR(64) NULL,
  scope_key VARCHAR(191) NULL,
  payload_json JSON NOT NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uniq_crm_comercial_daily_stats (stat_date, process_id, line_id, scope_key),
  UNIQUE KEY uniq_crm_comercial_daily_stats_scope_key (scope_key),
  KEY idx_crm_comercial_daily_stats_process_id (process_id),
  KEY idx_crm_comercial_daily_stats_scope_key (scope_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_comercial_threads (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  process_id VARCHAR(64) NULL,
  process_slug VARCHAR(64) NULL,
  line_id VARCHAR(64) NULL,
  line_phone VARCHAR(32) NULL,
  line_phone_norm VARCHAR(16) NULL,
  target_phone VARCHAR(32) NULL,
  target_phone_norm VARCHAR(16) NULL,
  stage VARCHAR(64) NULL,
  status VARCHAR(64) NULL,
  lead_id VARCHAR(64) NULL,
  source_ref VARCHAR(191) NULL,
  source_payload JSON NULL,
  test_key VARCHAR(64) NULL,
  test_probe TINYINT(1) NOT NULL DEFAULT 0,
  human_taken TINYINT(1) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  replies_count INT NOT NULL DEFAULT 0,
  messages_sent_count INT NOT NULL DEFAULT 0,
  last_inbound_text TEXT NULL,
  last_outbound_text TEXT NULL,
  last_contact_at DATETIME NULL,
  responded_at DATETIME NULL,
  qualified_at DATETIME NULL,
  qualified_reply_sent_at DATETIME NULL,
  hot_at DATETIME NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  raw_json JSON NULL,
  KEY idx_crm_comercial_threads_process_id (process_id),
  KEY idx_crm_comercial_threads_line_id (line_id),
  KEY idx_crm_comercial_threads_lead_id (lead_id),
  KEY idx_crm_comercial_threads_target_phone_norm (target_phone_norm),
  KEY idx_crm_comercial_threads_stage (stage),
  KEY idx_crm_comercial_threads_status (status),
  KEY idx_crm_comercial_threads_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_comercial_leads (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  thread_id VARCHAR(64) NULL,
  process_id VARCHAR(64) NULL,
  line_id VARCHAR(64) NULL,
  telefono VARCHAR(32) NULL,
  telefono_norm VARCHAR(16) NULL,
  nombre VARCHAR(191) NULL,
  estado VARCHAR(64) NULL,
  notes TEXT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  raw_json JSON NULL,
  KEY idx_crm_comercial_leads_thread_id (thread_id),
  KEY idx_crm_comercial_leads_process_id (process_id),
  KEY idx_crm_comercial_leads_telefono_norm (telefono_norm)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_comercial_events (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  thread_id VARCHAR(64) NULL,
  process_id VARCHAR(64) NULL,
  line_id VARCHAR(64) NULL,
  line_phone_norm VARCHAR(16) NULL,
  target_phone_norm VARCHAR(16) NULL,
  event_type VARCHAR(64) NULL,
  text_preview TEXT NULL,
  ts DATETIME NULL,
  payload_json JSON NOT NULL,
  source_ref VARCHAR(191) NULL,
  KEY idx_crm_comercial_events_thread_id (thread_id),
  KEY idx_crm_comercial_events_process_id (process_id),
  KEY idx_crm_comercial_events_line_id (line_id),
  KEY idx_crm_comercial_events_target_phone_norm (target_phone_norm),
  KEY idx_crm_comercial_events_ts (ts),
  KEY idx_crm_comercial_events_event_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_comercial_webhook_logs (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  request_id VARCHAR(64) NULL,
  event_date DATE NULL,
  ts DATETIME NULL,
  log_type VARCHAR(64) NULL,
  from_phone_norm VARCHAR(16) NULL,
  to_phone_norm VARCHAR(16) NULL,
  message_id VARCHAR(191) NULL,
  text_preview TEXT NULL,
  remote_ip VARCHAR(64) NULL,
  http_status INT NULL,
  payload_json JSON NOT NULL,
  KEY idx_crm_comercial_webhook_logs_request_id (request_id),
  KEY idx_crm_comercial_webhook_logs_message_id (message_id),
  KEY idx_crm_comercial_webhook_logs_ts (ts),
  KEY idx_crm_comercial_webhook_logs_log_type (log_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_comercial_webhook_seen (
  message_id VARCHAR(191) NOT NULL PRIMARY KEY,
  first_seen_at DATETIME NULL,
  payload_json JSON NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_comercial_queue_items (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  process_slug VARCHAR(64) NOT NULL,
  queue_slot VARCHAR(64) NULL,
  group_key VARCHAR(191) NULL,
  target_phone VARCHAR(32) NULL,
  target_phone_norm VARCHAR(16) NULL,
  source_file VARCHAR(255) NULL,
  consumed_at DATETIME NULL,
  created_at DATETIME NULL,
  payload_json JSON NOT NULL,
  KEY idx_crm_comercial_queue_items_process_slug (process_slug),
  KEY idx_crm_comercial_queue_items_target_phone_norm (target_phone_norm),
  KEY idx_crm_comercial_queue_items_consumed_at (consumed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_publicista_accounts (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  display_name VARCHAR(191) NULL,
  portal_code VARCHAR(64) NULL,
  portal_label VARCHAR(191) NULL,
  portal_url TEXT NULL,
  login_user VARCHAR(191) NULL,
  login_pass VARCHAR(191) NULL,
  estado VARCHAR(64) NULL,
  descripcion TEXT NULL,
  max_active_ads INT NULL,
  listing_slot_count INT NULL,
  active_ads_count INT NULL,
  published_ads_count INT NULL,
  created_ads_count INT NULL,
  free_bump_tasks_count INT NULL,
  daily_publish_limit INT NULL,
  priority_weight INT NULL,
  automation_mode VARCHAR(64) NULL,
  health_status VARCHAR(64) NULL,
  free_bump_start_time CHAR(5) NULL,
  free_bump_end_time CHAR(5) NULL,
  free_bump_anticipation_minutes INT NULL,
  free_bump_interval_min_minutes INT NULL,
  free_bump_interval_max_minutes INT NULL,
  free_bump_retry_empty_min_minutes INT NULL,
  free_bump_retry_empty_max_minutes INT NULL,
  free_bump_jitter_min_seconds INT NULL,
  free_bump_jitter_max_seconds INT NULL,
  last_success_at DATETIME NULL,
  last_error_at DATETIME NULL,
  last_used_at DATETIME NULL,
  last_error TEXT NULL,
  notes_internal TEXT NULL,
  portal_listing_ids JSON NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  raw_json JSON NULL,
  KEY idx_crm_publicista_accounts_portal_code (portal_code),
  KEY idx_crm_publicista_accounts_estado (estado),
  KEY idx_crm_publicista_accounts_health_status (health_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_publicista_templates (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  nombre VARCHAR(191) NULL,
  tipo VARCHAR(64) NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  payload_json JSON NOT NULL,
  KEY idx_crm_publicista_templates_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_publicista_jobs (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  nombre_trabajo VARCHAR(191) NULL,
  estado VARCHAR(64) NULL,
  clienta_id VARCHAR(64) NULL,
  clienta_scope VARCHAR(32) NULL,
  clienta_nombre_snapshot VARCHAR(191) NULL,
  localidad_snapshot VARCHAR(191) NULL,
  provincia_snapshot VARCHAR(191) NULL,
  notas TEXT NULL,
  physical_notes TEXT NULL,
  services_snapshot TEXT NULL,
  tarifas_snapshot TEXT NULL,
  source_image JSON NULL,
  asset_dirs JSON NULL,
  local_assets JSON NULL,
  final_images JSON NULL,
  costs JSON NULL,
  models JSON NULL,
  processing JSON NULL,
  workflow JSON NULL,
  product_profile JSON NULL,
  copy_pack JSON NULL,
  descriptor JSON NULL,
  prompt_master JSON NULL,
  pipeline JSON NULL,
  candidates JSON NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  raw_json JSON NULL,
  KEY idx_crm_publicista_jobs_estado (estado),
  KEY idx_crm_publicista_jobs_clienta_id (clienta_id),
  KEY idx_crm_publicista_jobs_clienta_scope (clienta_scope),
  KEY idx_crm_publicista_jobs_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_publicista_job_artifacts (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  job_id VARCHAR(64) NOT NULL,
  artifact_group VARCHAR(64) NULL,
  artifact_type VARCHAR(64) NULL,
  relative_path VARCHAR(255) NULL,
  payload_json JSON NULL,
  created_at DATETIME NULL,
  KEY idx_crm_publicista_job_artifacts_job_id (job_id),
  KEY idx_crm_publicista_job_artifacts_artifact_type (artifact_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_publicista_plannings (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  nombre VARCHAR(255) NULL,
  estado VARCHAR(64) NULL,
  version INT NULL,
  parent_planning_id VARCHAR(64) NULL,
  portal_code VARCHAR(64) NULL,
  portal_label VARCHAR(191) NULL,
  portal_url TEXT NULL,
  city VARCHAR(191) NULL,
  province VARCHAR(191) NULL,
  category VARCHAR(191) NULL,
  category_label VARCHAR(191) NULL,
  num_products_target INT NULL,
  default_option_code VARCHAR(64) NULL,
  competition_snapshot JSON NULL,
  pricing_snapshot JSON NULL,
  strategy_snapshot JSON NULL,
  recommendation_options JSON NULL,
  analysis_sources JSON NULL,
  market_signals JSON NULL,
  cost_snapshot JSON NULL,
  selection_rules JSON NULL,
  summary_json JSON NULL,
  calculated_at DATETIME NULL,
  notes TEXT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  raw_json JSON NULL,
  KEY idx_crm_publicista_plannings_parent_planning_id (parent_planning_id),
  KEY idx_crm_publicista_plannings_portal_code (portal_code),
  KEY idx_crm_publicista_plannings_estado (estado),
  KEY idx_crm_publicista_plannings_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_publicista_campaigns (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  planning_id VARCHAR(64) NULL,
  estado VARCHAR(64) NULL,
  nombre VARCHAR(191) NULL,
  planning_snapshot JSON NULL,
  product_ids JSON NULL,
  products_snapshot JSON NULL,
  account_ids JSON NULL,
  accounts_snapshot JSON NULL,
  selected_listing_refs JSON NULL,
  min_products INT NULL,
  max_products INT NULL,
  composition_plan JSON NULL,
  automation_plan JSON NULL,
  approval_snapshot JSON NULL,
  recalculation_snapshot JSON NULL,
  execution_summary JSON NULL,
  notes TEXT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  raw_json JSON NULL,
  KEY idx_crm_publicista_campaigns_planning_id (planning_id),
  KEY idx_crm_publicista_campaigns_estado (estado),
  KEY idx_crm_publicista_campaigns_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_publicista_campaign_items (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  campaign_id VARCHAR(64) NULL,
  account_id VARCHAR(64) NULL,
  phone_id VARCHAR(64) NULL,
  product_job_id VARCHAR(64) NULL,
  portal_code VARCHAR(64) NULL,
  publish_mode VARCHAR(64) NULL,
  copy_variant_id VARCHAR(64) NULL,
  external_ad_id VARCHAR(64) NULL,
  estado VARCHAR(64) NULL,
  title VARCHAR(255) NULL,
  city VARCHAR(191) NULL,
  localidad VARCHAR(191) NULL,
  telefono VARCHAR(32) NULL,
  telefono_norm VARCHAR(16) NULL,
  image_ids JSON NULL,
  product_snapshot JSON NULL,
  account_snapshot JSON NULL,
  copy_snapshot JSON NULL,
  image_snapshot JSON NULL,
  planning_profile_snapshot JSON NULL,
  publish_result JSON NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  raw_json JSON NULL,
  KEY idx_crm_publicista_campaign_items_campaign_id (campaign_id),
  KEY idx_crm_publicista_campaign_items_account_id (account_id),
  KEY idx_crm_publicista_campaign_items_phone_id (phone_id),
  KEY idx_crm_publicista_campaign_items_product_job_id (product_job_id),
  KEY idx_crm_publicista_campaign_items_external_ad_id (external_ad_id),
  KEY idx_crm_publicista_campaign_items_estado (estado),
  KEY idx_crm_publicista_campaign_items_telefono_norm (telefono_norm)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_publicista_tasks (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  campaign_id VARCHAR(64) NULL,
  campaign_item_id VARCHAR(64) NULL,
  account_id VARCHAR(64) NULL,
  portal_code VARCHAR(64) NULL,
  task_type VARCHAR(64) NULL,
  estado VARCHAR(64) NULL,
  frequency_rule VARCHAR(191) NULL,
  next_run_at DATETIME NULL,
  last_run_at DATETIME NULL,
  fail_count INT NOT NULL DEFAULT 0,
  last_result JSON NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  raw_json JSON NULL,
  KEY idx_crm_publicista_tasks_campaign_id (campaign_id),
  KEY idx_crm_publicista_tasks_campaign_item_id (campaign_item_id),
  KEY idx_crm_publicista_tasks_account_id (account_id),
  KEY idx_crm_publicista_tasks_estado (estado),
  KEY idx_crm_publicista_tasks_next_run_at (next_run_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_publicista_runs (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  campaign_id VARCHAR(64) NULL,
  run_type VARCHAR(64) NULL,
  estado VARCHAR(64) NULL,
  started_at DATETIME NULL,
  finished_at DATETIME NULL,
  summary TEXT NULL,
  human_report LONGTEXT NULL,
  progress_json JSON NULL,
  pipeline_json JSON NULL,
  items_json JSON NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  raw_json JSON NULL,
  KEY idx_crm_publicista_runs_campaign_id (campaign_id),
  KEY idx_crm_publicista_runs_estado (estado),
  KEY idx_crm_publicista_runs_started_at (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_voice_commands_log (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  owner VARCHAR(191) NULL,
  stage VARCHAR(64) NULL,
  execution_mode VARCHAR(64) NULL,
  intent VARCHAR(191) NULL,
  transcript LONGTEXT NULL,
  normalized_text LONGTEXT NULL,
  params_json JSON NULL,
  context_json JSON NULL,
  ai_json JSON NULL,
  errors_json JSON NULL,
  clarification TEXT NULL,
  confirmation TEXT NULL,
  pending_token VARCHAR(64) NULL,
  result_message LONGTEXT NULL,
  timestamp DATETIME NULL,
  raw_json JSON NULL,
  KEY idx_crm_voice_commands_log_owner (owner),
  KEY idx_crm_voice_commands_log_stage (stage),
  KEY idx_crm_voice_commands_log_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_voice_pending_actions (
  token VARCHAR(64) NOT NULL PRIMARY KEY,
  owner VARCHAR(191) NULL,
  kind VARCHAR(64) NULL,
  intent VARCHAR(191) NULL,
  status VARCHAR(64) NULL,
  transcript LONGTEXT NULL,
  normalized_text LONGTEXT NULL,
  message LONGTEXT NULL,
  missing_fields JSON NULL,
  options_json JSON NULL,
  params_json JSON NULL,
  context_json JSON NULL,
  resolved_entities JSON NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  expires_at DATETIME NULL,
  raw_json JSON NULL,
  KEY idx_crm_voice_pending_actions_owner (owner),
  KEY idx_crm_voice_pending_actions_status (status),
  KEY idx_crm_voice_pending_actions_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

<?php
/**
 * SeederLinux Lite - API Router
 * Main entry point for all API requests
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// CORS headers (restrict in production)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;

// Get request data
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? $_POST;

try {
    switch ($path) {
        // ===============================
        // Authentication Endpoints
        // ===============================
        case 'login':
            if ($method !== 'POST') {
                jsonError('Method not allowed', 405);
            }
            handleLogin($input);
            break;

        case 'logout':
            if ($method !== 'POST') {
                jsonError('Method not allowed', 405);
            }
            handleLogout();
            break;

        case 'session':
            handleSessionCheck();
            break;

        // ===============================
        // Organizations Endpoints
        // ===============================
        case 'organizations':
            requireAuth();
            switch ($method) {
                case 'GET':
                    handleGetOrganizations();
                    break;
                case 'POST':
                    handleCreateOrganization($input);
                    break;
                default:
                    jsonError('Method not allowed', 405);
            }
            break;

        case 'organization':
            requireAuth();
            if (!$id) {
                jsonError('Organization ID required', 400);
            }
            switch ($method) {
                case 'GET':
                    handleGetOrganization((int) $id);
                    break;
                case 'PUT':
                    handleUpdateOrganization((int) $id, $input);
                    break;
                case 'DELETE':
                    handleDeleteOrganization((int) $id);
                    break;
                default:
                    jsonError('Method not allowed', 405);
            }
            break;

        // ===============================
        // Variables Endpoints
        // ===============================
        case 'variables':
            requireAuth();
            if ($method !== 'GET') {
                jsonError('Method not allowed', 405);
            }
            handleGetVariables($id);
            break;

        case 'variables-update':
            requireAuth();
            if ($method !== 'POST') {
                jsonError('Method not allowed', 405);
            }
            handleUpdateVariables($input);
            break;

        // ===============================
        // Bundle Endpoints
        // ===============================
        case 'bundle':
            if ($method !== 'GET') {
                jsonError('Method not allowed', 405);
            }
            handleGetBundle($id);
            break;

        case 'bundle-download':
            if ($method !== 'GET') {
                jsonError('Method not allowed', 405);
            }
            handleDownloadBundle($id);
            break;

        // ===============================
        // Stats Endpoint
        // ===============================
        case 'stats':
            handleGetStats();
            break;

        // ===============================
        // Health Check Endpoint (public)
        // ===============================
        case 'health':
            handleHealthCheck();
            break;

        // ===============================
        // Activity Log Endpoint
        // ===============================
        case 'activity-log':
            requireAuth();
            handleGetActivityLog($id);
            break;

        // ===============================
        // System Settings Endpoints
        // ===============================
        case 'settings':
            requireAdmin();
            if ($method === 'GET') {
                handleGetSettings();
            } elseif ($method === 'POST') {
                handleUpdateSettings($input);
            } else {
                jsonError('Method not allowed', 405);
            }
            break;

        // ===============================
        // Scripts Endpoints
        // ===============================
        case 'scripts':
            requireAuth();
            handleGetScripts();
            break;

        case 'script-upload':
            requireAuth();
            if ($method !== 'POST') {
                jsonError('Method not allowed', 405);
            }
            handleUploadScript();
            break;

        case 'script':
            requireAuth();
            if (!$id) {
                jsonError('Script ID required', 400);
            }
            if ($method === 'DELETE') {
                handleDeleteScript((int) $id);
            } else {
                jsonError('Method not allowed', 405);
            }
            break;

        default:
            jsonError('Invalid endpoint', 404);
    }
} catch (RuntimeException $e) {
    jsonError($e->getMessage());
} catch (Exception $e) {
    error_log('API Error: ' . $e->getMessage());
    jsonError('Internal server error', 500);
}

// ===============================
// Handler Functions
// ===============================

/**
 * Handle login
 */
function handleLogin(array $input): void {
    if (empty($input['username']) || empty($input['password'])) {
        jsonError('Usuário e senha são obrigatórios');
    }

    // Validate CSRF if provided
    if (!empty($input['csrf_token']) && !validateCSRFToken($input['csrf_token'])) {
        jsonError('Token CSRF inválido');
    }

    try {
        $user = login($input['username'], $input['password']);

        // Log successful login
        logActivity($user['id'], 'login', 'user', $user['id'], "User '{$user['username']}' logged in successfully");

        jsonSuccess($user, 'Login realizado com sucesso');
    } catch (Exception $e) {
        // Log failed login attempt
        logActivity(null, 'login_failed', 'user', null, "Failed login attempt for user '{$input['username']}': " . $e->getMessage());

        jsonError($e->getMessage());
    }
}

/**
 * Handle logout
 */
function handleLogout(): void {
    $user = getCurrentUser();
    $userId = $user['id'] ?? null;
    $username = $user['username'] ?? 'unknown';

    logout();

    // Log logout
    logActivity($userId, 'logout', 'user', $userId, "User '$username' logged out");

    jsonSuccess(null, 'Logout realizado com sucesso');
}

/**
 * Check session status
 */
function handleSessionCheck(): void {
    if (isLoggedIn()) {
        $user = getCurrentUser();
        jsonSuccess($user, 'Sessão ativa');
    } else {
        jsonError('Not authenticated', 401);
    }
}

/**
 * Get all organizations
 */
function handleGetOrganizations(): void {
    $orgs = Database::fetchAll(
        "SELECT id, name, acronym, domain, description, is_active,
                created_at, updated_at
         FROM organizations
         WHERE is_active = TRUE
         ORDER BY acronym ASC"
    );

    jsonSuccess($orgs);
}

/**
 * Get single organization
 */
function handleGetOrganization(int $id): void {
    $org = Database::fetchOne(
        "SELECT id, name, acronym, domain, description, is_active,
                created_at, updated_at
         FROM organizations
         WHERE id = ?",
        [$id]
    );

    if (!$org) {
        jsonError('Organização não encontrada', 404);
    }

    jsonSuccess($org);
}

/**
 * Create organization
 */
function handleCreateOrganization(array $input): void {
    if (empty($input['name']) || empty($input['acronym'])) {
        jsonError('Nome e sigla são obrigatórios');
    }

    $acronym = strtoupper(sanitizeInput($input['acronym']));
    $name = sanitizeInput($input['name']);
    $domain = sanitizeInput($input['domain'] ?? '');
    $description = sanitizeInput($input['description'] ?? '');

    // Check if acronym already exists
    $existing = Database::fetchOne(
        "SELECT id FROM organizations WHERE acronym = ?",
        [$acronym]
    );

    if ($existing) {
        jsonError('Sigla já cadastrada');
    }

    try {
        Database::beginTransaction();

        // Insert organization
        Database::execute(
            "INSERT INTO organizations (name, acronym, domain, description) VALUES (?, ?, ?, ?)",
            [$name, $acronym, $domain, $description]
        );

        $orgId = (int) Database::lastInsertId();

        // Create default variables for this organization
        $defaultVars = Database::fetchAll(
            "SELECT id, default_value FROM variable_definitions"
        );

        foreach ($defaultVars as $var) {
            Database::execute(
                "INSERT INTO organization_variables (organization_id, variable_id, value) VALUES (?, ?, ?)",
                [$orgId, $var['id'], $var['default_value']]
            );
        }

        Database::commit();

        // Log organization creation
        logActivity($_SESSION['user_id'] ?? null, 'create', 'organization', $orgId, "Created organization '$acronym' - '$name'");

        jsonSuccess(['id' => $orgId], 'Organização criada com sucesso');
    } catch (Exception $e) {
        Database::rollback();
        throw new RuntimeException('Erro ao criar organização: ' . $e->getMessage());
    }
}

/**
 * Update organization
 */
function handleUpdateOrganization(int $id, array $input): void {
    $org = Database::fetchOne("SELECT id FROM organizations WHERE id = ?", [$id]);

    if (!$org) {
        jsonError('Organização não encontrada', 404);
    }

    $name = sanitizeInput($input['name'] ?? '');
    $domain = sanitizeInput($input['domain'] ?? '');
    $description = sanitizeInput($input['description'] ?? '');

    $updates = [];
    $params = [];

    if ($name) {
        $updates[] = 'name = ?';
        $params[] = $name;
    }

    if (isset($input['domain'])) {
        $updates[] = 'domain = ?';
        $params[] = $domain;
    }

    if (isset($input['description'])) {
        $updates[] = 'description = ?';
        $params[] = $description;
    }

    if (empty($updates)) {
        jsonError('Nenhum campo para atualizar');
    }

    $params[] = $id;

    Database::execute(
        "UPDATE organizations SET " . implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?",
        $params
    );

    // Log organization update
    $org = Database::fetchOne("SELECT acronym FROM organizations WHERE id = ?", [$id]);
    logActivity($_SESSION['user_id'] ?? null, 'update', 'organization', $id, "Updated organization '{$org['acronym']}': " . implode(', ', $updates));

    jsonSuccess(null, 'Organização atualizada com sucesso');
}

/**
 * Delete organization (soft delete)
 */
function handleDeleteOrganization(int $id): void {
    $org = Database::fetchOne("SELECT id, acronym, name FROM organizations WHERE id = ?", [$id]);

    if (!$org) {
        jsonError('Organização não encontrada', 404);
    }

    // Soft delete
    Database::execute(
        "UPDATE organizations SET is_active = FALSE, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
        [$id]
    );

    // Log organization deletion
    logActivity($_SESSION['user_id'] ?? null, 'delete', 'organization', $id, "Deleted organization '{$org['acronym']}' - '{$org['name']}'");

    jsonSuccess(null, 'Organização removida com sucesso');
}

/**
 * Get variables for organization
 */
function handleGetVariables(?string $orgId): void {
    if (!$orgId) {
        jsonError('Organization ID required');
    }

    $orgId = (int) $orgId;

    $org = Database::fetchOne("SELECT id, acronym FROM organizations WHERE id = ?", [$orgId]);

    if (!$org) {
        jsonError('Organização não encontrada', 404);
    }

    $variables = Database::fetchAll(
        "SELECT vd.id, vd.name, vd.placeholder, vd.description, vd.category,
                vd.default_value, COALESCE(ov.value, vd.default_value) AS current_value,
                vd.is_required, vd.display_order
         FROM variable_definitions vd
         LEFT JOIN organization_variables ov ON ov.organization_id = ? AND ov.variable_id = vd.id
         ORDER BY vd.display_order",
        [$orgId]
    );

    jsonSuccess([
        'organization' => $org['acronym'],
        'variables' => $variables
    ]);
}

/**
 * Update organization variables with validation
 */
function handleUpdateVariables(array $input): void {
    if (empty($input['organization_id']) || empty($input['variables'])) {
        jsonError('Organization ID and variables are required');
    }

    $orgId = (int) $input['organization_id'];
    $variables = $input['variables'];

    $org = Database::fetchOne("SELECT id, acronym FROM organizations WHERE id = ?", [$orgId]);

    if (!$org) {
        jsonError('Organização não encontrada', 404);
    }

    // Validate all variables before saving
    $validation = validateAllVariables($variables);

    if (!$validation['valid']) {
        jsonError('Erros de validação encontrados', 400, $validation['errors']);
    }

    // Check for warnings (still save, but inform user)
    $warnings = $validation['warnings'];

    try {
        Database::beginTransaction();

        foreach ($variables as $varId => $value) {
            $varId = (int) $varId;
            $value = sanitizeInput((string) $value);

            // Upsert variable value
            Database::execute(
                "INSERT INTO organization_variables (organization_id, variable_id, value)
                 VALUES (?, ?, ?)
                 ON CONFLICT (organization_id, variable_id)
                 DO UPDATE SET value = EXCLUDED.value, updated_at = CURRENT_TIMESTAMP",
                [$orgId, $varId, $value]
            );
        }

        Database::commit();

        // Log variables update
        logActivity($_SESSION['user_id'] ?? null, 'update', 'variables', $orgId, "Updated " . count($variables) . " variables for organization '{$org['acronym']}'", $orgId);

        jsonResponse([
            'success' => true,
            'message' => 'Variáveis atualizadas com sucesso',
            'warnings' => $warnings,
            'updated_count' => count($variables)
        ]);
    } catch (Exception $e) {
        Database::rollback();
        throw new RuntimeException('Erro ao atualizar variáveis: ' . $e->getMessage());
    }
}

/**
 * Get scripts list
 */
function handleGetScripts(): void {
    $scripts = Database::fetchAll(
        "SELECT id, name, filename, description, is_core, execution_order,
                created_at, updated_at
         FROM scripts
         WHERE is_active = TRUE
         ORDER BY is_core DESC, execution_order ASC"
    );

    jsonSuccess($scripts);
}

/**
 * Upload custom script
 */
function handleUploadScript(): void {
    $input = getJsonInput();

    if (empty($input['name']) || empty($input['content'])) {
        jsonError('Nome e conteúdo são obrigatórios');
    }

    $name = sanitizeInput($input['name']);
    $description = sanitizeInput($input['description'] ?? '');
    $content = $input['content']; // Don't sanitize - script content

    // Validate script content
    if (strpos($content, '#!/bin/bash') === false && strpos($content, '#!/usr/bin/env bash') === false) {
        jsonError('Script deve começar com shebang (#!/bin/bash)');
    }

    // Extract placeholders
    preg_match_all('/\{\{([A-Z_][A-Z0-9_]*)\}\}/', $content, $matches);
    $placeholders = array_unique($matches[1]);

    // Check if all placeholders exist
    foreach ($placeholders as $placeholder) {
        $exists = Database::fetchOne(
            "SELECT id FROM variable_definitions WHERE name = ?",
            [$placeholder]
        );
        if (!$exists) {
            // Create placeholder if doesn't exist
            Database::execute(
                "INSERT INTO variable_definitions (name, placeholder, description, category, is_required)
                 VALUES (?, '{{' || ? || '}}', ?, 'custom', FALSE)",
                [$placeholder, $placeholder, "Variável personalizada: $placeholder"]
            );
        }
    }

    // Generate filename
    $filename = 'custom_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $name)) . '.sh';

    // Check if filename exists
    $existing = Database::fetchOne(
        "SELECT id FROM scripts WHERE filename = ?",
        [$filename]
    );

    if ($existing) {
        $filename = 'custom_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $name)) . '_' . time() . '.sh';
    }

    // Get max execution order for custom scripts
    $maxOrder = Database::fetchOne(
        "SELECT COALESCE(MAX(execution_order), 0) as max_order FROM scripts WHERE is_core = FALSE"
    )['max_order'];

    // Insert script
    Database::execute(
        "INSERT INTO scripts (name, filename, description, content, is_core, execution_order)
         VALUES (?, ?, ?, ?, FALSE, ?)",
        [$name, $filename, $description, $content, $maxOrder + 1]
    );

    logActivity($_SESSION['user_id'] ?? null, 'create', 'script', (int) Database::lastInsertId(), "Script '$name' uploaded with filename '$filename'");

    jsonSuccess([
        'id' => (int) Database::lastInsertId(),
        'filename' => $filename,
        'placeholders' => $placeholders
    ], 'Script enviado com sucesso');
}

/**
 * Delete custom script (soft delete)
 */
function handleDeleteScript(int $id): void {
    $script = Database::fetchOne(
        "SELECT id, name, is_core FROM scripts WHERE id = ?",
        [$id]
    );

    if (!$script) {
        jsonError('Script não encontrado', 404);
    }

    if ($script['is_core']) {
        jsonError('Scripts core não podem ser removidos');
    }

    Database::execute(
        "UPDATE scripts SET is_active = FALSE WHERE id = ?",
        [$id]
    );

    logActivity($_SESSION['user_id'] ?? null, 'delete', 'script', $id, "Script '{$script['name']}' deleted");

    jsonSuccess(null, 'Script removido com sucesso');
}

/**
 * Get bundle information
 */
function handleGetBundle(?string $orgId): void {
    if (!$orgId) {
        jsonError('Organization ID required');
    }

    $org = Database::fetchOne(
        "SELECT id, acronym, name FROM organizations WHERE acronym = ? OR id = ?",
        [strtoupper($orgId), (int) $orgId]
    );

    if (!$org) {
        jsonError('Organização não encontrada', 404);
    }

    $scripts = Database::fetchAll(
        "SELECT id, name, filename, is_core, execution_order
         FROM scripts
         WHERE is_active = TRUE
         ORDER BY is_core DESC, execution_order ASC"
    );

    $variables = Database::fetchAll(
        "SELECT vd.name, COALESCE(ov.value, vd.default_value) AS value
         FROM variable_definitions vd
         LEFT JOIN organization_variables ov ON ov.organization_id = ? AND ov.variable_id = vd.id",
        [$org['id']]
    );

    jsonSuccess([
        'organization' => $org,
        'scripts' => $scripts,
        'variables_count' => count($variables)
    ]);
}

/**
 * Download bundle
 */
function handleDownloadBundle(?string $orgId): void {
    if (!$orgId) {
        jsonError('Organization ID required');
    }

    $org = Database::fetchOne(
        "SELECT id, acronym, name FROM organizations WHERE acronym = ? OR id = ?",
        [strtoupper($orgId), (int) $orgId]
    );

    if (!$org) {
        jsonError('Organização não encontrada', 404);
    }

    try {
        $bundle = buildBundle($org['id']);

        // Log execution
        Database::execute(
            "INSERT INTO script_executions (organization_id, script_filename, execution_ip, status, agent_version)
             VALUES (?, ?, ?, ?, ?)",
            [$org['id'], $bundle['filename'], getClientIP(), 'downloaded', 'manual']
        );

        // Log bundle download
        logActivity($_SESSION['user_id'] ?? null, 'download', 'bundle', $org['id'], "Downloaded bundle '{$bundle['filename']}' for organization '{$org['acronym']}'", $org['id']);

        // Send file as download
        header('Content-Type: application/x-sh');
        header('Content-Disposition: attachment; filename="' . $bundle['filename'] . '"');
        header('Content-Length: ' . strlen($bundle['content']));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: public');

        echo $bundle['content'];
        exit;
    } catch (Exception $e) {
        jsonError('Erro ao gerar bundle: ' . $e->getMessage());
    }
}

/**
 * Get public statistics
 */
function handleGetStats(): void {
    $stats = [
        'organizations' => (int) Database::fetchOne(
            "SELECT COUNT(*) as count FROM organizations WHERE is_active = TRUE"
        )['count'],
        'scripts' => (int) Database::fetchOne(
            "SELECT COUNT(*) as count FROM scripts WHERE is_active = TRUE"
        )['count'],
        'core_scripts' => (int) Database::fetchOne(
            "SELECT COUNT(*) as count FROM scripts WHERE is_active = TRUE AND is_core = TRUE"
        )['count'],
        'variables' => (int) Database::fetchOne(
            "SELECT COUNT(*) as count FROM variable_definitions"
        )['count'],
        'stations' => (int) Database::fetchOne(
            "SELECT COUNT(*) as count FROM script_executions"
        )['count']
    ];

    jsonSuccess($stats);
}

/**
 * Health check endpoint
 */
function handleHealthCheck(): void {
    $status = 'ok';
    $checks = [];

    // Check database
    try {
        Database::fetchOne("SELECT 1 as test");
        $checks['database'] = ['status' => 'ok', 'message' => 'PostgreSQL connected'];
    } catch (Exception $e) {
        $status = 'error';
        $checks['database'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // Check PHP version
    $checks['php'] = [
        'status' => 'ok',
        'version' => PHP_VERSION
    ];

    // Check writable directories
    $storagePath = realpath(__DIR__ . '/../storage');
    if ($storagePath) {
        $checks['storage'] = [
            'status' => is_writable($storagePath) ? 'ok' : 'error',
            'path' => $storagePath,
            'writable' => is_writable($storagePath)
        ];
    } else {
        $checks['storage'] = ['status' => 'error', 'message' => 'Storage directory not found'];
    }

    $response = [
        'status' => $status,
        'timestamp' => date('c'),
        'version' => '1.0.0',
        'checks' => $checks
    ];

    http_response_code($status === 'ok' ? 200 : 503);
    jsonResponse($response);
}

/**
 * Get activity log - Protected by admin session
 */
function handleGetActivityLog(?string $limit): void {
    // Rate limiting
    $limit = min((int) ($limit ?? 50), 100);

    // Optional filters from query string
    $action = $_GET['filter_action'] ?? null;
    $orgId = isset($_GET['filter_org']) ? (int) $_GET['filter_org'] : null;
    $target = $_GET['filter_target'] ?? null;

    $sql = "SELECT
                al.id,
                al.user_id,
                al.action,
                al.target,
                al.target_id,
                al.details,
                al.organization_id,
                al.ip_address,
                al.user_agent,
                al.session_id,
                al.created_at,
                u.username,
                u.full_name as user_name,
                o.acronym as org_acronym,
                o.name as org_name
            FROM activity_log al
            LEFT JOIN users u ON u.id = al.user_id
            LEFT JOIN organizations o ON o.id = al.organization_id
            WHERE 1=1";

    $params = [];

    if ($action) {
        $sql .= " AND al.action = ?";
        $params[] = sanitizeInput($action);
    }

    if ($orgId) {
        $sql .= " AND al.organization_id = ?";
        $params[] = $orgId;
    }

    if ($target) {
        $sql .= " AND al.target = ?";
        $params[] = sanitizeInput($target);
    }

    $sql .= " ORDER BY al.created_at DESC LIMIT ?";
    $params[] = $limit;

    $logs = Database::fetchAll($sql, $params);

    jsonSuccess([
        'total' => count($logs),
        'limit' => $limit,
        'filters' => [
            'action' => $action,
            'organization_id' => $orgId,
            'target' => $target
        ],
        'data' => $logs
    ]);
}

/**
 * Get system settings (admin only)
 */
function handleGetSettings(): void {
    $settings = Database::fetchAll(
        "SELECT key, value, value_type, description, is_public, updated_at FROM system_settings ORDER BY key"
    );

    $result = [];
    foreach ($settings as $setting) {
        $result[$setting['key']] = [
            'value' => $setting['value'],
            'type' => $setting['value_type'],
            'description' => $setting['description'],
            'is_public' => (bool) $setting['is_public'],
            'updated_at' => $setting['updated_at']
        ];
    }

    jsonSuccess([
        'settings' => $result,
        'count' => count($result)
    ]);
}

/**
 * Update system settings (admin only) with validation
 */
function handleUpdateSettings(array $input): void {
    // Define allowed settings and their validation rules
    $allowedSettings = [
        'app_name' => ['type' => 'string', 'max_length' => 100],
        'require_https' => ['type' => 'boolean'],
        'max_login_attempts' => ['type' => 'integer', 'min' => 1, 'max' => 10],
        'login_lockout_minutes' => ['type' => 'integer', 'min' => 5, 'max' => 60],
        'session_timeout' => ['type' => 'integer', 'min' => 3600, 'max' => 604800],
        'bundle_retention_days' => ['type' => 'integer', 'min' => 1, 'max' => 365],
        'max_bundle_downloads' => ['type' => 'integer', 'min' => 10, 'max' => 1000],
        'enable_activity_log' => ['type' => 'boolean'],
        'default_timezone' => ['type' => 'timezone']
    ];

    $updated = [];
    $errors = [];

    foreach ($input as $key => $value) {
        if (!isset($allowedSettings[$key])) {
            $errors[] = "Configuração '$key' não pode ser modificada";
            continue;
        }

        $rule = $allowedSettings[$key];
        $value = sanitizeInput((string) $value);

        // Validate based on type
        $valid = true;
        switch ($rule['type']) {
            case 'string':
                if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
                    $errors[] = "$key deve ter no máximo {$rule['max_length']} caracteres";
                    $valid = false;
                }
                break;

            case 'integer':
                if (!is_numeric($value)) {
                    $errors[] = "$key deve ser um número inteiro";
                    $valid = false;
                } else {
                    $intVal = (int) $value;
                    if (isset($rule['min']) && $intVal < $rule['min']) {
                        $errors[] = "$key deve ser no mínimo {$rule['min']}";
                        $valid = false;
                    }
                    if (isset($rule['max']) && $intVal > $rule['max']) {
                        $errors[] = "$key deve ser no máximo {$rule['max']}";
                        $valid = false;
                    }
                }
                break;

            case 'boolean':
                if (!in_array($value, ['true', 'false', '1', '0', 'yes', 'no'])) {
                    $errors[] = "$key deve ser true ou false";
                    $valid = false;
                }
                // Normalize boolean
                $value = in_array($value, ['true', '1', 'yes']) ? 'true' : 'false';
                break;

            case 'timezone':
                if (!in_array($value, timezone_identifiers_list())) {
                    $errors[] = "$key não é um fuso horário válido";
                    $valid = false;
                }
                break;
        }

        if ($valid) {
            Database::execute(
                "UPDATE system_settings
                 SET value = ?, updated_at = CURRENT_TIMESTAMP, updated_by = ?
                 WHERE key = ?",
                [$value, $_SESSION['user_id'] ?? null, $key]
            );
            $updated[] = $key;
        }
    }

    if (!empty($errors)) {
        jsonError('Erros de validação', 400, $errors);
        return;
    }

    logActivity($_SESSION['user_id'] ?? null, 'update', 'settings', null, 'Updated settings: ' . implode(', ', $updated));

    jsonSuccess([
        'updated' => $updated,
        'message' => count($updated) . ' configurações atualizadas'
    ], 'Configurações atualizadas com sucesso');
}

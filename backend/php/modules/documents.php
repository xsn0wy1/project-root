<?php
declare(strict_types=1);
use Hospital\Http\ApiException;
use Hospital\Http\Request;

function documents_manager(PDO $db, string $actor): bool
{
    return user_has_role($db, $actor, 'records_manager');
}

function documents_actor(PDO $db, string $actor): void
{
    $user = users_by_id($db, $actor, true);
    if (
        !$user
        || $user['status'] !== 'active'
        || $user['staff_status'] !== 'active'
        || (int) $user['auth_version'] !== (int) ($_SESSION['auth_version'] ?? 0)
    ) {
        throw new ApiException(401, 'SESSION_EXPIRED', \Hospital\I18n::t("errors.la_sesion_ya_no_tiene_acceso"));
    }
}

function documents_missing(): void
{
    throw new ApiException(404, 'DOCUMENT_NOT_FOUND', \Hospital\I18n::t("errors.no_se_encontro_el_documento"));
}

function documents_row(PDO $db, string $actor, string $id, bool $lock = false): array
{
    $sql = 'SELECT d.*, c.code AS category_code, c.name AS category_name, c.requires_patient, p.full_name AS patient_name, p.national_id AS patient_national_id, p.status AS patient_status, COALESCE(NULLIF(s.full_name,\'\'), u.username) AS uploader_name FROM documents d JOIN document_categories c ON c.id = d.category_id LEFT JOIN patients p ON p.id = d.patient_id JOIN users u ON u.id = d.uploader_user_id JOIN staff s ON s.id = u.staff_id WHERE d.id = ?';
    $parameters = [$id];
    $stmt = $db->prepare($sql . ($lock ? ' FOR UPDATE' : ''));
    $stmt->execute($parameters);
    $row = $stmt->fetch();
    if (!$row) {
        documents_missing();
    }
    return $row;
}

function documents_revision(array $row, $version): void
{
    if ((!is_int($version) && !is_string($version)) || !preg_match('/^[1-9][0-9]{0,9}$/', (string) $version)) {
        throw new ApiException(422, 'VERSION_REQUIRED', \Hospital\I18n::t("errors.se_requiere_la_revision_actual"));
    }
    if ((int) $row['version'] !== (int) $version) {
        throw new ApiException(409, 'VERSION_CONFLICT', \Hospital\I18n::t("errors.el_documento_cambio_volve_a_abrirlo_antes_de_guardar"));
    }
}

function documents_metadata(PDO $db, array $input, bool $lock = false, ?string $existingPatient = null): array
{
    $description = $input['description'] ?? '';
    if (!is_string($description) || !mb_check_encoding($description, 'UTF-8')) {
        throw new ApiException(422, 'VALIDATION_FAILED', \Hospital\I18n::t("errors.descripcion_no_valida"));
    }
    $description = trim(str_replace(["\r\n", "\r"], "\n", $description));
    if (
        mb_strlen($description, 'UTF-8') > 1000
        || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|[\p{Cf}\p{Cs}\p{Co}\p{Cn}]/u', $description)
    ) {
        throw new ApiException(422, 'VALIDATION_FAILED', \Hospital\I18n::t("errors.revisa_la_descripcion_y_su_longitud"));
    }
    $fields = [
        'title' => settings_text($input['title'] ?? null, 'title', 150, false),
        'description' => $description,
        'category_id' => Request::id($input['category_id'] ?? null)
    ];
    $date = $input['document_date'] ?? null;
    if (
        !is_string($date)
        || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)
        || (int) $parts[1] < 1900
        || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
    ) {
        throw new ApiException(
            422,
            'VALIDATION_FAILED',
            \Hospital\I18n::t("errors.ingresa_una_fecha_valida"),
            ['document_date' => \Hospital\I18n::t("errors.fecha_invalida")]
        );
    }
    $fields['document_date'] = $date;
    $stmt = $db->prepare('SELECT requires_patient FROM document_categories WHERE id = ?');
    $stmt->execute([$fields['category_id']]);
    $category = $stmt->fetch();
    if (!$category) {
        throw new ApiException(422, 'VALIDATION_FAILED', \Hospital\I18n::t("errors.selecciona_una_categoria_valida"));
    }
    $patient = $input['patient_id'] ?? null;
    $fields['patient_id'] = ($patient === null || $patient === '') ? null : Request::id($patient);
    if ($category['requires_patient'] && $fields['patient_id'] === null) {
        throw new ApiException(
            422,
            'VALIDATION_FAILED',
            \Hospital\I18n::t("document_form.selecciona_un_paciente_del_hospital"),
            ['patient_id' => \Hospital\I18n::t("errors.paciente_requerido")]
        );
    }
    if ($fields['patient_id'] !== null) {
        $stmt = $db->prepare('SELECT id FROM patients WHERE id = ?' . ($fields['patient_id'] === $existingPatient ? '' : " AND status = 'active'") . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute([$fields['patient_id']]);
        if (!$stmt->fetchColumn()) {
            throw new ApiException(422, 'VALIDATION_FAILED', \Hospital\I18n::t("errors.selecciona_un_paciente_activo_del_hospital"));
        }
    }
    return $fields;
}

function documents_audit(PDO $db, string $actor, string $action, string $id): void
{
    $stmt = $db->prepare("INSERT INTO audit_events (actor_user_id, action, resource_type, resource_id, request_id) VALUES (?, ?, 'document', ?, ?)");
    $stmt->execute([$actor, $action, $id, $GLOBALS['request_id']]);
}

function documents_add_file(PDO $db, string $actor, string $id, int $number, array $file): void
{
    $stmt = $db->prepare("INSERT INTO stored_files (owner_user_id, storage_key, mime_type, byte_size, sha256, purpose, scan_status) VALUES (?, ?, ?, ?, ?, 'document', ?)");
    $stmt->execute([$actor, $file['key'], $file['mime'], $file['size'], $file['sha256'], $file['scan_status']]);
    $fileId = $db->lastInsertId();
    $stmt = $db->prepare('INSERT INTO document_versions (document_id, version_number, stored_file_id, uploaded_by, original_name, extension) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$id, $number, $fileId, $actor, $file['name'], $file['extension']]);
}

function documents_create(PDO $db, array $config, string $actor, array $input, array $upload): array
{
    $fields = documents_metadata($db, $input);
    $key = $input['create_token'] ?? null;
    if (!is_string($key) || !preg_match('/^[a-f0-9]{64}$/', $key)) {
        throw new ApiException(422, 'CREATE_TOKEN_REQUIRED', \Hospital\I18n::t("errors.se_requiere_la_referencia_de_esta_carga"));
    }
    $file = document_file_stage($config, $upload);
    $committed = false;
    try {
        $fingerprint = hash('sha256', json_encode([$fields, $file['sha256'], $file['name']], JSON_THROW_ON_ERROR));
        $db->beginTransaction();
        documents_actor($db, $actor);
        documents_metadata($db, $input, true);
        // The actor row serializes retries even from independent browser sessions.
        $stmt = $db->prepare('SELECT id, create_fingerprint FROM documents WHERE uploader_user_id = ? AND create_key = ?');
        $stmt->execute([$actor, hash('sha256', $key)]);
        $existing = $stmt->fetch();
        if ($existing) {
            if (!hash_equals($existing['create_fingerprint'], $fingerprint)) {
                throw new ApiException(
                    409,
                    'CREATE_TOKEN_REUSED',
                    \Hospital\I18n::t("errors.esta_referencia_de_carga_ya_se_utilizo_con_otros_datos")
                );
            }
            $id = (string) $existing['id'];
            $db->commit();
            document_file_discard($file);
            return documents_get($db, $actor, $id);
        }
        document_file_publish($config, $file);
        $stmt = $db->prepare('INSERT INTO documents (uploader_user_id, patient_id, category_id, title, description, document_date, create_key, create_fingerprint) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $actor,
            $fields['patient_id'],
            $fields['category_id'],
            $fields['title'],
            $fields['description'],
            $fields['document_date'],
            hash('sha256', $key),
            $fingerprint
        ]);
        $id = $db->lastInsertId();
        documents_add_file($db, $actor, $id, 1, $file);
        documents_audit($db, $actor, 'document.created', $id);
        $db->commit();
        $committed = true;
    } catch (\Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    } finally {
        if (!$committed) {
            document_file_discard($file);
        }
    }
    return documents_get($db, $actor, $id);
}

function documents_update(PDO $db, array $config, string $actor, string $id, array $input, ?array $upload = null): array
{
    $file = null;
    $committed = false;
    try {
        // Permission/version checks precede parsing potentially expensive uploads.
        $row = documents_row($db, $actor, $id);
        documents_revision($row, $input['version'] ?? null);
        if ($row['archived_at'] !== null) {
            throw new ApiException(409, 'DOCUMENT_ARCHIVED', \Hospital\I18n::t("document_form.restaura_el_documento_antes_de_editarlo"));
        }
        $hasMetadata = array_key_exists('title', $input);
        if ($upload === null || $hasMetadata) {
            documents_metadata($db, $input, false, $row['patient_id'] === null ? null : (string) $row['patient_id']);
        }
        if ($upload !== null) {
            $file = document_file_stage($config, $upload);
        }
        $db->beginTransaction();
        documents_actor($db, $actor);
        $row = documents_row($db, $actor, $id, true);
        documents_revision($row, $input['version'] ?? null);
        if ($row['archived_at'] !== null) {
            throw new ApiException(409, 'DOCUMENT_ARCHIVED', \Hospital\I18n::t("document_form.restaura_el_documento_antes_de_editarlo"));
        }
        if ($upload === null || $hasMetadata) {
            $fields = documents_metadata($db, $input, true, $row['patient_id'] === null ? null : (string) $row['patient_id']);
            $stmt = $db->prepare('UPDATE documents SET title = ?, description = ?, category_id = ?, patient_id = ?, document_date = ? WHERE id = ?');
            $stmt->execute([
                $fields['title'],
                $fields['description'],
                $fields['category_id'],
                $fields['patient_id'],
                $fields['document_date'],
                $id
            ]);
        } elseif (array_diff(array_keys($input), ['id', 'version'])) {
            throw new ApiException(422, 'VALIDATION_FAILED', \Hospital\I18n::t("errors.envia_todos_los_datos_o_solamente_el_archivo"));
        }
        if ($file !== null) {
            document_file_publish($config, $file);
            $next = (int) $row['current_file_version'] + 1;
            documents_add_file($db, $actor, $id, $next, $file);
            $stmt = $db->prepare('UPDATE documents SET current_file_version = ? WHERE id = ?');
            $stmt->execute([$next, $id]);
        }
        $stmt = $db->prepare('UPDATE documents SET version = version + 1, updated_at = UTC_TIMESTAMP() WHERE id = ?');
        $stmt->execute([$id]);
        documents_audit($db, $actor, $file === null ? 'document.updated' : 'document.file_replaced', $id);
        $db->commit();
        $committed = true;
    } catch (\Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    } finally {
        if (!$committed) {
            document_file_discard($file);
        }
    }
    return documents_get($db, $actor, $id);
}

function documents_archive(PDO $db, string $actor, string $id, $version, bool $restore = false): array
{
    $db->beginTransaction();
    try {
        documents_actor($db, $actor);
        $row = documents_row($db, $actor, $id, true);
        documents_revision($row, $version);
        if (($row['archived_at'] === null) === $restore) {
            throw new ApiException(409, 'DOCUMENT_STATE_CONFLICT', \Hospital\I18n::t("errors.el_estado_del_documento_ya_cambio"));
        }
        $stmt = $db->prepare('UPDATE documents SET archived_at = ' . ($restore ? 'NULL' : 'UTC_TIMESTAMP()') . ', version = version + 1, updated_at = UTC_TIMESTAMP() WHERE id = ?');
        $stmt->execute([$id]);
        if (!$restore) {
            // Restoring a document must never revive a previously distributed QR.
            $stmt = $db->prepare('UPDATE document_access_links l JOIN document_versions v ON v.id = l.document_version_id SET l.revoked_at = UTC_TIMESTAMP() WHERE v.document_id = ? AND l.revoked_at IS NULL');
            $stmt->execute([$id]);
        }
        documents_audit($db, $actor, $restore ? 'document.restored' : 'document.archived', $id);
        $db->commit();
    } catch (\Throwable $error) {
        $db->rollBack();
        throw $error;
    }
    return documents_get($db, $actor, $id);
}

function documents_file_payload(array $row): array
{
    return [
        'version' => (int) $row['version_number'],
        'original_name' => $row['original_name'],
        'extension' => $row['extension'],
        'mime_type' => $row['mime_type'],
        'byte_size' => (int) $row['byte_size'],
        'sha256' => $row['sha256'],
        'scan_status' => $row['scan_status'],
        'created_at' => users_date($row['created_at'])
    ];
}

function documents_versions(PDO $db, string $id): array
{
    $stmt = $db->prepare("SELECT v.version_number, v.original_name, v.extension, v.created_at, f.mime_type, f.byte_size, f.sha256, f.scan_status FROM document_versions v JOIN stored_files f ON f.id = v.stored_file_id AND f.purpose = 'document' AND f.state = 'ready' WHERE v.document_id = ? ORDER BY v.version_number DESC");
    $stmt->execute([$id]);
    return array_map('documents_file_payload', $stmt->fetchAll());
}

function documents_payload(array $row, array $file): array
{
    return [
        'id' => (string) $row['id'],
        'version' => (int) $row['version'],
        'title' => $row['title'],
        'description' => $row['description'],
        'document_date' => $row['document_date'],
        'category' => [
            'id' => (string) $row['category_id'],
            'code' => $row['category_code'],
            'name' => $row['category_name'],
            'requires_patient' => (bool) $row['requires_patient']
        ],
        'patient' => $row['patient_id'] === null ? null : [
            'id' => (string) $row['patient_id'],
            'full_name' => $row['patient_name'],
            'national_id' => $row['patient_national_id'],
            'active' => $row['patient_status'] === 'active'
        ],
        'uploader' => ['id' => (string) $row['uploader_user_id'], 'name' => $row['uploader_name']],
        'file' => $file,
        'archived_at' => users_date($row['archived_at']),
        'created_at' => users_date($row['created_at']),
        'updated_at' => users_date($row['updated_at'])
    ];
}

function documents_get(PDO $db, string $actor, string $id): array
{
    $row = documents_row($db, $actor, $id);
    $versions = documents_versions($db, $id);
    $file = array_values(array_filter(
        $versions,
        static fn (array $fileVersionRecord): bool => $fileVersionRecord['version'] === (int) $row['current_file_version']
    ))[0] ?? null;
    if (!$file) {
        documents_missing();
    }
    documents_audit($db, $actor, 'document.viewed', $id);
    return documents_payload($row, $file) + ['file_versions' => $versions];
}

function documents_listing(PDO $db, string $actor, array $query): array
{
    if (array_diff(array_keys($query), ['page', 'page_size', 'q', 'type', 'sort', 'state'])) {
        throw new ApiException(422, 'UNEXPECTED_FIELDS', \Hospital\I18n::t("errors.filtro_no_permitido"));
    }
    foreach ($query as $value) {
        if (!is_string($value)) {
            throw new ApiException(422, 'VALIDATION_FAILED', \Hospital\I18n::t("errors.filtro_invalido"));
        }
    }
    $page = $query['page'] ?? '1';
    $size = $query['page_size'] ?? '20';
    if (
        !preg_match('/^[1-9][0-9]{0,5}$/', $page)
        || !preg_match('/^[1-9][0-9]{0,2}$/', $size)
        || (int) $size > 100
    ) {
        throw new ApiException(422, 'VALIDATION_FAILED', \Hospital\I18n::t("errors.paginacion_invalida"));
    }
    $searchQuery = settings_text($query['q'] ?? '', 'q', 150, true);
    $type = $query['type'] ?? '';
    $sort = $query['sort'] ?? 'recent';
    $state = $query['state'] ?? 'active';
    if (
        !in_array($type, ['', 'pdf', 'docx', 'png', 'jpg'], true)
        || !in_array($sort, ['recent', 'oldest'], true)
        || !in_array($state, ['active', 'archived'], true)
    ) {
        throw new ApiException(422, 'VALIDATION_FAILED', \Hospital\I18n::t("errors.filtro_invalido"));
    }
    $where = ['d.archived_at IS ' . ($state === 'active' ? 'NULL' : 'NOT NULL')];
    $params = [];
    if ($type !== '') {
        $where[] = 'v.extension = ?';
        $params[] = $type;
    }
    if ($searchQuery !== '') {
        $where[] = "(d.title LIKE ? ESCAPE '!' OR d.description LIKE ? ESCAPE '!')";
        $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $searchQuery) . '%';
        array_push($params, $pattern, $pattern);
    }
    $join = " FROM documents d JOIN document_versions v ON v.document_id = d.id AND v.version_number = d.current_file_version JOIN stored_files f ON f.id = v.stored_file_id AND f.state = 'ready' AND f.purpose = 'document' JOIN document_categories c ON c.id = d.category_id LEFT JOIN patients p ON p.id = d.patient_id JOIN users u ON u.id = d.uploader_user_id JOIN staff s ON s.id = u.staff_id WHERE " . implode(' AND ', $where);
    $stmt = $db->prepare('SELECT COUNT(*)' . $join);
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();
    $order = $sort === 'oldest' ? 'ASC' : 'DESC';
    $offset = ((int) $page - 1) * (int) $size;
    $stmt = $db->prepare("SELECT d.*, c.code AS category_code, c.name AS category_name, c.requires_patient, p.full_name AS patient_name, p.national_id AS patient_national_id, p.status AS patient_status, COALESCE(NULLIF(s.full_name,''), u.username) AS uploader_name, v.version_number, v.original_name, v.extension, v.created_at AS file_created_at, f.mime_type, f.byte_size, f.sha256, f.scan_status" . $join . " ORDER BY d.created_at $order, d.id $order LIMIT " . (int) $size . ' OFFSET ' . $offset);
    $stmt->execute($params);
    $items = array_map(
        function (array $row): array {
            $fileRow = $row;
            $fileRow['created_at'] = $row['file_created_at'];
            return documents_payload($row, documents_file_payload($fileRow));
        },
        $stmt->fetchAll()
    );
    return ['items' => $items, 'page' => (int) $page, 'page_size' => (int) $size, 'total' => $total];
}

function documents_content(PDO $db, array $config, string $actor, string $id, ?string $version, bool $download): array
{
    $row = documents_row($db, $actor, $id);
    if ($row['archived_at'] !== null) {
        documents_missing();
    }
    $number = $version ?? (string) $row['current_file_version'];
    $file = documents_file($db, $id, $number);
    $response = documents_file_response($config, $file);
    documents_audit($db, $actor, $download ? 'document.downloaded' : 'document.previewed', $id);
    return $response;
}

function documents_file(PDO $db, string $id, string $number, bool $readyOnly = true): array
{
    $stmt = $db->prepare("SELECT v.id AS document_version_id, v.original_name, v.extension, f.storage_key, f.mime_type, f.byte_size FROM document_versions v JOIN stored_files f ON f.id = v.stored_file_id AND f.purpose = 'document'" . ($readyOnly ? " AND f.state = 'ready'" : '') . ' WHERE v.document_id = ? AND v.version_number = ?');
    $stmt->execute([$id, $number]);
    $file = $stmt->fetch();
    if (!$file) {
        documents_missing();
    }
    return $file;
}

function documents_file_response(array $config, array $file): array
{
    $path = (new Hospital\Storage\PrivateStorage($config))->path('documents', $file['storage_key']);
    if (!is_file($path) || filesize($path) !== (int) $file['byte_size']) {
        documents_missing();
    }
    return [
        'path' => $path,
        'name' => $file['original_name'],
        'mime' => $file['mime_type'],
        'size' => (int) $file['byte_size'],
        'extension' => $file['extension']
    ];
}

function documents_link_token(string $appKey, array $link): string
{
    return hash_hmac('sha256', 'document-link:' . $link['document_version_id'] . ':' . $link['nonce'], $appKey);
}

function documents_patient_link(
    PDO $db,
    array $config,
    string $actor,
    string $id,
    $revision,
    string $fileVersion,
    ?string $revokeToken = null
): array {
    $db->beginTransaction();
    try {
        documents_actor($db, $actor);
        $row = documents_row($db, $actor, $id, true);
        documents_revision($row, $revision);
        if ($row['archived_at'] !== null) {
            documents_missing();
        }
        // Revocation must still work while a file is missing or quarantined.
        $file = documents_file($db, $id, $fileVersion, $revokeToken === null);
        if ($revokeToken === null) {
            documents_file_response($config, $file);
        }
        $stmt = $db->prepare('SELECT * FROM document_access_links WHERE document_version_id = ? FOR UPDATE');
        $stmt->execute([$file['document_version_id']]);
        $link = $stmt->fetch();
        if ($revokeToken !== null) {
            if (!$link || !hash_equals($link['token_hash'], hash('sha256', $revokeToken))) {
                throw new ApiException(409, 'DOCUMENT_LINK_CHANGED', \Hospital\I18n::t("errors.document_link_changed"));
            }
            $stmt = $db->prepare('UPDATE document_access_links SET revoked_at = UTC_TIMESTAMP() WHERE document_version_id = ?');
            $stmt->execute([$file['document_version_id']]);
            documents_audit($db, $actor, 'document.patient_link_revoked', $id);
            $result = ['revoked' => true];
        } else {
            if (
                !$link
                || $link['revoked_at'] !== null
                || $link['expires_at'] <= gmdate('Y-m-d H:i:s')
                || !hash_equals($link['token_hash'], hash('sha256', documents_link_token($config['app_key'], $link)))
            ) {
                $link = [
                    'document_version_id' => $file['document_version_id'],
                    'nonce' => bin2hex(random_bytes(16)),
                    'expires_at' => gmdate('Y-m-d H:i:s', time() + 86400)
                ];
                $stmt = $db->prepare('INSERT INTO document_access_links (document_version_id, nonce, token_hash, created_by_user_id, created_at, expires_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP(), ?) ON DUPLICATE KEY UPDATE nonce = VALUES(nonce), token_hash = VALUES(token_hash), created_by_user_id = VALUES(created_by_user_id), created_at = VALUES(created_at), expires_at = VALUES(expires_at), revoked_at = NULL');
                $stmt->execute([
                    $file['document_version_id'],
                    $link['nonce'],
                    hash('sha256', documents_link_token($config['app_key'], $link)),
                    $actor,
                    $link['expires_at']
                ]);
                documents_audit($db, $actor, 'document.patient_link_created', $id);
            }
            $result = [
                'token' => documents_link_token($config['app_key'], $link),
                'expires_at' => users_date($link['expires_at']),
                'file_version' => (int) $fileVersion
            ];
        }
        $db->commit();
        return $result;
    } catch (\Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}

function documents_patient_content(PDO $db, array $config, string $token): array
{
    $file = false;
    if (preg_match('/^[a-f0-9]{64}$/', $token)) {
        $stmt = $db->prepare("SELECT v.original_name, v.extension, f.storage_key, f.mime_type, f.byte_size FROM document_access_links l JOIN document_versions v ON v.id = l.document_version_id JOIN documents d ON d.id = v.document_id JOIN stored_files f ON f.id = v.stored_file_id AND f.state = 'ready' AND f.purpose = 'document' WHERE l.token_hash = ? AND l.revoked_at IS NULL AND l.expires_at > UTC_TIMESTAMP() AND d.archived_at IS NULL");
        $stmt->execute([hash('sha256', $token)]);
        $file = $stmt->fetch();
    }
    if (!$file) {
        throw new ApiException(404, 'DOCUMENT_LINK_INVALID', \Hospital\I18n::t("errors.document_link_invalid"));
    }
    return documents_file_response($config, $file);
}

<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/php/bootstrap.php';

use Hospital\Http\ApiException;
use Hospital\Http\Request;
use Hospital\Http\Response;
use Hospital\Security\Csrf;

$action = $_GET['action'] ?? '';
if (!is_string($action) || !in_array($action, ['facilities', 'specialties', 'document-categories', 'patients'], true)) {
    throw new ApiException(404, 'NOT_FOUND', Hospital\I18n::t('errors.no_se_encontro_el_endpoint'));
}
// Patient identity searches remain POST so they never enter URL/access logs.
Request::method($action === 'patients' ? 'POST' : 'GET');
auth_require_user($db, $config, false);
if ($action === 'patients') Csrf::verify();
switch ($action) {
    case 'facilities':
        $rows = $db->query("SELECT id, name, address, locality FROM facilities WHERE status = 'active' AND availability = 'active' ORDER BY name LIMIT 500")->fetchAll();
        $counts = array_count_values(array_map(static fn(array $row): string => mb_strtolower($row['name']), $rows));
        Response::json(array_map(static fn(array $row): array => ['id' => (string) $row['id'],
            'name' => $counts[mb_strtolower($row['name'])] > 1 ? implode(' · ', array_filter([$row['name'],$row['address'],$row['locality']])) : $row['name']], $rows));
        break;
    case 'patients':
        // Search terms may contain patient identity. Keep them out of URLs/access logs.
        
        if (array_diff(array_keys($_GET), ['action'])) throw new ApiException(422, 'UNEXPECTED_FIELDS', \Hospital\I18n::t("errors.filtro_no_permitido"));
        $body = Request::body(['q']);
        $q = settings_text($body['q'] ?? '', 'q', 150, true);
        if (mb_strlen($q) < 2) { Response::json([]); return; }
        $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
        $ci = preg_replace('/[.\s-]/', '', $q);
        $stmt = $db->prepare("SELECT id, full_name, national_id FROM patients WHERE status = 'active' AND (full_name LIKE ? ESCAPE '!' OR national_id LIKE ? ESCAPE '!') ORDER BY full_name, id LIMIT 20");
        $stmt->execute([$pattern, '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $ci) . '%']);
        Response::json(array_map(static fn(array $row): array => ['id' => (string) $row['id'], 'full_name' => $row['full_name'], 'national_id' => $row['national_id']], $stmt->fetchAll()));
        break;
    case 'document-categories':
        $rows = $db->query('SELECT id, code, name, requires_patient FROM document_categories ORDER BY id')->fetchAll();
        Response::json(array_map(static fn(array $row): array => ['id' => (string) $row['id'], 'code' => $row['code'], 'name' => $row['name'], 'requires_patient' => (bool) $row['requires_patient']], $rows));
        break;
    case 'specialties':
        $rows = $db->query("SELECT id, name FROM specialties WHERE status = 'active' ORDER BY name LIMIT 500")->fetchAll();
        Response::json(array_map(static fn(array $row): array => ['id' => (string) $row['id'], 'name' => $row['name']], $rows));
        break;
}

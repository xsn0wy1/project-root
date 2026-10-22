<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/php/bootstrap.php';

use Hospital\Http\ApiException;
use Hospital\Http\Request;
use Hospital\Http\Response;

if (($_GET['action'] ?? '') !== 'snapshot') throw new ApiException(404, 'NOT_FOUND', Hospital\I18n::t('errors.no_se_encontro_el_endpoint'));
Request::method('GET');
$query = $_GET;
unset($query['action']);
$user = auth_require_user($db, $config, false);
session_write_close();
Response::json(tracking_snapshot($db, (string)$user['id'], $query));

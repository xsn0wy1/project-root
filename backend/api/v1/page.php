<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/php/core/page.php';
$view = $_GET['view'] ?? '';
require_staff_page(is_string($view) ? $view : '');
if (!is_string($view) || !preg_match('#^(?:[a-z0-9-]+/)*[a-z0-9-]+\.html$#', $view)) {
    throw new Hospital\Http\ApiException(404, 'PAGE_NOT_FOUND', Hospital\I18n::t('errors.no_se_encontro_la_pagina'));
}
$root = realpath(dirname(__DIR__, 3) . '/public/src/views');
$file = realpath($root . '/' . $view);
if (!$file || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) {
    throw new Hospital\Http\ApiException(404, 'PAGE_NOT_FOUND', Hospital\I18n::t('errors.no_se_encontro_la_pagina'));
}
readfile($file);

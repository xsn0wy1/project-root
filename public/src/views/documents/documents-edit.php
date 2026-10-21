<?php
declare(strict_types=1);
require dirname(__DIR__, 4) . '/backend/php/core/page.php';
require_staff_page('documents/documents-edit.php');
$editing = true;
require dirname(__DIR__, 4) . '/backend/php/components/document-form.php';

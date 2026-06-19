<?php
$view = $_REQUEST['view'] ?? 'grid';

switch ($view) {
    case 'form':
        include __DIR__ . '/views/form.php';
        break;
    case 'settings':
        include __DIR__ . '/views/settings.php';
        break;
    default:
        include __DIR__ . '/views/grid.php';
        break;
}

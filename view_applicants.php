<?php
// Simple redirect shim to the main applicants page
$qs = $_SERVER['QUERY_STRING'] ?? '';
$target = 'applicant_management.php' . ($qs ? ('?' . $qs) : '');
header('Location: ' . $target);
exit;
?>
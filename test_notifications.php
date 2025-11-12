<?php
require_once 'includes/db.php';
$db = getDB();
$result = $db->query('DESCRIBE notifications')->fetchAll();
echo "<pre>";
print_r($result);
echo "</pre>";
?>

<?php

require_once '/var/www/html/htdocs/conf/conf.php';
require_once '/var/www/html/htdocs/master.inc.php';
global $db, $user;
if (!$db) { echo 'DB error'; exit; }
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
$user = new User($db); $user->fetch(1);
require_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactucron.class.php';
$cron = new VerifactuCron($db);
$count = 0; $message = '';
//$result = $cron->doScheduledJob('', $count, $message);
$result = $cron->doScheduledJobErrors('', $count, $message);
echo "Resultado: $result, Count: $count, Message: $message\n";
?>
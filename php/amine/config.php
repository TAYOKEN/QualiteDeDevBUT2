<?php
$mode = 'local'; // tu met le mode que tu veux utiliser (local ou prod)

if ($mode === 'local') { // change selon ta configuration
    $db_host = "localhost";
    $db_user = "root";
    $db_pass = "";
    $db_name = "talk";
}

if ($mode === 'prod') { // ne pas toucher 
    $db_host = "starrk.xyz";
    $db_user = "admin";   
    $db_pass = "";
    $db_name = "TALK";
}
?>

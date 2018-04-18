<?php
require_once 'SimpleESI.php';

$esi = new SimpleESI;
$esi->post($bulk, 'universe/ids/', ['The Forge'])->exec();

if (isset($bulk['regions'][0]['id']))
    echo 'The Forge\'s id is '.$bulk['regions'][0]['id'].'.'.PHP_EOL;
?>

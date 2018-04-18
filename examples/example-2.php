<?php
require_once 'SimpleESI.php';

$esi = new SimpleESI;
$esi->get($items, [9832, 33468, 12612], 'universe/types/~/')
    ->get($status, 'status/')
    ->exec();

foreach ($items as $id => $thing)
    echo $thing['name'].' has got an id of '.$id.'.'.PHP_EOL;

echo 'There are currently '.$status['players'].
    ' players in EVE Online.'.PHP_EOL;
?>

<?php
require_once 'SimpleESI.php';

$esi = new SimpleESI;

$RegionNameToId = $esi->meta('RegionNameToId');

if (empty($RegionNameToId)) {
    $esi->get($RegionIds, 'universe/regions/')
        ->exec()
        ->get($Regions, $RegionIds, 'universe/regions/~/')
        ->exec();
    $RegionNameToId = array_column($Regions, 'region_id', 'name');
    $esi->meta('RegionNameToId', $RegionNameToId);
}

if (isset($RegionNameToId['The Forge']))
    echo 'The Forge\'s id is '.$RegionNameToId['The Forge'].'.'.PHP_EOL;
?>

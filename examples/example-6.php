<?php
require_once 'SimpleESI.php';

$esi = new SimpleESI;

$esi->get($TypeIdsBook, 'universe/types/')
    ->get($TSTypeIdsBook, 'universe/types/?datasource=singularity')
    ->exec();

$old_ids = $new_ids = [];
foreach ($TypeIdsBook as $page)
    foreach ($page as $id)
        $old_ids[] = $id;
foreach ($TSTypeIdsBook as $page)
    foreach ($page as $id)
        $new_ids[] = $id;

echo 'Number of item types found on Tranquility: '.count($old_ids).PHP_EOL;
echo '                              Singularity: '.count($new_ids).PHP_EOL;

$missing_ids = array_diff($old_ids, $new_ids);
echo PHP_EOL.'   Missing on Singularity: '.count($missing_ids).PHP_EOL;
$esi->get($MissingTypes, $missing_ids, 'universe/types/~/')->exec();
if (isset($MissingTypes))
    foreach ($MissingTypes as $type)
        echo '      #'.$id.' - \''.($type['name'] ?? '- unknown -').'\''.PHP_EOL;

$added_ids = array_diff($new_ids, $old_ids);
echo PHP_EOL.'   New on Singularity: '.count($added_ids).PHP_EOL;
$esi->get($AddedTypes, $added_ids, 'universe/types/~/?datasource=singularity')->exec();
if (isset($AddedTypes))
    foreach ($AddedTypes as $id => $type)
        echo '      #'.$id.' - \''.($type['name'] ?? '- unknown -').'\''.PHP_EOL;
?>

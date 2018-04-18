<?php
require_once 'SimpleESI.php';

$esi = new SimpleESI;
$esi->get($veldspar, 'universe/types/1230/')->exec();

echo $veldspar['description'].PHP_EOL;
?>

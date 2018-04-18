<?php
require_once 'SimpleESI.php';

$esi = new SimpleESI;
$esi->get($result, 'search/?categories=region&strict=1&', ['search' => 'The Forge'])->exec();

if (isset($result['region'][0]))
    echo 'The Forge\'s id is '.$result['region'][0].'.'.PHP_EOL;
?>

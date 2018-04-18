<?php
require_once 'SimpleESI.php';

$esi = new SimpleESI;

$RegionsOfInterest = [ 10000002 => 'The Forge'  , 10000030 => 'Heimatar',
                       10000032 => 'Sinq Laison', 10000043 => 'Domain' ];
$IdOfPLEX = 44992;

$GLOBALS += $esi->meta('GLOBALS') ?: [];

$esi->get($SellOrders,
          array_keys($RegionsOfInterest),
          'markets/~/orders/?order_type=sell&type_id='.$IdOfPLEX,
          'orders_callback')
    ->exec();
function orders_callback($esi, $rq) {
    global $Stations;
    foreach ($rq->vl as $order)
        if (isset($order['location_id'])) {
            $id = $order['location_id'];
            if (empty($Stations[$id]))
                $esi->get($Stations[$id], 'universe/stations/'.$id.'/', 24*60*60, 'station_callback');
        }
}
function station_callback($esi, $rq) {
    global $Systems;
    if (isset($rq->vl['system_id'])) {
        $id = $rq->vl['system_id'];
        if (empty($Systems[$id]))
            $esi->get($Systems[$id], 'universe/systems/'.$id.'/', 30*60);
    }
}

foreach ($RegionsOfInterest as $id => $name) {
    echo 'The best 3 sell orders for PLEX in '.$name.':'.PHP_EOL;
    $orders = [];
    foreach ($SellOrders[$id] as $page)
        foreach ($page as $order)
            $orders[] = $order;
    usort($orders, function ($a, $b) { return $a['price'] <=> $b['price']; });
    for ($i = 0; $i < 3; ++$i) {
        $o = $orders[$i];
        $station = $Stations[$o['location_id']];
        $system = $Systems[$station['system_id']];
        $security = $system['security_status'];
        printf('   %10.2f ISK (%4d units) - %10s (%.1f) %s'.PHP_EOL,
               $o['price'], $o['volume_remain'], $system['name'], $security, $station['name']);
    }
}

$esi->meta('GLOBALS', [ 'Stations' => $Stations,
                        'Systems'  => $Systems ]);
?>

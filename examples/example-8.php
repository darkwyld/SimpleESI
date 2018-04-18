<!DOCTYPE html><html lang="en"> <meta http-equiv="refresh" content="300"><head><style>
body      { color: #ffffff; background-color: #373737; }
table     { width: 100%; }
tr.region { font-size: 90%; margin: 0px; text-align:   left; background-color: #5f5f5f; }
tr.head   { font-size: 80%; margin: 0px; text-align: center; background-color: #4f4f4f; }
tr.data   { font-size: 70%; margin: 0px; text-align:  right; }
td, th    { padding-left: 5px; padding-right: 5px; padding-top: 0px; padding-bottom: 0px; }
</style></head><body><table>
<?php
require_once '../SimpleESI.php';

$esi = new SimpleESI;

if (isset($_GET['debug'])) {
    $esi->debug_level = 4;
    $esi->debug_html = true;
}

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
    echo '<tr class="region"><th colspan="10">The best 3 sell orders for PLEX in '.$name.'</th></tr>';
    $orders = [];
    foreach ($SellOrders[$id] as $page)
        foreach ($page as $order)
            $orders[] = $order;
    usort($orders, function ($a, $b) { return $a['price'] <=> $b['price']; });
    echo '<tr class="head">'
        .     '<th colspan="2">Price & Volume</th><th>System</th><th>Security</th>'
        .     '<th style="text-align:left">Station</th></h5></tr>';
    for ($i = 0; $i < 3; ++$i) {
        $o = $orders[$i];
        $station = $Stations[$o['location_id']];
        $system = $Systems[$station['system_id']];
        $security = $system['security_status'];
        printf('<tr class="data">'
               .  '<td>%10.2f ISK</td><td>%4d units</td><td style="text-align:center">%s</td>'
               .  '<td style="text-align:center">%.1f</td><td style="text-align:left">%s</td></tr>',
               $o['price'], $o['volume_remain'], $system['name'], $security, $station['name']);
    }
}

$esi->meta('GLOBALS', [ 'Stations' => $Stations,
                        'Systems'  => $Systems ]);
?></table></body></html>

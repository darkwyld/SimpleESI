<?php
require_once '../SimpleESI.php';

if (isset($_GET['code'])) {
    $code = $_GET['code'];
    setcookie('code', $code);
} elseif (isset($_GET['signout']))
    setcookie('code', '', 0);
elseif (isset($_COOKIE['code']))
    $code = $_COOKIE['code'];

$esi = new SimpleESI('confidential');
$esi->debug_level = 4;
$esi->debug_html = true;

$Authorization = $esi->meta('LastAuthorization');
if (empty($code) || empty($Authorization['code']) || $code !== $Authorization['code'])
    $Authorization = [ 'client_id'     => '...',
                       'client_secret' => '...',
                       'redirect_uri'  => 'http://localhost:9000/login.php',
                       'scopes'        => [ 'esi-characters.read_standings.v1' ] ];

if ($esi->auth($Authorization, $code) === true) {
    $esi->meta('LastAuthorization', $Authorization);
    echo '<h3><p><a href="'.$Authorization['redirect_uri'].'?signout=true">Sign out</a></p></h3>';

    $esi->get($Standings, "characters/{$Authorization['char_id']}/standings/")
        ->get($Factions, 'universe/factions/')
        ->exec($Authorization);

    $FactionIdToName = array_column($Factions, 'name', 'faction_id');
    $FactionStandings = [];
    foreach ($Standings as $st)
        if ($st['from_type'] === 'faction')
            $FactionStandings[$FactionIdToName[$st['from_id']]] = $st['standing'];
    arsort($FactionStandings, SORT_NUMERIC);
    echo '<p>Faction standings of '.$Authorization['char_name'].':</p><table>';
    foreach ($FactionStandings as $name => $standing)
        printf('<tr><td style="padding-right:10px">%s</td><td style="text-align:right">%.2f</td></tr>', $name, $standing);
    echo '</table>';
    
    $t = $Authorization['expires'] - time();
    printf('<p>Current token expires in %2d:%02d minutes.</p>'.PHP_EOL, $t / 60, $t % 60);
} else {
    echo '<h3><p><a href="'.$Authorization['auth_uri'].'">Sign on</a></p></h3>';
    echo '<h6><p>Note: this example uses cookies and requests your permission to access private data on your'
        .' EVE Online account.</p><p>Once access has been granted through CCP\'s SSO sevice can it be refreshed by an'
        .' application indefinitely and until it is manually revoked.</p><p>To revoke an application\'s access'
        .' please go to:</p>'
        .'<p><a href="https://community.eveonline.com/support/third-party-applications/">'
        .'https://community.eveonline.com/support/third-party-applications/</a></p></h6>';
}
?>

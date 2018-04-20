<?php
require_once '../SimpleESI.php';

if (isset($_GET['code'])) {
    $code = $_GET['code'];
    setcookie('code', $code);
} elseif (isset($_GET['signout']))
    setcookie('code', '', 0);
elseif (isset($_COOKIE['code']))
    $code = $_COOKIE['code'];

echo <<<'EOT'
<!DOCTYPE html><html lang="en"><head><style>
table { background-color: #efefef; }
td    { font-size: 70%; padding-left: 5px; padding-right: 10px; padding-top: 0px; padding-bottom: 0px; }
</style></head><body>
EOT;

$esi = new SimpleESI('confidential');

$Authorization = $esi->meta('LastAuthorization');
if (empty($code) || empty($Authorization['code']) || $code !== $Authorization['code'])
    $Authorization = [ 'client_id'     => '...',
                       'client_secret' => '...',
                       'redirect_uri'  => 'http://localhost:9000/login.php',
                       'scopes'        => [ 'esi-characters.read_standings.v1' ] ];

if ($esi->auth($Authorization, $code) === false) {
    echo '<h3><p><a href="'.$Authorization['auth_uri'].'">Sign on</a></p></h3>';
    echo '<h6><p>Note: this example uses cookies and requests your permission to access private data on your'
        .' EVE Online account.</p><p>Once access has been granted through CCP\'s SSO sevice can it be refreshed by the'
        .' application indefinitely and until it is manually revoked.</p><p>To revoke an application\'s access'
        .' please go to:</p>'
        .'<p><a href="https://community.eveonline.com/support/third-party-applications/">'
        .'https://community.eveonline.com/support/third-party-applications/</a></p></h6></body>';
    exit;
}

$esi->meta('LastAuthorization', $Authorization);
echo '<h3><p><a href="'.$Authorization['redirect_uri'].'?signout=true">Sign out</a></p></h3>';

$t = $Authorization['expires'] - time();
printf('<p>Current token expires in %2d:%02d minutes.</p>'.PHP_EOL, $t / 60, $t % 60);

$esi->get($Standings, 'characters/'.$Authorization['char_id'].'/standings/', 0, $Authorization)
    ->get($Factions, 'universe/factions/')
    ->exec();
$FactionIdToName = array_column($Factions, 'name', 'faction_id');
$FactionStandings = [];
foreach ($Standings as $st)
    if ($st['from_type'] === 'faction')
        $FactionStandings[$FactionIdToName[$st['from_id']]] = $st['standing'];
arsort($FactionStandings, SORT_NUMERIC);

echo '<p>Faction standings of '.$Authorization['char_name'].':</p><table>';
foreach ($FactionStandings as $name => $standing)
    printf('<tr><td>%s</td><td style="text-align:right">%.2f</td></tr>', $name, $standing);
echo '</table></body>';
?>

<?php /* SimpleESI.php, © 2018, GPLv3, please see LICENSE file for details. */
trait nodebug {
    protected function debug_init() {}
    protected function debug_trnsfr($b) {}
    protected function debug($l, ...$msg) {}
}
trait debug {
    protected function debug_init() {}
    protected function debug_trnsfr($b) {}
    protected function debug($l, ...$msg) {
        if ($l <= 0) {
            $s = '['.__CLASS__.'] '.implode('', $msg);
            if ($l < 0)
                throw new \Exception($s);
            else
                echo $s.PHP_EOL;
        }
    }
}
trait fulldebug {
    public $debug_level = 0, $debug_html, $debug_file;
    protected $debug_t0 = 0, $debug_trnsfr = 0;
    protected function debug_init() { $this->debug_t0 = microtime(true); }
    protected function debug_trnsfr($b) { $this->debug_trnsfr += $b; return $b; }
    protected function debug($l, ...$msg) {
        if ($this->debug_level >= $l) {
            $s = rtrim(implode('', $msg));
            $d = microtime(true) - $this->debug_t0;
            if (isset($this->debug_html)) {
                $c = [ -1 => '#ff00ff', '#ff003f', '#00ff3f', '#ff00bf', '#007fff', '#00bfff', '#ffbf00' ];
                printf('<h6 class="SimpleESI" style="color:%s;margin:0">['.__CLASS__.'] %06.3fs %db (%d) %s</h6>'
                       .PHP_EOL, $c[$l] ?? 'black', $d, $this->debug_trnsfr, $l, $s);
            } elseif (isset($this->debug_file))
                file_put_contents($this->debug_file,
                                  sprintf('['.__CLASS__.'] %s %07.3fs %db (%d) %s'.PHP_EOL, date('r'),
                                          $d, $this->debug_trnsfr, $l, $s, FILE_APPEND | LOCK_EX));
            else
                printf('['.__CLASS__.'] %07.3fs %db (%d) %s'.PHP_EOL, $d, $this->debug_trnsfr, $l, $s);
        }
        if ($l < 0)
            throw new \Exception('['.__CLASS__.'] Fatal error occured. Please see debug messages.');
    }
}

trait nodb {
    protected function db_init($f) {}
    protected function query_cache($rq, $ex) {}
    protected function update_cache($rq, $ex, $pn, $lm, $vl) {}
    public function meta($k, $v = null) {}
}
trait dirdb {
    public $caching = true;
    protected $db_cache_dir, $db_meta_dir;

    protected function db_init($f) {
        $f .= '.dir/';
        $this->db_cache_dir = $f.'cache/';
        $this->db_meta_dir = $f.'meta/';
        if (!file_exists($this->db_cache_dir) && !mkdir($this->db_cache_dir, 0755, true))
            $this->debug(-1, 'Could not create cache directory');
        if (!file_exists($this->db_meta_dir) && !mkdir($this->db_meta_dir, 0755, true))
            $this->debug(-1, 'Could not create meta directory');
    }
    protected function query_cache($rq, $ex) {
        if ($this->caching) {
            $a = parse_url($rq);
            $d = rtrim($this->db_cache_dir.$a['path'], '/');
            $f = $d.rawurlencode($a['query'] ?? '?');
            $d = dirname($d);
            $h = @fopen($f, 'r');
            if ($h && fstat($h)[9] >= $ex) {
                flock($h, LOCK_SH);
                $s = stream_get_contents($h);
                flock($h, LOCK_UN);
                fclose($h);
                $a = json_decode($s, true);
                if (isset($a) && count($a) === 4) {
                    $this->debug(4, 'Cached: ', $rq);
                    return $a;
                }
            }
        }
    }
    protected function update_cache($rq, $ex, $pn, $lm, $vl) {
        if ($this->caching) {
            $a = parse_url($rq);
            $d = rtrim($this->db_cache_dir.$a['path'], '/');
            $f = $d.rawurlencode($a['query'] ?? '?');
            $d = dirname($d);
            if (!file_exists($d) && !mkdir($d, 0755, true))
                $this->debug(-1, 'Could not create cache subdirectory');
            $h = fopen($f, 'c');
            if ($h) {
                $s = json_encode([ $ex, $pn, $lm, $vl ]);
                flock($h, LOCK_EX);
                fwrite($h, $s);
                ftruncate($h, strlen($s));
                touch($f, $ex);
                flock($h, LOCK_UN);
                fclose($h);
            } else
                $this->debug(-1, 'Could not write/update cache file');
        }
    }
    public function meta($k, $v = null) {
        $f = $this->db_meta_dir.rtrim($k, '/');
        if (isset($v)) {
            $d = dirname($f);
            if (!file_exists($d) && !mkdir($d, 0755, true))
                $this->debug(-1, 'Could not create meta subdirectory');
            $h = fopen($f, 'c');
            if ($h) {
                $s = serialize($v);
                flock($h, LOCK_EX);
                fwrite($h, $s);
                ftruncate($h, strlen($s));
                flock($h, LOCK_UN);
                fclose($h);
            } else
                $this->debug(-1, 'Could not write/update meta file');
        } else {
            $h = @fopen($f, 'r');
            if ($h) {
                flock($h, LOCK_SH);
                $s = stream_get_contents($h);
                flock($h, LOCK_UN);
                fclose($h);
                return unserialize($s);
            }
        }
    }
}
trait sqlite3db {
    public $caching = true;
    protected $db, $db_rq_ref, $db_ex_ref, $db_pn_ref, $db_lm_ref, $db_vl_ref;
    protected $cache_qry_stmt, $cache_upd_stmt, $meta_qry_stmt, $meta_upd_stmt;

    protected function db_init($f) {
        $this->db = new \SQLite3($f.'.sq3', SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
        $this->db->exec(
            'PRAGMA synchronous = OFF; PRAGMA journal_mode = OFF;'.
            'CREATE TABLE IF NOT EXISTS cache(rq TEXT NOT NULL PRIMARY KEY, ex INT, pn INT, lm INT, vl TEXT);'.
            'CREATE TABLE IF NOT EXISTS  meta(rq TEXT NOT NULL PRIMARY KEY, vl BLOB);');
        $this->cache_qry_stmt = $this->db->prepare('SELECT ex, pn, lm, vl FROM cache WHERE rq = ? AND ex >= ?');
        $this->cache_qry_stmt->bindParam(1, $this->db_rq_ref, SQLITE3_TEXT);
        $this->cache_qry_stmt->bindParam(2, $this->db_ex_ref, SQLITE3_INTEGER);
        $this->cache_upd_stmt = $this->db->prepare('REPLACE INTO cache(rq, ex, pn, lm, vl) VALUES(?, ?, ?, ?, ?)');
        $this->cache_upd_stmt->bindParam(1, $this->db_rq_ref, SQLITE3_TEXT);
        $this->cache_upd_stmt->bindParam(2, $this->db_ex_ref, SQLITE3_INTEGER);
        $this->cache_upd_stmt->bindParam(3, $this->db_pn_ref, SQLITE3_INTEGER);
        $this->cache_upd_stmt->bindParam(4, $this->db_lm_ref, SQLITE3_INTEGER);
        $this->cache_upd_stmt->bindParam(5, $this->db_vl_ref, SQLITE3_TEXT);
        $this->meta_qry_stmt = $this->db->prepare('SELECT vl FROM meta WHERE rq = ?');
        $this->meta_qry_stmt->bindParam(1, $this->db_rq_ref, SQLITE3_TEXT);
        $this->meta_upd_stmt = $this->db->prepare('REPLACE INTO meta(rq, vl) VALUES(?, ?)');
        $this->meta_upd_stmt->bindParam(1, $this->db_rq_ref, SQLITE3_TEXT);
        $this->meta_upd_stmt->bindParam(2, $this->db_vl_ref, SQLITE3_BLOB);
    }
    protected function query_cache($rq, $ex) {
        if ($this->caching) {
            $this->db_rq_ref = $rq;
            $this->db_ex_ref = $ex;
            $a = $this->cache_qry_stmt->execute()->fetchArray(SQLITE3_NUM);
            if ($a !== false && count($a) === 4) {
                $this->debug(4, 'Cached: ', $rq);
                return $a;
            }
        }
    }
    protected function update_cache($rq, $ex, $pn, $lm, $vl) {
        if ($this->caching) {
            $this->db_rq_ref = $rq;
            $this->db_ex_ref = $ex;
            $this->db_pn_ref = $pn;
            $this->db_lm_ref = $lm;
            $this->db_vl_ref = $vl;
            $this->cache_upd_stmt->execute();
        }
    }
    public function meta($k, $v = null) {
        $this->db_rq_ref = $k;
        if (isset($v)) {
            $s = serialize($v);
            if (is_string($s)) {
                $this->db_vl_ref = $s;
                $this->meta_upd_stmt->execute();
            }
        } else {
            $a = $this->meta_qry_stmt->execute()->fetchArray(SQLITE3_NUM);
            if (is_array($a) && count($a) === 1)
                return unserialize($a[0]);
        }
    }
}

class SimpleESI {
    use fulldebug, sqlite3db;

    public $esi_uri = 'https://esi.tech.ccp.is/latest/';
    public $oauth_uri = 'https://login.eveonline.com/oauth/';
    public $marker = '~';
    public $paging = true;
    public $retries = 3;
    public $error_throttle = 80, $error_exit = 20;

    protected $get_arr, $post_arr, $idle = true;
    protected $curl_mh, $curl_opt_get_arr, $curl_opt_post_arr, $curl_arr;
    protected $error_window = 60, $error_usleep;

    public function __construct($f = 'esi', $u = null) {
        $this->debug_init();
        $this->db_init($f);
        $this->curl_mh = curl_multi_init();
        curl_multi_setopt($this->curl_mh, CURLMOPT_PIPELINING, CURLPIPE_HTTP1 | CURLPIPE_MULTIPLEX);
        $this->curl_opt_get_arr = [ CURLOPT_RETURNTRANSFER => true,
                                    CURLOPT_HTTPHEADER     => [ 'Accept: application/json' ],
                                    CURLOPT_HEADERFUNCTION => __CLASS__.'::process_header',
                                    CURLOPT_PIPEWAIT       => true,
                                    CURLOPT_BUFFERSIZE     => 256 << 10,
                                    CURLOPT_TIMEOUT        => 300,
                                    CURLOPT_HTTPAUTH       => CURLAUTH_ANY,
                                    CURLOPT_TCP_NODELAY    => true ];

        if (isset($u))
            $this->curl_opt_get_arr[CURLOPT_HTTPHEADER][] = 'X-User-Agent: '.$u;

        $h = curl_init($this->esi_uri);
        foreach ([ CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_2_0,
                   CURLOPT_TCP_FASTOPEN   => true,
                   CURLOPT_SSL_FALSESTART => true ] as $o => $v)
            if (curl_setopt($h, $o, $v) === true)
                $this->curl_opt_get_arr[$o] = $v;
            else
                $this->debug(4, 'cURL option '.$o.' is not supported.');
        curl_close($h);
        $this->curl_opt_post_arr = $this->curl_opt_get_arr;
        $this->curl_opt_post_arr[CURLOPT_POST]         = true;
        $this->curl_opt_post_arr[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
    }

    public function __destruct() {
        curl_multi_close($this->curl_mh);
        $this->debug(1, 'Elapsed.');
    }

    protected function process_header($h, $s) {
        $this->debug(5, 'Header: ', $s);
        $a = explode(': ', $s, 2);
        switch (strtolower($a[0])) {
        case 'last-modified'           : $this->curl_arr[(int) $h]->lm = strtotime($a[1]); break;
        case 'expires'                 : $this->curl_arr[(int) $h]->ex = strtotime($a[1]); break;
        case 'x-pages'                 : $this->curl_arr[(int) $h]->pn = (int) $a[1];      break;
        case 'x-esi-error-limit-reset' : $this->error_window           = (int) $a[1];      break;
        case 'x-esi-error-limit-remain':
            $l = (int) $a[1];
            if ($l < 100)
                $this->debug(2, 'Error limit: ', $l, ', window: ', $this->error_window, 's');
            if ($l <= $this->error_throttle)
                $this->error_usleep = (int) (1.0e6 * max(1, $this->error_window) / pow(max(1, $l), 1.5));
            else
                unset($this->error_usleep);
            if ($l > $this->error_exit)
                break;
            // Fall-through.
        case 'x-esi-error-limited':
            $this->debug(-1, 'Error limit reached: ', $s);
        }
        $l = strlen($s);
        $this->debug_trnsfr($l);
        return $l;
    }

    protected function queue_get($rq) {
        $a = $this->query_cache($rq->rq, $rq->ex);
        if (isset($a)) {
            list($rq->ex, $rq->pn, $rq->lm) = $a;
            if (isset($rq->pn) && empty($rq->pi)) {
                if ($rq->pn > 1 && isset($this->paging))
                    $this->pages_get($rq->vl, $rq->rq, 1, $rq->pn, $rq->ex, $rq->cb);
                $rq->vl = &$rq->vl[0];
            }
            $rq->vl = json_decode($a[3], true);
            if ($rq->cb)
                ($rq->cb)($this, $rq);
            return;
        }
        if (isset($this->error_usleep)) {
            $this->debug(2, 'Throttling traffic.');
            usleep($this->error_usleep);
        }
        $this->debug(3, 'Requesting: ', $rq->rq);
        $h = curl_init($this->esi_uri.$rq->rq);
        curl_setopt_array($h, $this->curl_opt_get_arr);
        curl_multi_add_handle($this->curl_mh, $h);
        $this->curl_arr[(int) $h] = $rq;
    }

    public function single_get(&$v, $r, $x = 0, $c = null) {
        if (isset($this->get_arr[$r]))
            return;
        $this->get_arr[$r] = (object) [ 'rq' => $r, 'ex' => time() - $x, 'lm' => 0, 'vl' => &$v,
                                        'cb' => $c, 'pn' => null, 'pi' => null, 'rt' => 0 ];
        if (empty($this->idle))
            $this->queue_get($this->get_arr[$r]);
    }

    public function pages_get(&$v, $r, $p0, $p1, $x, $c) {
        $a = parse_url($r);
        $r = $a['path'].'?';
        if (isset($a['query']))
            parse_str($a['query'], $q);
        if (isset($q['page']))
            return;
        $this->debug(3, 'Requesting pages ', $p0 + 1, ' to ', $p1, ' of: ', $r);
        for ($i = $p0; $i < $p1; ++$i) {
            $q['page'] = $i + 1;
            $rr = $r.http_build_query($q);
            if (isset($this->get_arr[$rr]))
                continue;
            $this->get_arr[$rr] = (object) [ 'rq' => $rr, 'ex' =>  $x, 'lm' =>  0, 'vl' => &$v[$i],
                                             'cb' =>  $c, 'pn' => $p1, 'pi' => $i, 'rt' => 0 ];
            if (empty($this->idle))
                $this->queue_get($this->get_arr[$rr]);
        }
    }

    public function get(&$v, ...$args) {
        $r = current($args);
        if (is_string($r)) {
            $c = next($args);
            if (is_array($c)) {
                if ($r[-1] === '/')
                    $r .= '?';
                elseif ($r[-1] !== '&')
                    $r .= '&';
                $r .= http_build_query($c, '', '&', PHP_QUERY_RFC3986);
                $c = next($args);
            }
            if (is_int($c)) {
                $x = $c;
                $c = next($args);
            } else
                $x = 0;
            $this->single_get($v, $r, $x, $c);
        } else {
            $rr = next($args);
            if (is_string($rr)) {
                $c = next($args);
                if (is_array($c)) {
                    if ($rr[-1] === '/')
                        $rr .= '?';
                    elseif ($rr[-1] !== '&')
                        $rr .= '&';
                    $rr .= http_build_query($c, '', '&', PHP_QUERY_RFC3986);
                    $c = next($args);
                }
                list($s1, $s2) = explode($this->marker, $rr, 2);
                $rr = explode($this->marker, $s1.implode($s2.$this->marker.$s1, $r).$s2);
            } else {
                $c = next($args);
                if (is_array($c)) {
                    $q = '?'.http_build_query($c, '', '&', PHP_QUERY_RFC3986);
                    foreach ($rr as &$s)
                        $s .= $q;
                    $c = next($args);
                }
            }
            if (is_int($c)) {
                $x = $c;
                $c = next($args);
            } else
                $x = 0;
            foreach ($r as $i) {
                $this->single_get($v[$i], current($rr), $x, $c);
                next($rr);
            }
        }
        return $this;
    }

    protected function queue_post($rq) {
        if (isset($this->error_usleep)) {
            $this->debug(2, 'Throttling traffic.');
            usleep($this->error_usleep);
        }
        $this->debug(3, 'Requesting (POST): ', $rq->rq);
        $h = curl_init($this->esi_uri.$rq->rq);
        $this->curl_opt_post_arr[CURLOPT_POSTFIELDS] = $rq->pd;
        curl_setopt_array($h, $this->curl_opt_post_arr);
        curl_multi_add_handle($this->curl_mh, $h);
        $this->curl_arr[(int) $h] = $rq;
    }

    public function post(&$v, $r, $d, $c = null) {
        if (empty($this->post_arr[$r])) {
            $s = json_encode($d);
            if (isset($s)) {
                $this->post_arr[$r] = (object) [ 'rq' => $r, 'pd' => $s, 'vl' => &$v,
                                                 'cb' => $c, 'rt' => 0 ];
                if (empty($this->idle))
                    $this->queue_post($this->post_arr[$r]);
            }
        }
        return $this;
    }

    public function exec($a = null) {
        if (isset($a['header'])) {
            $this->curl_opt_get_arr[CURLOPT_HTTPHEADER][] = $a['header'];
            $this->curl_opt_post_arr[CURLOPT_HTTPHEADER][] = $a['header'];
        }

        if (isset($this->post_arr) || isset($this->get_arr) && isset($this->idle)) {
            unset($this->idle);
            if (isset($this->post_arr))
                foreach ($this->post_arr as $r => $rr)
                    $this->queue_post($rr);
            if (isset($this->get_arr))
                foreach ($this->get_arr as $r => $rr)
                    $this->queue_get($rr);

            while (!empty($this->curl_arr)) {
                if (curl_multi_exec($this->curl_mh, $xn) === CURLM_OK) {
                    while ($i = curl_multi_info_read($this->curl_mh)) {
                        $h = $i['handle'];
                        if ($i['result'] === CURLE_OK) {
                            $rq = $this->curl_arr[(int) $h];
                            $this->debug(3, 'Received: ', $rq->rq);
                            $s = curl_multi_getcontent($h);
                            $this->debug_trnsfr(strlen($s));
                            $v = json_decode($s, true);
                            if (isset($v)) {
                                if (empty($v['error'])) {
                                    if (empty($rq->pd)) {
                                        $this->update_cache($rq->rq, $rq->ex, $rq->pn, $rq->lm, $s);
                                        if (isset($rq->pn) && empty($rq->pi)) {
                                            if ($rq->pn > 1 && isset($this->paging))
                                                $this->pages_get($rq->vl, $rq->rq, 1, $rq->pn, $rq->ex, $rq->cb);
                                            $rq->vl = &$rq->vl[0];
                                        }
                                    }
                                    $rq->rt = 0;
                                    $rq->vl = $v;
                                    if ($rq->cb)
                                        ($rq->cb)($this, $rq);
                                    goto request_end;
                                } else
                                    $this->debug(0, 'Error response: ', $rq->rq, ' - ', $v['error']);
                            } else
                                $this->debug(0, 'Unexpected response: ', $rq->rq, ' - ', $s);
                        } else
                            $this->debug(0, 'Unexpected cURL result: ', $rq->rq, ' - ', curl_strerror($i['result']));
                        if (++$rq->rt <= $this->retries) {
                            $this->debug(2, 'Retry (#', $rq->rt, '): ', $rq->rq);
                            if (isset($rq->pd))
                                $this->queue_post($rq);
                            else
                                $this->queue_get($rq);
                        } else
                            $this->debug(0, 'No response: ', $rq->rq);
                        request_end:
                        curl_multi_remove_handle($this->curl_mh, $h);
                        curl_close($h);
                        unset($this->curl_arr[(int) $h]);
                    }
                }
                if (curl_multi_select($this->curl_mh, 10.0) < 0) {
                    $e = curl_multi_errno($this->curl_mh);
                    if ($e !== CURLE_OK)
                        $this->debug(0, 'cURL error: ', curl_multi_strerror($e));
                }
            }
            $this->idle = true;
            unset($this->curl_arr, $this->get_arr, $this->post_arr);
        }
        if (isset($a['header'])) {
            array_pop($this->curl_opt_get_arr);
            array_pop($this->curl_opt_post_arr);
        }
        return $this;
    }

    public function auth(&$a, $c = null) {
        if (empty($a['auth_uri']))
            $a['auth_uri'] = $this->oauth_uri.'authorize?response_type=code&'
                           .http_build_query([ 'redirect_uri' => $a['redirect_uri'],
                                               'client_id'    => $a['client_id'],
                                               'scope'        => implode(' ', $a['scopes']) ], PHP_QUERY_RFC3986);
        $t0 = time();
        if (isset($c) && empty($a['code'])) {
            $this->debug(3, 'Requesting authorization.');
            $a['code'] = $c;
            $g = '{"grant_type":"authorization_code","code":"'.$c.'"}';
        } elseif (isset($a['refresh_token'])) {
            if ($a['expires'] > $t0 + 300)
                return true;
            $this->debug(3, 'Refreshing authorization.');
            $g = '{"grant_type":"refresh_token","refresh_token":"'.$a['refresh_token'].'"}';
        } else {
            return false;
        }
        $h = curl_init($this->oauth_uri.'token');
        curl_setopt_array($h, [
            CURLOPT_HTTP_VERSION     => CURL_HTTP_VERSION_1_1,
            CURLOPT_RETURNTRANSFER   => true,
            CURLOPT_HTTPHEADER       => [ 'Accept: application/json',
                                          'Authorization: Basic '.base64_encode($a['client_id'].':'.
                                                                                $a['client_secret']),
                                          'Content-Type: application/json' ],
            CURLHEADER_SEPARATE      => true,
            CURLOPT_POST             => true,
            CURLOPT_POSTFIELDS       => $g,
            CURLOPT_TIMEOUT          => 30,
            CURLOPT_HTTPAUTH         => CURLAUTH_ANYSAFE,
            CURLOPT_SSL_VERIFYPEER   => true,
            CURLOPT_SSL_VERIFYHOST   => 2,
            CURLOPT_FORBID_REUSE     => false,
            CURLOPT_TCP_NODELAY      => true
        ]);
        curl_setopt($h, CURLOPT_TCP_FASTOPEN, true);
        curl_setopt($h, CURLOPT_SSL_FALSESTART, true);
        $tk = json_decode(curl_exec($h), true);
        curl_close($h);
        if (isset($tk['access_token'])) {
            $this->debug(3, 'Authorization tokens received.');
            $a['header']        = 'Authorization: Bearer '.$tk['access_token'];
            $a['refresh_token'] = $tk['refresh_token'];
            $a['expires']       = $t0 + $tk['expires_in'];
            if (isset($a['char_id']))
                return true;
            $this->debug(3, 'Requesting character identification.');
            $h = curl_init($this->oauth_uri.'verify');
            curl_setopt_array($h, [ CURLOPT_HTTP_VERSION     => CURL_HTTP_VERSION_1_1,
                                    CURLOPT_RETURNTRANSFER   => true,
                                    CURLOPT_HTTPHEADER       => [ 'Accept: application/json', $a['header'] ],
                                    CURLHEADER_SEPARATE      => true,
                                    CURLOPT_TIMEOUT          => 30,
                                    CURLOPT_HTTPAUTH         => CURLAUTH_ANYSAFE,
                                    CURLOPT_SSL_VERIFYPEER   => true,
                                    CURLOPT_SSL_VERIFYHOST   => 2,
                                    CURLOPT_FRESH_CONNECT    => false,
                                    CURLOPT_TCP_NODELAY      => true
            ]);
            curl_setopt($h, CURLOPT_TCP_FASTOPEN, true);
            curl_setopt($h, CURLOPT_SSL_FALSESTART, true);
            $ci = json_decode(curl_exec($h), true);
            curl_close($h);
            if (is_array($ci)) {
                $this->debug(3, 'Character identification received.');
                $a['char_id']   = $ci['CharacterID'];
                $a['char_name'] = $ci['CharacterName'];
                $a['char_scopes'] = explode(' ', $ci['Scopes']);
                return true;
            }
            $this->debug(0, 'Authorization error: no character identification received.');
        } else
            $this->debug(0, 'Authorization error: no tokens received.');

        unset($a['code'], $a['header'], $a['refresh_token'], $a['expires'],
              $a['char_id'], $a['char_name'], $a['char_scopes']);
        return false;
    }
}
?>

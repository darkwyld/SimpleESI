<?php /* SimpleESI.php, © 2018, GPLv3, please see LICENSE file for details. */
trait nodebug {
    protected function debug_init() {}
    protected function debug_trnsfr($bt) {}
    protected function debug($lv, ...$msg) {}
}
trait debug {
    public $debug_level = 0, $debug_html, $debug_file;
    protected $debug_t0 = 0, $debug_trnsfr = 0;
    protected function debug_init() { $this->debug_t0 = microtime(true); }
    protected function debug_trnsfr($bt) { $this->debug_trnsfr += $bt; return $bt; }
    protected function debug($lv, ...$msg) {
        if ($this->debug_level >= $lv) {
            $str = rtrim(implode('', $msg));
            $dt = microtime(true) - $this->debug_t0;
            if (isset($this->debug_html)) {
                $cl = [ -1 => '#ff00ff', '#ff003f', '#00ff3f', '#ff00bf', '#007fff', '#00bfff', '#ffbf00' ];
                printf('<h6 class="SimpleESI" style="color:%s;margin:0">['.__CLASS__.'] %06.3fs %db (%d) %s</h6>'
                       .PHP_EOL, $cl[$lv] ?? 'black', $dt, $this->debug_trnsfr, $lv, $str);
            } elseif (isset($this->debug_file))
                file_put_contents($this->debug_file,
                                  sprintf('['.__CLASS__.'] %s %07.3fs %db (%d) %s'.PHP_EOL, date('r'),
                                          $dt, $this->debug_trnsfr, $lv, $str, FILE_APPEND | LOCK_EX));
            else
                printf('['.__CLASS__.'] %07.3fs %db (%d) %s'.PHP_EOL, $dt, $this->debug_trnsfr, $lv, $str);
        }
        if ($lv < 0)
            throw new \Exception('['.__CLASS__.'] Fatal error occured. Please see debug messages.');
    }
}

trait nodb {
    protected function db_init($fn) {}
    protected function query_cache($rq, $ci, $ex) {}
    protected function update_cache($rq, $ci, $ex, $pn, $lm, $vl) {}
    public function meta($ky, $vl = null) {}
}
trait dirdb {
    public $caching = true;
    protected $db_cache_dir, $db_meta_dir;

    protected function db_init($fn) {
        $fn .= '.dir/';
        $this->db_cache_dir = $fn.'cache/';
        $this->db_meta_dir = $fn.'meta/';
        if (!file_exists($this->db_cache_dir) && !mkdir($this->db_cache_dir, 0755, true))
            $this->debug(-1, 'Could not create cache directory');
        if (!file_exists($this->db_meta_dir) && !mkdir($this->db_meta_dir, 0755, true))
            $this->debug(-1, 'Could not create meta directory');
    }
    protected function query_cache($rq, $ci, $ex) {
        if ($this->caching) {
            $url = parse_url($rq);
            $dir = rtrim($this->db_cache_dir.$url['path'], '/');
            $fn = $dir.','.rawurlencode($url['query'] ?? '?').','.$ci;
            $hn = @fopen($fn, 'r');
            if ($hn && fstat($hn)[9] >= $ex) {
                flock($hn, LOCK_SH);
                $str = stream_get_contents($hn);
                flock($hn, LOCK_UN);
                fclose($hn);
                $cd = json_decode($str, true);
                if (isset($cd) && count($cd) === 4) {
                    $this->debug(4, 'Cached: ', $rq);
                    return $cd;
                }
            }
        }
    }
    protected function update_cache($rq, $ci, $ex, $pn, $lm, $vl) {
        if ($this->caching) {
            $url = parse_url($rq);
            $dir = rtrim($this->db_cache_dir.$url['path'], '/');
            $fn = $dir.','.rawurlencode($url['query'] ?? '?').','.$ci;
            $dir = dirname($dir);
            if (!file_exists($dir) && !mkdir($dir, 0755, true))
                $this->debug(-1, 'Could not create cache subdirectory');
            $hn = fopen($fn, 'c');
            if ($hn) {
                $str = json_encode([ $ex, $pn, $lm, $vl ]);
                flock($hn, LOCK_EX);
                fwrite($hn, $str);
                ftruncate($hn, strlen($str));
                touch($fn, $ex);
                flock($hn, LOCK_UN);
                fclose($hn);
            } else
                $this->debug(-1, 'Could not write/update cache file');
        }
    }
    public function meta($ky, $vl = null) {
        $fn = $this->db_meta_dir.rtrim($ky, '/');
        if (isset($vl)) {
            $dir = dirname($fn);
            if (!file_exists($dir) && !mkdir($dir, 0755, true))
                $this->debug(-1, 'Could not create meta subdirectory');
            $hn = fopen($fn, 'c');
            if ($hn) {
                $str = serialize($vl);
                flock($hn, LOCK_EX);
                fwrite($hn, $str);
                ftruncate($hn, strlen($str));
                flock($hn, LOCK_UN);
                fclose($hn);
            } else
                $this->debug(-1, 'Could not write/update meta file');
        } else {
            $hn = @fopen($fn, 'r');
            if ($hn) {
                flock($hn, LOCK_SH);
                $str = stream_get_contents($hn);
                flock($hn, LOCK_UN);
                fclose($hn);
                return unserialize($str);
            }
        }
    }
}
trait sqlite3db {
    public $caching = true;
    protected $db, $db_rq_ref, $db_ci_ref, $db_ex_ref, $db_pn_ref, $db_lm_ref, $db_vl_ref;
    protected $cache_qry_stmt, $cache_upd_stmt, $meta_qry_stmt, $meta_upd_stmt;

    protected function db_init($fn) {
        $this->db = new \SQLite3($fn.'.sq3', SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
        $this->db->exec(
            'PRAGMA synchronous = OFF; PRAGMA journal_mode = OFF;'.
            'CREATE TABLE IF NOT EXISTS cache(rq TEXT NOT NULL PRIMARY KEY, ci INT, ex INT, pn INT, lm INT, vl TEXT);'.
            'CREATE TABLE IF NOT EXISTS  meta(rq TEXT NOT NULL PRIMARY KEY, vl BLOB);');
        $this->cache_qry_stmt = $this->db->prepare('SELECT ex, pn, lm, vl FROM cache WHERE rq = ? AND ci = ? AND ex >= ?');
        $this->cache_qry_stmt->bindParam(1, $this->db_rq_ref, SQLITE3_TEXT);
        $this->cache_qry_stmt->bindParam(2, $this->db_ci_ref, SQLITE3_INTEGER);
        $this->cache_qry_stmt->bindParam(3, $this->db_ex_ref, SQLITE3_INTEGER);
        $this->cache_upd_stmt = $this->db->prepare('REPLACE INTO cache(rq, ci, ex, pn, lm, vl) VALUES(?, ?, ?, ?, ?, ?)');
        $this->cache_upd_stmt->bindParam(1, $this->db_rq_ref, SQLITE3_TEXT);
        $this->cache_upd_stmt->bindParam(2, $this->db_ci_ref, SQLITE3_INTEGER);
        $this->cache_upd_stmt->bindParam(3, $this->db_ex_ref, SQLITE3_INTEGER);
        $this->cache_upd_stmt->bindParam(4, $this->db_pn_ref, SQLITE3_INTEGER);
        $this->cache_upd_stmt->bindParam(5, $this->db_lm_ref, SQLITE3_INTEGER);
        $this->cache_upd_stmt->bindParam(6, $this->db_vl_ref, SQLITE3_TEXT);
        $this->meta_qry_stmt = $this->db->prepare('SELECT vl FROM meta WHERE rq = ?');
        $this->meta_qry_stmt->bindParam(1, $this->db_rq_ref, SQLITE3_TEXT);
        $this->meta_upd_stmt = $this->db->prepare('REPLACE INTO meta(rq, vl) VALUES(?, ?)');
        $this->meta_upd_stmt->bindParam(1, $this->db_rq_ref, SQLITE3_TEXT);
        $this->meta_upd_stmt->bindParam(2, $this->db_vl_ref, SQLITE3_BLOB);
    }
    protected function query_cache($rq, $ci, $ex) {
        if ($this->caching) {
            $this->db_rq_ref = $rq;
            $this->db_ci_ref = $ci;
            $this->db_ex_ref = $ex;
            $cd = $this->cache_qry_stmt->execute()->fetchArray(SQLITE3_NUM);
            if ($cd !== false && count($cd) === 4) {
                $this->debug(4, 'Cached: ', $rq);
                return $cd;
            }
        }
    }
    protected function update_cache($rq, $ci, $ex, $pn, $lm, $vl) {
        if ($this->caching) {
            $this->db_rq_ref = $rq;
            $this->db_ci_ref = $ci;
            $this->db_ex_ref = $ex;
            $this->db_pn_ref = $pn;
            $this->db_lm_ref = $lm;
            $this->db_vl_ref = $vl;
            $this->cache_upd_stmt->execute();
        }
    }
    public function meta($ky, $vl = null) {
        $this->db_rq_ref = $ky;
        if (isset($vl)) {
            $this->db_vl_ref = serialize($vl);
            $this->meta_upd_stmt->execute();
        } else {
            $md = $this->meta_qry_stmt->execute()->fetchArray(SQLITE3_NUM);
            if (is_array($md) && count($md) === 1)
                return unserialize($md[0]);
        }
    }
}

class SimpleESI {
    use debug, sqlite3db;

    public $esi_uri = 'https://esi.tech.ccp.is/latest/';
    public $oauth_uri = 'https://login.eveonline.com/oauth/';
    public $marker = '~';
    public $paging = true;
    public $retries = 3;
    public $error_throttle = 80, $error_exit = 20;

    protected $get_arr, $post_arr, $idle = true;
    protected $curl_mh, $curl_opt_get_arr, $curl_opt_post_arr, $curl_arr;
    protected $error_window = 60, $error_usleep;

    public function __construct($fn = 'esi', $us = null) {
        $this->debug_init();
        $this->db_init($fn);
        $this->curl_mh = curl_multi_init();
        curl_multi_setopt($this->curl_mh, CURLMOPT_PIPELINING, CURLPIPE_HTTP1 | CURLPIPE_MULTIPLEX);
        $this->curl_opt_get_arr = [ CURLOPT_RETURNTRANSFER => true,
                                    CURLOPT_HTTPHEADER     => [ null /*reserved*/, 'Accept: application/json' ],
                                    CURLOPT_HEADERFUNCTION => __CLASS__.'::process_header',
                                    CURLOPT_PIPEWAIT       => true,
                                    CURLOPT_BUFFERSIZE     => 256 << 10,
                                    CURLOPT_TIMEOUT        => 300,
                                    CURLOPT_HTTPAUTH       => CURLAUTH_ANY,
                                    CURLOPT_TCP_NODELAY    => true ];
        if (isset($us))
            $this->curl_opt_get_arr[CURLOPT_HTTPHEADER][] = 'X-User-Agent: '.$us;

        $hn = curl_init($this->esi_uri);
        foreach ([ CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_2_0,
                   CURLOPT_TCP_FASTOPEN   => true,
                   CURLOPT_SSL_FALSESTART => true ] as $op => $vl)
            if (curl_setopt($hn, $op, $vl) === true)
                $this->curl_opt_get_arr[$op] = $vl;
            else
                $this->debug(4, 'cURL option '.$op.' is not supported.');
        curl_close($hn);
        $this->curl_opt_post_arr = $this->curl_opt_get_arr;
        $this->curl_opt_post_arr[CURLOPT_POST]         = true;
        $this->curl_opt_post_arr[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
    }

    public function __destruct() {
        curl_multi_close($this->curl_mh);
        $this->debug(1, 'Elapsed.');
    }

    protected function process_header($hn, $str) {
        $this->debug(5, 'Header: ', $str);
        $hd = explode(': ', $str, 2);
        switch (strtolower($hd[0])) {
        case 'last-modified'           : $this->curl_arr[(int) $hn]->lm = strtotime($hd[1]); break;
        case 'expires'                 : $this->curl_arr[(int) $hn]->ex = strtotime($hd[1]); break;
        case 'x-pages'                 : $this->curl_arr[(int) $hn]->pn = (int) $hd[1];      break;
        case 'x-esi-error-limit-reset' : $this->error_window            = (int) $hd[1];      break;
        case 'x-esi-error-limit-remain':
            $lm = (int) $hd[1];
            if ($lm < 100)
                $this->debug(2, 'Error limit: ', $lm, ', window: ', $this->error_window, 's');
            if ($lm <= $this->error_throttle)
                $this->error_usleep = (int) (1.0e6 * max(1, $this->error_window) / pow(max(1, $lm), 1.5));
            else
                unset($this->error_usleep);
            if ($lm > $this->error_exit)
                break;
            // Fall-through.
        case 'x-esi-error-limited':
            $this->debug(-1, 'Error limit reached: ', $str);
        }
        $bt = strlen($str);
        $this->debug_trnsfr($bt);
        return $bt;
    }

    protected function queue_get($rq) {
        $cd = $this->query_cache($rq->rq, $rq->ci, $rq->ex);
        if (isset($cd)) {
            list($rq->ex, $rq->pn, $rq->lm) = $cd;
            if (isset($rq->pn) && empty($rq->pi)) {
                if ($rq->pn > 1 && isset($this->paging))
                    $this->pages_get($rq->vl, $rq->rq, 2, $rq->pn, $rq->ex, $rq->ci, $rq->ah, $rq->cb);
                $rq->vl = &$rq->vl[0];
            }
            $rq->vl = json_decode($cd[3], true);
            if ($rq->cb)
                ($rq->cb)($this, $rq);
            return;
        }
        if (isset($this->error_usleep)) {
            $this->debug(2, 'Throttling traffic.');
            usleep($this->error_usleep);
        }
        $this->debug(3, 'Requesting: ', $rq->rq);
        $hn = curl_init($this->esi_uri.$rq->rq);
        $this->curl_opt_get_arr[CURLOPT_HTTPHEADER][0] = $rq->ah;
        curl_setopt_array($hn, $this->curl_opt_get_arr);
        curl_multi_add_handle($this->curl_mh, $hn);
        $this->curl_arr[(int) $hn] = $rq;
    }

    public function single_get(&$vl, $rq, $ex = 0, $ci = 0, $ah = null, $cb = null) {
        if (empty($this->get_arr[$rq]) || $this->get_arr[$rq]->ah !== $ah) {
            $this->get_arr[$rq] = (object) [ 'rq' =>  $rq, 'ci' =>  $ci, 'ex' => $ex, 'lm' =>   0, 'vl' => &$vl,
                                             'pn' => null, 'pi' => null, 'ah' => $ah, 'cb' => $cb, 'rt' => 0 ];
            if (empty($this->idle))
                $this->queue_get($this->get_arr[$rq]);
        }
    }

    public function pages_get(&$vl, $rq, $p0, $p1, $ex, $ci = 0, $ah = null, $cb = null) {
        $url = parse_url($rq);
        $rq = $url['path'].'?';
        if (isset($url['query']))
            parse_str($url['query'], $qr);
        if (isset($qr['page']))
            return;
        $this->debug(3, 'Requesting pages ', $p0, ' to ', $p1, ' of: ', $rq);
        for ($i = $p0; $i <= $p1; ++$i) {
            $qr['page'] = $i;
            $rr = $rq.http_build_query($qr);
            if (isset($this->get_arr[$rr]))
                continue;
            $this->get_arr[$rr] = (object) [ 'rq' => $rr, 'ci' => $ci, 'ex' => $ex, 'lm' =>   0, 'vl' => &$vl[$i - 1],
                                             'pn' => $p1, 'pi' =>  $i, 'ah' => $ah, 'cb' => $cb, 'rt' => 0 ];
            if (empty($this->idle))
                $this->queue_get($this->get_arr[$rr]);
        }
    }

    public function get(&$vl, ...$args) {
        $rq = current($args);
        if (is_string($rq)) {
            $a = next($args);
            if (is_array($a) && empty($a['client_secret'])) {
                if ($rq[-1] === '/')
                    $rq .= '?';
                elseif ($rq[-1] !== '&')
                    $rq .= '&';
                $rq .= http_build_query($a, '', '&', PHP_QUERY_RFC3986);
                $a = next($args);
            }
            if (is_int($a)) {
                $ex = time() - $a;
                $a = next($args);
            } else
                $ex = time();
            if (is_array($a)) {
                $ci = $a['cid'];
                $ah = $a['header'];
                $a = next($args);
            } else {
                $ci = 0;
                $ah = null;
            }
            $this->single_get($vl, $rq, $ex, $ci, $ah, $a);
        } else {
            $rp = next($args);
            if (is_string($rp)) {
                $a = next($args);
                if (is_array($a) && empty($a['client_secret'])) {
                    if ($rp[-1] === '/')
                        $rp .= '?';
                    elseif ($rp[-1] !== '&')
                        $rp .= '&';
                    $rp .= http_build_query($a, '', '&', PHP_QUERY_RFC3986);
                    list($s1, $s2) = explode($this->marker, $rp, 2);
                    $rp = [];
                    foreach ($rq as $r)
                        $rp[] = $s1.rawurlencode($r).$s2;
                    $a = next($args);
                } else {
                    list($s1, $s2) = explode($this->marker, $rp, 2);
                    $rp = explode($this->marker, $s1.implode($s2.$this->marker.$s1, $rq).$s2);
                }
            } else {
                $a = next($args);
                if (is_array($a) && empty($a['client_secret'])) {
                    $qr = '?'.http_build_query($a, '', '&', PHP_QUERY_RFC3986);
                    foreach ($rp as &$r)
                        $r .= $qr;
                    $a = next($args);
                }
            }
            if (is_int($a)) {
                $ex = time() - $a;
                $a = next($args);
            } else
                $ex = time();
            if (is_array($a)) {
                $ci = $a['cid'];
                $ah = $a['header'];
                $a = next($args);
            } else {
                $ci = 0;
                $ah = null;
            }
            foreach ($rq as $r) {
                $this->single_get($vl[$r], current($rp), $ex, $ci, $ah, $a);
                next($rp);
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
        $hn = curl_init($this->esi_uri.$rq->rq);
        $this->curl_opt_post_arr[CURLOPT_POSTFIELDS] = $rq->pd;
        $this->curl_opt_post_arr[CURLOPT_HTTPHEADER][0] = $rq->ah;
        curl_setopt_array($hn, $this->curl_opt_post_arr);
        curl_multi_add_handle($this->curl_mh, $hn);
        $this->curl_arr[(int) $hn] = $rq;
    }

    public function post(&$vl, $rq, $pd, $at = null, $cb = null) {
        if (empty($this->post_arr[$rq]) || $this->post_arr[$rq]->ah !== $at['header']) {
            $str = json_encode($pd);
            if (isset($str)) {
                $this->post_arr[$rq] = (object) [ 'rq' => $rq, 'pd' => $str, 'vl' => &$vl,
                                                  'ah' => $at['header'], 'cb' => $cb, 'rt' => 0 ];
                if (empty($this->idle))
                    $this->queue_post($this->post_arr[$rq]);
            }
        }
        return $this;
    }

    public function exec() {
        if (isset($this->post_arr) || isset($this->get_arr) && isset($this->idle)) {
            unset($this->idle);
            if (isset($this->post_arr))
                foreach ($this->post_arr as $rq)
                    $this->queue_post($rq);
            if (isset($this->get_arr))
                foreach ($this->get_arr as $rq)
                    $this->queue_get($rq);

            while (!empty($this->curl_arr)) {
                if (curl_multi_exec($this->curl_mh, $xn) === CURLM_OK) {
                    while ($in = curl_multi_info_read($this->curl_mh)) {
                        $hn = $in['handle'];
                        if ($in['result'] === CURLE_OK) {
                            $rq = $this->curl_arr[(int) $hn];
                            $this->debug(3, 'Received: ', $rq->rq);
                            $str = curl_multi_getcontent($hn);
                            $this->debug_trnsfr(strlen($str));
                            $vl = json_decode($str, true);
                            if (isset($vl)) {
                                if (empty($vl['error'])) {
                                    if (empty($rq->pd)) {
                                        $this->update_cache($rq->rq, $rq->ci, $rq->ex, $rq->pn, $rq->lm, $str);
                                        if (isset($rq->pn) && empty($rq->pi)) {
                                            if ($rq->pn > 1 && isset($this->paging))
                                                $this->pages_get($rq->vl, $rq->rq, 2, $rq->pn, $rq->ex, $rq->ci, $rq->ah, $rq->cb);
                                            $rq->vl = &$rq->vl[0];
                                        }
                                    }
                                    $rq->rt = 0;
                                    $rq->vl = $vl;
                                    if ($rq->cb)
                                        ($rq->cb)($this, $rq);
                                    goto request_end;
                                } else
                                    $this->debug(0, 'Error response: ', $rq->rq, ' - ', $vl['error']);
                            } else
                                $this->debug(0, 'Unexpected response: ', $rq->rq, ' - ', $str);
                        } else
                            $this->debug(0, 'Unexpected cURL result: ', $rq->rq, ' - ', curl_strerror($in['result']));
                        if (++$rq->rt <= $this->retries) {
                            $this->debug(2, 'Retry (#', $rq->rt, '): ', $rq->rq);
                            if (isset($rq->pd))
                                $this->queue_post($rq);
                            else
                                $this->queue_get($rq);
                        } else
                            $this->debug(0, 'No response: ', $rq->rq);
                        request_end:
                        curl_multi_remove_handle($this->curl_mh, $hn);
                        curl_close($hn);
                        unset($this->curl_arr[(int) $hn]);
                    }
                }
                if (curl_multi_select($this->curl_mh, 10.0) < 0) {
                    $er = curl_multi_errno($this->curl_mh);
                    if ($er !== CURLE_OK)
                        $this->debug(0, 'cURL error: ', curl_multi_strerror($er));
                }
            }
            $this->idle = true;
            unset($this->curl_arr, $this->get_arr, $this->post_arr);
        }
        return $this;
    }

    public function auth(&$at, $cd = null) {
        if (empty($at['auth_uri']))
            $at['auth_uri'] = $this->oauth_uri.'authorize?response_type=code&'
                            .http_build_query([ 'redirect_uri' => $at['redirect_uri'],
                                                'client_id'    => $at['client_id'],
                                                'scope'        => implode(' ', $at['scopes']) ], PHP_QUERY_RFC3986);
        $t0 = time();
        if (isset($cd) && empty($at['code'])) {
            $this->debug(3, 'Requesting authorization.');
            $at['code'] = $cd;
            $gr = '{"grant_type":"authorization_code","code":"'.$cd.'"}';
        } elseif (isset($at['refresh'])) {
            if ($at['expires'] > $t0 + 300)
                return true;
            $this->debug(3, 'Refreshing authorization.');
            $gr = '{"grant_type":"refresh_token","refresh_token":"'.$at['refresh'].'"}';
        } else {
            return false;
        }
        $hn = curl_init($this->oauth_uri.'token');
        curl_setopt_array($hn, [
            CURLOPT_HTTP_VERSION     => CURL_HTTP_VERSION_1_1,
            CURLOPT_RETURNTRANSFER   => true,
            CURLOPT_HTTPHEADER       => [ 'Accept: application/json',
                                          'Authorization: Basic '.base64_encode($at['client_id'].':'.
                                                                                $at['client_secret']),
                                          'Content-Type: application/json' ],
            CURLHEADER_SEPARATE      => true,
            CURLOPT_POST             => true,
            CURLOPT_POSTFIELDS       => $gr,
            CURLOPT_TIMEOUT          => 30,
            CURLOPT_HTTPAUTH         => CURLAUTH_ANYSAFE,
            CURLOPT_SSL_VERIFYPEER   => true,
            CURLOPT_SSL_VERIFYHOST   => 2,
            CURLOPT_FORBID_REUSE     => false,
            CURLOPT_TCP_NODELAY      => true
        ]);
        curl_setopt($hn, CURLOPT_TCP_FASTOPEN, true);
        curl_setopt($hn, CURLOPT_SSL_FALSESTART, true);
        $tk = json_decode(curl_exec($hn), true);
        curl_close($hn);
        if (isset($tk['access_token'])) {
            $this->debug(3, 'Authorization tokens received.');
            $at['header']  = 'Authorization: Bearer '.$tk['access_token'];
            $at['refresh'] = $tk['refresh_token'];
            $at['expires'] = $t0 + $tk['expires_in'];
            if (isset($at['char_id']))
                return true;
            $this->debug(3, 'Requesting character identification.');
            $hn = curl_init($this->oauth_uri.'verify');
            curl_setopt_array($hn, [ CURLOPT_HTTP_VERSION     => CURL_HTTP_VERSION_1_1,
                                     CURLOPT_RETURNTRANSFER   => true,
                                     CURLOPT_HTTPHEADER       => [ 'Accept: application/json', $at['header'] ],
                                     CURLHEADER_SEPARATE      => true,
                                     CURLOPT_TIMEOUT          => 30,
                                     CURLOPT_HTTPAUTH         => CURLAUTH_ANYSAFE,
                                     CURLOPT_SSL_VERIFYPEER   => true,
                                     CURLOPT_SSL_VERIFYHOST   => 2,
                                     CURLOPT_FRESH_CONNECT    => false,
                                     CURLOPT_TCP_NODELAY      => true
            ]);
            curl_setopt($hn, CURLOPT_TCP_FASTOPEN, true);
            curl_setopt($hn, CURLOPT_SSL_FALSESTART, true);
            $ch = json_decode(curl_exec($hn), true);
            curl_close($hn);
            if (is_array($ch)) {
                $this->debug(3, 'Character identification received.');
                $at['cid']     = $ch['CharacterID'];
                $at['name']    = $ch['CharacterName'];
                $at['cscopes'] = explode(' ', $ch['Scopes']);
                return true;
            }
            $this->debug(0, 'Authorization error: no character identification received.');
        } else
            $this->debug(0, 'Authorization error: no tokens received.');

        unset($at['code'], $at['header'], $at['refresh'], $at['expires'],
              $at['cid'], $at['name'], $at['cscopes']);
        return false;
    }
}
?>

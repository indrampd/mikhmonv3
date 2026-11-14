<?php
/*
 * RouterOS v7 REST API Client for Mikhmon
 * Drop-in adapter translating legacy RouterOS API commands to RouterOS v7 REST HTTP calls.
 */

class RouterosRestAPI
{
    public $debug      = false;
    public $connected  = false;
    public $port       = 443;
    public $ssl        = true;
    public $timeout    = 5;
    public $attempts   = 3;
    public $delay      = 1;
    public $error_no   = 0;
    public $error_str  = '';

    private $base_url  = '';
    private $auth      = '';
    private $ip        = '';
    private $username  = '';
    private $password  = '';

    public function isIterable($var)
    {
        return $var !== null && (is_array($var) || $var instanceof Traversable);
    }

    public function debug($text)
    {
        if ($this->debug) {
            echo "[RouterosRestAPI] " . $text . "\n";
        }
    }

    public function connect($ip, $login, $password)
    {
        $this->ip = $ip;
        $this->username = $login;
        $this->password = $password;

        $clean_ip = trim($ip);
        $scheme = $this->ssl ? 'https' : 'http';
        $port = $this->port;

        if (preg_match('#^https?://#i', $clean_ip)) {
            $parts = parse_url($clean_ip);
            $scheme = $parts['scheme'] ?? $scheme;
            $clean_ip = $parts['host'] ?? $clean_ip;
            if (!empty($parts['port'])) {
                $port = (int)$parts['port'];
            }
        } elseif (strpos($clean_ip, ':') !== false) {
            $parts = explode(':', $clean_ip);
            $clean_ip = $parts[0];
            $port = (int)$parts[1];
            if ($port === 443 || $port === 8443) {
                $scheme = 'https';
            } elseif ($port === 80 || $port === 8080) {
                $scheme = 'http';
            }
        }

        $this->port = $port;
        $this->ssl = ($scheme === 'https');
        $this->base_url = "{$scheme}://{$clean_ip}:{$port}/rest";
        $this->auth = base64_encode("{$login}:{$password}");

        // Test connection
        for ($attempt = 1; $attempt <= $this->attempts; $attempt++) {
            $this->debug("Connection attempt #{$attempt} to {$this->base_url}...");
            $res = $this->request('GET', '/system/resource');
            if ($res !== false) {
                $this->connected = true;
                $this->debug("Connected via REST API successfully.");
                return true;
            }
            if ($attempt < $this->attempts) {
                sleep($this->delay);
            }
        }

        $this->connected = false;
        $this->debug("Connection failed: {$this->error_str} (code: {$this->error_no})");
        return false;
    }

    public function disconnect()
    {
        $this->connected = false;
    }

    public function comm($com, $arr = array())
    {
        $com = '/' . trim($com, '/');
        $parts = explode('/', trim($com, '/'));
        $action = end($parts);

        if ($action === 'print') {
            array_pop($parts);
            $path = '/' . implode('/', $parts);
            return $this->handlePrint($path, $arr);
        } elseif ($action === 'add') {
            array_pop($parts);
            $path = '/' . implode('/', $parts);
            return $this->handleAdd($path, $arr);
        } elseif ($action === 'set') {
            array_pop($parts);
            $path = '/' . implode('/', $parts);
            return $this->handleSet($path, $arr);
        } elseif ($action === 'remove') {
            array_pop($parts);
            $path = '/' . implode('/', $parts);
            return $this->handleRemove($path, $arr);
        } elseif ($action === 'enable' || $action === 'disable') {
            array_pop($parts);
            $path = '/' . implode('/', $parts);
            return $this->handleEnableDisable($path, $arr, ($action === 'disable'));
        } else {
            return $this->handleAction($com, $arr);
        }
    }

    private function handlePrint($path, $arr)
    {
        $is_count_only = false;
        $query_params = array();

        if ($this->isIterable($arr)) {
            foreach ($arr as $k => $v) {
                if ($k === 'count-only') {
                    $is_count_only = true;
                    continue;
                }
                // Handle filters like "?name" or "?profile" or "?.id"
                if ($k[0] === '?' || $k[0] === '~') {
                    $key_clean = substr($k, 1);
                    $query_params[$key_clean] = $v;
                } elseif ($k === '.proplist') {
                    $query_params['.proplist'] = $v;
                } else {
                    $query_params[$k] = $v;
                }
            }
        }

        if ($is_count_only && !isset($query_params['.proplist'])) {
            $query_params['.proplist'] = '.id';
        }

        $res = $this->request('GET', $path, null, $query_params);
        if ($res === false) {
            return $is_count_only ? "0" : array();
        }

        if ($is_count_only) {
            if (is_array($res)) {
                // If it's a list of items
                if (isset($res[0]) || empty($res)) {
                    return (string)count($res);
                }
                return "1";
            }
            return "0";
        }

        if (!is_array($res)) {
            return array();
        }

        // Singleton endpoints like /system/identity or /system/resource return a single dict {}
        // Legacy API wrapped these in array(array(...))
        if (!empty($res) && !isset($res[0])) {
            return array($res);
        }

        return $res;
    }

    private function handleAdd($path, $arr)
    {
        $payload = array();
        if ($this->isIterable($arr)) {
            foreach ($arr as $k => $v) {
                $payload[$k] = (string)$v;
            }
        }

        $res = $this->request('PUT', $path, $payload);
        if ($res === false) {
            return array('!trap' => array(array('message' => $this->error_str)));
        }

        if (is_array($res) && isset($res['.id'])) {
            return array('ret' => $res['.id']);
        }
        return $res;
    }

    private function handleSet($path, $arr)
    {
        $id = '';
        $payload = array();

        if ($this->isIterable($arr)) {
            foreach ($arr as $k => $v) {
                if ($k === '.id' || $k === '?.id' || $k === 'numbers') {
                    $id = $v;
                } else {
                    $payload[$k] = (string)$v;
                }
            }
        }

        $target_path = $path . ($id !== '' ? '/' . urlencode($id) : '');
        $res = $this->request('PATCH', $target_path, $payload);
        return ($res !== false) ? array() : array('!trap' => array(array('message' => $this->error_str)));
    }

    private function handleRemove($path, $arr)
    {
        $id = '';
        if ($this->isIterable($arr)) {
            foreach ($arr as $k => $v) {
                if ($k === '.id' || $k === '?.id' || $k === 'numbers') {
                    $id = $v;
                    break;
                }
            }
        }

        $target_path = $path . ($id !== '' ? '/' . urlencode($id) : '');
        $res = $this->request('DELETE', $target_path);
        return ($res !== false) ? array() : array('!trap' => array(array('message' => $this->error_str)));
    }

    private function handleEnableDisable($path, $arr, $disabled)
    {
        $id = '';
        if ($this->isIterable($arr)) {
            foreach ($arr as $k => $v) {
                if ($k === '.id' || $k === '?.id' || $k === 'numbers') {
                    $id = $v;
                    break;
                }
            }
        }

        $target_path = $path . ($id !== '' ? '/' . urlencode($id) : '');
        $payload = array('disabled' => $disabled ? 'true' : 'false');
        $res = $this->request('PATCH', $target_path, $payload);
        return ($res !== false) ? array() : array('!trap' => array(array('message' => $this->error_str)));
    }

    private function handleAction($path, $arr)
    {
        $payload = array();
        if ($this->isIterable($arr)) {
            foreach ($arr as $k => $v) {
                $payload[$k] = (string)$v;
            }
        }

        $res = $this->request('POST', $path, $payload);
        if ($res === false) {
            return array('!trap' => array(array('message' => $this->error_str)));
        }
        if (is_array($res) && !empty($res) && !isset($res[0])) {
            return array($res);
        }
        return is_array($res) ? $res : array();
    }

    private function request($method, $endpoint, $body = null, $query = null)
    {
        $url = $this->base_url . $endpoint;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);

        // MikroTik routers frequently use self-signed TLS certificates
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $headers = array(
            'Authorization: Basic ' . $this->auth,
            'Accept: application/json'
        );

        if ($body !== null && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $json_payload = json_encode($body);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($json_payload);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->error_no = curl_errno($ch);
        $this->error_str = curl_error($ch);
        curl_close($ch);

        $this->debug("{$method} {$url} -> HTTP {$http_code}");

        if ($this->error_no !== 0) {
            return false;
        }

        if ($http_code >= 200 && $http_code < 300) {
            if (empty($response) || $response === '') {
                return array();
            }
            $json = json_decode($response, true);
            return ($json !== null) ? $json : array();
        }

        $this->error_str = "HTTP {$http_code}: " . substr($response, 0, 200);
        return false;
    }
}

<?php
/*****************************
 *
 * RouterOS PHP API class v1.6
 * Author: Denis Basta
 * Contributors:
 *    Nick Barnes
 *    Ben Menking (ben [at] infotechsc [dot] com)
 *    Jeremy Jefferson (http://jeremyj.com)
 *    Cristian Deluxe (djcristiandeluxe [at] gmail [dot] com)
 *    Mikhail Moskalev (mmv.rus [at] gmail [dot] com)
 *
 * http://www.mikrotik.com
 * http://wiki.mikrotik.com/wiki/API_PHP_class
 *
 ******************************/

require_once __DIR__ . '/routeros_rest.class.php';

class RouterosAPI
{
    var $debug     = false; //  Show debug information
    var $connected = false; //  Connection state
    var $port      = 8728;  //  Port to connect to (default 8729 for ssl)
    var $ssl       = false; //  Connect using SSL (must enable api-ssl in IP/Services)
    var $timeout   = 3;     //  Connection attempt timeout and data read timeout
    var $attempts  = 5;     //  Connection attempt count
    var $delay     = 3;     //  Delay between connection attempts in seconds

    var $socket;            //  Variable for storing socket resource
    var $error_no;          //  Variable for storing connection error number, if any
    var $error_str;         //  Variable for storing connection error text, if any

    public $rest_client = null;
    public $use_rest    = null; // null = auto-detect, true = force REST, false = legacy socket

    /* Check, can be var used in foreach  */
    public function isIterable($var)
    {
        return $var !== null
                && (is_array($var)
                || $var instanceof Traversable
                || $var instanceof Iterator
                || $var instanceof IteratorAggregate
                );
    }

    /**
     * Print text for debug purposes
     *
     * @param string      $text       Text to print
     *
     * @return void
     */
    public function debug($text)
    {
        if ($this->debug) {
            echo $text . "\n";
        }
    }


    /**
     *
     *
     * @param string        $length
     *
     * @return void
     */
    public function encodeLength($length)
    {
        if ($length < 0x80) {
            $length = chr($length);
        } elseif ($length < 0x4000) {
            $length |= 0x8000;
            $length = chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        } elseif ($length < 0x200000) {
            $length |= 0xC00000;
            $length = chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        } elseif ($length < 0x10000000) {
            $length |= 0xE0000000;
            $length = chr(($length >> 24) & 0xFF) . chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        } elseif ($length >= 0x10000000) {
            $length = chr(0xF0) . chr(($length >> 24) & 0xFF) . chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        }

        return $length;
    }


    /**
     * Login to RouterOS
     *
     * @param string      $ip         Hostname (IP or domain) of the RouterOS server
     * @param string      $login      The RouterOS username
     * @param string      $password   The RouterOS password
     *
     * @return boolean                If we are connected or not
     */
    public function connect($ip, $login, $password)
    {
        // Check for REST API mode (explicit or implied by scheme/port)
        $is_rest = ($this->use_rest === true);
        if ($this->use_rest === null) {
            if (preg_match('#^https?://#i', $ip) || preg_match('/:(80|443|8080|8443)$/', $ip)) {
                $is_rest = true;
            }
        }

        if ($is_rest) {
            $this->rest_client = new RouterosRestAPI();
            $this->rest_client->debug = $this->debug;
            $this->rest_client->timeout = $this->timeout;
            $this->rest_client->attempts = $this->attempts;
            $this->rest_client->delay = $this->delay;
            $this->connected = $this->rest_client->connect($ip, $login, $password);
            $this->error_no = $this->rest_client->error_no;
            $this->error_str = $this->rest_client->error_str;
            return $this->connected;
        }

        for ($ATTEMPT = 1; $ATTEMPT <= $this->attempts; $ATTEMPT++) {
            $this->connected = false;
            $PROTOCOL = ($this->ssl ? 'ssl://' : '' );
            $context = stream_context_create(array('ssl' => array('ciphers' => 'ADH:ALL', 'verify_peer' => false, 'verify_peer_name' => false)));
            $this->debug('Connection attempt #' . $ATTEMPT . ' to ' . $PROTOCOL . $ip . ':' . $this->port . '...');
            $this->socket = @stream_socket_client($PROTOCOL . $ip.':'. $this->port, $this->error_no, $this->error_str, $this->timeout, STREAM_CLIENT_CONNECT,$context);
            if ($this->socket) {
                socket_set_timeout($this->socket, $this->timeout);
                $this->write('/login', false);
                $this->write('=name=' . $login, false);
                $this->write('=password=' . $password);
                $RESPONSE = $this->read(false);
                if (isset($RESPONSE[0])) {
                    if ($RESPONSE[0] == '!done') {
                        if (!isset($RESPONSE[1])) {
                            // Login method post-v6.43
                            $this->connected = true;
                            break;
                        } else {
                            // Login method pre-v6.43
                            $MATCHES = array();
                            if (preg_match_all('/[^=]+/i', $RESPONSE[1], $MATCHES)) {
                                if ($MATCHES[0][0] == 'ret' && strlen($MATCHES[0][1]) == 32) {
                                    $this->write('/login', false);
                                    $this->write('=name=' . $login, false);
                                    $this->write('=response=00' . md5(chr(0) . $password . pack('H*', $MATCHES[0][1])));
                                    $RESPONSE = $this->read(false);
                                    if (isset($RESPONSE[0]) && $RESPONSE[0] == '!done') {
                                        $this->connected = true;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
                fclose($this->socket);
            }
            sleep($this->delay);
        }

        if ($this->connected) {
            $this->debug('Connected...');
        } else {
            // Auto fallback to REST API if socket connection failed.
            // Only worth it when the caller explicitly asked for REST; an
            // unreachable router does not get faster by being asked twice.
            if ($this->use_rest === true) {
                $this->debug('Socket API failed, trying RouterOS v7 REST API fallback...');
                $this->rest_client = new RouterosRestAPI();
                $this->rest_client->debug = $this->debug;
                $this->rest_client->timeout = $this->timeout;
                // Socket mode already proved the router unreachable; one REST
                // probe is enough. Retrying here was doubling every page stall.
                $this->rest_client->attempts = 1;
                $this->rest_client->delay = 0;
                if ($this->rest_client->connect($ip, $login, $password)) {
                    $this->connected = true;
                    return true;
                }
            }
            $this->debug('Error...');
        }
        return $this->connected;
    }


    /**
     * Disconnect from RouterOS
     *
     * @return void
     */
    public function disconnect()
    {
        if ($this->rest_client !== null) {
            $this->rest_client->disconnect();
        }
        // let's make sure this socket is still valid.  it may have been closed by something else
        if( is_resource($this->socket) ) {
            fclose($this->socket);
        }
        $this->connected = false;
        $this->debug('Disconnected...');
    }


    /**
     * Parse response from Router OS
     *
     * @param array       $response   Response data
     *
     * @return array                  Array with parsed data
     */
    public function parseResponse($response)
    {
        if (is_array($response)) {
            $PARSED      = array();
            $CURRENT     = null;
            $singlevalue = null;
            foreach ($response as $x) {
                if (in_array($x, array('!fatal','!re','!trap'))) {
                    if ($x == '!re') {
                        $CURRENT =& $PARSED[];
                    } else {
                        $CURRENT =& $PARSED[$x][];
                    }
                } elseif ($x != '!done') {
                    $MATCHES = array();
                    if (preg_match_all('/[^=]+/i', $x, $MATCHES)) {
                        if ($MATCHES[0][0] == 'ret') {
                            $singlevalue = $MATCHES[0][1];
                        }
                        $CURRENT[$MATCHES[0][0]] = (isset($MATCHES[0][1]) ? $MATCHES[0][1] : '');
                    }
                }
            }

            if (empty($PARSED) && !is_null($singlevalue)) {
                $PARSED = $singlevalue;
            }

            return $PARSED;
        } else {
            return array();
        }
    }


    /**
     * Parse response from Router OS
     *
     * @param array       $response   Response data
     *
     * @return array                  Array with parsed data
     */
    public function parseResponse4Smarty($response)
    {
        if (is_array($response)) {
            $PARSED      = array();
            $CURRENT     = null;
            $singlevalue = null;
            foreach ($response as $x) {
                if (in_array($x, array('!fatal','!re','!trap'))) {
                    if ($x == '!re') {
                        $CURRENT =& $PARSED[];
                    } else {
                        $CURRENT =& $PARSED[$x][];
                    }
                } elseif ($x != '!done') {
                    $MATCHES = array();
                    if (preg_match_all('/[^=]+/i', $x, $MATCHES)) {
                        if ($MATCHES[0][0] == 'ret') {
                            $singlevalue = $MATCHES[0][1];
                        }
                        $CURRENT[$MATCHES[0][0]] = (isset($MATCHES[0][1]) ? $MATCHES[0][1] : '');
                    }
                }
            }
            foreach ($PARSED as $key => $value) {
                $PARSED[$key] = $this->arrayChangeKeyName($value);
            }
            return $PARSED;
            if (empty($PARSED) && !is_null($singlevalue)) {
                $PARSED = $singlevalue;
            }
        } else {
            return array();
        }
    }


    /**
     * Change "-" and "/" from array key to "_"
     *
     * @param array       $array      Input array
     *
     * @return array                  Array with changed key names
     */
    public function arrayChangeKeyName(&$array)
    {
        if (is_array($array)) {
            foreach ($array as $k => $v) {
                $tmp = str_replace("-", "_", $k);
                $tmp = str_replace("/", "_", $tmp);
                if ($tmp) {
                    $array_new[$tmp] = $v;
                } else {
                    $array_new[$k] = $v;
                }
            }
            return $array_new;
        } else {
            return $array;
        }
    }


    /**
     * Read data from Router OS
     *
     * @param boolean     $parse      Parse the data? default: true
     *
     * @return array                  Array with parsed or unparsed data
     */
    public function read($parse = true)
    {
        $RESPONSE     = array();
        $receiveddone = false;
        while (true) {
            // Read the first byte of input which gives us some or all of the length
            // of the remaining reply.
            $RAWBYTE = fread($this->socket, 1);
            // Empty read == peer closed/timed out. Without this the loop spins
            // forever and pins an FPM worker (the classic "mikhmon hang").
            if ($RAWBYTE === false || $RAWBYTE === '') {
                $this->connected = false;
                break;
            }
            $BYTE   = ord($RAWBYTE);
            $LENGTH = 0;
            // If the first bit is set then we need to remove the first four bits, shift left 8
            // and then read another byte in.
            // We repeat this for the second and third bits.
            // If the fourth bit is set, we need to remove anything left in the first byte
            // and then read in yet another byte.
            if ($BYTE & 128) {
                if (($BYTE & 192) == 128) {
                    $LENGTH = (($BYTE & 63) << 8) + ord(fread($this->socket, 1));
                } else {
                    if (($BYTE & 224) == 192) {
                        $LENGTH = (($BYTE & 31) << 8) + ord(fread($this->socket, 1));
                        $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                    } else {
                        if (($BYTE & 240) == 224) {
                            $LENGTH = (($BYTE & 15) << 8) + ord(fread($this->socket, 1));
                            $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                            $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                        } else {
                            $LENGTH = ord(fread($this->socket, 1));
                            $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                            $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                            $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                        }
                    }
                }
            } else {
                $LENGTH = $BYTE;
            }

            $_ = "";

            // If we have got more characters to read, read them in.
            if ($LENGTH > 0) {
                $_      = "";
                $retlen = 0;
                while ($retlen < $LENGTH) {
                    $toread = $LENGTH - $retlen;
                    $chunk  = fread($this->socket, $toread);
                    if ($chunk === false || $chunk === '') {
                        $this->connected = false;
                        break 2;
                    }
                    $_ .= $chunk;
                    $retlen = strlen($_);
                }
                $RESPONSE[] = $_;
                $this->debug('>>> [' . $retlen . '/' . $LENGTH . '] bytes read.');
            }

            // If we get a !done, make a note of it.
            if ($_ == "!done") {
                $receiveddone = true;
            }

            $STATUS = socket_get_status($this->socket);
            if ($LENGTH > 0) {
                $this->debug('>>> [' . $LENGTH . ', ' . $STATUS['unread_bytes'] . ']' . $_);
            }

            if ((!$this->connected && !$STATUS['unread_bytes']) || ($this->connected && !$STATUS['unread_bytes'] && $receiveddone)) {
                break;
            }
        }

        if ($parse) {
            $RESPONSE = $this->parseResponse($RESPONSE);
        }

        return $RESPONSE;
    }


    /**
     * Write (send) data to Router OS
     *
     * @param string      $command    A string with the command to send
     * @param mixed       $param2     If we set an integer, the command will send this data as a "tag"
     *                                If we set it to boolean true, the funcion will send the comand and finish
     *                                If we set it to boolean false, the funcion will send the comand and wait for next command
     *                                Default: true
     *
     * @return boolean                Return false if no command especified
     */
    public function write($command, $param2 = true)
    {
        if (!is_resource($this->socket)) {
            return false;
        }
        if ($command) {
            $data = explode("\n", $command);
            foreach ($data as $com) {
                $com = trim($com);
                fwrite($this->socket, $this->encodeLength(strlen($com)) . $com);
                $this->debug('<<< [' . strlen($com) . '] ' . $com);
            }

            if (gettype($param2) == 'integer') {
                fwrite($this->socket, $this->encodeLength(strlen('.tag=' . $param2)) . '.tag=' . $param2 . chr(0));
                $this->debug('<<< [' . strlen('.tag=' . $param2) . '] .tag=' . $param2);
            } elseif (gettype($param2) == 'boolean') {
                fwrite($this->socket, ($param2 ? chr(0) : ''));
            }

            return true;
        } else {
            return false;
        }
    }


    /**
     * Write (send) data to Router OS
     *
     * @param string      $com        A string with the command to send
     * @param array       $arr        An array with arguments or queries
     *
     * @return array                  Array with parsed
     */
    public function comm($com, $arr = array())
    {
        if ($this->rest_client !== null && $this->connected) {
            return $this->rest_client->comm($com, $arr);
        }
        // connect() failed: there is no socket, so every caller used to fatal
        // with "fwrite(): Argument #1 must be of type resource". An empty array
        // lets pages render their offline/empty state instead of dying.
        if (!is_resource($this->socket)) {
            return array();
        }

        $count = count($arr);
        $this->write($com, !$arr);
        $i = 0;
        if ($this->isIterable($arr)) {
            foreach ($arr as $k => $v) {
                switch ($k[0]) {
                    case "?":
                        $el = "$k=$v";
                        break;
                    case "~":
                        $el = "$k~$v";
                        break;
                    default:
                        $el = "=$k=$v";
                        break;
                }

                $last = ($i++ == $count - 1);
                $this->write($el, $last);
            }
        }

        return $this->read();
    }

    /**
     * Standard destructor
     *
     * @return void
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}

// encrypt decript

function encrypt($string, $key=128) {
	$string = (string)($string ?? '');
	$result = '';
	for($i=0, $k= strlen($string); $i<$k; $i++) {
		$char = substr($string, $i, 1);
		$keychar = substr($key, ($i % strlen($key))-1, 1);
		$char = chr(ord($char)+ord($keychar));
		$result .= $char;
	}
	return base64_encode($result);
}
function decrypt($string, $key=128) {
	$string = (string)($string ?? '');
	$result = '';
	$string = base64_decode($string);
	for($i=0, $k=strlen($string); $i< $k ; $i++) {
		$char = substr($string, $i, 1);
		$keychar = substr($key, ($i % strlen($key))-1, 1);
		$char = chr(ord($char)-ord($keychar));
		$result .= $char;
	}
	return $result;
}

// Reformat date time MikroTik
// by Laksamadi Guko

function formatInterval($dtm){
$val_convert = $dtm;
$new_format = str_replace("s", "", str_replace("m", "m ", str_replace("h", "h ", str_replace("d", "d ", str_replace("w", "w ", $val_convert)))));
return $new_format;
}

function formatDTM($dtm){
$dtm = (string)($dtm ?? '');
$day = '';
if (empty($dtm)) return '-';
if(substr($dtm, 1,1) == "d" || substr($dtm, 2,1) == "d"){
    $day = explode("d",$dtm)[0]."d";
    $day = str_replace("d", "d ", str_replace("w", "w ", $day));
    $dtm = explode("d",$dtm)[1];
}elseif(substr($dtm, 1,1) == "w" && substr($dtm, 3,1) == "d" || substr($dtm, 2,1) == "w" && substr($dtm, 4,1) == "d"){
    $day = explode("d",$dtm)[0]."d";
    $day = str_replace("d", "d ", str_replace("w", "w ", $day));
    $dtm = explode("d",$dtm)[1];
}elseif (substr($dtm, 1,1) == "w" || substr($dtm, 2,1) == "w" ) {
    $day = explode("w",$dtm)[0]."w";
    $day = str_replace("d", "d ", str_replace("w", "w ", $day));
    $dtm = explode("w",$dtm)[1];
}

// secs
if(strlen($dtm) == "2" && substr($dtm, -1) == "s"){
    $format = $day." 00:00:0".substr($dtm, 0,-1);
}elseif(strlen($dtm) == "3" && substr($dtm, -1) == "s"){
    $format = $day." 00:00:".substr($dtm, 0,-1);
//minutes
}elseif(strlen($dtm) == "2" && substr($dtm, -1) == "m"){
    $format = $day." 00:0".substr($dtm, 0,-1).":00";
}elseif(strlen($dtm) == "3" && substr($dtm, -1) == "m"){
    $format = $day." 00:".substr($dtm, 0,-1).":00";
//hours
}elseif(strlen($dtm) == "2" && substr($dtm, -1) == "h"){
    $format = $day." 0".substr($dtm, 0,-1).":00:00";
}elseif(strlen($dtm) == "3" && substr($dtm, -1) == "h"){
    $format = $day." ".substr($dtm, 0,-1).":00:00";
 
//minutes -secs
}elseif(strlen($dtm) == "4" && substr($dtm, -1) == "s" && substr($dtm,1,-2) == "m"){
    $format = $day." "."00:0".substr($dtm, 0,1).":0".substr($dtm, 2,-1);
}elseif(strlen($dtm) == "5" && substr($dtm, -1) == "s" && substr($dtm,1,-3) == "m"){
    $format = $day." "."00:0".substr($dtm, 0,1).":".substr($dtm, 2,-1);
}elseif(strlen($dtm) == "5" && substr($dtm, -1) == "s" && substr($dtm,2,-2) == "m"){
    $format = $day." "."00:".substr($dtm, 0,2).":0".substr($dtm, 3,-1);
}elseif(strlen($dtm) == "6" && substr($dtm, -1) == "s" && substr($dtm,2,-3) == "m"){
    $format = $day." "."00:".substr($dtm, 0,2).":".substr($dtm, 3,-1);

//hours -secs
}elseif(strlen($dtm) == "4" && substr($dtm, -1) == "s" && substr($dtm,1,-2) == "h"){
    $format = $day." 0".substr($dtm, 0,1).":00:0".substr($dtm, 2,-1);
}elseif(strlen($dtm) == "5" && substr($dtm, -1) == "s" && substr($dtm,1,-3) == "h"){
    $format = $day." 0".substr($dtm, 0,1).":00:".substr($dtm, 2,-1);
}elseif(strlen($dtm) == "5" && substr($dtm, -1) == "s" && substr($dtm,2,-2) == "h"){
    $format = $day." ".substr($dtm, 0,2).":00:0".substr($dtm, 3,-1);
}elseif(strlen($dtm) == "6" && substr($dtm, -1) == "s" && substr($dtm,2,-3) == "h"){
    $format = $day." ".substr($dtm, 0,2).":00:".substr($dtm, 3,-1);

//hours -secs
}elseif(strlen($dtm) == "4" && substr($dtm, -1) == "m" && substr($dtm,1,-2) == "h"){
    $format = $day." 0".substr($dtm, 0,1).":0".substr($dtm, 2,-1).":00";
}elseif(strlen($dtm) == "5" && substr($dtm, -1) == "m" && substr($dtm,1,-3) == "h"){
    $format = $day." 0".substr($dtm, 0,1).":".substr($dtm, 2,-1).":00";
}elseif(strlen($dtm) == "5" && substr($dtm, -1) == "m" && substr($dtm,2,-2) == "h"){
    $format = $day." ".substr($dtm, 0,2).":0".substr($dtm, 3,-1).":00";
}elseif(strlen($dtm) == "6" && substr($dtm, -1) == "m" && substr($dtm,2,-3) == "h"){
    $format = $day." ".substr($dtm, 0,2).":".substr($dtm, 3,-1).":00";

//hours minutes secs
}elseif(strlen($dtm) == "6" && substr($dtm, -1) == "s" && substr($dtm,3,-2) == "m" && substr($dtm,1,-4) == "h"){
    $format = $day." 0".substr($dtm, 0,1).":0".substr($dtm, 2,-3).":0".substr($dtm, 4,-1);
}elseif(strlen($dtm) == "7" && substr($dtm, -1) == "s" && substr($dtm,3,-3) == "m" && substr($dtm,1,-5) == "h"){
    $format = $day." 0".substr($dtm, 0,1).":0".substr($dtm, 2,-4).":".substr($dtm, 4,-1);
}elseif(strlen($dtm) == "7" && substr($dtm, -1) == "s" && substr($dtm,4,-2) == "m" && substr($dtm,1,-5) == "h"){
    $format = $day." 0".substr($dtm, 0,1).":".substr($dtm, 2,-3).":0".substr($dtm, 5,-1);
}elseif(strlen($dtm) == "8" && substr($dtm, -1) == "s" && substr($dtm,4,-3) == "m" && substr($dtm,1,-6) == "h"){
    $format = $day." 0".substr($dtm, 0,1).":".substr($dtm, 2,-4).":".substr($dtm, 5,-1);
}elseif(strlen($dtm) == "7" && substr($dtm, -1) == "s" && substr($dtm,4,-2) == "m" && substr($dtm,2,-4) == "h"){
    $format = $day." ".substr($dtm, 0,2).":0".substr($dtm, 3,-3).":0".substr($dtm, 5,-1);
}elseif(strlen($dtm) == "8" && substr($dtm, -1) == "s" && substr($dtm,4,-3) == "m" && substr($dtm,2,-5) == "h"){
    $format = $day." ".substr($dtm, 0,2).":0".substr($dtm, 3,-4).":".substr($dtm, 5,-1);
}elseif(strlen($dtm) == "8" && substr($dtm, -1) == "s" && substr($dtm,5,-2) == "m" && substr($dtm,2,-5) == "h"){
    $format = $day." ".substr($dtm, 0,2).":".substr($dtm, 3,-3).":0".substr($dtm, 6,-1);
}elseif(strlen($dtm) == "9" && substr($dtm, -1) == "s" && substr($dtm,5,-3) == "m" && substr($dtm,2,-6) == "h"){
    $format = $day." ".substr($dtm, 0,2).":".substr($dtm, 3,-4).":".substr($dtm, 6,-1);

}else{
    $format = $dtm;
}
return $format;
}


function randN($length) {
	$chars = "23456789";
	$charArray = str_split($chars);
	$charCount = strlen($chars);
	$result = "";
	for($i=1;$i<=$length;$i++)
	{
		$randChar = rand(0,$charCount-1);
		$result .= $charArray[$randChar];
	}
	return $result;
}

function randUC($length) {
	$chars = "ABCDEFGHJKLMNPRSTUVWXYZ";
	$charArray = str_split($chars);
	$charCount = strlen($chars);
	$result = "";
	for($i=1;$i<=$length;$i++)
	{
		$randChar = rand(0,$charCount-1);
		$result .= $charArray[$randChar];
	}
	return $result;
}
function randLC($length) {
	$chars = "abcdefghijkmnprstuvwxyz";
	$charArray = str_split($chars);
	$charCount = strlen($chars);
	$result = "";
	for($i=1;$i<=$length;$i++)
	{
		$randChar = rand(0,$charCount-1);
		$result .= $charArray[$randChar];
	}
	return $result;
}

function randULC($length) {
	$chars = "ABCDEFGHJKLMNPRSTUVWXYZabcdefghijkmnprstuvwxyz";
	$charArray = str_split($chars);
	$charCount = strlen($chars);
	$result = "";
	for($i=1;$i<=$length;$i++)
	{
		$randChar = rand(0,$charCount-1);
		$result .= $charArray[$randChar];
	}
	return $result;
}

function randNLC($length) {
	$chars = "23456789abcdefghijkmnprstuvwxyz";
	$charArray = str_split($chars);
	$charCount = strlen($chars);
	$result = "";
	for($i=1;$i<=$length;$i++)
	{
		$randChar = rand(0,$charCount-1);
		$result .= $charArray[$randChar];
	}
	return $result;
}

function randNUC($length) {
	$chars = "23456789ABCDEFGHJKLMNPRSTUVWXYZ";
	$charArray = str_split($chars);
	$charCount = strlen($chars);
	$result = "";
	for($i=1;$i<=$length;$i++)
	{
		$randChar = rand(0,$charCount-1);
		$result .= $charArray[$randChar];
	}
	return $result;
}

function randNULC($length) {
	$chars = "23456789ABCDEFGHJKLMNPRSTUVWXYZabcdefghijkmnprstuvwxyz";
	$charArray = str_split($chars);
	$charCount = strlen($chars);
	$result = "";
	for($i=1;$i<=$length;$i++)
	{
		$randChar = rand(0,$charCount-1);
		$result .= $charArray[$randChar];
	}
	return $result;
}

?>

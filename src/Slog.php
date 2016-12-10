<?php
    
    /**
     * @author  kwan<303198069@qq.com>
     * @see     https://github.com/luofei614/SocketLog
     */
    namespace Cheukpang;
    
    use Exception;
    use PDO;
    
    /**
     * Class Slog
     * @package Cheukpang
     * @method static log($trace_msg)
     * @method static info($trace_msg)
     * @method static error($trace_msg)
     * @method static warn($trace_msg)
     * @method static table($trace_msg)
     * @method static group($trace_msg)
     * @method static groupCollapsed($trace_msg)
     * @method static alert($trace_msg)
     */
    class Slog
    {
        /**
         * @var int $start_time 开始时间
         */
        public static $start_time = 0;
        /**
         * @var int $start_memory 开始的占用内存
         */
        public static $start_memory = 0;
        /**
         * @var int $port SocketLog 服务的http的端口号
         */
        public static $port = 1116;
        /**
         * @var array $log_types 日志类型
         */
        public static $log_types = [
            'log',
            'info',
            'error',
            'warn',
            'table',
            'group',
            'groupCollapsed',
            'alert',
        ];
        
        /**
         * @var self $_instance single instance
         */
        protected static $_instance;
        
        /**
         * @var array $config could be overwrite
         */
        protected static $config = [
            //接收日志的主机，需要完整的FQDN，协议部分不能少
            'host'                => 'http://localhost',
            //是否显示利于优化的参数，如果允许时间，消耗内存等
            'optimize'            => true,
            'show_included_files' => false,
            //限制允许读取日志的client_id
            'allow_client_ids'    => ['kwan'],
        ];
        
        /**
         * @var array $logs log pool
         */
        protected static $logs = [];
        
        /**
         * @param $method
         * @param $args
         * @return mixed
         */
        public static function __callStatic($method, $args)
        {
            if (in_array($method, static::$log_types)) {
                array_unshift($args, $method);
                
                return call_user_func_array([static::getInstance(), 'record'], $args);
            }
            
            return false;
        }
        
        /**
         * sql日志
         * @param $sql
         * @param $link
         * @return bool
         * @throws \Exception
         */
        protected static function sql($sql, $link)
        {
            if (is_object($link) && 'mysqli' == get_class($link)) {
                return static::mysqliLog($sql, $link);
            }
            
            if (is_resource($link) && ('mysql link' == get_resource_type($link)
                    || 'mysql link persistent' == get_resource_type($link))
            ) {
                return static::mysqlLog($sql, $link);
            }
            
            if (is_object($link) && 'PDO' == get_class($link)) {
                return static::pdoLog($sql, $link);
            }
            
            throw new Exception('SocketLog only support mysql/mysqli/PDO');
        }
        
        /**
         * @param     $msg
         * @param int $trace_level
         * @return bool|mixed
         */
        protected static function trace($msg, $trace_level = 1)
        {
            if (!static::check()) {
                return false;
            }
            $traces = debug_backtrace(false);
            $traces = array_reverse($traces);
            $max = count($traces) - $trace_level;
            for ($i = 0; $i < $max; $i++) {
                $trace = $traces[$i];
                $fun = isset($trace['class']) ? $trace['class'] . '::' . $trace['function'] : $trace['function'];
                $file = isset($trace['file']) ? $trace['file'] : 'unknown file';
                $line = isset($trace['line']) ? $trace['line'] : 'unknown line';
                $trace_msg = '#' . $i . '  ' . $fun . ' called at [' . $file . ':' . $line . ']';
                static::log($trace_msg);
            }
        }
        
        /**
         * mysqli日志
         * @internal
         * @param $sql
         * @param $link_identifier
         * @return bool
         */
        protected static function mysqliLog($sql, $link_identifier)
        {
            if (!static::check()) {
                return false;
            }
            
            if (preg_match('/^SELECT /i', $sql)) {
                //explain
                $query = mysqli_query($link_identifier, 'EXPLAIN ' . $sql);
                $arr = mysqli_fetch_array($query);
                static::sqlExplain($arr, $sql);
            }
            static::sqlWhere($sql);
            static::trace($sql, 2);
        }
        
        /**
         * mysql日志
         * @deprecated
         * @param $sql
         * @param $link_identifier
         * @return bool
         */
        protected static function mysqlLog($sql, $link_identifier)
        {
            if (!static::check()) {
                return false;
            }
            if (preg_match('/^SELECT /i', $sql)) {
                //explain
                $query = mysql_query('EXPLAIN ' . $sql, $link_identifier);
                $arr = mysql_fetch_array($query);
                static::sqlExplain($arr, $sql);
            }
            //判断sql语句是否有where
            static::sqlWhere($sql);
            static::trace($sql, 2);
        }
        
        /**
         * pdo日志
         * @internal
         * @param $sql
         * @param $pdo
         * @return bool
         */
        protected static function pdoLog($sql, PDO $pdo)
        {
            if (!static::check()) {
                return false;
            }
            if (preg_match('/^SELECT /i', $sql)) {
                //explain
                try {
                    $pdoStatement = $pdo->query('EXPLAIN ' . $sql);
                    if (is_object($pdoStatement) && method_exists($pdoStatement, 'fetch')) {
                        $arr = $pdoStatement->fetch(PDO::FETCH_ASSOC);
                        static::sqlExplain($arr, $sql);
                    }
                } catch (Exception $e) {
                    return false;
                }
            }
            static::sqlWhere($sql);
            static::trace($sql, 2);
        }
        
        /**
         * @param array $arr
         * @param       $sql
         */
        private static function sqlExplain(array $arr, &$sql)
        {
            $arr = array_change_key_case($arr, CASE_LOWER);
            if (false !== strpos($arr['extra'], 'Using filesort')) {
                $sql .= ' <---################[Using filesort]';
            }
            if (false !== strpos($arr['extra'], 'Using temporary')) {
                $sql .= ' <---################[Using temporary]';
            }
        }
        
        /**
         * @param $sql
         */
        private static function sqlWhere(&$sql)
        {
            //判断sql语句是否有where
            if (preg_match('/^UPDATE |DELETE /i', $sql) && !preg_match('/WHERE.*(=|>|<|LIKE|IN)/i', $sql)) {
                $sql .= '<---###########[NO WHERE]';
            }
        }
        
        /**
         * @return self
         */
        public static function getInstance()
        {
            if (static::$_instance === null) {
                static::$_instance = new self();
            }
            
            return static::$_instance;
        }
        
        /**
         * @return bool
         */
        protected static function check()
        {
            $tabId = static::getClientArg('tabid');
            //是否记录日志的检查
            if (!$tabId) {
                return false;
            }
            //用户认证
            $allow_client_ids = static::getConfig('allow_client_ids');
            if (!$allow_client_ids) {
                return false;
            }
            if (!in_array(static::getClientArg('client_id'), $allow_client_ids)) {
                return false;
            }
            
            return true;
        }
        
        /**
         * 获取客户端参数
         * @param string $name
         * @return mixed|null
         */
        protected static function getClientArg($name = '')
        {
            static $args = [];
            
            $key = 'HTTP_USER_AGENT';
            
            if (isset($_SERVER['HTTP_SOCKETLOG'])) {
                $key = 'HTTP_SOCKETLOG';
            }
            
            if (!isset($_SERVER[$key])) {
                return null;
            }
            if (empty($args)) {
                if (!preg_match('/SocketLog\((.*?)\)/', $_SERVER[$key], $match)) {
                    $args = ['tabid' => null];
                    
                    return null;
                }
                parse_str($match[1], $args);
            }
            if (isset($args[$name])) {
                return $args[$name];
            }
            
            return null;
        }
        
        
        /**
         * 设置配置
         * @param array $config
         */
        public static function setConfig(array $config = [])
        {
            $config = array_merge(static::$config, $config);
            static::$config = $config;
            if (static::check()) {
                //强制初始化SocketLog实例
                static::getInstance();
                if ($config['optimize']) {
                    static::$start_time = microtime(true);
                    static::$start_memory = memory_get_usage();
                }
            }
        }
        
        
        /**
         * 获得配置
         * @param string $name
         * @return mixed|null
         */
        public static function getConfig($name = '')
        {
            if (isset(static::$config[$name])) {
                return static::$config[$name];
            }
            
            return null;
        }
        
        /**
         * 记录日志
         * @internal
         * @param  string $type
         * @param string  $msg
         * @return bool
         */
        protected function record($type = '', $msg = '')
        {
            if (!static::check()) {
                return false;
            }
            
            static::$logs[] = [
                'type' => $type,
                'msg'  => $msg,
            ];
            
            return true;
        }
        
        /**
         * @internal
         * @param string $host    - $host of socket server
         * @param string $message - 发送的消息
         * @param string $address - 地址
         * @return bool
         */
        protected static function send($host = '', $message = '', $address = '/')
        {
            $url = $host . ':' . static::$port . $address;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $headers = [
                'Content-Type: application/json;charset=UTF-8',
            ];
            //设置header
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_exec($ch);
            curl_close($ch);
            
            return true;
        }
        
        /**
         * 发送日志
         * @internal
         * @return bool
         */
        protected static function sendLog()
        {
            if (!static::check()) {
                return false;
            }
            
            $timeStr = '';
            $memoryStr = '';
            if (static::$start_time) {
                $runtime = microtime(true) - static::$start_time;
                $reqs = number_format(1 / $runtime, 2);
                $timeStr = "[运行时间：{$runtime}s][吞吐率：{$reqs}req/s]";
            }
            if (static::$start_memory) {
                $memory_use = number_format((memory_get_usage() - static::$start_memory) / 1024, 2);
                $memoryStr = "[内存消耗：{$memory_use}kb]";
            }
            
            if (isset($_SERVER['HTTP_HOST'])) {
                $currentUri = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            } else {
                $currentUri = 'cmd:' . implode(' ', $_SERVER['argv']);
            }
            array_unshift(
                static::$logs,
                [
                    'type' => 'group',
                    'msg'  => $currentUri . $timeStr . $memoryStr,
                ]
            );
            
            if (static::getConfig('show_included_files')) {
                static::$logs[] = [
                    'type' => 'groupCollapsed',
                    'msg'  => 'included_files',
                ];
                static::$logs[] = [
                    'type' => 'log',
                    'msg'  => implode("\n", get_included_files()),
                ];
                static::$logs[] = [
                    'type' => 'groupEnd',
                    'msg'  => '',
                ];
            }
            
            static::$logs[] = [
                'type' => 'groupEnd',
                'msg'  => '',
            ];
            
            $tabId = static::getClientArg('tabid');
            if (!$clientId = static::getClientArg('client_id')) {
                $clientId = '';
            }
            static::sendToClient($tabId, $clientId, static::$logs);
        }
        
        /**
         * 发送给指定客户端
         * @param $tabId
         * @param $clientId
         * @param $logs
         */
        protected static function sendToClient($tabId, $clientId, $logs)
        {
            $logs = [
                'tabid'     => $tabId,
                'client_id' => $clientId,
                'logs'      => $logs,
            ];
            $msg = json_encode($logs);
            //将client_id作为地址， server端通过地址判断将日志发布给谁
            $address = '/' . $clientId;
            static::send(static::getConfig('host'), $msg, $address);
        }
        
        public function __destruct()
        {
            static::sendLog();
        }
        
    }


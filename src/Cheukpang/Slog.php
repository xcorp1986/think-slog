<?php
    
    /**
     * @author  kwan<303198069@qq.com>
     * @see     https://github.com/luofei614/SocketLog
     */
    namespace Cheukpang;
    
    class Slog
    {
        public static $start_time = 0;
        public static $start_memory = 0;
        //SocketLog 服务的http的端口号
        public static $port = 1116;
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
        
        //配置强制推送且被授权的client_id
        protected static $_allowForceClientIds = ['kwan'];
        
        protected static $_instance;
        
        protected static $config = [
            //是否记录日志的开关
            'enable'              => true,
            'host'                => 'localhost',
            //是否显示利于优化的参数，如果允许时间，消耗内存等
            'optimize'            => true,
            'show_included_files' => false,
            'error_handler'       => false,
            //日志强制记录到配置的client_id
            'force_client_ids'    => ['kwan'],
            //限制允许读取日志的client_id
            'allow_client_ids'    => ['kwan'],
        ];
        
        protected static $logs = [];
        
        public static function __callStatic($method, $args)
        {
            if (in_array($method, static::$log_types)) {
                array_unshift($args, $method);
                
                return call_user_func_array([static::getInstance(), 'record'], $args);
            }
        }
        
        /**
         * sql日志
         * @param $sql
         * @param $link
         * @return bool
         * @throws \Exception
         */
        public static function sql($sql, $link)
        {
            if (is_object($link) && 'mysqli' == get_class($link)) {
                return static::mysqlilog($sql, $link);
            }
            
            if (is_resource($link) && ('mysql link' == get_resource_type(
                        $link
                    ) || 'mysql link persistent' == get_resource_type($link))
            ) {
                return static::mysqllog($sql, $link);
            }
            
            
            if (is_object($link) && 'PDO' == get_class($link)) {
                return static::pdolog($sql, $link);
            }
            
            throw new \Exception('SocketLog can not support this database link');
        }
        
        /**
         * @param     $msg
         * @param int $trace_level
         * @return bool
         */
        public static function trace($msg, $trace_level = 1)
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
        
        
        public static function mysqlilog($sql, $db)
        {
            if (!static::check()) {
                return false;
            }
            
            if (preg_match('/^SELECT /i', $sql)) {
                //explain
                $query = @mysqli_query($db, "EXPLAIN " . $sql);
                $arr = mysqli_fetch_array($query);
                static::sqlexplain($arr, $sql);
            }
            static::sqlwhere($sql);
            static::trace($sql, 2);
        }
        
        
        public static function mysqllog($sql, $db)
        {
            if (!static::check()) {
                return false;
            }
            if (preg_match('/^SELECT /i', $sql)) {
                //explain
                $query = @mysql_query("EXPLAIN " . $sql, $db);
                $arr = mysql_fetch_array($query);
                static::sqlexplain($arr, $sql);
            }
            //判断sql语句是否有where
            static::sqlwhere($sql);
            static::trace($sql, 2);
        }
        
        /**
         * pdo日志
         * @param $sql
         * @param $pdo
         * @return bool
         */
        public static function pdolog($sql, $pdo)
        {
            if (!static::check()) {
                return false;
            }
            if (preg_match('/^SELECT /i', $sql)) {
                //explain
                try {
                    $obj = $pdo->query("EXPLAIN " . $sql);
                    if (is_object($obj) && method_exists($obj, 'fetch')) {
                        $arr = $obj->fetch(\PDO::FETCH_ASSOC);
                        static::sqlexplain($arr, $sql);
                    }
                } catch (\Exception $e) {
                    
                }
            }
            static::sqlwhere($sql);
            static::trace($sql, 2);
        }
        
        /**
         * @param $arr
         * @param $sql
         */
        private static function sqlexplain($arr, &$sql)
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
        private static function sqlwhere(&$sql)
        {
            //判断sql语句是否有where
            if (preg_match('/^UPDATE |DELETE /i', $sql) && !preg_match('/WHERE.*(=|>|<|LIKE|IN)/i', $sql)) {
                $sql .= '<---###########[NO WHERE]';
            }
        }
        
        
        /**
         * 接管报错
         */
        public static function registerErrorHandler()
        {
            if (!static::check()) {
                return false;
            }
            
            set_error_handler([__CLASS__, 'error_handler']);
            register_shutdown_function([__CLASS__, 'fatalError']);
        }
        
        /**
         * 错误处理函数
         * @param $errno
         * @param $errstr
         * @param $errfile
         * @param $errline
         */
        public static function error_handler($errno, $errstr, $errfile, $errline)
        {
            switch ($errno) {
                case E_WARNING:
                    $severity = 'E_WARNING';
                    break;
                case E_NOTICE:
                    $severity = 'E_NOTICE';
                    break;
                case E_USER_ERROR:
                    $severity = 'E_USER_ERROR';
                    break;
                case E_USER_WARNING:
                    $severity = 'E_USER_WARNING';
                    break;
                case E_USER_NOTICE:
                    $severity = 'E_USER_NOTICE';
                    break;
                case E_STRICT:
                    $severity = 'E_STRICT';
                    break;
                case E_RECOVERABLE_ERROR:
                    $severity = 'E_RECOVERABLE_ERROR';
                    break;
                case E_DEPRECATED:
                    $severity = 'E_DEPRECATED';
                    break;
                case E_USER_DEPRECATED:
                    $severity = 'E_USER_DEPRECATED';
                    break;
                case E_ERROR:
                    $severity = 'E_ERR';
                    break;
                case E_PARSE:
                    $severity = 'E_PARSE';
                    break;
                case E_CORE_ERROR:
                    $severity = 'E_CORE_ERROR';
                    break;
                case E_COMPILE_ERROR:
                    $severity = 'E_COMPILE_ERROR';
                    break;
                default:
                    $severity = 'E_UNKNOWN_ERROR_' . $errno;
                    break;
            }
            $msg = "{$severity}: {$errstr} in {$errfile} on line {$errline} -- SocketLog error handler";
            static::trace($msg, 2);
        }
        
        /**
         *保存日志记录
         */
        public static function fatalError()
        {
            if ($e = error_get_last()) {
                static::error_handler($e['type'], $e['message'], $e['file'], $e['line']);
                //此类终止不会调用类的 __destruct 方法，所以此处手动sendLog
                static::sendLog();
            }
        }
        
        /**
         * @return mixed
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
            if (!static::getConfig('enable')) {
                return false;
            }
            $tabid = static::getClientArg('tabid');
            //是否记录日志的检查
            if (!$tabid && !static::getConfig('force_client_ids')) {
                return false;
            }
            //用户认证
            $allow_client_ids = static::getConfig('allow_client_ids');
            if (!empty($allow_client_ids)) {
                //通过数组交集得出授权强制推送的client_id
                static::$_allowForceClientIds = array_intersect($allow_client_ids, static::getConfig('force_client_ids'));
                if (!$tabid && count(static::$_allowForceClientIds)) {
                    return true;
                }
                
                $client_id = static::getClientArg('client_id');
                if (!in_array($client_id, $allow_client_ids)) {
                    return false;
                }
            } else {
                static::$_allowForceClientIds = static::getConfig('force_client_ids');
            }
            
            return true;
        }
        
        /**
         * 获取客户端参数
         * @param $name
         * @return mixed|null
         */
        protected static function getClientArg($name)
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
         * @param $config
         */
        public static function config($config)
        {
            $config = array_merge(static::$config, $config);
            if (isset($config['force_client_id'])) {
                //兼容老配置
                $config['force_client_ids'] = array_merge($config['force_client_ids'], [$config['force_client_id']]);
            }
            static::$config = $config;
            if (static::check()) {
                static::getInstance(); //强制初始化SocketLog实例
                if ($config['optimize']) {
                    static::$start_time = microtime(true);
                    static::$start_memory = memory_get_usage();
                }
                
                if ($config['error_handler']) {
                    static::registerErrorHandler();
                }
            }
        }
        
        
        /**
         * 获得配置
         * @param $name
         * @return mixed|null
         */
        public static function getConfig($name)
        {
            if (isset(static::$config[$name])) {
                return static::$config[$name];
            }
            
            return null;
        }
        
        /**
         * 记录日志
         * @param        $type
         * @param string $msg
         * @return bool
         */
        public function record($type, $msg = '')
        {
            if (!static::check()) {
                return false;
            }
            
            static::$logs[] = [
                'type' => $type,
                'msg'  => $msg,
            ];
        }
        
        /**
         * @param null   $host    - $host of socket server
         * @param string $message - 发送的消息
         * @param string $address - 地址
         * @return bool
         */
        public static function send($host, $message = '', $address = '/')
        {
            $scheme = is_ssl() ? 'https://' : 'http://';
            $url = $scheme() . $host . ':' . static::$port . $address;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $headers = [
                "Content-Type: application/json;charset=UTF-8",
            ];
            //设置header
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_exec($ch);
            
            return true;
        }
        
        /**
         * 发送日志
         * @return bool
         */
        public static function sendLog()
        {
            if (!static::check()) {
                return false;
            }
            
            $time_str = '';
            $memory_str = '';
            if (static::$start_time) {
                $runtime = microtime(true) - static::$start_time;
                $reqs = number_format(1 / $runtime, 2);
                $time_str = "[运行时间：{$runtime}s][吞吐率：{$reqs}req/s]";
            }
            if (static::$start_memory) {
                $memory_use = number_format((memory_get_usage() - static::$start_memory) / 1024, 2);
                $memory_str = "[内存消耗：{$memory_use}kb]";
            }
            
            if (isset($_SERVER['HTTP_HOST'])) {
                $current_uri = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            } else {
                $current_uri = "cmd:" . implode(' ', $_SERVER['argv']);
            }
            array_unshift(
                static::$logs,
                [
                    'type' => 'group',
                    'msg'  => $current_uri . $time_str . $memory_str,
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
            
            $tabid = static::getClientArg('tabid');
            if (!$client_id = static::getClientArg('client_id')) {
                $client_id = '';
            }
            if (!empty(static::$_allowForceClientIds)) {
                //强制推送到多个client_id
                foreach (static::$_allowForceClientIds as $force_client_id) {
                    $client_id = $force_client_id;
                    static::sendToClient($tabid, $client_id, static::$logs, $force_client_id);
                }
            } else {
                static::sendToClient($tabid, $client_id, static::$logs, '');
            }
        }
        
        /**
         * 发送给指定客户端
         * @author Zjmainstay
         * @param $tabid
         * @param $client_id
         * @param $logs
         * @param $force_client_id
         */
        protected static function sendToClient($tabid, $client_id, $logs, $force_client_id)
        {
            $logs = [
                'tabid'           => $tabid,
                'client_id'       => $client_id,
                'logs'            => $logs,
                'force_client_id' => $force_client_id,
            ];
            $msg = @json_encode($logs);
            //将client_id作为地址， server端通过地址判断将日志发布给谁
            $address = '/' . $client_id;
            static::send(static::getConfig('host'), $msg, $address);
        }
        
        public function __destruct()
        {
            static::sendLog();
        }
        
    }


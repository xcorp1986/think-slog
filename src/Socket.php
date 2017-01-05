<?php
    
    /**
     * @author  kwan<303198069@qq.com>
     * @see     https://github.com/luofei614/SocketLog
     */
    namespace Cheukpang;
    
    /**
     * Class Socket
     * @package Cheukpang
     */
    class Socket
    {
        
        protected $config = [
            // socket服务器地址 需要完整的FQDN
            'host'                => 'http://localhost',
            //SocketLog 服务的http的端口号
            'port'                => 1116,
            // 是否显示加载的文件列表
            'show_included_files' => false,
            // 日志强制记录到配置的client_id
            'force_client_ids'    => ['kwan'],
        ];
        
        /**
         * @param array $config 配置参数
         * @access public
         */
        public function __construct(array $config = [])
        {
            if (!empty($config)) {
                $this->config = array_merge($this->config, $config);
            }
        }
        
        /**
         * 调试输出接口
         * @access public
         * @param array $log 日志信息
         * @return bool
         */
        public function save(array $log = [])
        {
            $trace = [];
            $file_load = ' [文件加载：' . count(get_included_files()) . ']';
            
            if (isset($_SERVER['HTTP_HOST'])) {
                $current_uri = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            } else {
                $current_uri = 'cmd:' . implode(' ', $_SERVER['argv']);
            }
            // 基本信息
            $trace[] = [
                'type' => 'group',
                'msg'  => $current_uri . $file_load,
            ];
            
            foreach ($log as $type => $val) {
                $trace[] = [
                    'type' => 'groupCollapsed',
                    'msg'  => '[ ' . $type . ' ]',
                ];
                foreach ($val as $msg) {
                    if (!is_string($msg)) {
                        $msg = var_export($msg, true);
                    }
                    $trace[] = [
                        'type' => 'log',
                        'msg'  => $msg,
                    ];
                }
                $trace[] = [
                    'type' => 'groupEnd',
                ];
            }
            
            if ($this->config['show_included_files']) {
                $trace[] = [
                    'type' => 'groupCollapsed',
                    'msg'  => '[ file ]',
                ];
                $trace[] = [
                    'type' => 'log',
                    'msg'  => implode(PHP_EOL, get_included_files()),
                ];
                $trace[] = [
                    'type' => 'groupEnd',
                ];
            }
            
            $trace[] = [
                'type' => 'groupEnd',
            ];
            
            //推送到多个client_id
            foreach ($this->config['force_client_ids'] as $force_client_id) {
                $this->sendToClient($force_client_id, $trace);
            }
            
            return true;
        }
        
        /**
         * 发送给客户端
         * @param string $client_id
         * @param array  $logs
         */
        private function sendToClient($client_id = '', array $logs = [])
        {
            $logs = [
                'force_client_id' => $client_id,
                'logs'            => $logs,
            ];
            $msg = json_encode($logs);
            //将client_id作为地址， server端通过地址判断将日志发布给谁
            $address = '/' . $client_id;
            $this->send($this->config['host'], $msg, $address);
        }
        
        /**
         * @param string $host    - $host of socket server
         * @param string $message - 发送的消息
         * @param string $address - 地址
         * @return void
         */
        private function send($host, $message = '', $address = '/')
        {
            $url = $host . ':' . $this->config['port'] . $address;
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
        }
        
    }


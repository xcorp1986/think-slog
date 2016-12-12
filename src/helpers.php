<?php

    if (!function_exists('slog')) {
        /**
         * @description socketlog日志工具
         * @param string $log
         * @param string $type
         * @return bool|mixed
         * @throws \Exception
         */
        function slog($log = '', $type = 'log')
        {
            if (is_string($type)) {
                $type = preg_replace_callback(
                    '/_([a-zA-Z])/',
                    create_function('$matches', 'return strtoupper($matches[1]);'),
                    $type
                );
                if (method_exists('\Cheukpang\Slog', $type) || in_array($type, \Cheukpang\Slog::$log_types)) {
                    return call_user_func(['\Cheukpang\Slog', $type], $log);
                }
            }

            if (is_object($type) && 'mysqli' == get_class($type)) {
                return \Cheukpang\Slog::mysqliLog($log, $type);
            }

            if (is_resource($type) && ('mysql link' == get_resource_type($type)
                    || 'mysql link persistent' == get_resource_type($type))
            ) {
                return \Cheukpang\Slog::mysqlLog($log, $type);
            }

            if (is_object($type) && 'PDO' == get_class($type)) {
                return \Cheukpang\Slog::pdoLog($log, $type);
            }

            throw new \Exception($type . ' is not SocketLog method');
        }
    }

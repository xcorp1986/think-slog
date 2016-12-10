<?php
    
    namespace Cheukpang;
    
    use PHPUnit\Framework\TestCase;
    
    class SlogTest extends TestCase
    {
        
        protected static $_instance = null;
        
        protected static $config = [
            //接收日志的主机，需要完整的FQDN，协议部分不能少
            'host'                => 'http://localhost',
            //是否显示利于优化的参数，如果允许时间，消耗内存等
            'optimize'            => true,
            'show_included_files' => false,
            //限制允许读取日志的client_id
            'allow_client_ids'    => ['kwan'],
        ];
        
        public function setUp()
        {
            if (!extension_loaded('mysql') & !extension_loaded('mysqli') & !extension_loaded('pdo_mysql')) {
                $this->markTestSkipped('需要的扩展没安装');
            }
            self::$_instance = \Cheukpang\Slog::getInstance();
        }
        
        public function tearDown()
        {
            self::$_instance = null;
        }
        
        /**
         * @test
         */
        public function slog()
        {
            $condition = function_exists('\Cheukpang\Helper\slog');
            $this->assertTrue($condition);
        }
        
        /**
         * @test
         * @depends slog
         */
        public function getInstance()
        {
            $this->assertInstanceOf(\Cheukpang\Slog::class, self::$_instance);
        }
        
        /**
         * @test
         * @depends slog
         */
        public function getConfig()
        {
            $this->assertEquals('http://localhost', self::$_instance->getConfig('host'));
            $this->assertTrue(true, self::$_instance->getConfig('optimize'));
            $this->assertEquals(self::$config['allow_client_ids'], self::$_instance->getConfig('allow_client_ids'));
        }
        
    }
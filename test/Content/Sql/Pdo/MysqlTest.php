<?php
/**
 * Prepare the test setup.
 */
require_once __DIR__ . '/../Base.php';

class Content_Sql_Pdo_MysqlTest extends Content_Test_Sql_Base
{
    public static function setUpBeforeClass(): void
    {
        if (!extension_loaded('pdo') ||
            !in_array('mysql', PDO::getAvailableDrivers())) {
            self::$reason = 'No mysql extension or no mysql PDO driver';
            return;
        }
        $config = self::getConfig('CONTENT_SQL_PDO_MYSQL_TEST_CONFIG',
                                  __DIR__ . '/../..');
        if ($config && !empty($config['content']['sql']['pdo_mysql'])) {
            self::$db = new Horde_Db_Adapter_Pdo_Mysql($config['content']['sql']['pdo_mysql']);
            parent::setUpBeforeClass();
        }
    }
}

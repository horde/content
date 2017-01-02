<?php
/**
 * Prepare the test setup.
 */
require_once __DIR__ . '/../Base.php';

/**
 * Copyright 2011-2017 Horde LLC (http://www.horde.org/)
 *
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @package    Content
 * @subpackage UnitTests
 * @license    http://www.horde.org/licenses/bsd BSD
 */
class Content_Sql_Pdo_SqliteTest extends Content_Test_Sql_Base
{
    public static function setUpBeforeClass()
    {
        $factory_db = new Horde_Test_Factory_Db();

        try {
            self::$db = $factory_db->create();
            parent::setUpBeforeClass();
        } catch (Horde_Test_Exception $e) {
            self::$reason = 'Sqlite not available';
        }
    }
}
<?php
class RampageBaseTables extends Horde_Db_Migration_Base
{
    public function up()
    {
        // rampage_types
        $t = $this->createTable('rampage_types', array('primaryKey' => false));
          $t->column('type_id',   'primaryKey');
          $t->column('type_name', 'string', array('limit' => 255, 'null' => false));
        $t->end();

        $this->addIndex('rampage_types', array('type_name'), array('name' => 'rampage_objects_type_name', 'unique' => true));

        // rampage_objects
        $t = $this->createTable('rampage_objects', array('primaryKey' => false));
          $t->column('object_id',   'primaryKey');
          $t->column('object_name', 'string',  array('limit' => 255, 'null' => false));
          $t->column('type_id',     'integer', array('null' => false, 'unsigned' => true));
        $t->end();

        $this->addIndex('rampage_objects', array('type_id', 'object_name'), array('name' => 'rampage_objects_type_object_name', 'unique' => true));

        // rampage_users
        $t = $this->createTable('rampage_users', array('primaryKey' => false));
          $t->column('user_id',   'primaryKey');
          $t->column('user_name', 'string', array('limit' => 255, 'null' => false));
        $t->end();

        $this->addIndex('rampage_users', array('user_name'), array('name' => 'rampage_users_user_name', 'unique' => true));
    }

    public function down()
    {
        $this->dropTable('rampage_types');
        $this->dropTable('rampage_objects');
        $this->dropTable('rampage_users');
    }
}

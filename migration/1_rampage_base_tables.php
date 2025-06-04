<?php

class RampageBaseTables extends Horde_Db_Migration_Base
{
    public function up()
    {
        $tableList = $this->tables();

        if (!in_array('rampage_types', $tableList)) {
            // rampage_types
            $t = $this->createTable('rampage_types', ['autoincrementKey' => 'type_id']);
            $t->column('type_name', 'string', ['limit' => 255, 'null' => false]);
            $t->end();
            $this->addIndex('rampage_types', ['type_name'], ['name' => 'rampage_objects_type_name', 'unique' => true]);
        }

        if (!in_array('rampage_objects', $tableList)) {
            // rampage_objects
            $t = $this->createTable('rampage_objects', ['autoincrementKey' => 'object_id']);
            $t->column('object_name', 'string', ['limit' => 255, 'null' => false]);
            $t->column('type_id', 'integer', ['null' => false, 'unsigned' => true]);
            $t->end();
            $this->addIndex('rampage_objects', ['type_id', 'object_name'], ['name' => 'rampage_objects_type_object_name', 'unique' => true]);
        }

        if (!in_array('rampage_users', $tableList)) {
            // rampage_users
            $t = $this->createTable('rampage_users', ['autoincrementKey' => 'user_id']);
            $t->column('user_name', 'string', ['limit' => 255, 'null' => false]);
            $t->end();
            $this->addIndex('rampage_users', ['user_name'], ['name' => 'rampage_users_user_name', 'unique' => true]);
        }
    }

    public function down()
    {
        $this->dropTable('rampage_types');
        $this->dropTable('rampage_objects');
        $this->dropTable('rampage_users');
    }
}

<?php

class RampageTagTables extends Horde_Db_Migration_Base
{
    public function up()
    {
        $tableList = $this->tables();

        if (!in_array('rampage_tags', $tableList)) {
            // rampage_tags
            $t = $this->createTable('rampage_tags', ['autoincrementKey' => 'tag_id']);
            $t->column('tag_name', 'string', ['limit' => 255, 'null' => false]);
            $t->end();
            $this->addIndex('rampage_tags', ['tag_name'], ['name' => 'rampage_tags_tag_name', 'unique' => true]);
        }

        if (!in_array('rampage_tagged', $tableList)) {
            // rampage_tagged
            $t = $this->createTable('rampage_tagged', ['autoincrementKey' => false]);
            $t->column('user_id', 'integer', ['null' => false, 'unsigned' => true]);
            $t->column('object_id', 'integer', ['null' => false, 'unsigned' => true]);
            $t->column('tag_id', 'integer', ['null' => false, 'unsigned' => true]);
            $t->column('created', 'datetime');
            $t->primaryKey(['user_id', 'object_id', 'tag_id']);
            $t->end();
            $this->addIndex('rampage_tagged', ['object_id'], ['name' => 'rampage_tagged_object_id']);
            $this->addIndex('rampage_tagged', ['tag_id'], ['name' => 'rampage_tagged_tag_id']);
            $this->addIndex('rampage_tagged', ['created'], ['name' => 'rampage_tagged_created']);
        }

        if (!in_array('rampage_tag_stats', $tableList)) {
            // rampage_tag_stats
            $t = $this->createTable('rampage_tag_stats', ['autoincrementKey' => false]);
            $t->column('tag_id', 'integer', ['null' => false, 'unsigned' => true]);
            $t->column('count', 'integer', ['unsigned' => true]);
            $t->primaryKey(['tag_id']);
            $t->end();
        }

        if (!in_array('rampage_user_tag_stats', $tableList)) {
            // rampage_user_tag_stats
            $t = $this->createTable('rampage_user_tag_stats', ['autoincrementKey' => false]);
            $t->column('user_id', 'integer', ['null' => false, 'unsigned' => true]);
            $t->column('tag_id', 'integer', ['null' => false, 'unsigned' => true]);
            $t->column('count', 'integer', ['unsigned' => true]);
            $t->primaryKey(['user_id', 'tag_id']);
            $t->end();
            $this->addIndex('rampage_user_tag_stats', ['tag_id'], ['name' => 'rampage_user_tag_stats_tag_id']);
        }
    }

    public function down()
    {
        $this->dropTable('rampage_tags');
        $this->dropTable('rampage_tagged');
        $this->dropTable('rampage_tag_stats');
        $this->dropTable('rampage_user_tag_stats');
    }
}

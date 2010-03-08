<?php
/**
 * Copyright 2008-2010 The Horde Project (http://www.horde.org/)
 *
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @license  http://opensource.org/licenses/bsd-license.php BSD
 * @category Horde
 * @package  Horde_Content
 */

/**
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @license  http://opensource.org/licenses/bsd-license.php BSD
 * @category Horde
 * @package  Horde_Content
 *
 * References:
 *   http://forge.mysql.com/wiki/TagSchema
 *   http://www.slideshare.net/edbond/tagging-and-folksonomy-schema-design-for-scalability-and-performance
 *   http://blog.thinkphp.de/archives/124-An-alternative-Approach-to-Tagging.html
 *   http://code.google.com/p/freetag/
 *
 * @TODO:
 *   need to add type_id to the rampage_tagged table for performance?
 *   need stat tables by type_id?
 *
 * Potential features:
 *   Infer data from combined tags (capital + washington d.c. - http://www.slideshare.net/kakul/tagging-web-2-expo-2008/)
 *   Normalize tag text (http://tagsonomy.com/index.php/interview-with-gordon-luk-freetag/)
 */
class Content_Tagger
{
    /**
     * Database connection
     * @var Horde_Db
     */
    protected $_db;

    /**
     * Tables
     * @var array
     */
    protected $_tables = array(
        'tags' => 'rampage_tags',
        'tagged' => 'rampage_tagged',
        'objects' => 'rampage_objects',
        'tag_stats' => 'rampage_tag_stats',
        'user_tag_stats' => 'rampage_user_tag_stats',
        'users' => 'rampage_users',
    );

    /**
     * User manager object
     * @var Content_Users_Manager
     */
    protected $_userManager;

    /**
     * Type management object
     * @var Content_Types_Manager
     */
    protected $_typeManager;

    /**
     * Object manager
     * @var Content_Objects_Manager
     */
    protected $_objectManager;

    /**
     * Default radius for relationship queries.
     * @var integer
     */
    protected $_defaultRadius = 10;

    /**
     * Constructor
     */
    public function __construct(Horde_Db_Adapter_Base $db,
                                Content_Users_Manager $userManager,
                                Content_Types_Manager $typeManager,
                                Content_Objects_Manager $objectManager)
    {
        $this->_db = $db;
        $this->_userManager = $userManager;
        $this->_typeManager = $typeManager;
        $this->_objectManager = $objectManager;
    }

    /**
     * Adds a tag or several tags to an object_id. This method does not
     * remove other tags.
     *
     * @param mixed       $userId    The user tagging the object.
     * @param mixed       $objectId  The object id to tag or an array containing
     *                               the object_name and type.
     * @param array       $tags      An array of tag name or ids.
     * @param Horde_Date  $created   The datetime of the tagging operation.
     *
     * @return void
     */
    public function tag($userId, $objectId, $tags, $created = null)
    {
        if (is_null($created)) {
            $created = date('Y-m-d\TH:i:s');
        } else {
            $created = $created->format('Y-m-d\TH:i:s');
        }

        // Make sure the object exists
        $objectId = $this->_ensureObject($objectId);

        // Validate/ensure the parameters
        $userId = current($this->_userManager->ensureUsers($userId));

        foreach ($this->ensureTags($tags) as $tagId) {
            try {
                $this->_db->insert('INSERT INTO ' . $this->_t('tagged') . ' (user_id, object_id, tag_id, created)
                                      VALUES (' . (int)$userId . ',' . (int)$objectId . ',' . (int)$tagId . ',' . $this->_db->quote($created) . ')');
            } catch (Horde_Db_Exception $e) {
                // @TODO should make sure it's a duplicate and re-throw if not
                continue;
            }

            // increment tag stats
            if (!$this->_db->update('UPDATE ' . $this->_t('tag_stats') . ' SET count = count + 1 WHERE tag_id = ' . (int)$tagId)) {
                $this->_db->insert('INSERT INTO ' . $this->_t('tag_stats') . ' (tag_id, count) VALUES (' . (int)$tagId . ', 1)');
            }

            // increment user-tag stats
            if (!$this->_db->update('UPDATE ' . $this->_t('user_tag_stats') . ' SET count = count + 1 WHERE user_id = ' . (int)$userId . ' AND tag_id = ' . (int)$tagId)) {
                $this->_db->insert('INSERT INTO ' . $this->_t('user_tag_stats') . ' (user_id, tag_id, count) VALUES (' . (int)$userId . ', ' . (int)$tagId . ', 1)');
            }
        }
    }

    /**
     * Undo a user's tagging of an object.
     *
     * @param mixed       $userId    The user who tagged the object.
     * @param mixed       $objectId  The object to remove the tag from.
     * @param array       $tags      An array of tag name or ids to remove.
     */
    public function untag($userId, $objectId, $tags)
    {
        // Ensure parameters
        $userId = current($this->_userManager->ensureUsers($userId));
        $objectId = $this->_ensureObject($objectId);

        foreach ($this->ensureTags($tags) as $tagId) {
            if ($this->_db->delete('DELETE FROM ' . $this->_t('tagged') . ' WHERE user_id = ? AND object_id = ? AND tag_id = ?', array($userId, $objectId, $tagId))) {
                $this->_db->update('UPDATE ' . $this->_t('tag_stats') . ' SET count = count - 1 WHERE tag_id = ?', array($tagId));
                $this->_db->update('UPDATE ' . $this->_t('user_tag_stats') . ' SET count = count - 1 WHERE user_id = ? AND tag_id = ?', array($userId, $tagId));
            }
        }

        // Cleanup
        $this->_db->delete('DELETE FROM ' . $this->_t('tag_stats') . ' WHERE count = 0');
        $this->_db->delete('DELETE FROM ' . $this->_t('user_tag_stats') . ' WHERE count = 0');
    }

    /**
     * Remove all occurrences of a specific tag from an object regardless of
     * the username who tagged the object originally.
     *
     * @param mixed  $obejctId  The object identifier @see Content_Tagger::tag()
     * @param mixed  $tags      The tags to remove. @see Content_Tagger::tag()
     *
     * @return void
     */
    public function removeTagFromObject($objectId, $tags)
    {
        $objectId = $this->_ensureObject($objectId);
        if (!is_array($tags)) {
            $tags = array($tags);
        }

        foreach ($this->ensureTags($tags) as $tagId) {
            // Get the users who have tagged this so we can update the stats
            $users = $this->_db->selectValues('SELECT user_id, tag_id FROM ' . $this->_t('tagged') . ' WHERE object_id = ? AND tag_id = ?', array($objectId, $tagId));

            // Delete the tags
            if ($this->_db->delete('DELETE FROM ' . $this->_t('tagged') . ' WHERE object_id = ? AND tag_id = ?', array($objectId, $tagId))) {
                // Update the stats
                $this->_db->update('UPDATE ' . $this->_t('tag_stats') . ' SET count = count - ' . count($users) . ' WHERE tag_id = ?', array($tagId));
                $this->_db->update('UPDATE ' . $this->_t('user_tag_stats') . ' SET count = count - 1 WHERE user_id IN(' . str_repeat('?, ', count($users) - 1) . '?) AND tag_id = ?', array_merge($users, array($tagId)));

                // Housekeeping
                $this->_db->delete('DELETE FROM ' . $this->_t('tag_stats') . ' WHERE count = 0');
                $this->_db->delete('DELETE FROM ' . $this->_t('user_tag_stats') . ' WHERE count = 0');
            }
        }

    }

    /**
     * Retrieve tags based on criteria.
     *
     * @param array  $args  Search criteria:
     *   q          Starts-with search on tag_name.
     *   limit      Maximum number of tags to return.
     *   offset     Offset the results. Only useful for paginating, and not recommended.
     *   userId     Only return tags that have been applied by a specific user.
     *   typeId     Only return tags that have been applied by a specific object type.
     *   objectId   Only return tags that have been applied to a specific object.
     *
     * @return array  An array of tags, id => name.
     */
    public function getTags($args)
    {
        if (isset($args['objectId'])) {
            // Don't create the object just because we're trying to load an
            // objects's tags - just check if the object is there. Assume if we
            // have an integer, it's a valid object_id.
            if (is_array($args['objectId'])) {
                $args['objectId'] = $this->_objectManager->exists($args['objectId']['object'], $args['objectId']['type']);
            }
            if (!$args['objectId']) {
                return array();
            }

            $sql = 'SELECT DISTINCT t.tag_id AS tag_id, tag_name FROM ' . $this->_t('tags') . ' AS t INNER JOIN ' . $this->_t('tagged') . ' AS tagged ON t.tag_id = tagged.tag_id AND tagged.object_id = ' . (int)$args['objectId'];
        } elseif (isset($args['userId']) && isset($args['typeId'])) {
            $args['userId'] = current($this->_userManager->ensureUsers($args['userId']));
            $args['typeId'] = current($this->_typeManager->ensureTypes($args['typeId']));
            $sql = 'SELECT DISTINCT t.tag_id AS tag_id, tag_name FROM ' . $this->_t('tags') . ' AS t INNER JOIN ' . $this->_t('tagged') . ' AS tagged ON t.tag_id = tagged.tag_id AND tagged.user_id = ' . (int)$args['userId'] . ' INNER JOIN ' . $this->_t('objects') . ' objects ON tagged.object_id = objects.object_id AND objects.type_id = ' . (int)$args['typeId'];
        } elseif (isset($args['userId'])) {
            $args['userId'] = current($this->_userManager->ensureUsers($args['userId']));
            $sql = 'SELECT DISTINCT t.tag_id AS tag_id, tag_name FROM ' . $this->_t('tagged') . ' AS tagged INNER JOIN ' . $this->_t('tags') . ' AS t ON tagged.tag_id = t.tag_id WHERE tagged.user_id = ' . (int)$args['userId'];
            $haveWhere = true;
        } elseif (isset($args['typeId'])) {
            $args['typeId'] = current($this->_typeManager->ensureTypes($args['typeId']));
            $sql = 'SELECT DISTINCT t.tag_id AS tag_id, tag_name FROM ' . $this->_t('tagged') . ' AS tagged INNER JOIN ' . $this->_t('objects') . ' AS objects ON tagged.object_id = objects.object_id AND objects.type_id = ' . (int)$args['typeId'] . ' INNER JOIN ' . $this->_t('tags') . ' AS t ON tagged.tag_id = t.tag_id';
        } elseif (isset($args['tagId'])) {
            $radius = isset($args['limit']) ? (int)$args['limit'] : $this->_defaultRadius;
            unset($args['limit']);

            $inner = $this->_db->addLimitOffset('SELECT object_id FROM ' . $this->_t('tagged') . ' WHERE tag_id = ' . (int)$args['tagId'], array('limit' => $radius));
            $sql = $this->_db->addLimitOffset('SELECT DISTINCT tagged2.tag_id AS tag_id, tag_name FROM (' . $inner . ') AS tagged1 INNER JOIN ' . $this->_t('tagged') . ' AS tagged2 ON tagged1.object_id = tagged2.object_id INNER JOIN ' . $this->_t('tags') . ' AS t ON tagged2.tag_id = t.tag_id', array('limit' => $args['limit']));
        } else {
            $sql = 'SELECT DISTINCT t.tag_id, tag_name FROM ' . $this->_t('tags') . ' AS t JOIN ' . $this->_t('tagged') . ' AS tagged ON t.tag_id = tagged.tag_id';
        }

        if (isset($args['q']) && strlen($args['q'])) {
            // @TODO tossing a where clause in won't work with all query modes
            $sql .= (!empty($haveWhere) ? ' AND' : ' WHERE') .  ' tag_name LIKE ' . $this->_db->quoteString($args['q'] . '%');
        }

        if (isset($args['limit'])) {
            $sql = $this->_db->addLimitOffset($sql, array('limit' => $args['limit'], 'offset' => isset($args['offset']) ? $args['offset'] : 0));
        }

        return $this->_db->selectAssoc($sql);
    }

    /**
     * Generate a tag cloud. Same syntax as getTags, except that fetching a
     * cloud for a userId + objectId combination doesn't make sense - the counts
     * would all be one. In addition, this method returns counts for each tag.
     *
     * @param array  $args  Search criteria:
     *   limit      Maximum number of tags to return.
     *   offset     Offset the results. Only useful for paginating, and not recommended.
     *   userId     Only return tags that have been applied by a specific user.
     *   typeId     Only return tags that have been applied by a specific object type.
     *   objectId   Only return tags that have been applied to a specific object.
     *
     * @return array  An array of hashes, each containing tag_id, tag_name, and count.
     */
    public function getTagCloud($args = array())
    {
        if (isset($args['objectId'])) {
            $args['objectId'] = $this->_ensureObject($args['objectId']);
            $sql = 'SELECT t.tag_id AS tag_id, tag_name, COUNT(*) AS count FROM ' . $this->_t('tagged') . ' AS tagged INNER JOIN ' . $this->_t('tags') . ' AS t ON tagged.tag_id = t.tag_id WHERE tagged.object_id = ' . (int)$args['objectId'] . ' GROUP BY t.tag_id';
        } elseif (isset($args['userId']) && isset($args['typeId'])) {
            $args['userId'] = current($this->_userManager->ensureUsers($args['userId']));
            $args['typeId'] = current($this->_typeManager->ensureTypes($args['typeId']));
            // This doesn't use a stat table, so may be slow.
            $sql = 'SELECT t.tag_id AS tag_id, tag_name, COUNT(*) AS count FROM ' . $this->_t('tagged') . ' AS tagged INNER JOIN ' . $this->_t('objects') . ' AS objects ON tagged.object_id = objects.object_id AND objects.type_id = ' . (int)$args['typeId'] . ' INNER JOIN ' . $this->_t('tags') . ' AS t ON tagged.tag_id = t.tag_id WHERE tagged.user_id = ' . (int)$args['user_id'] . ' GROUP BY t.tag_id';
        } elseif (isset($args['userId'])) {
            $args['userId'] = current($this->_userManager->ensureUsers($args['userId']));
            $sql = 'SELECT t.tag_id AS tag_id, tag_name, count FROM ' . $this->_t('tagged') . ' AS tagged INNER JOIN ' . $this->_t('tags') . ' AS t ON tagged.tag_id = t.tag_id INNER JOIN ' . $this->_t('user_tag_stats') . ' AS uts ON t.tag_id = uts.tag_id AND uts.user_id = ' . (int)$args['userId'] . ' GROUP BY t.tag_id, tag_name, count';
        } elseif (isset($args['typeId'])) {
            $args['typeId'] = current($this->_typeManager->ensureTypes($args['typeId']));
            // This doesn't use a stat table, so may be slow.
            $sql = 'SELECT t.tag_id AS tag_id, tag_name, COUNT(*) AS count FROM ' . $this->_t('tagged') . ' AS tagged INNER JOIN ' . $this->_t('objects') . ' AS objects ON tagged.object_id = objects.object_id AND objects.type_id = ' . (int)$args['typeId'] . ' INNER JOIN ' . $this->_t('tags') . ' AS t ON tagged.tag_id = t.tag_id GROUP BY t.tag_id';
        } else {
            $sql = 'SELECT t.tag_id AS tag_id, tag_name, count FROM ' . $this->_t('tagged') . ' AS tagged INNER JOIN ' . $this->_t('tags') . ' AS t ON tagged.tag_id = t.tag_id INNER JOIN ' . $this->_t('tag_stats') . ' AS ts ON t.tag_id = ts.tag_id GROUP BY t.tag_id';
        }

        if (isset($args['limit'])) {
            $sql = $this->_db->addLimitOffset($sql . ' ORDER BY count DESC', array('limit' => $args['limit'], 'offset' => isset($args['offset']) ? $args['offset'] : 0));
        }

        return $this->_db->selectAll($sql);
    }

    /**
     * Get the most recently used tags.
     *
     * @param array  $args  Search criteria:
     *   limit      Maximum number of tags to return.
     *   offset     Offset the results. Only useful for paginating, and not recommended.
     *   userId     Only return tags that have been used by a specific user.
     *   typeId     Only return tags applied to objects of a specific type.
     *
     * @return array
     */
    public function getRecentTags($args = array())
    {
        $sql = 'SELECT tagged.tag_id AS tag_id, tag_name, MAX(created) AS created FROM ' . $this->_t('tagged') . ' AS tagged INNER JOIN ' . $this->_t('tags') . ' AS t ON tagged.tag_id = t.tag_id';
        if (isset($args['typeId'])) {
            $args['typeId'] = current($this->_typeManager->ensureTypes($args['typeId']));
            $sql .= ' INNER JOIN ' . $this->_t('objects') . ' AS objects ON tagged.object_id = objects.object_id AND objects.type_id = ' . (int)$args['typeId'];
        }
        if (isset($args['userId'])) {
            $args['userId'] = current($this->_userManager->ensureUsers($args['userId']));
            $sql .= ' WHERE tagged.user_id = ' . (int)$args['userId'];
        }
        $sql .= ' GROUP BY tagged.tag_id ORDER BY created DESC';

        if (isset($args['limit'])) {
            $sql = $this->_db->addLimitOffset($sql, array('limit' => $args['limit'], 'offset' => isset($args['offset']) ? $args['offset'] : 0));
        }

        return $this->_db->selectAll($sql);
    }

    /**
     * Get objects matching search criteria.
     *
     * @param array  $args  Search criteria:
     *   limit      Maximum number of objects to return.
     *   offset     Offset the results. Only useful for paginating, and not recommended.
     *   tagId      Return objects related through one or more tags.
     *   notTagId   Don't return objects tagged with one or more tags.
     *   typeId     Only return objects with a specific type.
     *   objectId   Return objects with the same tags as $objectId.
     *   userId     Limit results to objects tagged by a specific user.
     *
     * @return array  An array of object ids.
     */
    public function getObjects($args)
    {
        if (isset($args['objectId'])) {
            $args['objectId'] = current($this->_objectManager->ensureObject($args['objectId']));
            $radius = isset($args['radius']) ? (int)$args['radius'] : $this->_defaultRadius;
            $inner = $this->_db->addLimitOffset('SELECT tag_id FROM ' . $this->_t('tagged') . ' WHERE object_id = ' . (int)$objectId, array('limit' => $radius));
            $sql = $this->_db->addLimitOffset('SELECT tagged2.object_id AS object_id, object_name FROM (' . $inner . ') AS t1 INNER JOIN ' . $this->_tagged . ' AS tagged2 ON t1.tag_id = t2.tag_id INNER JOIN ' . $this->_t('objects') . ' AS objects ON objects.object_id = tagged.object_id WHERE t2.object_id != ' . (int)$objectId . ' GROUP BY t2.object_id', array('limit' => $radius));
        } elseif (isset($args['tagId'])) {
            $tags = is_array($args['tagId']) ? array_values($args['tagId']) : array($args['tagId']);
            $count = count($tags);
            if (!$count) {
                return array();
            }

            $notTags = isset($args['notTagId']) ? (is_array($args['notTagId']) ? array_values($args['notTagId']) : array($args['notTagId'])) : array();
            $notCount = count($notTags);

            $sql = 'SELECT DISTINCT tagged.object_id AS object_id, object_name FROM ' . $this->_t('tagged') . ' AS tagged INNER JOIN ' . $this->_t('objects') . ' AS objects ON objects.object_id = tagged.object_id';

            if (!empty($args['typeId'])) {
                $args['typeId'] = $this->_typeManager->ensureTypes($args['typeId']);
            }

            if ($count > 1) {
                for ($i = 1; $i < $count; $i++) {
                    $sql .= ' INNER JOIN ' . $this->_t('tagged') . ' AS tagged' . $i . ' ON tagged.object_id = tagged' . $i . '.object_id';
                }
            }

            if ($notCount) {
                // Left joins for tags we want to exclude.
                for ($j = 0; $j < $notCount; $j++) {
                    $sql .= ' LEFT JOIN ' . $this->_t('tagged') . ' AS not_tagged' . $j . ' ON tagged.object_id = not_tagged' . $j . '.object_id AND not_tagged' . $j . '.tag_id = ' . (int)$notTags[$j];
                }
            }

            $sql .= ' WHERE tagged.tag_id = ' . (int)$tags[0];

            if ($count > 1) {
                for ($i = 1; $i < $count; $i++) {
                    $sql .= ' AND tagged' . $i . '.tag_id = ' . (int)$tags[$i];
                }
            }
            if ($notCount) {
                for ($j = 0; $j < $notCount; $j++) {
                    $sql .= ' AND not_tagged' . $j . '.object_id IS NULL';
                }
            }

            if (!empty($args['typeId']) && count($args['typeId'])) {
                $sql .= ' AND objects.type_id IN (' . implode(', ', $args['typeId']) . ')';
            }

            if (array_key_exists('userId', $args)) {
                $args['userId'] = $this->_userManager->ensureUsers($args['userId']);
                $sql .= ' AND tagged.user_id IN ( ' . implode(', ', $args['userId']) . ')';
            }
        }

        if (isset($args['limit'])) {
            $sql = $this->_db->addLimitOffset($sql, array('limit' => $args['limit'], 'offset' => isset($args['offset']) ? $args['offset'] : 0));
        }

        return $this->_db->selectAssoc($sql);
    }

    /**
     * Return objects related to the given object via tags, along with a
     * similarity rank.
     *
     * @param array $args
     *   limit      Maximum number of objects to return (default 10).
     *   userId     Only return objects that have been tagged by a specific user.
     *   typeId     Only return objects of a specific type.
     *   objectId   The object to find relations for.
     *   threshold  Number of tags-in-common objects must have to match (default 1).
     *
     * @return array
     */
    public function getSimilarObjects($args)
    {
        $object_id = $this->_ensureObject($args['objectId']);

        /* TODO */
        $threshold = intval($threshold);
        $max_objects = intval($max_objects);
        if (!isset($object_id) || !($object_id > 0)) {
            return $retarr;
        }
        if ($threshold <= 0) {
            return $retarr;
        }
        if ($max_objects <= 0) {
            return $retarr;
        }

        // Pass in a zero-limit to get all tags.
        $tagObjects = $this->get_tags_on_object($object_id, 0, 0);

        $tagArray = array();
        foreach ($tagObjects as $tagObject) {
            $tagArray[] = $db->Quote($tagObject['tag']);
        }
        $tagArray = array_unique($tagArray);

        $numTags = count($tagArray);
        if ($numTags == 0) {
            return $retarr; // Return empty set of matches
        }

        $tagList = implode(',', $tagArray);

        $prefix = $this->_table_prefix;

        $sql = "SELECT matches.object_id, COUNT(matches.object_id) AS num_common_tags
            FROM ${prefix}freetagged_objects AS matches
            INNER JOIN ${prefix}freetags AS tags ON (tags.id = matches.tag_id)
            WHERE tags.tag IN ($tagList)
            GROUP BY matches.object_id
            HAVING num_common_tags >= $threshold
            ORDER BY num_common_tags DESC
            LIMIT 0, $max_objects
            ";

        $rs = $db->Execute($sql) or die("Syntax Error: $sql, Error: " . $db->ErrorMsg());
        while (!$rs->EOF) {
            $retarr[] = array (
                'object_id' => $rs->fields['object_id'],
                'strength' => ($rs->fields['num_common_tags'] / $numTags)
                );
            $rs->MoveNext();
        }

        return $retarra;
    }

    /**
     * Get the most recently tagged objects.
     *
     * @param array  $args  Search criteria:
     *   limit      Maximum number of objects to return.
     *   offset     Offset the results. Only useful for paginating, and not recommended.
     *   userId     Only return objects that have been tagged by a specific user.
     *   typeId     Only return objects of a specific object type.
     *
     * @return array
     */
    public function getRecentObjects($args = array())
    {
        $sql = 'SELECT tagged.object_id AS object_id, MAX(created) AS created FROM ' . $this->_t('tagged') . ' AS tagged';
        if (isset($args['typeId'])) {
            $args['typeId'] = current($this->_typeManager->ensureTypes($args['typeId']));
            $sql .= ' INNER JOIN ' . $this->_t('objects') . ' AS objects ON tagged.object_id = objects.object_id AND objects.type_id = ' . (int)$args['typeId'];
        }
        if (isset($args['userId'])) {
            $args['userId'] = current($this->_userManager->ensureUsers($args['userId']));
            $sql .= ' WHERE tagged.user_id = ' . (int)$args['userId'];
        }
        $sql .= ' GROUP BY tagged.object_id ORDER BY created DESC';

        if (isset($args['limit'])) {
            $sql = $this->_db->addLimitOffset($sql, array('limit' => $args['limit'], 'offset' => isset($args['offset']) ? $args['offset'] : 0));
        }

        return $this->_db->selectAll($sql);
    }

    /**
     * Find users through objects, tags, or other users.
     */
    public function getUsers($args)
    {
        if (isset($args['objectId'])) {
            $args['objectId'] = $this->_ensureObject($args['objectId']);
            $sql = 'SELECT t.user_id, user_name FROM ' . $this->_t('tagged') . ' AS t INNER JOIN ' . $this->_t('users') . ' AS u ON t.user_id = u.user_id WHERE object_id = ' . (int)$args['objectId'];
        } elseif (isset($args['userId'])) {
            $args['userId'] = current($this->_userManager->ensureUsers($args['userId']));
            $radius = isset($args['radius']) ? (int)$args['radius'] : $this->_defaultRadius;
            $sql = 'SELECT others.user_id, user_name FROM ' . $this->_t('tagged') . ' AS others INNER JOIN ' . $this->_t('users') . ' AS u ON u.user_id = others.user_id INNER JOIN (SELECT tag_id FROM ' . $this->_t('tagged') . ' WHERE user_id = ' . (int)$args['userId'] . ' GROUP BY tag_id HAVING COUNT(tag_id) >= ' . $radius . ') AS self ON others.tag_id = self.tag_id GROUP BY others.user_id';
        } elseif (isset($args['tagId'])) {
            $tags = $this->ensureTags($args['tagId']);
            //$tags = is_array($args['tagId']) ? array_values($args['tagId']) : array($args['tagId']);
            $count = count($tags);
            if (!$count) {
                return array();
            }

            $notTags = isset($args['notTagId']) ? (is_array($args['notTagId']) ? array_values($args['notTagId']) : array($args['notTagId'])) : array();
            $notCount = count($notTags);

            $sql = 'SELECT DISTINCT tagged.user_id, user_name  FROM ' . $this->_t('tagged') . ' AS tagged INNER JOIN ' . $this->_t('users') . ' AS u ON u.user_id = tagged.user_id ';
            if ($count > 1) {
                for ($i = 1; $i < $count; $i++) {
                    $sql .= ' INNER JOIN ' . $this->_t('tagged') . ' AS tagged' . $i . ' ON tagged.user_id = tagged' . $i . '.user_id';
                }
            }
            if ($notCount) {
                // Left joins for tags we want to exclude.
                for ($j = 0; $j < $notCount; $j++) {
                    $sql .= ' LEFT JOIN ' . $this->_t('tagged') . ' AS not_tagged' . $j . ' ON tagged.user_id = not_tagged' . $j . '.user_id AND not_tagged' . $j . '.tag_id = ' . (int)$notTags[$j];
                }
            }

            $sql .= ' WHERE tagged.tag_id = ' . (int)$tags[0];

            if ($count > 1) {
                for ($i = 1; $i < $count; $i++) {
                    $sql .= ' AND tagged' . $i . '.tag_id = ' . (int)$tags[$i];
                }
            }
            if ($notCount) {
                for ($j = 0; $j < $notCount; $j++) {
                    $sql .= ' AND not_tagged' . $j . '.user_id IS NULL';
                }
            }
        }

        if (isset($args['limit'])) {
            $sql = $this->_db->addLimitOffset($sql, array('limit' => $args['limit'], 'offset' => isset($args['offset']) ? $args['offset'] : 0));
        }

        return $this->_db->selectAssoc($sql);
    }

    /**
     * Get the users who have most recently tagged objects.
     *
     * @param array  $args  Search criteria:
     *   limit      Maximum number of users to return.
     *   offset     Offset the results. Only useful for paginating, and not recommended.
     *   typeId     Only return users who have tagged objects of a specific object type.
     *
     * @return array
     */
    public function getRecentUsers($args = array())
    {
        $sql = 'SELECT tagged.user_id AS user_id, MAX(created) AS created FROM ' . $this->_t('tagged') . ' AS tagged';
        if (isset($args['typeId'])) {
            $args['typeId'] = current($this->_typeManager->ensureTypes($args['typeId']));
            $sql .= ' INNER JOIN ' . $this->_t('objects') . ' AS objects ON tagged.object_id = objects.object_id AND objects.type_id = ' . (int)$args['typeId'];
        }
        $sql .= ' GROUP BY tagged.user_id ORDER BY created DESC';

        if (isset($args['limit'])) {
            $sql = $this->_db->addLimitOffset($sql, array('limit' => $args['limit'], 'offset' => isset($args['offset']) ? $args['offset'] : 0));
        }

        return $this->_db->selectAll($sql);
    }

    /**
     * Return users related to a given user along with a similarity rank.
     */
    public function getSimilarUsers($args)
    {
        $args['userId'] = current($this->_userManager->ensureUsers($args['userId']));
        $radius = isset($args['radius']) ? (int)$args['radius'] : $this->_defaultRadius;
        $sql = 'SELECT others.user_id, (others.count - self.count) AS rank FROM ' . $this->_t('user_tag_stats') . ' AS others INNER JOIN (SELECT tag_id, count FROM ' . $this->_t('user_tag_stats') . ' WHERE user_id = ' . (int)$args['userId'] . ' AND count >= ' . $radius . ') AS self ON others.tag_id = self.tag_id ORDER BY rank DESC';

        if (isset($args['limit'])) {
            $sql = $this->_db->addLimitOffset($sql, array('limit' => $args['limit']));
        }

        return $this->_db->selectAssoc($sql);
    }

    /**
     * Ensure that an array of tags exist, create any that don't, and
     * return ids for all of them.
     *
     * @param array $tags  Array of tag names or ids.
     *
     * @return array  Array of tag ids.
     */
    public function ensureTags($tags)
    {
        if (!is_array($tags)) {
            $tags = array($tags);
        }

        $tagIds = array();
        $tagText = array();

        // Anything already typed as an integer is assumed to be a tag id.
        foreach ($tags as $tagIndex => $tag) {
            if (is_int($tag)) {
                $tagIds[$tagIndex] = $tag;
            } else {
                $tagText[$tag] = $tagIndex;
            }
        }

        // Get the ids for any tags that already exist.
        if (count($tagText)) {
            foreach ($this->_db->selectAll('SELECT tag_id, tag_name FROM ' . $this->_t('tags') . ' WHERE tag_name IN ('.implode(',', array_map(array($this->_db, 'quote'), array_keys($tagText))).')') as $row) {
                $tagTextCopy = $tagText;
                foreach ($tagTextCopy as $tag => $tagIndex) {
                    if (strtolower($row['tag_name']) == strtolower($tag)) {
                        unset($tagText[$tag]);
                        break;
                    }
                }
                $tagIds[$tagIndex] = $row['tag_id'];
            }
        }

        // Create any tags that didn't already exist
        foreach ($tagText as $tag => $tagIndex) {
            $tagIds[$tagIndex] = $this->_db->insert('INSERT INTO ' . $this->_t('tags') . ' (tag_name) VALUES (' . $this->_db->quote($tag) . ')');
        }

        return $tagIds;
    }

    /**
     * Split a string into an array of tag names, respecting tags with spaces
     * and ones that are quoted in some way. For example:
     *   this, "somecompany, llc", "and ""this"" w,o.rks", foo bar
     *
     * Would parse to:
     *   array('this', 'somecompany, llc', 'and "this" w,o.rks', 'foo bar')
     *
     * @param string $text  String to split into 1 or more tags.
     *
     * @return array        Split tag array.
     */
    public function splitTags($text)
    {
        // From http://drupal.org/project/community_tags
        $regexp = '%(?:^|,\ *)("(?>[^"]*)(?>""[^"]* )*"|(?: [^",]*))%x';
        preg_match_all($regexp, $text, $matches);

        $tags = array();
        foreach (array_unique($matches[1]) as $tag) {
            // Remove escape codes
            $tag = trim(str_replace('""', '"', preg_replace('/^"(.*)"$/', '\1', $tag)));
            if (strlen($tag)) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    /**
     * Convenience method - if $object is an array, it is taken as an array of
     * 'object' and 'type' to pass to objectManager::ensureObjects() if it's a
     * scalar value, it's taken as the object_id and simply returned.
     */
    protected function _ensureObject($object)
    {
        if (is_array($object)) {
            $object = current($this->_objectManager->ensureObjects(
                $object['object'], current($this->_typeManager->ensureTypes($object['type']))));
        }

        return (int)$object;
    }

    /**
     * Shortcut for getting a table name.
     *
     * @param string $tableType
     *
     * @return string  Configured table name.
     */
    protected function _t($tableType)
    {
        return $this->_db->quoteTableName($this->_tables[$tableType]);
    }

}

<?php

/**
 * Corresponds to regular function get_collection_for_item().
 *
 * @see get_collection_for_item()
 * @param Item $item
 * @return array An array of Collections.
 */
function multicollections_get_collections_for_item($item = null)
{
    if (is_null($item)) {
        $item = get_current_record('item');
    }
    $params = array(
        'subject_record_type' => 'Item',
        'object_record_type' => 'Collection',
        'subject_id' => $item->id,
        'property_id' => get_record_relations_property_id(DCTERMS, 'isPartOf'),
    );

    $collections = get_db()->getTable('RecordRelationsRelation')->findObjectRecordsByParams($params);
    return $collections;
}

/**
 * Corresponds to function get_records('Item', array('collection' => $collection->id)).
 *
 * @uses get_records()
 * @param $num
 * @return array An array of Items.
 */
function multicollections_get_items_in_collection($num = 10, $item_type = null)
{
    $collection = get_current_record('collection');;
    $db = get_db();
    $itemTable = $db->getTable('Item');
    $select = $itemTable->getSelect();
    $select->limit($num);
    if ($item_type) {
        $type = $db->getTable('ItemType')->findByName($item_type);
        $select->where('item_type_id = ?', $type->id);
    }

    $select->joinInner(
        array('rr' => $db->RecordRelationsRelation),
        'rr.subject_id = i.id',
        array()
    );
    $select->where('rr.object_id = ?', $collection->id);
    $select->where('rr.object_record_type = "Collection"');
    $select->where('rr.property_id = ?', get_record_relations_property_id(DCTERMS, 'isPartOf'));
    $select->where('rr.subject_record_type = "Item"');
    $items = $itemTable->fetchObjects($select);
    return $items;
}

/**
 * Corresponds to regular function loop('items').
 *
 * @see loop()
 * @param $num
 */
function multicollections_loop_items_in_collection($num = 10)
{
    // Cache this so we don't end up calling the DB query over and over again
    // inside the loop.
    static $loopIsRun = false;

    if (!$loopIsRun) {
        // Retrieve a limited # of items based on the collection given.
        $items = multicollections_get_items_in_collection($num);
        set_loop_records('items', $items);
         $loopIsRun = true;
    }

    $item = loop('items');
    if (!$item) {
        $loopIsRun = false;
    }
    return $item;
}

/**
 * Corresponds to regular function total_records().
 *
 * @see total_records()
 * @param Collection $collection
 * @return Integer
 */
function multicollections_total_items_in_collection($collection = null)
{
    if (is_null($collection)) {
        $collection = get_current_record('collection');;
    }
    $params = MultiCollectionsPlugin::defaultParams();
    $params['object_id'] = $collection->id;
    //$result = get_db()->getTable('RecordRelationsRelation')->findSubjectRecordsByParams($params, array('count' => true));
    $result = get_db()->getTable('RecordRelationsRelation')->countSubjectRecordsByParams($params);
    return $result;
}

/**
 * Corresponds to old regular function item_belongs_to_collection().
 *
 * @see item_belongs_to_collection
 * @param unknown_type $name
 * @param unknown_type $item
 * @return boolean
 */
function multicollections_item_belongs_to_collection($collectionName = null, $item = null)
{
     if (is_null($item)) {
         $item = get_current_item();
     }

     $collections = multicollections_get_collections_for_item($item);
     if ($collectionName) {
         foreach ($collections as $collection) {
             if ((metadata($collection, array('Dublin Core', 'Title')) == $collectionName)
                 && ($collection->public || has_permission('Collections', 'showNotPublic')) )  {
                 return true;
             }
         }
     } else {
         return ! empty($collections);
     }

     return false;
}

/**
 * Corresponds to regular function link_to_collection().
 *
 * @see link_to_collection()
 * @uses link_to()
 * @param string $text text to use for the title of the collection.  Default
 * behavior is to use the name of the collection.
 * @param array $props Set of attributes to use for the link.
 * @param array $action The action to link to for the collection.
 * @param array $collectionObj Collection record can be passed to this to
 * override the collection object retrieved by get_current_record().
 * @return string
 */
function multicollections_link_to_collection($text = null, $props = array(), $action = 'show', $collectionObj = null)
{
    if (is_null($collectionObj)) {
        $collectionObj = get_current_record('collection');
    }

    $collectionTitle = metadata($collectionObj, array('Dublin Core', 'Title'));
    $text = !empty($text) ? $text : $collectionTitle;
    return link_to($collectionObj, $action, $text, $props);
}

/**
 * Corresponds to regular function link_to_items_browse().
 *
 * @see link_to_items_browse()
 * @uses link_to()
 * @param string|null $text
 * @param array $props
 * @param string $action
 * @param Collection $collectionObj
 * @return string
 */
function multicollections_link_to_items_in_collection($text = null, $props = array(),
    $action = 'browse', $collectionObj = null
) {
    if (!$collectionObj) {
        $collectionObj = get_current_record('collection');
    }

    $queryParams = array();
    $queryParams['multi-collection'] = $collectionObj->id;

    if ($text === null) {
        $text = multicollections_total_items_in_collection($collectionObj);
    }

    return link_to('items', $action, $text, $props, $queryParams);
}

/**
 * Corresponds to regular function loop('collections', $collections).
 *
 * @see loop()
 * @param $item
 * @return
 */
function multicollections_loop_collections_for_item($item = null)
{
    if (is_null($item)) {
        $item = get_current_record('item');
    }
    $collections = multicollections_get_collections_for_item($item);
    return loop('collections', $collections);
}

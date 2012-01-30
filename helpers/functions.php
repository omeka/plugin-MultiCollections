<?php

/**
 *
 * Corresponds to regular collections function get_collections_for_item()
 * @see get_collections_for_item()
 * @param Item $item
 */

function multicollections_get_collections_for_item($item = null)
{
    if (!$item) {
        $item = get_current_item();
    }
    $params = array(
        'subject_record_type' => 'Item',
        'object_record_type' => 'Collection',
        'subject_id' => $item->id,
        'property_id' => record_relations_property_id(DCTERMS, 'isPartOf')
    );

    $collections = get_db()->getTable('RecordRelationsRelation')->findObjectRecordsByParams($params);
    return $collections;
}

/**
 *
 * Corresponds to regular items function get_items(array('collection'=>$collection->id)) as used in
 * loop_items_in_collection()
 * @see loop_items_in_collection()
 * @see get_items()
 * @param $num
 */

function multicollections_get_items_in_collection($num = 10)
{
    $collection = get_current_collection();
    $db = get_db();
    $itemTable = $db->getTable('Item');
    $select = $itemTable->getSelect();
    $select->joinInner(
        array('rr' => $db->RecordRelationsRelation),
        'rr.subject_id = i.id',
        array()
    );
    $select->where('rr.object_id = ?', $collection->id);
    $select->where('rr.object_record_type = "Collection"');
    $select->where('rr.property_id = ?', record_relations_property_id(DCTERMS, 'isPartOf'));
    $select->where('rr.subject_record_type = "Item"');
    $items = $itemTable->fetchObjects($select);
    return $items;
}

/**
 *
 * Corresponds to regular collections function loop_items_in_collection()
 * @see loop_items_in_collection()
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
        set_items_for_loop($items);
        $loopIsRun = true;
    }

    $item = loop_items();
    if (!$item) {
        $loopIsRun = false;
    }
    return $item;
}

/**
 *
 * Corresponds to regular collections function total_items_in_collection()
 * @see total_items_in_collection()
 * @param Collection $collection
 */

function multicollections_total_items_in_collection($collection = null)
{
    if(is_null($collection)) {
        $collection = get_current_collection();
    }
    $params = MultiCollectionsPlugin::defaultParams();
   // unset($params['subject_id']);
    $params['object_id'] = $collection->id;
    $result = get_db()->getTable('RecordRelationsRelation')->findSubjectRecordsByParams($params, array('count'=> true));
    return $result;


}

/**
 *
 * Corresponds to regular items function item_belongs_to_collection
 * @see item_belongs_to_collection
 * @param unknown_type $name
 * @param unknown_type $item
 */

function multicollections_item_belongs_to_collection($name=null, $item=null)
{
     if(!$item) {
         $item = get_current_item();
     }

     $collections = multicollections_get_collections_for_item($item);
     foreach($collections as $collection) {
         if ($collection->name == $name){
             return true;
         }
     }
     return false;
}

function multicollections_link_to_items_in_collection($text = null, $props = array(), $action = 'browse', $collectionObj = null)
{
    if (!$collectionObj) {
        $collectionObj = get_current_collection();
    }

    $queryParams = array();
    $queryParams['multi-collection'] = $collectionObj->id;

    if ($text === null) {
        $text = multicollections_total_items_in_collection($collectionObj);
    }

    return link_to('items', $action, $text, $props, $queryParams);
}

function multicollections_link_to_collection($text=null, $props=array(), $action='show', $collectionObj = null)
{
    if (!$collectionObj) {
        $collectionObj = get_current_collection();
    }

    $collectionName = collection('name', array(), $collectionObj);

	$text = (!empty($text) ? $text : (!empty($collectionName) ? $collectionName : '[Untitled]'));
	$url = uri('multi-collections/multi-collections/show/id/' . $collectionObj->id);
	return "<a href='$url'>$collectionName</a>";
	return link_to($collectionObj, $action, $text, $props);
}
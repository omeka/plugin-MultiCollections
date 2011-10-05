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
    return get_db()->getTable('RecordRelationsRelation')->findObjectRecordsByParams($params);
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
    $params = MultiCollectionsPlugin::defaultParams();
    $items = get_db()->getTable('RecordRelationsRelation')->findObjectRecordsByParams($params, array('limit'=>$num));
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

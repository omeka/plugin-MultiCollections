<?php

class MultiCollectionsPlugin extends Omeka_Plugin_Abstract
{

    protected $_hooks = array(
        'install',
        'after_save_form_item',
        'admin_append_to_items_show_secondary',
        'item_browse_sql',
        'config_form',
        'config'
        );

    protected $_filters = array('admin_items_form_tabs', 'admin_navigation_main');

    protected $_options = null;

    public function hookInstall()
    {
        //check props I need

        if(!record_relations_property_id(DCTERMS, 'isPartOf')) {
            record_relations_install_properties(  array(
                  array(
                        'name' => 'Dublin Core',
                        'description' => 'Dublin Core Terms',
                        'namespace_uri' => DCTERMS,
                        'namespace_prefix' => 'dcterms',
                        'properties' => array(
                            array(
                                'local_part' => 'isPartOf',
                                'label' => 'is part of',
                                'description' => ''
                            )
                        )
                    )

              ));
        }
        //build existing Collection relations
        $relationTable = get_db()->getTable('RecordRelationsRelation');
        $props = self::defaultParams();
        $items = get_db()->getTable('Item')->findAll();
        foreach($items as $item) {
            $props['subject_id'] = $item->id;
            if(!is_null($item->collection_id) && $item->collection_id != 0) {
                $props['object_id'] = $item->collection_id;
                $relation = new RecordRelationsRelation();
                $relation->setProps($props);
                $relation->save();
            }

        }
    }

    public function hookConfig()
    {
        set_option('multicollections_override', $_POST['multicollections_override']);
    }

    public function hookConfigForm()
    {
        include 'config_form.php';
    }

    public function filterAdminNavigationMain($tabs)
    {
        $label = get_option('multicollections_override') ? 'Collections' : 'Multi-Collections';
        $tabs[$label] = uri('multi-collections/multi-collections/browse');
        return $tabs;
    }

    public function filterAdminItemsFormTabs($tabs, $item)
    {
        $db = get_db();
        $collection = get_collection_for_item($item);
        $relationTable = $db->getTable('RecordRelationsRelation');
        $params = self::defaultParams();
        if(isset($item->id) && $item->id != null) {
            $params['subject_id'] = $item->id;
        }

        $multicollections = $relationTable->findObjectRecordsByParams($params, array('indexById'=>true));
        //if already in a collection the usual way add the record relation for it immediately for display
        if($collection) {
            if(! isset($multicollections[$collection->id]) ) {
                $params['object_id'] = $collection->id;
                $params['public'] = true;
                $newMcRelation = new RecordRelationsRelation();
                $newMcRelation->setProps($params);
                $newMcRelation->save();
                $multicollections[$collection->id] = $newMcRelation;
            }
        }
        $allCollections = $db->getTable('Collection')->findPairsForSelectForm();
        $html = "<h3>Check the Collections for the Item</h3>";
        $html .= __v()->formMultiCheckbox('multicollections_collections', array_keys($multicollections), null, $allCollections , '');
        $label = get_option('multicollections_override') ? 'Collection' : 'Multi-Collections';
        $tabs['Collections'] = $html;
        unset($tabs['Collection']);
        return $tabs;
    }

    public function hookAfterSaveFormItem($item, $post)
    {
        $relationTable = get_db()->getTable('RecordRelationsRelation');
        $props = self::defaultParams();
        $props['subject_id'] = $item->id;
        /* quick an dirty fix on the update item */
        $table = get_db()->getTable('RecordRelationsRelation')->getTableName();
        $query = "DELETE FROM $table WHERE {$table}.subject_id = ? LIMIT 1";
        get_db()->delete($table, 'subject_id = '  . (int) $item->id);
        /*end hack*/        
        foreach($post['multicollections_collections'] as $collection_id) {
            $props['object_id'] = $collection_id;
            if($relationTable->count($props) == 0) {
                $relation = new RecordRelationsRelation();
                $relation->setProps($props);
                $relation->save();
            }
        }
    }

    public function hookItemBrowseSql($select, $params)
    {
        if (($request = Zend_Controller_Front::getInstance()->getRequest())) {
            $db = get_db();
            $collection_id = $request->get('multi-collection');
            if (is_numeric($collection_id)) {
                $select->joinInner(
                    array('rr' => $db->RecordRelationsRelation),
                    'rr.subject_id = i.id',
                    array()
                );
                $select->where('rr.object_id = ?', $collection_id);
                $select->where('rr.object_record_type = "Collection"');
                $select->where('rr.property_id = ?', record_relations_property_id(DCTERMS, 'isPartOf'));
                $select->where('rr.subject_record_type = "Item"');
                $select->group('i.id');
            }
        }
    }

    public function hookAdminAppendToItemsShowSecondary($item)
    {
        $html = '<div class="info-panel">';
        $html .= "<h2>Multiple Collections</h2>";
        $html .= "<div>";
        $collections = multicollections_get_collections_for_item($item);
        set_collections_for_loop($collections);
        while(loop_collections()) {
            $collection = get_current_collection();
            $html .= "<p>";
            $html .= $collection->name;
            $html .= " Item count: " . multicollections_total_items_in_collection() ;
            $html .= "</p>";
        }
        $html .= "</div>";
        echo $html;
    }

    public static function defaultParams()
    {
        return array(
            'subject_record_type' => 'Item',
            'object_record_type' => 'Collection',
            'property_id' => record_relations_property_id(DCTERMS, 'isPartOf'),
            'public' => true
        );

    }
}


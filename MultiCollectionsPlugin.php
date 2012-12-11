<?php

class MultiCollectionsPlugin extends Omeka_Plugin_AbstractPlugin
{

    protected $_hooks = array(
        'install',
        'after_save_form_item',
        'admin_items_show_sidebar',
        'admin_items_panel_fields',
        'items_browse_sql',
        'config_form',
        'config'
        );
//TODO:check for override in setUp
    protected $_filters = array(
            'admin_navigation_main',
            'item_search_filters'
            );

    protected $_options = null;

    public function setUp()
    {
        parent::setUp();
        Zend_Controller_Front::getInstance()->registerPlugin(new MultiCollections_ControllerPlugin);        
        
    }
    
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

    public function filterItemSearchFilters($displayArray, $args)
    {        
        $request_array = $args['request_array'];
        if(isset($request_array['multi-collection'])) {
            $db = get_db();
            $collection = $db->getTable('Collection')->find($request_array['multi-collection']);
            $displayValue = strip_formatting(metadata($collection, array('Dublin Core', 'Title')));            
            $displayArray['collection'] = $displayValue;
        }        
        return $displayArray;
    }
    
    public function filterAdminNavigationMain($nav)
    {
        $nav[] = array(
                    'label' => get_option('multicollections_override') ? 'Collections' : 'Multi-Collections',
                    'uri' => url('multi-collections/multi-collections/browse')
                 );
        /*
        $label = get_option('multicollections_override') ? 'Collections' : 'Multi-Collections';
        $tabs[$label] = url('multi-collections/multi-collections/browse');
        */
        
        return $nav;
    }

    public function hookAdminItemsPanelFields($args)
    {
        $view = $args['view'];
        $item = $args['record'];
        $db = get_db();
        $relationTable = $db->getTable('RecordRelationsRelation');
        $params = self::defaultParams();
        if(isset($item->id) && $item->id != null) {
            $params['subject_id'] = $item->id;
        }
        
        if($item->exists()) {
            $multicollections = $relationTable->findObjectRecordsByParams($params, array('indexById'=>true));
            $values = array_keys($multicollections);
        } else {
            $values = array();
        }
        
        $allCollections = $db->getTable('Collection')->findPairsForSelectForm();
        $html = "<style type='text/css'>div#collection-form {display: none;} \n ";
        $html .= "div#multicollections-form div.inputs label {display:block; line-height: 1em; } ";
        $html .= "</style>";        
        $html .= '<div id="multicollections-form" class="field">';
        $label = get_option('multicollections_override') ? 'Collections' : 'Multi-Collections';
        $html .= $view->formLabel('collection-id', $label);
        $html .= '    <div class="inputs">';
        $html .= $view->formMultiCheckbox('multicollections_collections', $values, null, $allCollections , '');
        $html .= "</div></div>";

        echo $html;
    }

    public function hookAfterSaveFormItem($item, $args)    
    {
        $post = $args['post'];
        $relationTable = get_db()->getTable('RecordRelationsRelation');
        $props = self::defaultParams();
        $props['subject_id'] = $item->id;
        $currCollections = $relationTable->findBy($props);

        foreach($currCollections as $collection) {
            $collection->delete();
        }

        foreach($post['multicollections_collections'] as $collection_id) {
            $props['object_id'] = $collection_id;
            if($relationTable->count($props) == 0) {
                $relation = new RecordRelationsRelation();
                $relation->setProps($props);
                $relation->save();
            }
        }
    }

    public function hookItemsBrowseSql($args)
    {
        $select = $args['select'];
        $params = $args['params'];
        if (($request = Zend_Controller_Front::getInstance()->getRequest())) {
            $db = get_db();
            $collection_id = $request->get('multi-collection');
            $itemTable = $db->getTable('Item');
            $itemAlias = $itemTable->getTableAlias();
            if (is_numeric($collection_id)) {
                $select->joinInner(
                    array('record_relations_relations' => $db->RecordRelationsRelation),
                    "record_relations_relations.subject_id = $itemAlias.id",
                    array()
                );
                $select->where('record_relations_relations.object_id = ?', $collection_id);
                $select->where('record_relations_relations.object_record_type = "Collection"');
                $select->where('record_relations_relations.property_id = ?', get_record_relations_property_id(DCTERMS, 'isPartOf'));
                $select->where('record_relations_relations.subject_record_type = "Item"');
                $select->group("$itemAlias.id");
            }
        }
    }

    public function hookAdminItemsShowSidebar($args)
    {
        $item = $args['item'];
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
            'property_id' => get_record_relations_property_id(DCTERMS, 'isPartOf'),
            'public' => true
        );

    }
}


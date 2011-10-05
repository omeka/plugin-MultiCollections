<?php


if(class_exists('Omeka_Plugin_Abstract')) {

class MultiCollectionsPlugin extends Omeka_Plugin_Abstract
{
    
    protected $_hooks = array(
    	'install',
        'admin_append_to_items_show_secondary',
        
        );
                            
    protected $_filters = array( 'admin_items_form_tabs' );

    protected $_options = null;
    
    
    public function install()
    {
        //check props I need
        
        if(! record_relations_property_id(DCTERMS, 'isPartOf')) {
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
    }
    
    public function adminAppendToItemsShowSecondary($item)
    {
        $html = "<p>Multi collections will go here</p>";
        //return $html;
    }
    
    public function adminItemsFormTabs($tabs, $item)
    {
        $html = "<p>Multi collections will go here</p>";
        $tabs['Multiple Collections'] = $html;
        return $tabs;
    }
    
    
}

} else {
    
class MultiCollectionsPlugin
{
    
    protected $_hooks = array(
    	'install',
    	'after_save_form_item',
        'admin_append_to_items_show_secondary'
        );
                            
    protected $_filters = array('admin_items_form_tabs');

    protected $_options = null;
    
    public function install()
    {
        //check props I need
        
        if(! record_relations_property_id(DCTERMS, 'isPartOf')) {
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
    }
    
    public function adminItemsFormTabs($tabs, $item)
    {
        $db = get_db();
        $collection = get_collection_for_item($item);
        $params = self::defaultParams();
        $params['subject_id'] = $item->id;
        $multicollections = $db->getTable('RecordRelationsRelation')->findObjectRecordsByParams($params, true);

        //if already in a collection the usual way add the record relation for it immediately for display
        if($collection) {
            if(! isset($multicollections[$collection->id]) ) {
                $newMcRelation = new RecordRelationsRelation();
                $params['object_id'] = $collection->id;
                $params['public'] = true;
                $newMcRelation->setProps($params);
                $newMcRelation->save();
                $multicollections[$collection->id] = $newMcRelation;
            }
        }
        $allCollections = $db->getTable('Collection')->findPairsForSelectForm();
        $html = "<h3>Check the Collection for the Item</h3>";
        $html .= __v()->formMultiCheckbox('multicollections_collections', $multicollections, null, $allCollections , '');
        $tabs['Multiple Collections'] = $html;
        return $tabs;
    }
    
    public function afterSaveFormItem($item, $post)
    {
        $props = self::defaultParams();
        $props['subject_id'] = $item->id;
        foreach($post['multicollections_collections'] as $collection_id) {
            $props['object_id'] = $collection_id;
            $relation = new RecordRelationsRelation();
            $relation->setProps($props);
            $relation->save();
        }
    }
    
    public function adminAppendToItemsShowSecondary($item)
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
    
   public function __construct()
    {
        $this->_db = Omeka_Context::getInstance()->getDb();
        $this->_addHooks();
        $this->_addFilters();
    }
    
    /**
     * Set options with default values.
     *
     * Plugin authors may want to use this convenience method in their install
     * hook callback.
     */
    protected function _installOptions()
    {
        $options = $this->_options;
        if (!is_array($options)) {
            return;
        }
        foreach ($options as $name => $value) {
            // Don't set options without default values.
            if (!is_string($name)) {
                continue;
            }
            set_option($name, $value);
        }
    }
    
    /**
     * Delete all options.
     *
     * Plugin authors may want to use this convenience method in their uninstall
     * hook callback.
     */
    protected function _uninstallOptions()
    {
        $options = self::$_options;
        if (!is_array($options)) {
            return;
        }
        foreach ($options as $name => $value) {
            delete_option($name);
        }
    }
    
    /**
     * Validate and add hooks.
     */
    private function _addHooks()
    {
        $hookNames = $this->_hooks;
        if (!is_array($hookNames)) {
            return;
        }
        foreach ($hookNames as $hookName) {
            $functionName = Inflector::variablize($hookName);
            if (!is_callable(array($this, $functionName))) {
                throw new Exception('Hook callback "' . $functionName . '" does not exist.');
            }
            add_plugin_hook($hookName, array($this, $functionName));
        }
    }
    
    /**
     * Validate and add filters.
     */
    private function _addFilters()
    {
        $filterNames = $this->_filters;
        if (!is_array($filterNames)) {
            return;
        }
        foreach ($filterNames as $filterName) {
            $functionName = Inflector::variablize($filterName);
            if (!is_callable(array($this, $functionName))) {
                throw new Omeka_Plugin_Exception('Filter callback "' . $functionName . '" does not exist.');
            }
            add_filter($filterName, array($this, $functionName));
        }
    }
    
    public static function defaultParams()
    {
        return array(
            'subject_record_type' => 'Item',
            'object_record_type' => 'Collection',
           // 'subject_id' => $item->id,
          //  'object_id' => $collection->id,
            'property_id' => record_relations_property_id(DCTERMS, 'isPartOf'),
            'public' => true
        );
        
    }
}
    
    
    
}

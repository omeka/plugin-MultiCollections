<?php
/**
 * Multi Collections
 *
 * Adds multiple collection functionality to Omeka.
 *
 * @copyright Copyright 2011-2012 Roy Rosenzweig Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 * @package MultiCollections
 */

define('MULTICOLLECTIONS_DIR', dirname(__FILE__));
require_once MULTICOLLECTIONS_DIR . '/helpers/functions.php';
require_once MULTICOLLECTIONS_DIR . '/MultiCollections_ControllerPlugin.php';

/**
 * The MultiCollections plugin.
 * @package Omeka\Plugins\MultiCollections
 */
class MultiCollectionsPlugin extends Omeka_Plugin_AbstractPlugin
{
    /**
     * @var array Hooks for the plugin.
     */
    protected $_hooks = array(
        'install',
        'uninstall',
        'config_form',
        'config',
        'admin_items_panel_fields',
        'admin_items_show_sidebar',
        'after_save_item',
        'items_browse_sql',
    );

//TODO:check for override in setUp
    /**
     * @var array Filters for the plugin.
     */
    protected $_filters = array(
        'admin_navigation_main',
        'item_search_filters',
    );

    /**
     * @var array Options and their default values.
     */
    protected $_options = array(
        'multicollections_override' => false,
    );

    public function setUp()
    {
        parent::setUp();
        Zend_Controller_Front::getInstance()->registerPlugin(new MultiCollections_ControllerPlugin);
    }

    /**
     * Install the plugin.
     */
    public function hookInstall()
    {
        $this->_installOptions();

        // Check props I need.
        if (!get_record_relations_property_id(DCTERMS, 'isPartOf')) {
            record_relations_install_properties(array(
                array(
                    'name' => 'Dublin Core',
                    'description' => 'Dublin Core Terms',
                    'namespace_uri' => DCTERMS,
                    'namespace_prefix' => 'dcterms',
                    'properties' => array(
                        array(
                            'local_part' => 'isPartOf',
                            'label' => 'is part of',
                            'description' => '',
                        ),
                    ),
                ),
          ));
        }

        // Build existing Collection relations.
        $relationTable = $this->_db->getTable('RecordRelationsRelation');
        $props = self::defaultParams();
        $items = $this->_db->getTable('Item')->findAll();
        foreach ($items as $item) {
            $props['subject_id'] = $item->id;
            if (!is_null($item->collection_id) && $item->collection_id != 0) {
                $props['object_id'] = $item->collection_id;
                $relation = new RecordRelationsRelation();
                $relation->setProps($props);
                $relation->save();
            }
        }
    }

    /**
     * Uninstall the plugin.
     */
    public function hookUninstall()
    {
        $this->_uninstallOptions();
    }

    /**
     * Shows plugin configuration page.
     */
    public function hookConfigForm($args)
    {
        $view = $args['view'];
        echo $view->partial(
            'plugins/multi-collections-config-form.php',
            array(
                'view' => $view,
        ));
    }

    /**
     * Saves plugin configuration page.
     *
     * @param array Options set in the config form.
     */
    public function hookConfig($args)
    {
        $post = $args['post'];
        foreach ($post as $key => $value) {
            set_option($key, $value);
        }
    }

    public function hookAdminItemsPanelFields($args)
    {
        $view = $args['view'];
        $item = $args['record'];
        $db = $this->_db;
        $relationTable = $db->getTable('RecordRelationsRelation');
        $params = self::defaultParams();
        if (isset($item->id) && $item->id != null) {
            $params['subject_id'] = $item->id;
        }

        $values = array();
        if ($item->exists()) {
            $multicollections = $relationTable->findObjectRecordsByParams($params, array('indexById' => true));
            $values = array_keys($multicollections);
        }

        $allCollections = $db->getTable('Collection')->findPairsForSelectForm();
        $label = get_option('multicollections_override') ? __('Collections') : __('Multi-Collections');

        $html = '<style type="text/css">';
        $html .= 'div#collection-form {display: none;}';
        $html .= 'div#multicollections-form div.inputs label {display:block; line-height: 1em; font-weight: normal;}';
        $html .= 'div#multicollections-form div.inputs label input { margin-right: 6px;}';
        $html .= '</style>';
        $html .= '<div id="multicollections-form" class="field">';
        $html .= $view->formLabel('collection-id', $label);
        $html .= '<div class="inputs">';
        $html .= $view->formMultiCheckbox('multicollections_collections', $values, null, $allCollections, '');
        $html .= '</div>';
        $html .= '</div>';

        echo $html;
    }

    public function hookAdminItemsShowSidebar($args)
    {
        $item = $args['item'];

        $html = '<div class="info panel">';
        $html .= '<h4>' . __('Multiple Collections') . '</h4>';

        $collections = multicollections_get_collections_for_item($item);

        // No collection.
        if (empty($collections)) {
            $html .= '<p>' . __('No multiple collections') . '</p>';
        }
        // List of collections.
        else {
            $html .= '<ul>';
            foreach ($collections as $collection) {
                $html .= '<li>';
                $html .= __('%s [Items count: %d]',
                    link_to_collection(null, array(), 'show', $collection),
                    multicollections_total_items_in_collection($collection));
                $html .= '</li>';
            }
            $html .= '</ul>';
        }
        $html .= '</div>';

        echo $html;
    }

    public function hookAfterSaveItem($args)
    {
        // Only when saving form.
        if (!$args['post']) {
            return;
        }

        $item = $args['record'];
        $post = $args['post'];

        $relationTable = get_db()->getTable('RecordRelationsRelation');
        $props = self::defaultParams();
        $props['subject_id'] = $item->id;
        $currCollections = $relationTable->findBy($props);

        foreach ($currCollections as $collection) {
            $collection->delete();
        }

        foreach ($post['multicollections_collections'] as $collection_id) {
            $props['object_id'] = $collection_id;
            if ($relationTable->count($props) == 0) {
                $relation = new RecordRelationsRelation();
                $relation->setProps($props);
                $relation->save();
            }
        }
    }

    public function hookItemsBrowseSql($args)
    {
        $request = Zend_Controller_Front::getInstance()->getRequest();
        if (empty($request)) {
            return;
        }

        $collection_id = $request->get('multi-collection');
        if (!is_numeric($collection_id)) {
            return;
        }

        $select = $args['select'];
        $params = $args['params'];

        $db = $this->_db;
        $itemTable = $db->getTable('Item');
        $itemAlias = $itemTable->getTableAlias();
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

    /**
     * Append an entry to the admin navigation.
     *
     * @param array $nav
     * @return array
     */
    public function filterAdminNavigationMain($nav)
    {
        // Override default 'Collections' menu.
        if (get_option('multicollections_override')) {
            $uri = url('collections');
            foreach ($nav as $key => &$tab) {
                if ($tab['uri'] == $uri) {
                    $tab['uri'] = url('multi-collections/multi-collections/browse');
                    break;
                }
            }
        }
        // Distinct menus, so insert menu just after Collections.
        else {
            $uri = url('collections');
            foreach ($nav as $key => $tab) {
                if ($tab['uri'] == $uri) {
                    array_splice($nav, ++$key, 0, array(array(
                        'label' => __('Multi-Collections'),
                        'uri' => url('multi-collections/multi-collections/browse'),
                    )));
                    break;
                }
            }
        }
        return $nav;
    }

    public function filterItemSearchFilters($displayArray, $args)
    {
        $request_array = $args['request_array'];
        if (isset($request_array['multi-collection'])) {
            $collection = $this->_db->getTable('Collection')->find($request_array['multi-collection']);
            $displayValue = strip_formatting(metadata($collection, array('Dublin Core', 'Title')));
            $displayArray['collection'] = $displayValue;
        }
        return $displayArray;
    }

    public static function defaultParams()
    {
        return array(
            'subject_record_type' => 'Item',
            'object_record_type' => 'Collection',
            'property_id' => get_record_relations_property_id(DCTERMS, 'isPartOf'),
            'public' => true,
        );
    }
}


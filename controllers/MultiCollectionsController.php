<?php

require_once(APP_DIR . '/controllers/CollectionsController.php');

class MultiCollections_MultiCollectionsController extends CollectionsController
{

    public function showAction()
    {
        Omeka_Controller_AbstractActionController::showAction();
        $db = $this->_helper->db;
        $itemTable = $db->getTable('Item');
        $itemAlias = $itemTable->getTableAlias();
        $select = $itemTable->getSelectForFindBy(array(
                'collection' => $this->view->collection->id),
                 is_admin_theme() ? 10 : 5
                );
        $rrTable = $db->getTable('RecordRelationsRelation');
        $rrAlias = $rrTable->getTableAlias();
        $select->joinInner(
                        array($rrAlias => $rrTable->getTableName()),
                        "$rrAlias.subject_id = $itemAlias.id",
                        array()
        );
        $select->where("$rrAlias.object_id = ?", $this->view->collection->id);
        $select->where("$rrAlias.object_record_type = 'Collection'");
        $select->where("$rrAlias.property_id = ?", get_record_relations_property_id(DCTERMS, 'isPartOf'));
        $select->where("$rrAlias.subject_record_type = 'Item'");
        
        $this->view->items = $itemTable->fetchObjects($select);
        
    }
}
<?php

class MultiCollections_MultiCollectionsController extends Omeka_Controller_Action
{

    public function init()
    {

        if (version_compare(OMEKA_VERSION, '2.0-dev', '>=')) {
            $this->_helper->db->setDefaultModelName('Collection');
        } else {
            $this->_modelClass = 'Collection';
        }

    }


}
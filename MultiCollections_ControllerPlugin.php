<?php
class MultiCollections_ControllerPlugin extends Zend_Controller_Plugin_Abstract
{

    public function preDispatch(Zend_Controller_Request_Abstract $request)
    {
        if (get_option('multicollections_override')) {
            $params = $request->getParams();

            if ($params['controller'] == 'collections') {
                $redirect = $this->_getRedirect();
                $params['controller'] = 'multi-collections';
                $params['module'] = 'multi-collections';
                unset($params['admin']);
                debug(print_r($params, true));

                $request->setParam('controller', 'multi-collections');
                $request->setParam('module', 'multi-collections');
                //$redirect->gotoUrl('multi-collections/multi-collections/');
                //$redirect->gotoRoute($params);
                if (isset($params['id'])) {
                    $options = array('id' => $params['id']);
                } else {
                    $options = array();
                }

                $redirect->gotoSimple($params['action'], 'multi-collections', 'multi-collections', $options);
            }
        }
    }

    protected function _getRedirect()
    {
        return Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
    }
}

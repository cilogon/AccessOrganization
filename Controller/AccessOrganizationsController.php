<?php

App::uses("StandardController", "Controller");

class AccessOrganizationsController extends StandardController {
  public $name = "AccessOrganizations";

  public $uses = array('AccessOrganization.AccessOrganization');

  public $requires_co = true;

  function beforeFilter() {
    parent::beforeFilter();

    $this->Auth->allow('reply');
  }

  public function find() {
    $organizations = array();

    if(!empty($this->request->query['term'])) {
      $organizations = $this->AccessOrganization->search($this->cur_co['Co']['id'], $this->request->query['term'], 25);
    }

    $matches = array();

    if(count($organizations) > 100) {
      $matches[] = array(
        'value' => -1,
        'label' => 'Too many results, continue typing to narrow your search'
      );
    } else {
      foreach($organizations as $o) {
        $matches[] = array(
          'value' => $o['AccessOrganization']['id'],
          'label' => $o['AccessOrganization']['name']
        );
      }
    }

    $this->set('vv_access_organizations', $matches);
    $this->layout = 'ajax';
    $this->response->type('json');
    $this->render('/AccessOrganizations/json/find');
  }

  public function isAuthorized() {
    $roles = $this->Role->calculateCMRoles();

    $p = array();

    // Allow all access to the find action in support of AJAX calls.
    $p['find'] = true;

    // Administrators can view all Access Organizations.
    $p['index'] = ($roles['cmadmin'] || $roles['coadmin']);

    $this->set('permissions', $p);
    return $p[$this->action];
  }

}

<?php

class AccessOrganization extends AppModel {
  // Required by COmanage Plugins
  public $cmPluginType = "job";

  // Document foreign keys
  public $cmPluginHasMany = array();

  // Validation rules for table elements
  public $validate = array(
    'co_id' => array(
      'rule' => 'numeric',
      'required' => true,
      'allowEmpty' => false
    ),
    'organization_id' => array(
      'rule' => 'numeric',
      'required' => true,
      'allowEmpty' => false
    ),
    'name' => array(
      'rule' => array('validateInput'),
      'required' => true,
      'allowEmpty' => false
    ),
    'status' => array(
      'rule' => array('inList', array(
          AccessOrganizationStatusEnum::Active,
          AccessOrganizationStatusEnum::Inactive
        )
      )
    )
  );

  /**
   * Expose menu items.
   * 
   * @return Array with menu location type as key and array of labels, controllers, actions as values.
   */
  public function cmPluginMenus() {
    return array();
  }

  /**
   * @since  COmanage Registry v4.1.0
   * @return Array Array of supported parameters.
   */

  public function getAvailableJobs() {
    $availableJobs = array();

    $availableJobs['Sync'] = "Synchronize ACCESS Organizations with the ACCESS User Database";
    $availableJobs['Merge'] = "Merge ACCESS Organizations with similar names";

    return $availableJobs;
  }

  public function search($coId, $q, $limit) {
    // Tokenize $q on spaces
    $tokens = explode(" ", $q);

    $ret = array();

    // We take two loops through, the first time we only do a prefix search
    // (foo%). If that doesn't reach the search limit, we'll do an infix search
    // the second time around.

    // While this will return duplicate records, the controller
    // will filter them while collating results. It will, however, throw off
    // the limit calculation.

    for($i = 0; $i < 2; $i++) {
      $args = array();

      foreach($tokens as $t) {
          $args['conditions']['AND'][] = array(
            'OR' => array(
              'LOWER(AccessOrganization.name) LIKE' => ($i == 1 ? '%' : '') . strtolower($t) . '%'
            )
          );
      }
    }

    $args['conditions']['AccessOrganization.co_id'] = $coId;
    $args['conditions']['AccessOrganization.status'] = AccessOrganizationStatusEnum::Active;

    $args['order'] = array('AccessOrganization.name');
    $args['limit'] = $limit;
    $args['contain'] = false;

    $ret += $this->find('all', $args);

    return $ret;
  }
}

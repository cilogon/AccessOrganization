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

    return $availableJobs;
  }
}

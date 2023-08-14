<?php

App::uses("CoJobBackend", "Model");
App::uses("HttpServer", "Model");

class MergeJob extends CoJobBackend {
  // Required by COmanage Plugins
  public $cmPluginType = "job";

  // Association rules from this model to other models.
  public $hasMany = array(
    'AccessOrganization.AccessOrganization',
    'HttpServer'
  );

  // Current CO Job Object
  private $CoJob;

  // Current CO ID
  private $coId;

  /**
   * Execute the requested Job.
   *
   * @param  int   $coId    CO ID
   * @param  CoJob $CoJob   CO Job Object, id available at $CoJob->id
   * @param  array $params  Array of parameters, as requested via parameterFormat()
   * @throws InvalidArgumentException
   * @throws RuntimeException
   * @return void
   */
  public function execute($coId, $CoJob, $params) {
    $CoJob->update($CoJob->id, null, "full", null);

    $this->CoJob = $CoJob;
    $this->coId = $coId;

    $mergeFilePath = $params['csv'] ?? null;

    if(!empty($mergeFilePath)) {
      list($status, $summary) = $this->mergeByCsvFile($mergeFilePath);
    } else {
      list($status, $summary) = $this->mergeByQuery();
    }

    $CoJob->finish($CoJob->id, $summary, $status);
  }

  /**
   * Merge ACCESS Organizations using input from CSV file.
   *
   * @param str $mergeFilePath Full path to the CSV file containing merge data
   * @return array Array of status and summary to be passed to finish method
   */
  protected function mergeByCsvFile($mergeFilePath) {
    $mergeCsvFile = fopen($mergeFilePath, "r");

    // Throw away the first line.
    fgets($mergeCsvFile);

    $mergeObjects = array();

    // Process the CSV file.
    while(($line = fgets($mergeCsvFile)) !== false) {

      $lineExploded = explode(',', $line);

      $mergeObjects[] = array(
        "id"                     => $lineExploded[0],
        "keep_organization_id"   => $lineExploded[1],
        "delete_organization_id" => $lineExploded[2]
      );
    }

    fclose($mergeCsvFile);

    // Loop over and process each merge.
    $mergedCount = 0;
    foreach($mergeObjects as $m) {
      $mergedCount += $this->mergeOrganizations($m);
    }

    $status = JobStatusEnum::Complete;
    $summary = "Merged $mergedCount ACCESS Organizations";

    return array($status, $summary);
  }

  /**
   * Merge ACCESS Organizations using merged_organizations API endpoint.
   *
   * @return array Array of status and summary to be passed to finish method
   *
   */
  protected function mergeByQuery() {
    $CoJob = $this->CoJob;

    // Pull the ACCESS User Database using the configured HttpServer ID
    $httpServerId = Configure::read('AccessOrganization.db.HttpServer.id');

    $args = array();
    $args['conditions']['HttpServer.id'] = $httpServerId;
    $args['contain'] = false;

    $httpServer = $this->HttpServer->find('first', $args);

    // Configure curl libraries to query ACCESS Database API.
    $urlBase = $httpServer['HttpServer']['serverurl'];
    $url = $urlBase . '/merged_organizations';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_URL, $url);

    // Include headers necessary for authentication.
    $headers = array();
    $headers[] = 'XA-REQUESTER: ' . $httpServer['HttpServer']['username'];
    $headers[] = 'XA-API-KEY: ' .  $httpServer['HttpServer']['password'];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Return the payload from the curl_exec call below.
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Make the query and get the response and return code.
    $response = curl_exec($ch);
    $curlReturnCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

    curl_close($ch);

    if($response === false) {
      $status = JobStatusEnum::Failed;
      $summary = "Unable to query ACCESS User Database merged_organizations endpoint";

      return array($status, $summary);
    }

    if($curlReturnCode != 200) {
      $status = JobStatusEnum::Failed;
      $summary = "Query to ACCESS User Database merged_organizations endpoint returned code $curlReturnCode";

      return array($status, $summary);
    }

    // Convert JSON.
    try {
      $mergeObjects = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    } catch (Exception $e) {
      $status = JobStatusEnum::Failed;
      $summary = "Error decoding JSON returned from ACCESS User Database: " . $e->getMessage();

      return array($status, $summary);
    }

    // Loop over and process each merge.
    $mergedCount = 0;
    foreach($mergeObjects as $m) {
      $mergedCount += $this->mergeOrganizations($m);
    }

    $status = JobStatusEnum::Complete;
    $summary = "Merged $mergedCount ACCESS Organizations";

    return array($status, $summary);
  }

  /**
   * Merge two ACCESS Organizations.
   *
   * @param array $mergeObject Array describing organizations to merge
   * @return int count of merged organizations
   *
   */
  protected function mergeOrganizations($mergeObject) {
    $mergedCount = 0;

    $keepOrganizationId = $mergeObject['keep_organization_id'];
    $deleteOrganizationId = $mergeObject['delete_organization_id'];

    // Query to find the "keep" Organization.
    $args = array();
    $args['conditions']['AccessOrganization.organization_id'] = $keepOrganizationId;
    $args['conditions']['AccessOrganization.status'] = AccessOrganizationStatusEnum::Active;
    $args['contain'] = false;

    $keepOrganization = $this->AccessOrganization->find('first', $args);

    // If keep Organization does not exist exit.
    if(empty($keepOrganization)) {
      return $mergedCount;
    }

    // Query to find the "delete" Organization.
    $args = array();
    $args['conditions']['AccessOrganization.organization_id'] = $deleteOrganizationId;
    $args['conditions']['AccessOrganization.status'] = AccessOrganizationStatusEnum::Active;
    $args['contain'] = false;

    $deleteOrganization = $this->AccessOrganization->find('first', $args);

    // If delete Organization does not exist exit.
    if(empty($deleteOrganization)) {
      return $mergedCount;
    }

    $keepName = $keepOrganization['AccessOrganization']['name'];
    $delName = $deleteOrganization['AccessOrganization']['name'];

    $keepId = $keepOrganization['AccessOrganization']['organization_id'];
    $delId = $deleteOrganization['AccessOrganization']['organization_id'];

    // Find all the CO Person Roles in this CO that have the delete Organization.
    $args = array();
    $args['conditions']['CoPersonRole.o'] = $delName;
    $args['conditions']['CoPersonRole.status'] = StatusEnum::Active;
    $args['contain'] = 'CoPerson';

    $roles = $this->CoJob->Co->CoPerson->CoPersonRole->find('all', $args);

    // Update the Organization on the CO Person Role.
    foreach($roles as $role) {
      $coPersonId = $role['CoPerson']['id'];
      $coPersonRoleId = $role['CoPersonRole']['id'];
      $this->CoJob->Co->CoPerson->CoPersonRole->id = $coPersonRoleId;
      $this->CoJob->Co->CoPerson->CoPersonRole->saveField('o', $keepName);

      // Write a job history record.
      $key = "CoPersonRole $coPersonRoleId; ACCESS Org ID $keepId";
      $comment = "Changed o for CoPersonRole from $delName to $keepName";
      $comment = substr($comment, 0, 256);
      $status = JobStatusEnum::Complete;
      $this->CoJob->CoJobHistoryRecord->record($this->CoJob->id, $key, $comment, null, null, $status);

      // Write a history record for the CO Person Role.
      $this->CoJob->Co->CoPerson->HistoryRecord->record($coPersonId, $coPersonRoleId, null, null, ActionEnum::CoPersonRoleEditedManual, $comment);
    }

    // Set the status to inactive for the delete Organization.
    $this->AccessOrganization->id = $deleteOrganization['AccessOrganization']['id'];
    $this->AccessOrganization->saveField('status', AccessOrganizationStatusEnum::Inactive);

    // Write a job history record.
    $key = "ACCESS Org ID $keepId; ACCESS Org ID $delId";
    $comment = "Merged $delName to $keepName";
    $comment = substr($comment, 0, 256);
    $status = JobStatusEnum::Complete;
    $this->CoJob->CoJobHistoryRecord->record($this->CoJob->id, $key, $comment, null, null, $status);

    $mergedCount += 1;

    return $mergedCount;
  }

  /**
   * Obtain the list of parameters supported by this Job.
   *
   * @since  COmanage Registry v4.1.0
   * @return Array Array of supported parameters.
   */
  public function parameterFormat() {

    $params = array(
      'csv' => array(
        'help' => _txt("FOO"),
        'type' => 'string',
        'required' => false
      )
    );

    return $params;
  }
}

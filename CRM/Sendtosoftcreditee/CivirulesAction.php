<?php
/**
 * @author Jon Goldberg <jon@megaphonetech.com>
 * @license AGPL-3.0
 */

class CRM_Sendtosoftcreditee_CivirulesAction extends CRM_Civirules_Action {

  /**
   * Process the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @access public
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $actionParams = $this->getActionParameters();
    $contributionId = $triggerData->getEntityId() ?? NULL;
    if (!$contributionId) {
      return;
    }

    // Find the soft creditee(s).
    $softCreditees = $this->getSoftCreditees($contributionId);
    foreach ($softCreditees as $softCreditee) {
      $params = $actionParams;
      $params['contact_id'] = $softCreditee;

      // change e-mailaddress if other location type is used, falling back on primary if set
      $alternativeAddress = $this->checkAlternativeAddress($params, $softCreditee);
      if ($alternativeAddress) {
        $params['alternative_receiver_address'] = $alternativeAddress;
      }
      $extra_data = (array) $triggerData;
      $params['extra_data'] = $extra_data["\0CRM_Civirules_TriggerData_TriggerData\0entity_data"];
      $params['Contribution_id'] = $contributionId;
      // This is because I hope one day we deal with this capitalization nonsense.
      $params['contribution_id'] = $contributionId;
      //execute the action
      civicrm_api3('Email', 'send', $params);
    }
  }

  protected function getSoftCreditees($contributionId) {
    $softCredits = civicrm_api3('ContributionSoft', 'get', [
      'sequential' => 1,
      'return' => ["contact_id"],
      'contribution_id' => $contributionId,
      'options' => ['limit' => 0],
    ])['values'];
    $contacts = [];
    foreach ($softCredits as $softCredit) {
      $contacts[] = $softCredit['contact_id'];
    }
    return $contacts;
  }

  /**
   * Returns a redirect url to extra data input from the user after adding a action
   *
   * Return false if you do not need extra data input
   *
   * @param int $ruleActionId
   * @return bool|string
   * $access public
   */
  public function getExtraDataInputUrl($ruleActionId) {
    return CRM_Utils_System::url('civicrm/civirules/actions/emailapi_softcreditee', 'rule_action_id=' . $ruleActionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   * @access public
   */
  public function userFriendlyConditionParams() {
    $template = 'unknown template';
    $params = $this->getActionParameters();
    $version = CRM_Core_BAO_Domain::version();
    // Compatibility with CiviCRM > 4.3
    if($version >= 4.4) {
      $messageTemplates = new CRM_Core_DAO_MessageTemplate();
    } else {
      $messageTemplates = new CRM_Core_DAO_MessageTemplates();
    }
    $messageTemplates->id = $params['template_id'];
    $messageTemplates->is_active = true;
    if ($messageTemplates->find(TRUE)) {
      $template = $messageTemplates->msg_title;
    }
    if (isset($params['location_type_id']) && !empty($params['location_type_id'])) {
      try {
        $locationText = 'location type ' . civicrm_api3('LocationType', 'getvalue', array(
            'return' => 'display_name',
            'id' => $params['location_type_id'],
          )) . ' with primary e-mailaddress as fall back';
      }
      catch (CiviCRM_API3_Exception $ex) {
        $locationText = 'location type ' . $params['location_type_id'];
      }
    }
    else {
      $locationText = "primary e-mailaddress";
    }
    $to = '';

    $cc = "";
    if (!empty($params['cc'])) {
      $cc = ts(' and cc to %1', array(1=>$params['cc']));
    }
    $bcc = "";
    if (!empty($params['bcc'])) {
      $bcc = ts(' and bcc to %1', array(1=>$params['bcc']));
    }
    return ts('Send e-mail from "%1 (%2 using %3)" with Template "%4" to %5 %6 %7', array(
      1=>$params['from_name'],
      2=>$params['from_email'],
      3=>$locationText,
      4=>$template,
      5 => $to,
      6 => $cc,
      7 => $bcc
    ));
  }

  /*
   * Method to check if an alternative address is required. This is the case if:
   * - the location type is set, then the e-mailaddress of the specific location type (if found) is to be used.
   * - if alternative receiver address is set, that is to be used
   *
   * @param array $actionParameters
   * @param int $contactId
   * @return string|bool
   */
    private function checkAlternativeAddress($actionParameters, $contactId) {
    if (isset($actionParameters['location_type_id']) &&!empty($actionParameters['location_type_id'])) {
       try {
        $alternateAddress = civicrm_api3('Email', 'getvalue', array(
          'return' => 'email',
          'contact_id' => $contactId,
          'location_type_id' => $actionParameters['location_type_id'],
          'options' => array('limit' => 1, 'sort' => 'id DESC'),
        ));
      return (string) $alternateAddress;
      }
      catch (CiviCRM_API3_Exception $ex) {

      }
    }
    return FALSE;
  }

}

<?php
/**
 * @file
 * Single email interface.
 */

/**
 * An interface to send a single email.
 *
 * @extends CRM_Lettertowho_Interface
 */
class CRM_Lettertowho_Interface_Single extends CRM_Lettertowho_Interface {

  /**
   * Instantiate the delivery interface.
   *
   * @param int $survey_id
   *   The ID of the petition.
   */
  public function __construct($survey_id) {
    $this->interfaceType = 'single';

    $this->neededFields[] = 'Sends_Email';
    $this->neededFields[] = 'Subject';
    $this->neededFields[] = 'Recipient_Name';
    $this->neededFields[] = 'Recipient_Email';

    $fields = $this->findFields();
    $petitionemailval = $this->getFieldsData($survey_id);

    foreach ($this->neededFields as $neededField) {
      if (empty($fields[$neededField]) || empty($petitionemailval[$fields[$neededField]])) {
        // TODO: provide something more meaningful.
        return;
      }
    }
  }

  /**
   * Take the signature activity and send an email to the recipient.
   *
   * @param int $activityId
   *   The petition signature activity ID.
   */
  public function processSignature($activityId) {
    $fields = $this->findFields();
    $petitionemailval = $this->getFieldsData($survey_id);
    $messageField = 'custom_' . intval($petitionemailval[$fields['Message_Field']]);

    // Get custom message value.
    try {
      $signatureParams = array(
        'id' => $activityId,
        'api.ActivityContact.getsingle' => array(
          'record_type_id' => $this->getSourceRecordType(),
          'options' => array('limit' => 1),
          'api.Contact.getsingle' => array(
            'return' => array(
              'display_name',
              'email',
            ),
          ),
        ),
        'return' => array(
          $messageField,
        ),
      );
      $signature = civicrm_api3('Activity', 'getsingle', $signatureParams);
    }
    catch (CiviCRM_API3_Exception $e) {
      $error = $e->getMessage();
      CRM_Core_Error::debug_log_message(t('API Error: %1', array(1 => $error, 'domain' => 'com.aghstrategies.lettertowho')));
    }

    $petitionMessage = empty($signature[$messageField]) ? CRM_Utils_Array::value($fields['Default_Message'], $petitionemailval) : $signature[$messageField];

    // Populate the "from" address:
    if (empty($signature['api.ActivityContact.getsingle']['api.Contact.getsingle']['email'])) {
      $from = $this->getDefaultFromAddress();
    }
    elseif (empty($signature['api.ActivityContact.getsingle']['api.Contact.getsingle']['display_name'])) {
      $from = $signature['api.ActivityContact.getsingle']['api.Contact.getsingle']['email'];
    }
    else {
      $from = "\"{$signature['api.ActivityContact.getsingle']['api.Contact.getsingle']['display_name']}\" <{$signature['api.ActivityContact.getsingle']['api.Contact.getsingle']['email']}>";
    }

    // Setup email message:
    $mailParams = array(
      'groupName' => 'Activity Email Sender',
      'from' => $from,
      'toName' => $petitionemailval[$fields['Recipient_Name']],
      'toEmail' => $petitionemailval[$fields['Recipient_Email']],
      'subject' => $petitionemailval[$fields['Subject']],
      // 'cc' => $cc, TODO: offer option to CC.
      // 'bcc' => $bcc,
      'text' => $petitionMessage,
      // 'html' => $html_message, TODO: offer HTML option.
    );

    if (!CRM_Utils_Mail::send($mailParams)) {
      CRM_Core_Session::setStatus(ts('Error sending message to %1', array('domain' => 'com.aghstrategies.lettertowho', 1 => $mailParams['toName'])));
    }
    else {
      CRM_Core_Session::setStatus(ts('Message sent successfully to %1', array('domain' => 'com.aghstrategies.lettertowho', 1 => $mailParams['toName'])));
    }

    parent::processSignature($activityId, $message);
  }

}

<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 */

/**
 * This class generates form components generic to all the contact types.
 *
 * It delegates the work to lower level subclasses and integrates the changes
 * back in. It also uses a lot of functionality with the CRM API's, so any change
 * made here could potentially affect the API etc. Be careful, be aware, use unit tests.
 *
 */
class CRM_Contact_Form_Contact extends CRM_Core_Form {

  /**
   * The contact type of the form.
   *
   * @var string
   */
  public $_contactType;

  /**
   * The contact type of the form.
   *
   * @var string
   */
  public $_contactSubType;

  /**
   * The contact id, used when editing the form
   *
   * @var int
   */
  public $_contactId;

  /**
   * The default group id passed in via the url.
   *
   * @var int
   */
  public $_gid;

  /**
   * The default tag id passed in via the url.
   *
   * @var int
   */
  public $_tid;

  /**
   * Name of de-dupe button
   *
   * @var string
   */
  protected $_dedupeButtonName;

  /**
   * Name of optional save duplicate button.
   *
   * @var string
   */
  protected $_duplicateButtonName;

  protected $_editOptions = [];

  protected $_oldSubtypes = [];

  public $_blocks;

  public $_values = [];

  public $_action;

  public $_customValueCount;
  /**
   * The array of greetings with option group and filed names.
   *
   * @var array
   */
  public $_greetings;

  /**
   * Do we want to parse street address.
   * @var bool
   */
  public $_parseStreetAddress;

  /**
   * Check contact has a subtype or not.
   * @var bool
   */
  public $_isContactSubType;

  /**
   * Lets keep a cache of all the values that we retrieved.
   * THis is an attempt to avoid the number of update statements
   * during the write phase
   * @var array
   */
  public $_preEditValues;

  /**
   * Explicitly declare the entity api name.
   */
  public function getDefaultEntity() {
    return 'Contact';
  }

  /**
   * Explicitly declare the form context.
   */
  public function getDefaultContext() {
    return 'create';
  }

  /**
   * Get any smarty elements that may not be present in the form.
   *
   * To make life simpler for smarty we ensure they are set to null
   * rather than unset. This is done at the last minute when $this
   * is converted to an array to be assigned to the form.
   *
   * @return array
   */
  public function getOptionalSmartyElements(): array {
    return ['group'];
  }

  /**
   * Build all the data structures needed to build the form.
   */
  public function preProcess() {
    $this->_action = CRM_Utils_Request::retrieve('action', 'String', $this, FALSE, 'add');

    $this->_dedupeButtonName = $this->getButtonName('refresh', 'dedupe');
    $this->_duplicateButtonName = $this->getButtonName('upload', 'duplicate');

    CRM_Core_Resources::singleton()
      ->addStyleFile('civicrm', 'css/contactSummary.css', 2, 'html-header');

    $session = CRM_Core_Session::singleton();
    if ($this->_action == CRM_Core_Action::ADD) {
      // check for add contacts permissions
      if (!CRM_Core_Permission::check('add contacts')) {
        CRM_Utils_System::permissionDenied();
        CRM_Utils_System::civiExit();
      }
      $this->_contactType = CRM_Utils_Request::retrieve('ct', 'String',
        $this, TRUE, NULL, 'REQUEST'
      );
      if (!in_array($this->_contactType,
        ['Individual', 'Household', 'Organization']
      )
      ) {
        CRM_Core_Error::statusBounce(ts('Could not get a contact id and/or contact type'));
      }

      $this->_isContactSubType = FALSE;
      if ($this->_contactSubType = CRM_Utils_Request::retrieve('cst', 'String', $this)) {
        $this->_isContactSubType = TRUE;
      }

      if (
        $this->_contactSubType &&
        !(CRM_Contact_BAO_ContactType::isExtendsContactType($this->_contactSubType, $this->_contactType, TRUE))
      ) {
        CRM_Core_Error::statusBounce(ts("Could not get a valid contact subtype for contact type '%1'", [1 => $this->_contactType]));
      }

      $this->_gid = CRM_Utils_Request::retrieve('gid', 'Integer',
        CRM_Core_DAO::$_nullObject,
        FALSE, NULL, 'GET'
      );
      $this->_tid = CRM_Utils_Request::retrieve('tid', 'Integer',
        CRM_Core_DAO::$_nullObject,
        FALSE, NULL, 'GET'
      );
      $typeLabel = CRM_Contact_BAO_ContactType::contactTypePairs(TRUE, $this->_contactSubType ?
          $this->_contactSubType : $this->_contactType
      );
      $typeLabel = implode(' / ', $typeLabel);

      $this->setTitle(ts('New %1', [1 => $typeLabel]));
      $session->pushUserContext(CRM_Utils_System::url('civicrm/dashboard', 'reset=1'));
      $this->_contactId = NULL;
    }
    else {
      //update mode
      if (!$this->_contactId) {
        $this->_contactId = CRM_Utils_Request::retrieve('cid', 'Positive', $this, TRUE);
      }

      if ($this->_contactId) {
        $defaults = [];
        $params = ['id' => $this->_contactId];
        $returnProperities = ['id', 'contact_type', 'contact_sub_type', 'modified_date', 'is_deceased'];
        CRM_Core_DAO::commonRetrieve('CRM_Contact_DAO_Contact', $params, $defaults, $returnProperities);

        if (empty($defaults['id'])) {
          CRM_Core_Error::statusBounce(ts('A Contact with that ID does not exist: %1', [1 => $this->_contactId]));
        }

        $this->_contactType = $defaults['contact_type'] ?? NULL;
        $this->_contactSubType = $defaults['contact_sub_type'] ?? NULL;

        // check for permissions
        $session = CRM_Core_Session::singleton();
        if (!CRM_Contact_BAO_Contact_Permission::allow($this->_contactId, CRM_Core_Permission::EDIT)) {
          CRM_Core_Error::statusBounce(ts('You do not have the necessary permission to edit this contact.'));
        }

        $displayName = CRM_Contact_BAO_Contact::displayName($this->_contactId);
        if ($defaults['is_deceased']) {
          $displayName .= '  <span class="crm-contact-deceased">(' . ts('deceased') . ')</span>';
        }
        $displayName = ts('Edit %1', [1 => $displayName]);

        // Check if this is default domain contact CRM-10482
        if (CRM_Contact_BAO_Contact::checkDomainContact($this->_contactId)) {
          $displayName .= ' (' . ts('default organization') . ')';
        }

        // omitting contactImage from title for now since the summary overlay css doesn't work outside of our crm-container
        $this->setTitle($displayName);
        $context = CRM_Utils_Request::retrieve('context', 'Alphanumeric', $this);
        $qfKey = CRM_Utils_Request::retrieve('key', 'String', $this);

        $urlParams = 'reset=1&cid=' . $this->_contactId;
        if ($context) {
          $urlParams .= "&context=$context";
        }

        if (CRM_Utils_Rule::qfKey($qfKey)) {

          $urlParams .= "&key=$qfKey";

        }
        $session->pushUserContext(CRM_Utils_System::url('civicrm/contact/view', $urlParams));

        $values = $this->get('values');
        // get contact values.
        if (!empty($values)) {
          $this->_values = $values;
        }
        else {
          $params = [
            'id' => $this->_contactId,
            'contact_id' => $this->_contactId,
            'noRelationships' => TRUE,
            'noNotes' => TRUE,
            'noGroups' => TRUE,
          ];

          $contact = CRM_Contact_BAO_Contact::retrieve($params, $this->_values, TRUE);
          $this->set('values', $this->_values);
        }
      }
      else {
        CRM_Core_Error::statusBounce(ts('Could not get a contact_id and/or contact_type'));
      }
    }

    // parse street address, CRM-5450
    $this->_parseStreetAddress = $this->get('parseStreetAddress');
    if (!isset($this->_parseStreetAddress)) {
      $addressOptions = CRM_Core_BAO_Setting::valueOptions(CRM_Core_BAO_Setting::SYSTEM_PREFERENCES_NAME,
        'address_options'
      );
      $this->_parseStreetAddress = FALSE;
      if (!empty($addressOptions['street_address']) && !empty($addressOptions['street_address_parsing'])) {
        $this->_parseStreetAddress = TRUE;
      }
      $this->set('parseStreetAddress', $this->_parseStreetAddress);
    }
    $this->assign('parseStreetAddress', $this->_parseStreetAddress);

    $this->_editOptions = $this->get('contactEditOptions');
    if (CRM_Utils_System::isNull($this->_editOptions)) {
      $this->_editOptions = CRM_Core_BAO_Setting::valueOptions(CRM_Core_BAO_Setting::SYSTEM_PREFERENCES_NAME,
        'contact_edit_options', TRUE, NULL,
        FALSE, 'name', TRUE, 'AND v.filter = 0'
      );
      $this->set('contactEditOptions', $this->_editOptions);
    }

    // build demographics only for Individual contact type
    if ($this->_contactType != 'Individual' &&
      array_key_exists('Demographics', $this->_editOptions)
    ) {
      unset($this->_editOptions['Demographics']);
    }

    // in update mode don't show notes
    if ($this->_contactId && array_key_exists('Notes', $this->_editOptions)) {
      unset($this->_editOptions['Notes']);
    }

    $this->assign('editOptions', $this->_editOptions);
    $this->assign('contactType', $this->_contactType);
    $this->assign('contactSubType', $this->_contactSubType);

    // get the location blocks.
    $this->_blocks = $this->get('blocks');
    if (CRM_Utils_System::isNull($this->_blocks)) {
      $this->_blocks = CRM_Core_BAO_Setting::valueOptions(CRM_Core_BAO_Setting::SYSTEM_PREFERENCES_NAME,
        'contact_edit_options', TRUE, NULL,
        FALSE, 'name', TRUE, 'AND v.filter = 1'
      );
      $this->set('blocks', $this->_blocks);
    }
    $this->assign('blocks', $this->_blocks);

    // this is needed for custom data.
    $this->assign('entityID', $this->_contactId);

    // also keep the convention.
    $this->assign('contactId', $this->_contactId);

    // location blocks.
    CRM_Contact_Form_Location::preProcess($this);

    // retain the multiple count custom fields value
    if (!empty($_POST['hidden_custom'])) {
      $customGroupCount = $_POST['hidden_custom_group_count'] ?? NULL;

      if ($contactSubType = CRM_Utils_Array::value('contact_sub_type', $_POST)) {
        $paramSubType = implode(',', $contactSubType);
      }

      $this->_getCachedTree = FALSE;
      unset($customGroupCount[0]);
      foreach ($customGroupCount as $groupID => $groupCount) {
        if ($groupCount > 1) {
          $this->set('groupID', $groupID);
          //loop the group
          for ($i = 1; $i <= $groupCount; $i++) {
            CRM_Custom_Form_CustomData::preProcess($this, NULL, $contactSubType,
              $i, $this->_contactType, $this->_contactId
            );
            CRM_Contact_Form_Edit_CustomData::buildQuickForm($this);
          }
        }
      }

      //reset all the ajax stuff, for normal processing
      if (isset($this->_groupTree)) {
        $this->_groupTree = NULL;
      }
      $this->set('groupID', NULL);
      $this->_getCachedTree = TRUE;
    }

    // execute preProcess dynamically by js else execute normal preProcess
    if (array_key_exists('CustomData', $this->_editOptions)) {
      //assign a parameter to pass for sub type multivalue
      //custom field to load
      if ($this->_contactSubType || isset($paramSubType)) {
        $paramSubType = (isset($paramSubType)) ? $paramSubType :
          str_replace(CRM_Core_DAO::VALUE_SEPARATOR, ',', trim($this->_contactSubType, CRM_Core_DAO::VALUE_SEPARATOR));
      }

      if (CRM_Utils_Request::retrieve('type', 'String')) {
        CRM_Contact_Form_Edit_CustomData::preProcess($this);
      }
      else {
        $contactSubType = $this->_contactSubType;
        // need contact sub type to build related grouptree array during post process
        if (!empty($_POST['qfKey'])) {
          $contactSubType = $_POST['contact_sub_type'] ?? NULL;
        }
        //only custom data has preprocess hence directly call it
        CRM_Custom_Form_CustomData::preProcess($this, NULL, $contactSubType,
          1, $this->_contactType, $this->_contactId
        );
        $this->assign('customValueCount', $this->_customValueCount);
      }
    }
    $this->assign('paramSubType', $paramSubType ?? '');
  }

  /**
   * Set default values for the form.
   *
   * Note that in edit/view mode the default values are retrieved from the database
   */
  public function setDefaultValues() {
    $defaults = $this->_values;

    if ($this->_action & CRM_Core_Action::ADD) {
      if (array_key_exists('TagsAndGroups', $this->_editOptions)) {
        // set group and tag defaults if any
        if ($this->_gid) {
          $defaults['group'][] = $this->_gid;
        }
        if ($this->_tid) {
          $defaults['tag'][$this->_tid] = 1;
        }
      }
      if ($this->_contactSubType) {
        $defaults['contact_sub_type'] = $this->_contactSubType;
      }
    }
    else {
      foreach ($defaults['email'] as $dontCare => & $val) {
        if (isset($val['signature_text'])) {
          $val['signature_text_hidden'] = $val['signature_text'];
        }
        if (isset($val['signature_html'])) {
          $val['signature_html_hidden'] = $val['signature_html'];
        }
      }

      if (!empty($defaults['contact_sub_type'])) {
        $defaults['contact_sub_type'] = $this->_oldSubtypes;
      }
    }
    // set defaults for blocks ( custom data, address, communication preference, notes, tags and groups )
    foreach ($this->_editOptions as $name => $label) {
      if (!in_array($name, ['Address', 'Notes'])) {
        $className = 'CRM_Contact_Form_Edit_' . $name;
        $className::setDefaultValues($this, $defaults);
      }
    }

    //set address block defaults
    CRM_Contact_Form_Edit_Address::setDefaultValues($defaults, $this);

    $this->assign('imageURL', !empty($defaults['image_URL']) ? CRM_Utils_File::getImageURL($defaults['image_URL']) : '');

    //set location type and country to default for each block
    $this->blockSetDefaults($defaults);

    $this->_preEditValues = $defaults;
    return $defaults;
  }

  /**
   * Do the set default related to location type id, primary location,  default country.
   *
   * @param array $defaults
   */
  public function blockSetDefaults(&$defaults) {
    $locationTypeKeys = array_filter(array_keys(CRM_Core_PseudoConstant::get('CRM_Core_DAO_Address', 'location_type_id')), 'is_int');
    sort($locationTypeKeys);

    // get the default location type
    $locationType = CRM_Core_BAO_LocationType::getDefault();

    // unset primary location type
    $primaryLocationTypeIdKey = CRM_Utils_Array::key($locationType->id, $locationTypeKeys);
    unset($locationTypeKeys[$primaryLocationTypeIdKey]);

    // reset the array sequence
    $locationTypeKeys = array_values($locationTypeKeys);

    // get default phone and im provider id.
    $defPhoneTypeId = key(CRM_Core_OptionGroup::values('phone_type', FALSE, FALSE, FALSE, ' AND is_default = 1'));
    $defIMProviderId = key(CRM_Core_OptionGroup::values('instant_messenger_service',
      FALSE, FALSE, FALSE, ' AND is_default = 1'
    ));
    $defWebsiteTypeId = key(CRM_Core_OptionGroup::values('website_type',
      FALSE, FALSE, FALSE, ' AND is_default = 1'
    ));

    $allBlocks = $this->_blocks;
    if (array_key_exists('Address', $this->_editOptions)) {
      $allBlocks['Address'] = $this->_editOptions['Address'];
    }

    $config = CRM_Core_Config::singleton();
    foreach ($allBlocks as $blockName => $label) {
      $name = strtolower($blockName);
      $hasPrimary = $updateMode = FALSE;

      // user is in update mode.
      if (array_key_exists($name, $defaults) &&
        !CRM_Utils_System::isNull($defaults[$name])
      ) {
        $updateMode = TRUE;
      }

      for ($instance = 1; $instance <= $this->get($blockName . '_Block_Count'); $instance++) {
        // make we require one primary block, CRM-5505
        if ($updateMode) {
          if (!$hasPrimary) {
            $hasPrimary = CRM_Utils_Array::value(
              'is_primary',
              CRM_Utils_Array::value($instance, $defaults[$name])
            );
          }
          continue;
        }

        //set location to primary for first one.
        if ($instance == 1) {
          $hasPrimary = TRUE;
          $defaults[$name][$instance]['is_primary'] = TRUE;
          $defaults[$name][$instance]['location_type_id'] = $locationType->id;
        }
        else {
          $locTypeId = $locationTypeKeys[$instance - 1] ?? $locationType->id;
          $defaults[$name][$instance]['location_type_id'] = $locTypeId;
        }

        //set default country
        if ($name == 'address' && $config->defaultContactCountry) {
          $defaults[$name][$instance]['country_id'] = $config->defaultContactCountry;
        }

        //set default state/province
        if ($name == 'address' && $config->defaultContactStateProvince) {
          $defaults[$name][$instance]['state_province_id'] = $config->defaultContactStateProvince;
        }

        //set default phone type.
        if ($name == 'phone' && $defPhoneTypeId) {
          $defaults[$name][$instance]['phone_type_id'] = $defPhoneTypeId;
        }
        //set default website type.
        if ($name == 'website' && $defWebsiteTypeId) {
          $defaults[$name][$instance]['website_type_id'] = $defWebsiteTypeId;
        }

        //set default im provider.
        if ($name == 'im' && $defIMProviderId) {
          $defaults[$name][$instance]['provider_id'] = $defIMProviderId;
        }
      }

      if (!$hasPrimary) {
        $defaults[$name][1]['is_primary'] = TRUE;
      }
    }
  }

  /**
   * add the rules (mainly global rules) for form.
   * All local rules are added near the element
   *
   * @see valid_date
   */
  public function addRules() {
    // skip adding formRules when custom data is build
    if ($this->_addBlockName || ($this->_action & CRM_Core_Action::DELETE)) {
      return;
    }

    $this->addFormRule(['CRM_Contact_Form_Edit_' . $this->_contactType, 'formRule'], $this->_contactId);

    // Call Locking check if editing existing contact
    if ($this->_contactId) {
      $this->addFormRule(['CRM_Contact_Form_Edit_Lock', 'formRule'], $this->_contactId);
    }

    if (array_key_exists('Address', $this->_editOptions)) {
      $this->addFormRule(['CRM_Contact_Form_Edit_Address', 'formRule'], $this);
    }

    if (array_key_exists('CommunicationPreferences', $this->_editOptions)) {
      $this->addFormRule(['CRM_Contact_Form_Edit_CommunicationPreferences', 'formRule'], $this);
    }
  }

  /**
   * Global validation rules for the form.
   *
   * @param array $fields
   *   Posted values of the form.
   * @param array $errors
   *   List of errors to be posted back to the form.
   * @param int $contactId
   *   Contact id if doing update.
   * @param string $contactType
   *
   * @return bool
   *   email/openId
   */
  public static function formRule($fields, &$errors, $contactId, $contactType) {
    $config = CRM_Core_Config::singleton();

    // validations.
    //1. for each block only single value can be marked as is_primary = true.
    //2. location type id should be present if block data present.
    //3. check open id across db and other each block for duplicate.
    //4. at least one location should be primary.
    //5. also get primaryID from email or open id block.

    // take the location blocks.
    $blocks = CRM_Core_BAO_Setting::valueOptions(CRM_Core_BAO_Setting::SYSTEM_PREFERENCES_NAME,
      'contact_edit_options', TRUE, NULL,
      FALSE, 'name', TRUE, 'AND v.filter = 1'
    );

    $otherEditOptions = CRM_Core_BAO_Setting::valueOptions(CRM_Core_BAO_Setting::SYSTEM_PREFERENCES_NAME,
      'contact_edit_options', TRUE, NULL,
      FALSE, 'name', TRUE, 'AND v.filter = 0'
    );
    //get address block inside.
    if (array_key_exists('Address', $otherEditOptions)) {
      $blocks['Address'] = $otherEditOptions['Address'];
    }

    $website_types = [];
    $openIds = [];
    $primaryID = FALSE;
    foreach ($blocks as $name => $label) {
      $hasData = $hasPrimary = [];
      $name = strtolower($name);
      if (!empty($fields[$name]) && is_array($fields[$name])) {
        foreach ($fields[$name] as $instance => $blockValues) {
          $dataExists = self::blockDataExists($blockValues);

          if (!$dataExists && $name == 'address') {
            $dataExists = $fields['address'][$instance]['use_shared_address'] ?? NULL;
          }

          if ($dataExists) {
            if ($name == 'website') {
              if (!empty($blockValues['website_type_id'])) {
                if (empty($website_types[$blockValues['website_type_id']])) {
                  $website_types[$blockValues['website_type_id']] = $blockValues['website_type_id'];
                }
                else {
                  $errors["{$name}[1][website_type_id]"] = ts('Contacts may only have one website of each type at most.');
                }
              }

              // skip remaining checks for website
              continue;
            }

            $hasData[] = $instance;
            if (!empty($blockValues['is_primary'])) {
              $hasPrimary[] = $instance;
              if (!$primaryID &&
                in_array($name, [
                  'email',
                  'openid',
                ]) && !empty($blockValues[$name])
              ) {
                $primaryID = $blockValues[$name];
              }
            }

            if (empty($blockValues['location_type_id'])) {
              $errors["{$name}[$instance][location_type_id]"] = ts('The Location Type should be set if there is  %1 information.', [1 => $label]);
            }
          }

          if ($name == 'openid' && !empty($blockValues[$name])) {
            $oid = new CRM_Core_DAO_OpenID();
            $oid->openid = $openIds[$instance] = $blockValues[$name];
            $cid = $contactId ?? 0;
            if ($oid->find(TRUE) && ($oid->contact_id != $cid)) {
              $errors["{$name}[$instance][openid]"] = ts('%1 already exist.', [1 => $blocks['OpenID']]);
            }
          }
        }

        if (empty($hasPrimary) && !empty($hasData)) {
          $errors["{$name}[1][is_primary]"] = ts('One %1 should be marked as primary.', [1 => $label]);
        }

        if (count($hasPrimary) > 1) {
          $errors["{$name}[" . array_pop($hasPrimary) . "][is_primary]"] = ts('Only one %1 can be marked as primary.',
            [1 => $label]
          );
        }
      }
    }

    //do validations for all opend ids they should be distinct.
    if (!empty($openIds) && (count(array_unique($openIds)) != count($openIds))) {
      foreach ($openIds as $instance => $value) {
        if (!array_key_exists($instance, array_unique($openIds))) {
          $errors["openid[$instance][openid]"] = ts('%1 already used.', [1 => $blocks['OpenID']]);
        }
      }
    }

    // street number should be digit + suffix, CRM-5450
    $parseStreetAddress = CRM_Utils_Array::value('street_address_parsing',
      CRM_Core_BAO_Setting::valueOptions(CRM_Core_BAO_Setting::SYSTEM_PREFERENCES_NAME,
        'address_options'
      )
    );
    if ($parseStreetAddress) {
      if (isset($fields['address']) &&
        is_array($fields['address'])
      ) {
        $invalidStreetNumbers = [];
        foreach ($fields['address'] as $cnt => $address) {
          if ($streetNumber = CRM_Utils_Array::value('street_number', $address)) {
            $parsedAddress = CRM_Core_BAO_Address::parseStreetAddress($address['street_number']);
            if (empty($parsedAddress['street_number'])) {
              $invalidStreetNumbers[] = $cnt;
            }
          }
        }

        if (!empty($invalidStreetNumbers)) {
          $first = $invalidStreetNumbers[0];
          foreach ($invalidStreetNumbers as & $num) {
            $num = CRM_Contact_Form_Contact::ordinalNumber($num);
          }
          $errors["address[$first][street_number]"] = ts('The street number you entered for the %1 address block(s) is not in an expected format. Street numbers may include numeric digit(s) followed by other characters. You can still enter the complete street address (unparsed) by clicking "Edit Complete Street Address".', [1 => implode(', ', $invalidStreetNumbers)]);
        }
      }
    }

    // Check for duplicate contact if it wasn't already handled by ajax or disabled
    if (!Civi::settings()->get('contact_ajax_check_similar') || !empty($fields['_qf_Contact_refresh_dedupe'])) {
      self::checkDuplicateContacts($fields, $errors, $contactId, $contactType);
    }

    return $primaryID;
  }

  /**
   * Build the form object.
   */
  public function buildQuickForm() {
    //load form for child blocks
    if ($this->_addBlockName) {
      $className = 'CRM_Contact_Form_Edit_' . $this->_addBlockName;
      return $className::buildQuickForm($this);
    }

    if ($this->_action == CRM_Core_Action::UPDATE) {
      $deleteExtra = json_encode(ts('Are you sure you want to delete contact image.'));
      $deleteURL = [
        CRM_Core_Action::DELETE => [
          'name' => ts('Delete Contact Image'),
          'url' => 'civicrm/contact/image',
          'qs' => 'reset=1&cid=%%id%%&action=delete',
          'extra' => 'onclick = "' . htmlspecialchars("if (confirm($deleteExtra)) this.href+='&confirmed=1'; else return false;") . '"',
        ],
      ];
      $deleteURL = CRM_Core_Action::formLink($deleteURL,
        CRM_Core_Action::DELETE,
        [
          'id' => $this->_contactId,
        ],
        ts('more'),
        FALSE,
        'contact.image.delete',
        'Contact',
        $this->_contactId
      );
      $this->assign('deleteURL', $deleteURL);
    }

    //build contact type specific fields
    $className = 'CRM_Contact_Form_Edit_' . $this->_contactType;
    $className::buildQuickForm($this);

    // Ajax duplicate checking
    $checkSimilar = Civi::settings()->get('contact_ajax_check_similar');
    $this->assign('checkSimilar', $checkSimilar);
    if ($checkSimilar == 1) {
      $ruleParams = ['used' => 'Supervised', 'contact_type' => $this->_contactType];
      $this->assign('ruleFields', CRM_Dedupe_BAO_DedupeRule::dedupeRuleFields($ruleParams));
    }

    // build Custom data if Custom data present in edit option
    $buildCustomData = 'noCustomDataPresent';
    if (array_key_exists('CustomData', $this->_editOptions)) {
      $buildCustomData = "customDataPresent";
    }

    // subtype is a common field. lets keep it here
    $subtypes = CRM_Contact_BAO_Contact::buildOptions('contact_sub_type', 'create', ['contact_type' => $this->_contactType]);
    if (!empty($subtypes)) {
      $this->addField('contact_sub_type', [
        'label' => ts('Contact Type'),
        'options' => $subtypes,
        'class' => $buildCustomData,
        'multiple' => 'multiple',
        'option_url' => NULL,
      ]);
    }

    // build edit blocks ( custom data, demographics, communication preference, notes, tags and groups )
    foreach ($this->_editOptions as $name => $label) {
      if ($name == 'Address') {
        $this->_blocks['Address'] = $this->_editOptions['Address'];
        continue;
      }
      if ($name == 'TagsAndGroups') {
        continue;
      }
      $className = 'CRM_Contact_Form_Edit_' . $name;
      $className::buildQuickForm($this);
    }

    // build tags and groups
    CRM_Contact_Form_Edit_TagsAndGroups::buildQuickForm($this, 0, CRM_Contact_Form_Edit_TagsAndGroups::ALL,
      FALSE, NULL, 'Group(s)', 'Tag(s)', NULL, 'select');

    // build location blocks.
    CRM_Contact_Form_Edit_Lock::buildQuickForm($this);
    CRM_Contact_Form_Location::buildQuickForm($this);

    // add attachment
    $this->addField('image_URL', ['maxlength' => '255', 'label' => ts('Browse/Upload Image')]);

    // add the dedupe button
    $this->addElement('xbutton',
      $this->_dedupeButtonName,
      ts('Check for Matching Contact(s)'),
      [
        'type' => 'submit',
        'value' => 1,
        'class' => "crm-button crm-button{$this->_dedupeButtonName}",
      ]
    );
    $this->addElement('xbutton',
      $this->_duplicateButtonName,
      ts('Save Matching Contact'),
      [
        'type' => 'submit',
        'value' => 1,
        'class' => "crm-button crm-button{$this->_duplicateButtonName}",
      ]
    );
    $this->addElement('xbutton',
      $this->getButtonName('next', 'sharedHouseholdDuplicate'),
      ts('Save With Duplicate Household'),
      [
        'type' => 'submit',
        'value' => 1,
      ]
    );

    $buttons = [
      [
        'type' => 'upload',
        'name' => ts('Save'),
        'subName' => 'view',
        'isDefault' => TRUE,
      ],
    ];
    if (CRM_Core_Permission::check('add contacts')) {
      $buttons[] = [
        'type' => 'upload',
        'name' => ts('Save and New'),
        'spacing' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;',
        'subName' => 'new',
      ];
    }
    $buttons[] = [
      'type' => 'cancel',
      'name' => ts('Cancel'),
    ];

    if (!empty($this->_values['contact_sub_type'])) {
      $this->_oldSubtypes = explode(CRM_Core_DAO::VALUE_SEPARATOR,
        trim($this->_values['contact_sub_type'], CRM_Core_DAO::VALUE_SEPARATOR)
      );
    }
    $this->assign('oldSubtypes', json_encode($this->_oldSubtypes));

    $this->addButtons($buttons);
  }

  /**
   * Form submission of new/edit contact is processed.
   */
  public function postProcess() {
    // check if dedupe button, if so return.
    $buttonName = $this->controller->getButtonName();
    if ($buttonName == $this->_dedupeButtonName) {
      return;
    }

    //get the submitted values in an array
    $params = $this->controller->exportValues($this->_name);
    if (!isset($params['preferred_communication_method'])) {
      // If this field is empty QF will trim it so we have to add it in.
      $params['preferred_communication_method'] = 'null';
    }

    $group = $params['group'] ?? NULL;
    $params['group'] = ($params['group'] == '') ? [] : $params['group'];
    if (!empty($group)) {
      $group = is_array($group) ? $group : explode(',', $group);
      $params['group'] = [];
      foreach ($group as $key => $value) {
        $params['group'][$value] = 1;
      }
    }

    if (!empty($params['image_URL'])) {
      CRM_Contact_BAO_Contact::processImageParams($params);
    }

    if (is_numeric(CRM_Utils_Array::value('current_employer_id', $params)) && !empty($params['current_employer'])) {
      $params['current_employer'] = $params['current_employer_id'];
    }

    // don't carry current_employer_id field,
    // since we don't want to directly update DAO object without
    // handling related business logic ( eg related membership )
    if (isset($params['current_employer_id'])) {
      unset($params['current_employer_id']);
    }

    $params['contact_type'] = $this->_contactType;
    if (empty($params['contact_sub_type']) && $this->_isContactSubType) {
      $params['contact_sub_type'] = [$this->_contactSubType];
    }

    if ($this->_contactId) {
      $params['contact_id'] = $this->_contactId;
    }

    //make deceased date null when is_deceased = false
    if ($this->_contactType == 'Individual' && !empty($this->_editOptions['Demographics']) && empty($params['is_deceased'])) {
      $params['is_deceased'] = FALSE;
      $params['deceased_date'] = NULL;
    }

    // action is taken depending upon the mode
    if ($this->_action & CRM_Core_Action::UPDATE) {
      CRM_Utils_Hook::pre('edit', $params['contact_type'], $params['contact_id'], $params);
    }
    else {
      CRM_Utils_Hook::pre('create', $params['contact_type'], NULL, $params);
    }

    //CRM-5143
    //if subtype is set, send subtype as extend to validate subtype customfield
    $customFieldExtends = (CRM_Utils_Array::value('contact_sub_type', $params)) ? $params['contact_sub_type'] : $params['contact_type'];

    $params['custom'] = CRM_Core_BAO_CustomField::postProcess($params,
      $this->_contactId,
      $customFieldExtends,
      TRUE
    );
    if ($this->_contactId && !empty($this->_oldSubtypes)) {
      CRM_Contact_BAO_ContactType::deleteCustomSetForSubtypeMigration($this->_contactId,
        $params['contact_type'],
        $this->_oldSubtypes,
        $params['contact_sub_type']
      );
    }

    if (array_key_exists('CommunicationPreferences', $this->_editOptions)) {
      // this is a chekbox, so mark false if we dont get a POST value
      $params['is_opt_out'] = CRM_Utils_Array::value('is_opt_out', $params, FALSE);
    }

    // process shared contact address.
    CRM_Contact_BAO_Contact_Utils::processSharedAddress($params['address']);

    if (!array_key_exists('TagsAndGroups', $this->_editOptions)) {
      unset($params['group']);
    }
    elseif (!empty($params['contact_id']) && ($this->_action & CRM_Core_Action::UPDATE)) {
      // figure out which all groups are intended to be removed
      $contactGroupList = CRM_Contact_BAO_GroupContact::getContactGroup($params['contact_id'], 'Added');
      if (is_array($contactGroupList)) {
        foreach ($contactGroupList as $key) {
          if ((!array_key_exists($key['group_id'], $params['group']) || $params['group'][$key['group_id']] != 1) && empty($key['is_hidden'])) {
            $params['group'][$key['group_id']] = -1;
          }
        }
      }
    }

    // parse street address, CRM-5450
    $parseStatusMsg = NULL;
    if ($this->_parseStreetAddress) {
      $parseResult = self::parseAddress($params);
      $parseStatusMsg = self::parseAddressStatusMsg($parseResult);
    }

    $blocks = ['email', 'phone', 'im', 'openid', 'address', 'website'];
    foreach ($blocks as $block) {
      if (!empty($this->_preEditValues[$block]) && is_array($this->_preEditValues[$block])) {
        foreach ($this->_preEditValues[$block] as $count => $value) {
          if (!empty($value['id'])) {
            $params[$block][$count]['id'] = $value['id'];
            $params[$block]['isIdSet'] = TRUE;
          }
        }
      }
    }

    // Allow un-setting of location info, CRM-5969
    $params['updateBlankLocInfo'] = TRUE;

    $contact = CRM_Contact_BAO_Contact::create($params, TRUE, FALSE, TRUE);

    // status message
    if ($this->_contactId) {
      $message = ts('%1 has been updated.', [1 => $contact->display_name]);
    }
    else {
      $message = ts('%1 has been created.', [1 => $contact->display_name]);
    }

    // set the contact ID
    $this->_contactId = $contact->id;

    if (array_key_exists('TagsAndGroups', $this->_editOptions)) {
      //add contact to tags
      if (isset($params['tag'])) {
        $params['tag'] = array_flip(explode(',', $params['tag']));
        CRM_Core_BAO_EntityTag::create($params['tag'], 'civicrm_contact', $params['contact_id']);
      }
      //save free tags
      if (isset($params['contact_taglist']) && !empty($params['contact_taglist'])) {
        CRM_Core_Form_Tag::postProcess($params['contact_taglist'], $params['contact_id'], 'civicrm_contact', $this);
      }
    }

    if (!empty($parseStatusMsg)) {
      $message .= "<br />$parseStatusMsg";
    }

    $session = CRM_Core_Session::singleton();
    $session->setStatus($message, ts('Contact Saved'), 'success');

    // add the recently viewed contact
    $recentOther = [];
    if (($session->get('userID') == $contact->id) ||
      CRM_Contact_BAO_Contact_Permission::allow($contact->id, CRM_Core_Permission::EDIT)
    ) {
      $recentOther['editUrl'] = CRM_Utils_System::url('civicrm/contact/add', 'reset=1&action=update&cid=' . $contact->id);
    }

    if (($session->get('userID') != $this->_contactId) && CRM_Core_Permission::check('delete contacts')) {
      $recentOther['deleteUrl'] = CRM_Utils_System::url('civicrm/contact/view/delete', 'reset=1&delete=1&cid=' . $contact->id);
    }

    CRM_Utils_Recent::add($contact->display_name,
      CRM_Utils_System::url('civicrm/contact/view', 'reset=1&cid=' . $contact->id),
      $contact->id,
      $this->_contactType,
      $contact->id,
      $contact->display_name,
      $recentOther
    );

    // here we replace the user context with the url to view this contact
    $buttonName = $this->controller->getButtonName();
    if ($buttonName == $this->getButtonName('upload', 'new')) {
      $contactSubTypes = array_filter(explode(CRM_Core_DAO::VALUE_SEPARATOR, $this->_contactSubType));
      $resetStr = "reset=1&ct={$contact->contact_type}";
      $resetStr .= (count($contactSubTypes) == 1) ? "&cst=" . array_pop($contactSubTypes) : '';
      $session->replaceUserContext(CRM_Utils_System::url('civicrm/contact/add', $resetStr));
    }
    else {
      $context = CRM_Utils_Request::retrieve('context', 'Alphanumeric', $this);
      $qfKey = CRM_Utils_Request::retrieve('key', 'String', $this);
      //validate the qfKey
      $urlParams = 'reset=1&cid=' . $contact->id;
      if ($context) {
        $urlParams .= "&context=$context";
      }
      if (CRM_Utils_Rule::qfKey($qfKey)) {
        $urlParams .= "&key=$qfKey";
      }

      $session->replaceUserContext(CRM_Utils_System::url('civicrm/contact/view', $urlParams));
    }

    // now invoke the post hook
    if ($this->_action & CRM_Core_Action::UPDATE) {
      CRM_Utils_Hook::post('edit', $params['contact_type'], $contact->id, $contact);
    }
    else {
      CRM_Utils_Hook::post('create', $params['contact_type'], $contact->id, $contact);
    }
  }

  /**
   * Is there any real significant data in the hierarchical location array.
   *
   * @param array $fields
   *   The hierarchical value representation of this location.
   *
   * @return bool
   *   true if data exists, false otherwise
   */
  public static function blockDataExists(&$fields) {
    if (!is_array($fields)) {
      return FALSE;
    }

    static $skipFields = [
      'location_type_id',
      'is_primary',
      'phone_type_id',
      'provider_id',
      'country_id',
      'website_type_id',
      'master_id',
    ];
    foreach ($fields as $name => $value) {
      $skipField = FALSE;
      foreach ($skipFields as $skip) {
        if (strpos("[$skip]", $name) !== FALSE) {
          if ($name == 'phone') {
            continue;
          }
          $skipField = TRUE;
          break;
        }
      }
      if ($skipField) {
        continue;
      }
      if (is_array($value)) {
        if (self::blockDataExists($value)) {
          return TRUE;
        }
      }
      else {
        if (!empty($value)) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * That checks for duplicate contacts.
   *
   * @param array $fields
   *   Fields array which are submitted.
   * @param $errors
   * @param int $contactID
   *   Contact id.
   * @param string $contactType
   *   Contact type.
   */
  public static function checkDuplicateContacts(&$fields, &$errors, $contactID, $contactType) {
    // if this is a forced save, ignore find duplicate rule
    if (empty($fields['_qf_Contact_upload_duplicate'])) {

      $ids = CRM_Contact_BAO_Contact::getDuplicateContacts($fields, $contactType, 'Supervised', [$contactID]);
      if ($ids) {

        $contactLinks = CRM_Contact_BAO_Contact_Utils::formatContactIDSToLinks($ids, TRUE, TRUE, $contactID);

        $duplicateContactsLinks = '<div class="matching-contacts-found">';
        $duplicateContactsLinks .= ts('One matching contact was found. ', [
          'count' => count($contactLinks['rows']),
          'plural' => '%count matching contacts were found.<br />',
        ]);
        if ($contactLinks['msg'] == 'view') {
          $duplicateContactsLinks .= ts('You can View the existing contact', [
            'count' => count($contactLinks['rows']),
            'plural' => 'You can View the existing contacts',
          ]);
        }
        else {
          $duplicateContactsLinks .= ts('You can View or Edit the existing contact', [
            'count' => count($contactLinks['rows']),
            'plural' => 'You can View or Edit the existing contacts',
          ]);
        }
        if ($contactLinks['msg'] == 'merge') {
          // We should also get a merge link if this is for an existing contact
          $duplicateContactsLinks .= ts(', or Merge this contact with an existing contact');
        }
        $duplicateContactsLinks .= '.';
        $duplicateContactsLinks .= '</div>';
        $duplicateContactsLinks .= '<table class="matching-contacts-actions">';
        $row = '';
        for ($i = 0; $i < count($contactLinks['rows']); $i++) {
          $row .= '  <tr>   ';
          $row .= '    <td class="matching-contacts-name"> ';
          $row .= CRM_Utils_Array::value('display_name', $contactLinks['rows'][$i]);
          $row .= '    </td>';
          $row .= '    <td class="matching-contacts-email"> ';
          $row .= CRM_Utils_Array::value('primary_email', $contactLinks['rows'][$i]);
          $row .= '    </td>';
          $row .= '    <td class="action-items"> ';
          $row .= CRM_Utils_Array::value('view', $contactLinks['rows'][$i]);
          $row .= CRM_Utils_Array::value('edit', $contactLinks['rows'][$i]);
          $row .= CRM_Utils_Array::value('merge', $contactLinks['rows'][$i]);
          $row .= '    </td>';
          $row .= '  </tr>   ';
        }

        $duplicateContactsLinks .= $row . '</table>';
        $duplicateContactsLinks .= ts("If you're sure this record is not a duplicate, click the 'Save Matching Contact' button below.");

        $errors['_qf_default'] = $duplicateContactsLinks;

        // let smarty know that there are duplicates
        $template = CRM_Core_Smarty::singleton();
        $template->assign('isDuplicate', 1);
      }
      elseif (!empty($fields['_qf_Contact_refresh_dedupe'])) {
        // add a session message for no matching contacts
        CRM_Core_Session::setStatus(ts('No matching contact found.'), ts('None Found'), 'info');
      }
    }
  }

  /**
   * Use the form name to create the tpl file name.
   *
   * @return string
   */
  public function getTemplateFileName() {
    if ($this->_contactSubType) {
      $templateFile = "CRM/Contact/Form/Edit/SubType/{$this->_contactSubType}.tpl";
      $template = CRM_Core_Form::getTemplate();
      if ($template->template_exists($templateFile)) {
        return $templateFile;
      }
    }
    return parent::getTemplateFileName();
  }

  /**
   * Parse all address blocks present in given params
   * and return parse result for all address blocks,
   * This function either parse street address in to child
   * elements or build street address from child elements.
   *
   * @param array $params
   *   of key value consist of address blocks.
   *
   * @return array
   *   as array of success/fails for each address block
   */
  public function parseAddress(&$params) {
    $parseSuccess = $parsedFields = [];
    if (!is_array($params['address']) ||
      CRM_Utils_System::isNull($params['address'])
    ) {
      return $parseSuccess;
    }

    foreach ($params['address'] as $instance => & $address) {
      $buildStreetAddress = FALSE;
      $parseFieldName = 'street_address';
      foreach ([
        'street_number',
        'street_name',
        'street_unit',
      ] as $fld) {
        if (!empty($address[$fld])) {
          $parseFieldName = 'street_number';
          $buildStreetAddress = TRUE;
          break;
        }
      }

      // main parse string.
      $parseString = $address[$parseFieldName] ?? NULL;

      // parse address field.
      $parsedFields = CRM_Core_BAO_Address::parseStreetAddress($parseString);

      if ($buildStreetAddress) {
        //hack to ignore spaces between number and suffix.
        //here user gives input as street_number so it has to
        //be street_number and street_number_suffix, but
        //due to spaces though preg detect string as street_name
        //consider it as 'street_number_suffix'.
        $suffix = $parsedFields['street_number_suffix'];
        if (!$suffix) {
          $suffix = $parsedFields['street_name'];
        }
        $address['street_number_suffix'] = $suffix;
        $address['street_number'] = $parsedFields['street_number'];

        $streetAddress = NULL;
        foreach ([
          'street_number',
          'street_number_suffix',
          'street_name',
          'street_unit',
        ] as $fld) {
          if (in_array($fld, [
            'street_name',
            'street_unit',
          ])) {
            $streetAddress .= ' ';
          }
          // CRM-17619 - if the street number suffix begins with a number, add a space
          $thesuffix = $address['street_number_suffix'] ?? NULL;
          if ($fld === 'street_number_suffix' && $thesuffix) {
            if (ctype_digit(substr($thesuffix, 0, 1))) {
              $streetAddress .= ' ';
            }
          }
          $streetAddress .= CRM_Utils_Array::value($fld, $address);
        }
        $address['street_address'] = trim($streetAddress);
        $parseSuccess[$instance] = TRUE;
      }
      else {
        $success = TRUE;
        // consider address is automatically parseable,
        // when we should found street_number and street_name
        if (empty($parsedFields['street_name']) || empty($parsedFields['street_number'])) {
          $success = FALSE;
        }

        // check for original street address string.
        if (empty($parseString)) {
          $success = TRUE;
        }

        $parseSuccess[$instance] = $success;

        // we do not reset element values, but keep what we've parsed
        // in case of partial matches: CRM-8378

        // merge parse address in to main address block.
        $address = array_merge($address, $parsedFields);
      }
    }

    return $parseSuccess;
  }

  /**
   * Check parse result and if some address block fails then this
   * function return the status message for all address blocks.
   *
   * @param array $parseResult
   *   An array of address blk instance and its status.
   *
   * @return null|string
   *   $statusMsg   string status message for all address blocks.
   */
  public static function parseAddressStatusMsg($parseResult) {
    $statusMsg = NULL;
    if (!is_array($parseResult) || empty($parseResult)) {
      return $statusMsg;
    }

    $parseFails = [];
    foreach ($parseResult as $instance => $success) {
      if (!$success) {
        $parseFails[] = self::ordinalNumber($instance);
      }
    }

    if (!empty($parseFails)) {
      $statusMsg = ts("Complete street address(es) have been saved. However we were unable to split the address in the %1 address block(s) into address elements (street number, street name, street unit) due to an unrecognized address format. You can set the address elements manually by clicking 'Edit Address Elements' next to the Street Address field while in edit mode.",
        [1 => implode(', ', $parseFails)]
      );
    }

    return $statusMsg;
  }

  /**
   * Convert normal number to ordinal number format.
   * like 1 => 1st, 2 => 2nd and so on...
   *
   * @param int $number
   *   number to convert in to ordinal number.
   *
   * @return string
   *   ordinal number for given number.
   */
  public static function ordinalNumber($number) {
    if (empty($number)) {
      return NULL;
    }

    $str = 'th';
    switch (floor($number / 10) % 10) {
      case 1:
      default:
        switch ($number % 10) {
          case 1:
            $str = 'st';
            break;

          case 2:
            $str = 'nd';
            break;

          case 3:
            $str = 'rd';
            break;
        }
    }

    return "$number$str";
  }

}

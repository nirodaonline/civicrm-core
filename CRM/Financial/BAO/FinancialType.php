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
class CRM_Financial_BAO_FinancialType extends CRM_Financial_DAO_FinancialType implements \Civi\Test\HookInterface {

  /**
   * Static cache holder of available financial types for this session
   * @var array
   */
  public static $_availableFinancialTypes = [];

  /**
   * Static cache holder of status of ACL-FT enabled/disabled for this session
   * @var array
   */
  public static $_statusACLFt = [];

  /**
   * Class constructor.
   */
  public function __construct() {
    parent::__construct();
  }

  /**
   * Fetch object based on array of properties.
   *
   * @param array $params
   *   (reference ) an assoc array of name/value pairs.
   * @param array $defaults
   *   (reference ) an assoc array to hold the flattened values.
   *
   * @return CRM_Financial_DAO_FinancialType
   */
  public static function retrieve(&$params, &$defaults) {
    $financialType = new CRM_Financial_DAO_FinancialType();
    $financialType->copyValues($params);
    if ($financialType->find(TRUE)) {
      CRM_Core_DAO::storeValues($financialType, $defaults);
      return $financialType;
    }
    return NULL;
  }

  /**
   * Update the is_active flag in the db.
   *
   * @param int $id
   *   Id of the database record.
   * @param bool $is_active
   *   Value we want to set the is_active field.
   *
   * @return bool
   */
  public static function setIsActive($id, $is_active) {
    return CRM_Core_DAO::setFieldValue('CRM_Financial_DAO_FinancialType', $id, 'is_active', $is_active);
  }

  /**
   * Create a financial type.
   *
   * @param array $params
   *
   * @return \CRM_Financial_DAO_FinancialType
   */
  public static function create(array $params) {
    $hook = empty($params['id']) ? 'create' : 'edit';
    CRM_Utils_Hook::pre($hook, 'FinancialType', $params['id'] ?? NULL, $params);
    $financialType = self::add($params);
    CRM_Utils_Hook::post($hook, 'FinancialType', $financialType->id, $financialType);
    return $financialType;
  }

  /**
   * Add the financial types.
   *
   * Note that add functions are being deprecated in favour of create.
   * The steps here are to remove direct calls to this function from
   * core & then move the innids of the function to the create function.
   * This function would remain for 6 months or so as a wrapper of create with
   * a deprecation notice.
   *
   * @param array $params
   *   Values from the database object.
   * @param array $ids
   *   Array that we wish to deprecate and remove.
   *
   * @return object
   */
  public static function add(array $params, $ids = []) {
    // @todo deprecate this function, move the code to create & call create from add.
    $financialType = new CRM_Financial_DAO_FinancialType();
    $financialType->copyValues($params);
    $financialType->save();
    // CRM-12470
    if (empty($ids['financialType']) && empty($params['id'])) {
      $titles = CRM_Financial_BAO_FinancialTypeAccount::createDefaultFinancialAccounts($financialType);
      $financialType->titles = $titles;
    }
    return $financialType;
  }

  /**
   * Delete financial Types.
   *
   * @param int $financialTypeId
   * @deprecated
   * @return array|bool
   */
  public static function del($financialTypeId) {
    try {
      static::deleteRecord(['id' => $financialTypeId]);
      return TRUE;
    }
    catch (CRM_Core_Exception $e) {
      return [
        'is_error' => 1,
        'error_message' => $e->getMessage(),
      ];
    }
  }

  /**
   * Callback for hook_civicrm_pre().
   * @param \Civi\Core\Event\PreEvent $event
   * @throws CRM_Core_Exception
   */
  public static function self_hook_civicrm_pre(\Civi\Core\Event\PreEvent $event) {
    if ($event->action === 'delete') {
      $financialType = new CRM_Financial_DAO_FinancialType();
      $financialType->id = $event->id;

      // tables to ignore checks for financial_type_id
      $ignoreTables = ['CRM_Financial_DAO_EntityFinancialAccount'];

      // ensure that we have no objects that have an FK to this financial type id TODO: that cannot be null
      $tables = [];
      foreach ($financialType->findReferences() as $occurrence) {
        $className = get_class($occurrence);
        if (!in_array($className, $tables) && !in_array($className, $ignoreTables)) {
          $tables[] = $className;
        }
      }
      if (!empty($tables)) {
        throw new CRM_Core_Exception(ts('The following tables have an entry for this financial type: %1', [1 => implode(', ', $tables)]));
      }
    }
  }

  /**
   * Callback for hook_civicrm_post().
   * @param \Civi\Core\Event\PostEvent $event
   */
  public static function self_hook_civicrm_post(\Civi\Core\Event\PostEvent $event) {
    if ($event->action === 'delete') {
      \Civi\Api4\EntityFinancialAccount::delete(FALSE)
        ->addWhere('entity_id', '=', $event->id)
        ->addWhere('entity_table', '=', 'civicrm_financial_type')
        ->execute();
    }
  }

  /**
   * fetch financial type having relationship as Income Account is.
   *
   *
   * @return array
   *   all financial type with income account is relationship
   */
  public static function getIncomeFinancialType() {
    // Financial Type
    $financialType = CRM_Contribute_PseudoConstant::financialType();
    $revenueFinancialType = [];
    $relationTypeId = key(CRM_Core_PseudoConstant::accountOptionValues('account_relationship', NULL, " AND v.name LIKE 'Income Account is' "));
    CRM_Core_PseudoConstant::populate(
      $revenueFinancialType,
      'CRM_Financial_DAO_EntityFinancialAccount',
      $all = TRUE,
      $retrieve = 'entity_id',
      $filter = NULL,
      "account_relationship = $relationTypeId AND entity_table = 'civicrm_financial_type' "
    );

    foreach ($financialType as $key => $financialTypeName) {
      if (!in_array($key, $revenueFinancialType)
        || (CRM_Financial_BAO_FinancialType::isACLFinancialTypeStatus()
          && !CRM_Core_Permission::check('add contributions of type ' . $financialTypeName))
      ) {
        unset($financialType[$key]);
      }
    }
    return $financialType;
  }

  /**
   * Wrapper aroung getAvailableFinancialTypes to get all including disabled FinancialTypes
   * @param int|string $action
   *   the type of action, can be add, view, edit, delete
   * @param bool $resetCache
   *   load values from static cache
   *
   * @return array
   */
  public static function getAllAvailableFinancialTypes($action = CRM_Core_Action::VIEW, $resetCache = FALSE) {
    // Flush pseudoconstant cache
    CRM_Contribute_PseudoConstant::flush('financialType');
    $thisIsAUselessVariableButSolvesPHPError = NULL;
    $financialTypes = self::getAvailableFinancialTypes($thisIsAUselessVariableButSolvesPHPError, $action, $resetCache, TRUE);
    return $financialTypes;
  }

  /**
   * Wrapper aroung getAvailableFinancialTypes to get all FinancialTypes Excluding Disabled ones.
   * @param int|string $action
   *   the type of action, can be add, view, edit, delete
   * @param bool $resetCache
   *   load values from static cache
   *
   * @return array
   */
  public static function getAllEnabledAvailableFinancialTypes($action = CRM_Core_Action::VIEW, $resetCache = FALSE) {
    $thisIsAUselessVariableButSolvesPHPError = NULL;
    $financialTypes = self::getAvailableFinancialTypes($thisIsAUselessVariableButSolvesPHPError, $action, $resetCache);
    return $financialTypes;
  }

  /**
   * Get available Financial Types.
   *
   * This logic is being moved into the financialacls extension.
   *
   * Rather than call this function consider using
   *
   * $types = \CRM_Contribute_BAO_Contribution::buildOptions('financial_type_id', 'search');
   *
   * @param array $financialTypes
   *   (reference ) an array of financial types
   * @param int|string $action
   *   the type of action, can be add, view, edit, delete
   * @param bool $resetCache
   *   load values from static cache
   * @param bool $includeDisabled
   *   Whether we should load in disabled FinancialTypes or Not
   *
   * @return array
   */
  public static function getAvailableFinancialTypes(&$financialTypes = NULL, $action = CRM_Core_Action::VIEW, $resetCache = FALSE, $includeDisabled = FALSE) {
    if (empty($financialTypes)) {
      $financialTypes = CRM_Contribute_PseudoConstant::financialType(NULL, $includeDisabled);
    }
    if (!self::isACLFinancialTypeStatus()) {
      return $financialTypes;
    }
    $actions = [
      CRM_Core_Action::VIEW => 'view',
      CRM_Core_Action::UPDATE => 'edit',
      CRM_Core_Action::ADD => 'add',
      CRM_Core_Action::DELETE => 'delete',
    ];

    if (!isset(\Civi::$statics[__CLASS__]['available_types_' . $action])) {
      foreach ($financialTypes as $finTypeId => $type) {
        if (!CRM_Core_Permission::check($actions[$action] . ' contributions of type ' . $type)) {
          unset($financialTypes[$finTypeId]);
        }
      }
      \Civi::$statics[__CLASS__]['available_types_' . $action] = $financialTypes;
    }
    $financialTypes = \Civi::$statics[__CLASS__]['available_types_' . $action];
    return \Civi::$statics[__CLASS__]['available_types_' . $action];
  }

  /**
   * Get available Membership Types.
   *
   * @param array $membershipTypes
   *   (reference ) an array of membership types
   * @param int|string $action
   *   the type of action, can be add, view, edit, delete
   *
   * @return array
   */
  public static function getAvailableMembershipTypes(&$membershipTypes = NULL, $action = CRM_Core_Action::VIEW) {
    if (empty($membershipTypes)) {
      $membershipTypes = CRM_Member_PseudoConstant::membershipType();
    }
    if (!self::isACLFinancialTypeStatus()) {
      return $membershipTypes;
    }
    $actions = [
      CRM_Core_Action::VIEW => 'view',
      CRM_Core_Action::UPDATE => 'edit',
      CRM_Core_Action::ADD => 'add',
      CRM_Core_Action::DELETE => 'delete',
    ];
    foreach ($membershipTypes as $memTypeId => $type) {
      $finTypeId = CRM_Core_DAO::getFieldValue('CRM_Member_DAO_MembershipType', $memTypeId, 'financial_type_id');
      $finType = CRM_Contribute_PseudoConstant::financialType($finTypeId);
      if (!CRM_Core_Permission::check($actions[$action] . ' contributions of type ' . $finType)) {
        unset($membershipTypes[$memTypeId]);
      }
    }
    return $membershipTypes;
  }

  /**
   * This function adds the Financial ACL clauses to the where clause.
   *
   * This is currently somewhat mocking the native hook implementation
   * for the acls that are in core. If the financialaclreport extension is installed
   * core acls are not applied as that would result in them being applied twice.
   *
   * Long term we should either consolidate the financial acls in core or use only the extension.
   * Both require substantial clean up before implementing and by the time the code is clean enough to
   * take the final step we should
   * be able to implement by removing one half of the other of this function.
   *
   * @param array $whereClauses
   */
  public static function addACLClausesToWhereClauses(&$whereClauses) {
    $contributionBAO = new CRM_Contribute_BAO_Contribution();
    $whereClauses = array_merge($whereClauses, $contributionBAO->addSelectWhereClause());

  }

  /**
   * Function to build a permissioned sql where clause based on available financial types.
   *
   * @param array $whereClauses
   *   (reference ) an array of clauses
   * @param string $component
   *   the type of component
   * @param string $alias
   *   the alias to use
   *
   */
  public static function buildPermissionedClause(&$whereClauses, $component = NULL, $alias = NULL) {
    // @todo the relevant addSelectWhere clause should be called.
    if (!self::isACLFinancialTypeStatus()) {
      return FALSE;
    }
    if (is_array($whereClauses)) {
      $types = self::getAllEnabledAvailableFinancialTypes();
      if (empty($types)) {
        $whereClauses[] = ' ' . $alias . '.financial_type_id IN (0)';
      }
      else {
        $whereClauses[] = ' ' . $alias . '.financial_type_id IN (' . implode(',', array_keys($types)) . ')';
      }
    }
    else {
      if ($component == 'contribution') {
        $types = self::getAllEnabledAvailableFinancialTypes();
        $column = "financial_type_id";
      }
      if ($component == 'membership') {
        self::getAvailableMembershipTypes($types, CRM_Core_Action::VIEW);
        $column = "membership_type_id";
      }
      if (!empty($whereClauses)) {
        $whereClauses .= ' AND ';
      }
      if (empty($types)) {
        $whereClauses .= " civicrm_{$component}.{$column} IN (0)";
        return;
      }
      $whereClauses .= " civicrm_{$component}.{$column} IN (" . implode(',', array_keys($types)) . ")";
    }
  }

  /**
   * Function to check if lineitems present in a contribution have permissioned FTs.
   *
   * @param int $id
   *   contribution id
   * @param string $op
   *   the mode of operation, can be add, view, edit, delete
   * @param bool $force
   * @param int $contactID
   *
   * @return bool
   */
  public static function checkPermissionedLineItems($id, $op, $force = TRUE, $contactID = NULL) {
    if (!self::isACLFinancialTypeStatus()) {
      return TRUE;
    }
    $lineItems = CRM_Price_BAO_LineItem::getLineItemsByContributionID($id);
    $flag = FALSE;
    foreach ($lineItems as $items) {
      if (!CRM_Core_Permission::check($op . ' contributions of type ' . CRM_Contribute_PseudoConstant::financialType($items['financial_type_id']), $contactID)) {
        if ($force) {
          throw new CRM_Core_Exception(ts('You do not have permission to access this page.'));
        }
        $flag = FALSE;
        break;
      }
      else {
        $flag = TRUE;
      }
    }
    return $flag;
  }

  /**
   * Check if the logged in user has permission to edit the given financial type.
   *
   * This is called when determining if they can edit things like option values
   * in price sets. At the moment it is not possible to change an option value from
   * a type you do not have permission to to a type that you do.
   *
   * @todo it is currently not possible to edit disabled types if you have ACLs on.
   * Do ACLs still apply once disabled? That question should be resolved if tackling
   * that gap.
   *
   * @param int $financialTypeID
   *
   * @return bool
   */
  public static function checkPermissionToEditFinancialType($financialTypeID) {
    if (!self::isACLFinancialTypeStatus()) {
      return TRUE;
    }
    $financialTypes = CRM_Financial_BAO_FinancialType::getAllAvailableFinancialTypes(CRM_Core_Action::UPDATE);
    return isset($financialTypes[$financialTypeID]);
  }

  /**
   * Check if FT-ACL is turned on or off.
   *
   * @todo rename this function e.g isFinancialTypeACLsEnabled.
   *
   * @return bool
   */
  public static function isACLFinancialTypeStatus() {
    return Civi::settings()->get('acl_financial_type');
  }

}

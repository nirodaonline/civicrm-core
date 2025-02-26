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
 * Page for displaying list of message templates.
 */
class CRM_Admin_Page_MessageTemplates extends CRM_Core_Page_Basic {

  /**
   * The action links that we need to display for the browse screen.
   *
   * @var array
   */
  public static $_links = NULL;

  /**
   * ids of templates which diverted from the default ones and can be reverted
   * @var array
   */
  protected $_revertible = [];

  /**
   * set to the id that we’re reverting at the given moment (if we are)
   * @var int
   */
  protected $_revertedId;

  /**
   * @param null $title
   * @param null $mode
   */
  public function __construct($title = NULL, $mode = NULL) {
    parent::__construct($title, $mode);

    // fetch the ids of templates which diverted from defaults and can be reverted –
    // these templates have the same workflow_id as the defaults; defaults are reserved
    $sql = '
            SELECT diverted.id, orig.id orig_id
            FROM civicrm_msg_template diverted JOIN civicrm_msg_template orig ON (
                diverted.workflow_name = orig.workflow_name AND
                orig.is_reserved = 1                    AND (
                    diverted.msg_subject != orig.msg_subject OR
                    diverted.msg_text    != orig.msg_text    OR
                    diverted.msg_html    != orig.msg_html
                )
            )
        ';
    $dao = CRM_Core_DAO::executeQuery($sql);
    while ($dao->fetch()) {
      $this->_revertible[$dao->id] = $dao->orig_id;
    }
  }

  /**
   * Get BAO Name.
   *
   * @return string
   *   Classname of BAO.
   */
  public function getBAOName() {
    return 'CRM_Core_BAO_MessageTemplate';
  }

  /**
   * Get action Links.
   *
   * @return array
   *   (reference) of action links
   */
  public function &links() {
    if (!(self::$_links)) {
      $confirm = ts('Are you sure you want to revert this template to the default for this workflow? You will lose any customizations you have made.', ['escape' => 'js']) . '\n\n' . ts('We recommend that you save a copy of the your customized Text and HTML message content to a text file before reverting so you can combine your changes with the system default messages as needed.', ['escape' => 'js']);
      self::$_links = [
        CRM_Core_Action::UPDATE => [
          'name' => ts('Edit'),
          'url' => 'civicrm/admin/messageTemplates/add',
          'qs' => 'action=update&id=%%id%%&reset=1',
          'title' => ts('Edit this message template'),
        ],
        CRM_Core_Action::DISABLE => [
          'name' => ts('Disable'),
          'ref' => 'crm-enable-disable',
          'title' => ts('Disable this message template'),
        ],
        CRM_Core_Action::ENABLE => [
          'name' => ts('Enable'),
          'ref' => 'crm-enable-disable',
          'title' => ts('Enable this message template'),
        ],
        CRM_Core_Action::DELETE => [
          'name' => ts('Delete'),
          'url' => 'civicrm/admin/messageTemplates',
          'qs' => 'action=delete&id=%%id%%',
          'title' => ts('Delete this message template'),
        ],
        CRM_Core_Action::REVERT => [
          'name' => ts('Revert to Default'),
          'extra' => "onclick = 'return confirm(\"$confirm\");'",
          'url' => 'civicrm/admin/messageTemplates',
          'qs' => 'action=revert&id=%%id%%&selectedChild=workflow',
          'title' => ts('Revert this workflow message template to the system default'),
        ],
        CRM_Core_Action::VIEW => [
          'name' => ts('View Default'),
          'url' => 'civicrm/admin/messageTemplates',
          'qs' => 'action=view&id=%%orig_id%%&reset=1',
          'title' => ts('View the system default for this workflow message template'),
        ],
      ];
    }
    return self::$_links;
  }

  /**
   * @param CRM_Core_DAO $object
   * @param int $action
   * @param array $values
   * @param array $links
   * @param string $permission
   * @param bool $forceAction
   */
  public function action(&$object, $action, &$values, &$links, $permission, $forceAction = FALSE) {
    if ($object->workflow_name) {
      // do not expose action link for reverting to default if the template did not diverge or we just reverted it now
      if (!array_key_exists($object->id, $this->_revertible) or
        ($this->_action & CRM_Core_Action::REVERT and $object->id == $this->_revertedId)
      ) {
        $action &= ~CRM_Core_Action::REVERT;
        $action &= ~CRM_Core_Action::VIEW;
      }

      // default workflow templates shouldn’t be deletable
      // workflow templates shouldn’t have disable/enable actions (at least for CiviCRM 3.1)
      if ($object->workflow_name) {
        $action &= ~CRM_Core_Action::DISABLE;
        $action &= ~CRM_Core_Action::DELETE;
      }

      // rebuild the action links HTML, as we need to handle %%orig_id%% for revertible templates
      $values['action'] = CRM_Core_Action::formLink($links, $action,
        [
          'id' => $object->id,
          'orig_id' => $this->_revertible[$object->id] ?? NULL,
        ],
        ts('more'),
        FALSE,
        'messageTemplate.manage.action',
        'MessageTemplate',
        $object->id
      );
    }
    else {
      $action &= ~CRM_Core_Action::REVERT;
      $action &= ~CRM_Core_Action::VIEW;
      parent::action($object, $action, $values, $links, $permission);
    }
  }

  /**
   * @param null $args
   * @param null $pageArgs
   * @param null $sort
   *
   * @throws Exception
   */
  public function run($args = NULL, $pageArgs = NULL, $sort = NULL) {
    $id = $this->getIdAndAction();
    // handle the revert action and offload the rest to parent
    if ($this->_action & CRM_Core_Action::REVERT) {
      $this->_revertedId = $id;
      CRM_Core_BAO_MessageTemplate::revert($id);
    }

    return parent::run($args, $pageArgs, $sort);
  }

  /**
   * Get name of edit form.
   *
   * @return string
   *   Classname of edit form.
   */
  public function editForm() {
    return 'CRM_Admin_Form_MessageTemplates';
  }

  /**
   * Get edit form name.
   *
   * @return string
   *   name of this page.
   */
  public function editName() {
    return ts('Message Template');
  }

  /**
   * Get user context.
   *
   * @param null $mode
   *
   * @return string
   *   user context.
   */
  public function userContext($mode = NULL) {
    return 'civicrm/admin/messageTemplates';
  }

  /**
   * Browse all entities.
   */
  public function browse() {
    $action = func_num_args() ? func_get_arg(0) : NULL;
    if ($this->_action & CRM_Core_Action::ADD) {
      return;
    }
    $links = $this->links();
    if ($action == NULL) {
      if (!empty($links)) {
        $action = array_sum(array_keys($links));
      }
    }

    if ($action & CRM_Core_Action::DISABLE) {
      $action -= CRM_Core_Action::DISABLE;
    }

    if ($action & CRM_Core_Action::ENABLE) {
      $action -= CRM_Core_Action::ENABLE;
    }

    $messageTemplate = new CRM_Core_BAO_MessageTemplate();
    $messageTemplate->orderBy('msg_title' . ' asc');

    $userTemplates = [];
    $workflowTemplates = [];

    // find all objects
    $messageTemplate->find();
    while ($messageTemplate->fetch()) {
      $values[$messageTemplate->id] = ['class' => ''];
      CRM_Core_DAO::storeValues($messageTemplate, $values[$messageTemplate->id]);
      // populate action links
      $this->action($messageTemplate, $action, $values[$messageTemplate->id], $links, CRM_Core_Permission::EDIT);

      if (!$messageTemplate->workflow_name) {
        $userTemplates[$messageTemplate->id] = $values[$messageTemplate->id];
      }
      elseif (!$messageTemplate->is_reserved) {
        $workflowTemplates[$messageTemplate->id] = $values[$messageTemplate->id];
      }
    }

    $rows = [
      'userTemplates' => $userTemplates,
      'workflowTemplates' => $workflowTemplates,
    ];

    $this->assign('rows', $rows);
    $this->assign('canEditSystemTemplates', CRM_Core_Permission::check('edit system workflow message templates'));
    $this->assign('canEditMessageTemplates', CRM_Core_Permission::check('edit message templates'));
    $this->assign('canEditUserDrivenMessageTemplates', CRM_Core_Permission::check('edit user-driven message templates'));
    Civi::resources()
      ->addScriptFile('civicrm', 'templates/CRM/common/TabHeader.js', 1, 'html-header')
      ->addSetting([
        'tabSettings' => ['active' => $_GET['selectedChild'] ?? NULL],
      ]);
  }

}

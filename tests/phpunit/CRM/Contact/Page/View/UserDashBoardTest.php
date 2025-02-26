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
 * Test class for CRM_Contact_Page_View_UserDashBoard
 *
 * @package   CiviCRM
 * @group headless
 */
class CRM_Contact_Page_View_UserDashBoardTest extends CiviUnitTestCase {

  use CRMTraits_Page_PageTestTrait;

  /**
   * Contact ID of logged in user.
   *
   * @var int
   */
  protected $contactID;

  /**
   * Contributions created for the test.
   *
   * @var array
   */
  protected $contributions = [];

  /**
   * Prepare for test
   */
  public function setUp(): void {
    parent::setUp();
    $this->contactID = $this->createLoggedInUser();
    $this->listenForPageContent();
  }

  /**
   * Clean up after each test.
   */
  public function tearDown(): void {
    $this->quickCleanUpFinancialEntities();
    $this->quickCleanup(['civicrm_uf_match']);
    CRM_Utils_Hook::singleton()->reset();
    CRM_Core_Session::singleton()->reset();
    CRM_Core_Smarty::singleton()->clearTemplateVars();
    $this->callAPISuccess('Contact', 'delete', ['id' => $this->contactID]);
    parent::tearDown();
  }

  /**
   * Test the content of the dashboard.
   */
  public function testDashboardContentEmptyContact() {
    $this->runUserDashboard();
    $expectedStrings = [
      'You are not currently subscribed to any Groups',
      'There are no contributions on record for you.',
      'There are no Pledges for your record.',
      'You are not registered for any current or upcoming Events.',
      'There are no memberships on record for you.',
      'You do not have any active Personal Campaign pages.',
    ];
    $this->assertPageContains($expectedStrings);
  }

  /**
   * Test the content of the dashboard.
   */
  public function testDashboardContentContributionsWithInvoicingEnabled(): void {
    $this->contributions[] = $this->contributionCreate([
      'contact_id' => $this->contactID,
      'receive_date' => '2018-11-21',
      'receipt_date' => '2018-11-22',
    ]);
    $this->contributions[] = $this->contributionCreate([
      'contact_id' => $this->contactID,
      'receive_date' => '2018-11-22',
      'receipt_date' => '2018-11-23',
      'trxn_id' => '',
      'invoice_id' => '',
    ]);
    $this->contributions[] = $this->contributionCreate([
      'contact_id' => $this->contactID,
      'receive_date' => '2018-11-24',
      'receipt_date' => '2018-11-24',
      'trxn_id' => '',
      'invoice_id' => '',
      'contribution_status_id' => 'Pending',
    ]);
    $recur = $this->callAPISuccess('ContributionRecur', 'create', [
      'contact_id' => $this->contactID,
      'frequency_interval' => 1,
      'amount' => 5,
    ]);
    $this->contributions[] = $this->contributionCreate([
      'contact_id' => $this->contactID,
      'receive_date' => '2018-11-20',
      'amount_level' => 'high',
      'contribution_status_id' => 'Cancelled',
      'invoice_id' => NULL,
      'trxn_id' => NULL,
      'contribution_recur_id' => $recur['id'],
    ]);
    $this->callAPISuccess('Setting', 'create', ['invoicing' => 1]);
    $this->callAPISuccess('Setting', 'create', ['default_invoice_page' => $this->contributionPageCreate()['id']]);
    $this->runUserDashboard();
    $expectedStrings = [
      'Your Contribution(s)',
      '<table class="selector"><tr class="columnheader"><th>Total Amount</th><th>Financial Type</th><th>Received date</th><th>Receipt Sent</th><th>Balance</th><th>Status</th><th></th>',
      '<td>Completed</td><td><a class="button no-popup nowrap"href="/index.php?q=civicrm/contribute/invoice&amp;reset=1&amp;id=1&amp;cid=' . $this->contactID . '"><i class="crm-i fa-download" aria-hidden="true"></i><span>Download Invoice</span></a></td></tr><tr id=\'rowid2\'',
      'Pay Now',
    ];

    $this->assertPageContains($expectedStrings);
    $this->assertSmartyVariableArrayIncludes('contribute_rows', 1, [
      'contact_id' => $this->contactID,
      'contribution_id' => '1',
      'total_amount' => '100.00',
      'financial_type' => 'Donation',
      'contribution_source' => 'SSF',
      'receive_date' => '2018-11-21 00:00:00',
      'contribution_status' => 'Completed',
      'currency' => 'USD',
      'receipt_date' => '2018-11-22 00:00:00',
    ]);

  }

  /**
   * Test the content of the dashboard.
   *
   * @throws \CRM_Core_Exception
   * @throws \CiviCRM_API3_Exception
   */
  public function testDashboardContentContributions() {
    $this->contributionCreate(['contact_id' => $this->contactID]);
    $this->contributions[] = civicrm_api3('Contribution', 'get', [
      'contact_id' => $this->contactID,
      'options' => ['limit' => 12, 'sort' => 'receive_date DESC'],
      'sequential' => 1,
    ])['values'];
    $this->runUserDashboard();
    $expectedStrings = [
      'Your Contribution(s)',
      '<table class="selector"><tr class="columnheader"><th>Total Amount</th><th>Financial Type</th><th>Received date</th><th>Receipt Sent</th><th>Balance</th><th>Status</th>',
      '<td>$ 100.00 </td><td>Donation</td>',
      '<td>Completed</td>',
    ];
    $this->assertPageContains($expectedStrings);
  }

  /**
   * Test the presence of a "Pay Now" button on partial payments
   *
   * @throws \CRM_Core_Exception
   * @throws \CiviCRM_API3_Exception
   */
  public function testDashboardPartialPayments() {
    $contributionId = $this->contributionCreate([
      'contact_id' => $this->contactID,
      'contribution_status_id' => 'Pending',
      'total_amount' => 25,
    ]);
    $result = civicrm_api3('Payment', 'create', [
      'contribution_id' => $contributionId,
      'total_amount' => 11,
      'trxn_date' => "2021-05-11",
    ]);
    $this->contributions[] = civicrm_api3('Contribution', 'get', [
      'contact_id' => $this->contactID,
      'options' => ['limit' => 12, 'sort' => 'receive_date DESC'],
      'sequential' => 1,
    ])['values'];
    $this->runUserDashboard();
    $expectedStrings = [
      'Your Contribution(s)',
      '<table class="selector"><tr class="columnheader"><th>Total Amount</th><th>Financial Type</th><th>Received date</th><th>Receipt Sent</th><th>Balance</th><th>Status</th>',
      '<td>$ 25.00 </td><td>Donation</td>',
      '<td>$ 14.00</td><td>Partially paid</td>',
      'Pay Now',
    ];
    $this->assertPageContains($expectedStrings);
  }

  /**
   * Run the user dashboard.
   */
  protected function runUserDashboard(): void {
    $_REQUEST = ['reset' => 1, 'id' => $this->contactID];
    $dashboard = new CRM_Contact_Page_View_UserDashBoard();
    $dashboard->_contactId = $this->contactID;
    $dashboard->assign('formTpl', NULL);
    $dashboard->assign('action', CRM_Core_Action::VIEW);
    $dashboard->run();
    $_REQUEST = [];
  }

  /**
   * Tests the event dashboard as a minimally permissioned user.
   */
  public function testEventDashboard() {
    CRM_Core_Config::singleton()->userPermissionClass->permissions = [
      'register for events',
      'access Contact Dashboard',
    ];
    $event1id = $this->eventCreate()['id'];
    $event2id = $this->eventCreate(['title' => 'Social Distancing Meetup Group'])['id'];
    $params['contact_id'] = $this->contactID;
    $params['event_id'] = $event1id;
    $this->participantCreate($params);
    $params['event_id'] = $event2id;
    $this->participantCreate($params);
    $this->runUserDashboard();
    $expectedStrings = [
      '<div class="header-dark">Your Event(s)</div>',
      '<td class="crm-participant-event-id_1"><a href="/index.php?q=civicrm/event/info&amp;reset=1&amp;id=1&amp;context=dashboard">Annual CiviCRM meet</a></td>',
      '<td class="crm-participant-event-id_2"><a href="/index.php?q=civicrm/event/info&amp;reset=1&amp;id=2&amp;context=dashboard">Social Distancing Meetup Group</a></td>',
    ];
    $this->assertPageContains($expectedStrings);
    $this->individualCreate();
  }

}

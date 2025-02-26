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

use Civi\Api4\MembershipType;

/**
 * Class CRM_Member_BAO_MembershipTypeTest
 * @group headless
 */
class CRM_Member_BAO_MembershipTypeTest extends CiviUnitTestCase {

  public function setUp(): void {
    parent::setUp();

    //create relationship
    $params = [
      'name_a_b' => 'Relation 1',
      'name_b_a' => 'Relation 2',
      'contact_type_a' => 'Individual',
      'contact_type_b' => 'Organization',
      'is_reserved' => 1,
      'is_active' => 1,
    ];
    $this->_relationshipTypeId = $this->relationshipTypeCreate($params);
    $this->_orgContactID = $this->organizationCreate();
    $this->_indiviContactID = $this->individualCreate();
    $this->_financialTypeId = 1;
    $this->_membershipStatusID = $this->membershipStatusCreate('test status');
  }

  /**
   * Tears down the fixture, for example, closes a network connection.
   * This method is called after a test is executed.
   */
  public function tearDown(): void {
    $this->relationshipTypeDelete($this->_relationshipTypeId);
    $this->membershipStatusDelete($this->_membershipStatusID);
    $this->contactDelete($this->_orgContactID);
    $this->contactDelete($this->_indiviContactID);
    parent::tearDown();
  }

  /**
   * check function add()
   *
   */
  public function testAdd() {
    $ids = [];
    $params = [
      'name' => 'test type',
      'domain_id' => 1,
      'description' => NULL,
      'minimum_fee' => 10,
      'duration_unit' => 'year',
      'member_of_contact_id' => $this->_orgContactID,
      'period_type' => 'fixed',
      'duration_interval' => 1,
      'financial_type_id' => $this->_financialTypeId,
      'relationship_type_id' => $this->_relationshipTypeId,
      'visibility' => 'Public',
    ];

    $membershipType = CRM_Member_BAO_MembershipType::add($params, $ids);

    $membership = $this->assertDBNotNull('CRM_Member_BAO_MembershipType', $this->_orgContactID,
      'name', 'member_of_contact_id',
      'Database check on updated membership record.'
    );

    $this->assertEquals($membership, 'test type', 'Verify membership type name.');
    $this->membershipTypeDelete(['id' => $membershipType->id]);
  }

  /**
   * check function retrive()
   *
   */
  public function testRetrieve() {
    $ids = [];
    $params = [
      'name' => 'General',
      'description' => NULL,
      'domain_id' => 1,
      'minimum_fee' => 100,
      'duration_unit' => 'year',
      'period_type' => 'fixed',
      'member_of_contact_id' => $this->_orgContactID,
      'duration_interval' => 1,
      'financial_type_id' => $this->_financialTypeId,
      'relationship_type_id' => $this->_relationshipTypeId,
      'visibility' => 'Public',
    ];
    $membershipType = CRM_Member_BAO_MembershipType::add($params, $ids);

    $params = ['name' => 'General'];
    $default = [];
    $result = CRM_Member_BAO_MembershipType::retrieve($params, $default);
    $this->assertEquals($result->name, 'General', 'Verify membership type name.');
    $this->membershipTypeDelete(['id' => $membershipType->id]);
  }

  /**
   * check function isActive()
   *
   */
  public function testSetIsActive() {
    $ids = [];
    $params = [
      'name' => 'General',
      'description' => NULL,
      'domain_id' => 1,
      'minimum_fee' => 100,
      'duration_unit' => 'year',
      'period_type' => 'fixed',
      'duration_interval' => 1,
      'member_of_contact_id' => $this->_orgContactID,
      'financial_type_id' => $this->_financialTypeId,
      'relationship_type_id' => $this->_relationshipTypeId,
      'visibility' => 'Public',
      'is_active' => 1,
    ];
    $membership = CRM_Member_BAO_MembershipType::add($params, $ids);

    CRM_Member_BAO_MembershipType::setIsActive($membership->id, 0);

    $isActive = $this->assertDBNotNull('CRM_Member_BAO_MembershipType', $membership->id,
      'is_active', 'id',
      'Database check on membership type status.'
    );

    $this->assertEquals($isActive, 0, 'Verify membership type status.');
    $this->membershipTypeDelete(['id' => $membership->id]);
  }

  /**
   * check function del()
   *
   */
  public function testdel() {
    $ids = [];
    $params = [
      'name' => 'General',
      'description' => NULL,
      'minimum_fee' => 100,
      'domain_id' => 1,
      'duration_unit' => 'year',
      'period_type' => 'fixed',
      'member_of_contact_id' => $this->_orgContactID,
      'duration_interval' => 1,
      'financial_type_id' => $this->_financialTypeId,
      'relationship_type_id' => $this->_relationshipTypeId,
      'visibility' => 'Public',
      'is_active' => 1,
    ];
    $membership = CRM_Member_BAO_MembershipType::add($params, $ids);

    $result = CRM_Member_BAO_MembershipType::del($membership->id);

    $this->assertEquals($result, TRUE, 'Verify membership deleted.');
  }

  /**
   * check function convertDayFormat( )
   *
   */
  public function testConvertDayFormat() {
    $ids = [];
    $params = [
      'name' => 'General',
      'description' => NULL,
      'minimum_fee' => 100,
      'domain_id' => 1,
      'duration_unit' => 'year',
      'period_type' => 'fixed',
      'member_of_contact_id' => $this->_orgContactID,
      'fixed_period_start_day' => 1213,
      'fixed_period_rollover_day' => 1214,
      'duration_interval' => 1,
      'financial_type_id' => $this->_financialTypeId,
      'relationship_type_id' => $this->_relationshipTypeId,
      'visibility' => 'Public',
      'is_active' => 1,
    ];
    $membership = CRM_Member_BAO_MembershipType::add($params, $ids);
    $membershipType[$membership->id] = $params;

    CRM_Member_BAO_MembershipType::convertDayFormat($membershipType);

    $this->assertEquals($membershipType[$membership->id]['fixed_period_rollover_day'], 'Dec 14', 'Verify memberFixed Period Rollover Day.');
    $this->membershipTypeDelete(['id' => $membership->id]);
  }

  /**
   * check function getMembershipTypes( )
   *
   */
  public function testGetMembershipTypes() {
    $ids = [];
    $params = [
      'name' => 'General',
      'description' => NULL,
      'minimum_fee' => 100,
      'domain_id' => 1,
      'duration_unit' => 'year',
      'member_of_contact_id' => $this->_orgContactID,
      'period_type' => 'fixed',
      'duration_interval' => 1,
      'financial_type_id' => $this->_financialTypeId,
      'relationship_type_id' => $this->_relationshipTypeId,
      'visibility' => 'Public',
      'is_active' => 1,
    ];
    $membership = CRM_Member_BAO_MembershipType::add($params, $ids);
    $result = CRM_Member_BAO_MembershipType::getMembershipTypes();
    $this->assertEquals($result[$membership->id], 'General', 'Verify membership types.');
    $this->membershipTypeDelete(['id' => $membership->id]);
  }

  /**
   * check function getMembershipTypeDetails( )
   *
   */
  public function testGetMembershipTypeDetails() {
    $ids = [];
    $params = [
      'name' => 'General',
      'description' => NULL,
      'minimum_fee' => 100,
      'domain_id' => 1,
      'duration_unit' => 'year',
      'period_type' => 'fixed',
      'member_of_contact_id' => $this->_orgContactID,
      'duration_interval' => 1,
      'financial_type_id' => $this->_financialTypeId,
      'relationship_type_id' => $this->_relationshipTypeId,
      'visibility' => 'Public',
      'is_active' => 1,
    ];
    $membership = CRM_Member_BAO_MembershipType::add($params, $ids);
    $result = CRM_Member_BAO_MembershipType::getMembershipTypeDetails($membership->id);

    $this->assertEquals($result['name'], 'General', 'Verify membership type details.');
    $this->assertEquals($result['duration_unit'], 'year', 'Verify membership types details.');
    $this->membershipTypeDelete(['id' => $membership->id]);
  }

  /**
   * check function getDatesForMembershipType( )
   *
   */
  public function testGetDatesForMembershipType() {
    $ids = [];
    $params = [
      'name' => 'General',
      'description' => NULL,
      'minimum_fee' => 100,
      'domain_id' => 1,
      'duration_unit' => 'year',
      'member_of_contact_id' => $this->_orgContactID,
      'period_type' => 'rolling',
      'duration_interval' => 1,
      'financial_type_id' => $this->_financialTypeId,
      'relationship_type_id' => $this->_relationshipTypeId,
      'visibility' => 'Public',
      'is_active' => 1,
    ];
    $membership = CRM_Member_BAO_MembershipType::add($params, $ids);

    $membershipDates = CRM_Member_BAO_MembershipType::getDatesForMembershipType($membership->id);
    $this->assertEquals($membershipDates['start_date'], date('Ymd'), 'Verify membership types details.');
    $this->membershipTypeDelete(['id' => $membership->id]);
  }

  /**
   * check function getRenewalDatesForMembershipType( )
   *
   */
  public function testGetRenewalDatesForMembershipType() {
    $params = [
      'name' => 'General',
      'domain_id' => 1,
      'description' => NULL,
      'minimum_fee' => 100,
      'duration_unit' => 'year',
      'member_of_contact_id' => $this->_orgContactID,
      'period_type' => 'rolling',
      'duration_interval' => 1,
      'financial_type_id' => $this->_financialTypeId,
      'relationship_type_id' => $this->_relationshipTypeId,
      'visibility' => 'Public',
      'is_active' => 1,
    ];
    $membershipTypeID = MembershipType::create()->setValues($params)->execute()->first()['id'];

    $params = [
      'contact_id' => $this->_indiviContactID,
      'membership_type_id' => $membershipTypeID,
      'join_date' => '20060121000000',
      'start_date' => '20060121000000',
      'end_date' => '20070120000000',
      'source' => 'Payment',
      'is_override' => 1,
      'status_id' => $this->_membershipStatusID,
    ];

    $membership = $this->callAPISuccess('Membership', 'create', $params);

    $membershipRenewDates = CRM_Member_BAO_MembershipType::getRenewalDatesForMembershipType($membership['id']);

    $this->assertEquals('20060121', $membershipRenewDates['start_date'], 'Verify membership renewal start date.');
    $this->assertEquals('20080120', $membershipRenewDates['end_date'], 'Verify membership renewal end date.');

    $this->membershipDelete($membership['id']);
    $this->membershipTypeDelete(['id' => $membershipTypeID]);
  }

  /**
   * check function getMembershipTypesByOrg( )
   *
   */
  public function testGetMembershipTypesByOrg() {
    $ids = [];
    $params = [
      'name' => 'General',
      'description' => NULL,
      'domain_id' => 1,
      'minimum_fee' => 100,
      'duration_unit' => 'year',
      'member_of_contact_id' => $this->_orgContactID,
      'period_type' => 'rolling',
      'duration_interval' => 1,
      'financial_type_id' => $this->_financialTypeId,
      'relationship_type_id' => $this->_relationshipTypeId,
      'visibility' => 'Public',
      'is_active' => 1,
    ];
    $membershipType = CRM_Member_BAO_MembershipType::add($params, $ids);

    $membershipTypesResult = civicrm_api3('MembershipType', 'get', [
      'member_of_contact_id' => $this->_orgContactID,
      'options' => [
        'limit' => 0,
      ],
    ]);
    $result = $membershipTypesResult['values'] ?? NULL;
    $this->assertEquals(empty($result), FALSE, 'Verify membership types for organization.');

    $membershipTypesResult = civicrm_api3('MembershipType', 'get', [
      'member_of_contact_id' => 501,
      'options' => [
        'limit' => 0,
      ],
    ]);
    $result = $membershipTypesResult['values'] ?? NULL;
    $this->assertEquals(empty($result), TRUE, 'Verify membership types for organization.');

    $this->membershipTypeDelete(['id' => $membershipType->id]);
  }

}

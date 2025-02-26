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


namespace api\v4\Entity;

use Civi\Api4\CiviCase;
use api\v4\UnitTestCase;
use Civi\Api4\Relationship;

/**
 * @group headless
 */
class CaseTest extends UnitTestCase {

  public function setUp(): void {
    parent::setUp();
    \CRM_Core_BAO_ConfigSetting::enableComponent('CiviCase');
    $this->loadDataSet('CaseType');
  }

  public function tearDown(): void {
    $relatedTables = [
      'civicrm_activity',
      'civicrm_activity_contact',
      'civicrm_relationship',
      'civicrm_case_contact',
      'civicrm_case_type',
      'civicrm_case',
    ];
    $this->cleanup(['tablesToTruncate' => $relatedTables]);
    parent::tearDown();
  }

  public function testCreateUsingLoggedInUser() {
    $uid = $this->createLoggedInUser();

    $contactID = $this->createEntity(['type' => 'Individual'])['id'];

    $case = CiviCase::create(FALSE)
      ->addValue('case_type_id', $this->getReference('test_case_type_1')['id'])
      ->addValue('creator_id', 'user_contact_id')
      ->addValue('status_id', 1)
      ->addValue('contact_id', $contactID)
      ->execute()
      ->first();

    $relationships = Relationship::get(FALSE)
      ->addWhere('case_id', '=', $case['id'])
      ->execute();

    $this->assertCount(1, $relationships);
    $this->assertEquals($uid, $relationships[0]['contact_id_b']);
    $this->assertEquals($contactID, $relationships[0]['contact_id_a']);
  }

}

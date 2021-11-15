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

use api\v4\UnitTestCase;
use Civi\Api4\OptionGroup;
use Civi\Api4\OptionValue;
use Civi\Api4\SavedSearch;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * @group headless
 */
class ManagedEntityTest extends UnitTestCase implements TransactionalInterface, HookInterface {
  /**
   * @var array[]
   */
  private $_managedEntities = [];

  public function setUp(): void {
    $this->_managedEntities = [];
    parent::setUp();
  }

  public function hook_civicrm_managed(array &$entities): void {
    $entities = array_merge($entities, $this->_managedEntities);
  }

  public function testGetFields() {
    $fields = SavedSearch::getFields(FALSE)
      ->addWhere('type', '=', 'Extra')
      ->setLoadOptions(TRUE)
      ->execute()->indexBy('name');

    $this->assertEquals('Boolean', $fields['has_base']['data_type']);
    // If this core extension ever goes away or gets renamed, just pick a different one here
    $this->assertArrayHasKey('org.civicrm.flexmailer', $fields['base_module']['options']);
  }

  public function testRevertSavedSearch() {
    $this->_managedEntities[] = [
      // Setting module to 'civicrm' works for the test but not sure we should actually support that
      // as it's probably better to package stuff in a core extension instead of core itself.
      'module' => 'civicrm',
      'name' => 'testSavedSearch',
      'entity' => 'SavedSearch',
      'cleanup' => 'never',
      'update' => 'never',
      'params' => [
        'version' => 4,
        'values' => [
          'name' => 'TestManagedSavedSearch',
          'label' => 'Test Saved Search',
          'description' => 'Original state',
          'api_entity' => 'Contact',
          'api_params' => [
            'version' => 4,
            'select' => ['id'],
            'orderBy' => ['id', 'ASC'],
          ],
        ],
      ],
    ];

    \CRM_Core_ManagedEntities::singleton(TRUE)->reconcile();

    $search = SavedSearch::get(FALSE)
      ->addWhere('name', '=', 'TestManagedSavedSearch')
      ->addSelect('description', 'local_modified_date')
      ->execute()->single();
    $this->assertEquals('Original state', $search['description']);
    $this->assertNull($search['local_modified_date']);

    SavedSearch::update(FALSE)
      ->addValue('id', $search['id'])
      ->addValue('description', 'Altered state')
      ->execute();

    $time = $this->getCurrentTimestamp();
    $search = SavedSearch::get(FALSE)
      ->addWhere('name', '=', 'TestManagedSavedSearch')
      ->addSelect('description', 'has_base', 'base_module', 'local_modified_date')
      ->execute()->single();
    $this->assertEquals('Altered state', $search['description']);
    // Check calculated fields
    $this->assertTrue($search['has_base']);
    $this->assertEquals('civicrm', $search['base_module']);
    // local_modified_date should reflect the update just made
    $this->assertGreaterThanOrEqual($time, $search['local_modified_date']);
    $this->assertLessThanOrEqual($this->getCurrentTimestamp(), $search['local_modified_date']);

    SavedSearch::revert(FALSE)
      ->addWhere('name', '=', 'TestManagedSavedSearch')
      ->execute();

    // Entity should be revered to original state
    $result = SavedSearch::get(FALSE)
      ->addWhere('name', '=', 'TestManagedSavedSearch')
      ->addSelect('description', 'has_base', 'base_module', 'local_modified_date')
      ->execute();
    $search = $result->single();
    $this->assertEquals('Original state', $search['description']);
    // Check calculated fields
    $this->assertTrue($search['has_base']);
    $this->assertEquals('civicrm', $search['base_module']);
    // local_modified_date should be reset by the revert action
    $this->assertNull($search['local_modified_date']);

    // Check calculated fields for a non-managed entity - they should be empty
    $newName = uniqid(__FUNCTION__);
    SavedSearch::create(FALSE)
      ->addValue('name', $newName)
      ->addValue('label', 'Whatever')
      ->execute();
    $search = SavedSearch::get(FALSE)
      ->addWhere('name', '=', $newName)
      ->addSelect('label', 'has_base', 'base_module', 'local_modified_date')
      ->execute()->single();
    $this->assertEquals('Whatever', $search['label']);
    // Check calculated fields
    $this->assertEquals(NULL, $search['base_module']);
    $this->assertFalse($search['has_base']);
    $this->assertNull($search['local_modified_date']);
  }

  public function testAutoUpdateSearch() {
    $autoUpdateSearch = [
      'module' => 'civicrm',
      'name' => 'testAutoUpdate',
      'entity' => 'SavedSearch',
      'cleanup' => 'unused',
      'update' => 'unmodified',
      'params' => [
        'version' => 4,
        'values' => [
          'name' => 'TestAutoUpdateSavedSearch',
          'label' => 'Test AutoUpdate Search',
          'description' => 'Original state',
          'api_entity' => 'Email',
          'api_params' => [
            'version' => 4,
            'select' => ['id'],
            'orderBy' => ['id', 'ASC'],
          ],
        ],
      ],
    ];
    // Add managed search
    $this->_managedEntities[] = $autoUpdateSearch;
    \CRM_Core_ManagedEntities::singleton(TRUE)->reconcile();

    $search = SavedSearch::get(FALSE)
      ->addWhere('name', '=', 'TestAutoUpdateSavedSearch')
      ->addSelect('description', 'local_modified_date')
      ->execute()->single();
    $this->assertEquals('Original state', $search['description']);
    $this->assertNull($search['local_modified_date']);

    // Remove managed search
    $this->_managedEntities = [];
    \CRM_Core_ManagedEntities::singleton(TRUE)->reconcile();

    // Because the search has no displays, it will be deleted (cleanup = unused)
    $search = SavedSearch::get(FALSE)
      ->addWhere('name', '=', 'TestAutoUpdateSavedSearch')
      ->execute();
    $this->assertCount(0, $search);

    // Restore managed entity
    $this->_managedEntities = [];
    $this->_managedEntities[] = $autoUpdateSearch;
    \CRM_Core_ManagedEntities::singleton(TRUE)->reconcile();

    // Entity should be restored
    $result = SavedSearch::get(FALSE)
      ->addWhere('name', '=', 'TestAutoUpdateSavedSearch')
      ->addSelect('description', 'has_base', 'base_module', 'local_modified_date')
      ->execute();
    $search = $result->single();
    $this->assertEquals('Original state', $search['description']);
    // Check calculated fields
    $this->assertTrue($search['has_base']);
    $this->assertEquals('civicrm', $search['base_module']);
    $this->assertNull($search['local_modified_date']);

    $search = SavedSearch::get(FALSE)
      ->addWhere('name', '=', 'TestAutoUpdateSavedSearch')
      ->addSelect('description', 'local_modified_date')
      ->execute()->single();
    $this->assertEquals('Original state', $search['description']);
    $this->assertNull($search['local_modified_date']);

    // Update packaged version
    $autoUpdateSearch['params']['values']['description'] = 'New packaged state';
    $this->_managedEntities = [];
    $this->_managedEntities[] = $autoUpdateSearch;
    \CRM_Core_ManagedEntities::singleton(TRUE)->reconcile();

    // Because the entity was not modified, it will be updated to match the new packaged version
    $search = SavedSearch::get(FALSE)
      ->addWhere('name', '=', 'TestAutoUpdateSavedSearch')
      ->addSelect('description', 'local_modified_date')
      ->execute()->single();
    $this->assertEquals('New packaged state', $search['description']);
    $this->assertNull($search['local_modified_date']);

    // Update local
    SavedSearch::update(FALSE)
      ->addValue('id', $search['id'])
      ->addValue('description', 'Altered state')
      ->execute();

    // Update packaged version
    $autoUpdateSearch['params']['values']['description'] = 'Newer packaged state';
    $this->_managedEntities = [];
    $this->_managedEntities[] = $autoUpdateSearch;
    \CRM_Core_ManagedEntities::singleton(TRUE)->reconcile();

    // Because the entity was  modified, it will not be updated
    $search = SavedSearch::get(FALSE)
      ->addWhere('name', '=', 'TestAutoUpdateSavedSearch')
      ->addSelect('description', 'local_modified_date')
      ->execute()->single();
    $this->assertEquals('Altered state', $search['description']);
    $this->assertNotNull($search['local_modified_date']);

    SavedSearch::revert(FALSE)
      ->addWhere('name', '=', 'TestAutoUpdateSavedSearch')
      ->execute();

    // Entity should be revered to newer packaged state
    $result = SavedSearch::get(FALSE)
      ->addWhere('name', '=', 'TestAutoUpdateSavedSearch')
      ->addSelect('description', 'has_base', 'base_module', 'local_modified_date')
      ->execute();
    $search = $result->single();
    $this->assertEquals('Newer packaged state', $search['description']);
    // Check calculated fields
    $this->assertTrue($search['has_base']);
    $this->assertEquals('civicrm', $search['base_module']);
    // local_modified_date should be reset by the revert action
    $this->assertNull($search['local_modified_date']);
  }

  public function testOptionGroupAndValues() {
    $optionGroup = [
      'module' => 'civicrm',
      'name' => 'testManagedOptionGroup',
      'entity' => 'OptionGroup',
      'cleanup' => 'unused',
      'update' => 'unmodified',
      'params' => [
        'version' => 4,
        'values' => [
          'name' => 'testManagedOptionGroup',
          'title' => 'Test Managed Option Group',
          'description' => 'Original state',
          'is_active' => TRUE,
          'is_locked' => FALSE,
        ],
      ],
    ];
    $optionValue1 = [
      'module' => 'civicrm',
      'name' => 'testManagedOptionValue1',
      'entity' => 'OptionValue',
      'cleanup' => 'unused',
      'update' => 'unmodified',
      'params' => [
        'version' => 4,
        'values' => [
          'option_group_id.name' => 'testManagedOptionGroup',
          'value' => 1,
          'label' => 'Option Value 1',
          'description' => 'Original state',
          'is_active' => TRUE,
          'is_reserved' => FALSE,
          'weight' => 1,
          'icon' => 'fa-test',
        ],
      ],
    ];
    $this->_managedEntities[] = $optionGroup;
    $this->_managedEntities[] = $optionValue1;
    \CRM_Core_ManagedEntities::singleton(TRUE)->reconcile();

    $values = OptionValue::get(FALSE)
      ->addWhere('option_group_id.name', '=', 'testManagedOptionGroup')
      ->execute();

    $this->assertCount(1, $values);
    $this->assertEquals('Option Value 1', $values[0]['label']);

    $optionValue2 = [
      'module' => 'civicrm',
      'name' => 'testManagedOptionValue2',
      'entity' => 'OptionValue',
      'cleanup' => 'unused',
      'update' => 'unmodified',
      'params' => [
        'version' => 4,
        'values' => [
          'option_group_id.name' => 'testManagedOptionGroup',
          'value' => 2,
          'label' => 'Option Value 2',
          'description' => 'Original state',
          'is_active' => TRUE,
          'is_reserved' => FALSE,
          'weight' => 2,
          'icon' => 'fa-test',
        ],
      ],
    ];

    $this->_managedEntities[] = $optionValue2;
    \CRM_Core_ManagedEntities::singleton(TRUE)->reconcile();

    $values = OptionValue::get(FALSE)
      ->addWhere('option_group_id.name', '=', 'testManagedOptionGroup')
      ->addSelect('*', 'local_modified_date', 'has_base')
      ->addOrderBy('weight')
      ->execute();

    $this->assertCount(2, $values);
    $this->assertEquals('Option Value 2', $values[1]['label']);
    $this->assertNull($values[0]['local_modified_date']);
    $this->assertTrue($values[0]['has_base']);

    $this->_managedEntities = [];
    \CRM_Core_ManagedEntities::singleton(TRUE)->reconcile();

    $this->assertCount(0, OptionValue::get(FALSE)->addWhere('id', 'IN', $values->column('id'))->execute());
    $this->assertCount(0, OptionGroup::get(FALSE)->addWhere('name', '=', 'testManagedOptionGroup')->execute());
  }

  /**
   * @dataProvider sampleEntityTypes
   * @param string $entityName
   * @param bool $expected
   */
  public function testIsApi4ManagedType($entityName, $expected) {
    $this->assertEquals($expected, \CRM_Core_BAO_Managed::isAPi4ManagedType($entityName));
  }

  public function sampleEntityTypes() {
    $entityTypes = [
      // v3 pseudo-entity
      'ActivityType' => FALSE,
      // v3 pseudo-entity
      'CustomSearch' => FALSE,
      // Non-dao entities can't be managed
      'Entity' => FALSE,
      'Afform' => FALSE,
      'Settings' => FALSE,
      // v4 entity not using ManagedEntity trait
      'UFJoin' => FALSE,
      // v4 entities using ManagedEntity trait
      'ContactType' => TRUE,
      'CustomField' => TRUE,
      'CustomGroup' => TRUE,
      'MembershipType' => TRUE,
      'OptionGroup' => TRUE,
      'OptionValue' => TRUE,
      'SavedSearch' => TRUE,
    ];
    return array_combine(array_keys($entityTypes), \CRM_Utils_Array::makeNonAssociative($entityTypes, 0, 1));
  }

  private function getCurrentTimestamp() {
    return \CRM_Core_DAO::singleValueQuery('SELECT CURRENT_TIMESTAMP');
  }

}

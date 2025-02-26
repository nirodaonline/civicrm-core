<?php

/**
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 *
 * Generated from xml/schema/CRM/Mailing/MailingJob.xml
 * DO NOT EDIT.  Generated by CRM_Core_CodeGen
 * (GenCodeChecksum:2527bca832d0e7751c69a42d33f28159)
 */

/**
 * Database access object for the MailingJob entity.
 */
class CRM_Mailing_DAO_MailingJob extends CRM_Core_DAO {
  const EXT = 'civicrm';
  const TABLE_ADDED = '';
  const COMPONENT = 'CiviMail';

  /**
   * Static instance to hold the table name.
   *
   * @var string
   */
  public static $_tableName = 'civicrm_mailing_job';

  /**
   * Should CiviCRM log any modifications to this table in the civicrm_log table.
   *
   * @var bool
   */
  public static $_log = FALSE;

  /**
   * @var int
   */
  public $id;

  /**
   * The ID of the mailing this Job will send.
   *
   * @var int
   */
  public $mailing_id;

  /**
   * date on which this job was scheduled.
   *
   * @var timestamp
   */
  public $scheduled_date;

  /**
   * date on which this job was started.
   *
   * @var timestamp
   */
  public $start_date;

  /**
   * date on which this job ended.
   *
   * @var timestamp
   */
  public $end_date;

  /**
   * The state of this job
   *
   * @var string
   */
  public $status;

  /**
   * Is this job for a test mail?
   *
   * @var bool
   */
  public $is_test;

  /**
   * Type of mailling job: null | child
   *
   * @var string
   */
  public $job_type;

  /**
   * Parent job id
   *
   * @var int
   */
  public $parent_id;

  /**
   * Offset of the child job
   *
   * @var int
   */
  public $job_offset;

  /**
   * Queue size limit for each child job
   *
   * @var int
   */
  public $job_limit;

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->__table = 'civicrm_mailing_job';
    parent::__construct();
  }

  /**
   * Returns localized title of this entity.
   *
   * @param bool $plural
   *   Whether to return the plural version of the title.
   */
  public static function getEntityTitle($plural = FALSE) {
    return $plural ? ts('Mailing Jobs') : ts('Mailing Job');
  }

  /**
   * Returns foreign keys and entity references.
   *
   * @return array
   *   [CRM_Core_Reference_Interface]
   */
  public static function getReferenceColumns() {
    if (!isset(Civi::$statics[__CLASS__]['links'])) {
      Civi::$statics[__CLASS__]['links'] = static::createReferenceColumns(__CLASS__);
      Civi::$statics[__CLASS__]['links'][] = new CRM_Core_Reference_Basic(self::getTableName(), 'mailing_id', 'civicrm_mailing', 'id');
      Civi::$statics[__CLASS__]['links'][] = new CRM_Core_Reference_Basic(self::getTableName(), 'parent_id', 'civicrm_mailing_job', 'id');
      CRM_Core_DAO_AllCoreTables::invoke(__CLASS__, 'links_callback', Civi::$statics[__CLASS__]['links']);
    }
    return Civi::$statics[__CLASS__]['links'];
  }

  /**
   * Returns all the column names of this table
   *
   * @return array
   */
  public static function &fields() {
    if (!isset(Civi::$statics[__CLASS__]['fields'])) {
      Civi::$statics[__CLASS__]['fields'] = [
        'id' => [
          'name' => 'id',
          'type' => CRM_Utils_Type::T_INT,
          'title' => ts('Mailing Job ID'),
          'required' => TRUE,
          'where' => 'civicrm_mailing_job.id',
          'table_name' => 'civicrm_mailing_job',
          'entity' => 'MailingJob',
          'bao' => 'CRM_Mailing_BAO_MailingJob',
          'localizable' => 0,
          'html' => [
            'type' => 'Number',
          ],
          'readonly' => TRUE,
          'add' => NULL,
        ],
        'mailing_id' => [
          'name' => 'mailing_id',
          'type' => CRM_Utils_Type::T_INT,
          'title' => ts('Mailing ID'),
          'description' => ts('The ID of the mailing this Job will send.'),
          'required' => TRUE,
          'where' => 'civicrm_mailing_job.mailing_id',
          'table_name' => 'civicrm_mailing_job',
          'entity' => 'MailingJob',
          'bao' => 'CRM_Mailing_BAO_MailingJob',
          'localizable' => 0,
          'FKClassName' => 'CRM_Mailing_DAO_Mailing',
          'html' => [
            'label' => ts("Mailing"),
          ],
          'add' => NULL,
        ],
        'scheduled_date' => [
          'name' => 'scheduled_date',
          'type' => CRM_Utils_Type::T_TIMESTAMP,
          'title' => ts('Mailing Scheduled Date'),
          'description' => ts('date on which this job was scheduled.'),
          'required' => FALSE,
          'where' => 'civicrm_mailing_job.scheduled_date',
          'default' => NULL,
          'table_name' => 'civicrm_mailing_job',
          'entity' => 'MailingJob',
          'bao' => 'CRM_Mailing_BAO_MailingJob',
          'localizable' => 0,
          'html' => [
            'type' => 'Select Date',
            'formatType' => 'activityDateTime',
          ],
          'add' => NULL,
        ],
        'mailing_job_start_date' => [
          'name' => 'start_date',
          'type' => CRM_Utils_Type::T_TIMESTAMP,
          'title' => ts('Mailing Job Start Date'),
          'description' => ts('date on which this job was started.'),
          'required' => FALSE,
          'where' => 'civicrm_mailing_job.start_date',
          'default' => NULL,
          'table_name' => 'civicrm_mailing_job',
          'entity' => 'MailingJob',
          'bao' => 'CRM_Mailing_BAO_MailingJob',
          'localizable' => 0,
          'unique_title' => ts('Mailing Start Date'),
          'html' => [
            'type' => 'Select Date',
            'formatType' => 'activityDateTime',
          ],
          'add' => NULL,
        ],
        'end_date' => [
          'name' => 'end_date',
          'type' => CRM_Utils_Type::T_TIMESTAMP,
          'title' => ts('Mailing Job End Date'),
          'description' => ts('date on which this job ended.'),
          'required' => FALSE,
          'where' => 'civicrm_mailing_job.end_date',
          'default' => NULL,
          'table_name' => 'civicrm_mailing_job',
          'entity' => 'MailingJob',
          'bao' => 'CRM_Mailing_BAO_MailingJob',
          'localizable' => 0,
          'html' => [
            'type' => 'Select Date',
            'formatType' => 'activityDateTime',
          ],
          'add' => NULL,
        ],
        'mailing_job_status' => [
          'name' => 'status',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => ts('Mailing Job Status'),
          'description' => ts('The state of this job'),
          'maxlength' => 12,
          'size' => CRM_Utils_Type::TWELVE,
          'where' => 'civicrm_mailing_job.status',
          'table_name' => 'civicrm_mailing_job',
          'entity' => 'MailingJob',
          'bao' => 'CRM_Mailing_BAO_MailingJob',
          'localizable' => 0,
          'html' => [
            'type' => 'Select',
          ],
          'pseudoconstant' => [
            'callback' => 'CRM_Core_SelectValues::getMailingJobStatus',
          ],
          'add' => NULL,
        ],
        'is_test' => [
          'name' => 'is_test',
          'type' => CRM_Utils_Type::T_BOOLEAN,
          'title' => ts('Mailing Job Is Test?'),
          'description' => ts('Is this job for a test mail?'),
          'where' => 'civicrm_mailing_job.is_test',
          'default' => '0',
          'table_name' => 'civicrm_mailing_job',
          'entity' => 'MailingJob',
          'bao' => 'CRM_Mailing_BAO_MailingJob',
          'localizable' => 0,
          'add' => '1.9',
        ],
        'job_type' => [
          'name' => 'job_type',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => ts('Mailing Job Type'),
          'description' => ts('Type of mailling job: null | child '),
          'maxlength' => 255,
          'size' => CRM_Utils_Type::HUGE,
          'where' => 'civicrm_mailing_job.job_type',
          'table_name' => 'civicrm_mailing_job',
          'entity' => 'MailingJob',
          'bao' => 'CRM_Mailing_BAO_MailingJob',
          'localizable' => 0,
          'add' => '3.3',
        ],
        'parent_id' => [
          'name' => 'parent_id',
          'type' => CRM_Utils_Type::T_INT,
          'title' => ts('Parent ID'),
          'description' => ts('Parent job id'),
          'where' => 'civicrm_mailing_job.parent_id',
          'default' => NULL,
          'table_name' => 'civicrm_mailing_job',
          'entity' => 'MailingJob',
          'bao' => 'CRM_Mailing_BAO_MailingJob',
          'localizable' => 0,
          'FKClassName' => 'CRM_Mailing_DAO_MailingJob',
          'html' => [
            'label' => ts("Parent"),
          ],
          'add' => '3.3',
        ],
        'job_offset' => [
          'name' => 'job_offset',
          'type' => CRM_Utils_Type::T_INT,
          'title' => ts('Mailing Job Offset'),
          'description' => ts('Offset of the child job'),
          'where' => 'civicrm_mailing_job.job_offset',
          'default' => '0',
          'table_name' => 'civicrm_mailing_job',
          'entity' => 'MailingJob',
          'bao' => 'CRM_Mailing_BAO_MailingJob',
          'localizable' => 0,
          'add' => '3.3',
        ],
        'job_limit' => [
          'name' => 'job_limit',
          'type' => CRM_Utils_Type::T_INT,
          'title' => ts('Mailing Job Limit'),
          'description' => ts('Queue size limit for each child job'),
          'where' => 'civicrm_mailing_job.job_limit',
          'default' => '0',
          'table_name' => 'civicrm_mailing_job',
          'entity' => 'MailingJob',
          'bao' => 'CRM_Mailing_BAO_MailingJob',
          'localizable' => 0,
          'add' => '3.3',
        ],
      ];
      CRM_Core_DAO_AllCoreTables::invoke(__CLASS__, 'fields_callback', Civi::$statics[__CLASS__]['fields']);
    }
    return Civi::$statics[__CLASS__]['fields'];
  }

  /**
   * Return a mapping from field-name to the corresponding key (as used in fields()).
   *
   * @return array
   *   Array(string $name => string $uniqueName).
   */
  public static function &fieldKeys() {
    if (!isset(Civi::$statics[__CLASS__]['fieldKeys'])) {
      Civi::$statics[__CLASS__]['fieldKeys'] = array_flip(CRM_Utils_Array::collect('name', self::fields()));
    }
    return Civi::$statics[__CLASS__]['fieldKeys'];
  }

  /**
   * Returns the names of this table
   *
   * @return string
   */
  public static function getTableName() {
    return self::$_tableName;
  }

  /**
   * Returns if this table needs to be logged
   *
   * @return bool
   */
  public function getLog() {
    return self::$_log;
  }

  /**
   * Returns the list of fields that can be imported
   *
   * @param bool $prefix
   *
   * @return array
   */
  public static function &import($prefix = FALSE) {
    $r = CRM_Core_DAO_AllCoreTables::getImports(__CLASS__, 'mailing_job', $prefix, []);
    return $r;
  }

  /**
   * Returns the list of fields that can be exported
   *
   * @param bool $prefix
   *
   * @return array
   */
  public static function &export($prefix = FALSE) {
    $r = CRM_Core_DAO_AllCoreTables::getExports(__CLASS__, 'mailing_job', $prefix, []);
    return $r;
  }

  /**
   * Returns the list of indices
   *
   * @param bool $localize
   *
   * @return array
   */
  public static function indices($localize = TRUE) {
    $indices = [];
    return ($localize && !empty($indices)) ? CRM_Core_DAO_AllCoreTables::multilingualize(__CLASS__, $indices) : $indices;
  }

}

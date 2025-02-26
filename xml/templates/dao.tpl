<?php
/**
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 *
 * Generated from {$table.sourceFile}
 * {$generated}
 * (GenCodeChecksum:{$genCodeChecksum})
 */
{if isset($useHelper)}{$useHelper}{/if}
/**
 * Database access object for the {$table.entity} entity.
 */
class {$table.className} extends CRM_Core_DAO {ldelim}

     const EXT = {$ext};
     const TABLE_ADDED = '{$table.add}';
     {if !empty($table.component)}const COMPONENT = '{$table.component}';{/if}

     /**
      * Static instance to hold the table name.
      *
      * @var string
      */
      public static $_tableName = '{$table.name}';

   {* Only print this variable if it's different than the default in CRM_Core_DAO *}
   {if count($table.primaryKey.field) !== 1 || $table.primaryKey.field.0 !== 'id'}
     /**
      * Primary key field(s).
      *
      * @var string[]
      */
      public static $_primaryKey = [{if $table.primaryKey.field}'{"', '"|implode:$table.primaryKey.field}'{/if}];
   {/if}

   {if $table.icon}
     /**
      * Icon associated with this entity.
      *
      * @var string
      */
      public static $_icon = '{$table.icon}';
   {/if}

   {if $table.labelField}
     /**
      * Field to show when displaying a record.
      *
      * @var string
      */
      public static $_labelField = '{$table.labelField}';
   {/if}
      /**
       * Should CiviCRM log any modifications to this table in the civicrm_log table.
       *
       * @var bool
       */
      public static $_log = {$table.log|strtoupper};
      {if $table.paths}
     /**
      * Paths for accessing this entity in the UI.
      *
      * @var string[]
      */
      protected static $_paths = {$table.paths|@print_array};
   {/if}

{foreach from=$table.fields item=field}
    /**
{if $field.comment}
     * {$field.comment|regex_replace:"/\n[ ]*/":"\n* "}
     *
{/if}
     * @var {$field.phpType}
     */
    public ${$field.name};

{/foreach} {* table.fields *}

    /**
     * Class constructor.
     */
    public function __construct( ) {ldelim}
        $this->__table = '{$table.name}';

        parent::__construct( );
    {rdelim}

    /**
     * Returns localized title of this entity.
     *
     * @param bool $plural
     *   Whether to return the plural version of the title.
     */
    public static function getEntityTitle($plural = FALSE) {ldelim}
        return $plural ? {$tsFunctionName}('{$table.titlePlural}') : {$tsFunctionName}('{$table.title}');
    {rdelim}

{if !empty($table.description)}
  /**
  * Returns user-friendly description of this entity.
  *
  * @return string
  */
  public static function getEntityDescription() {ldelim}
    return {$tsFunctionName}('{$table.description|replace:"'":"\'"}');
  {rdelim}
{/if}


{if !empty($table.foreignKey) || !empty($table.dynamicForeignKey)}
    /**
     * Returns foreign keys and entity references.
     *
     * @return array
     *   [CRM_Core_Reference_Interface]
     */
    public static function getReferenceColumns() {ldelim}
      if (!isset(Civi::$statics[__CLASS__]['links'])) {ldelim}
        Civi::$statics[__CLASS__]['links'] = static::createReferenceColumns(__CLASS__);
{if isset($table.foreignKey)}
{foreach from=$table.foreignKey item=foreign}
        Civi::$statics[__CLASS__]['links'][] = new CRM_Core_Reference_Basic(self::getTableName(), '{$foreign.name}', '{$foreign.table}', '{$foreign.key}');
{/foreach}
{/if}
{if isset($table.dynamicForeignKey)}
{foreach from=$table.dynamicForeignKey item=foreign}
        Civi::$statics[__CLASS__]['links'][] = new CRM_Core_Reference_Dynamic(self::getTableName(), '{$foreign.idColumn}', NULL, '{$foreign.key|default:'id'}', '{$foreign.typeColumn}');
{/foreach}
{/if}
        CRM_Core_DAO_AllCoreTables::invoke(__CLASS__, 'links_callback', Civi::$statics[__CLASS__]['links']);
      {rdelim}
      return Civi::$statics[__CLASS__]['links'];
    {rdelim}
{/if} {* table.foreignKey *}

      /**
       * Returns all the column names of this table
       *
       * @return array
       */
      public static function &fields( ) {ldelim}
        if ( ! isset(Civi::$statics[__CLASS__]['fields']) ) {ldelim}
          Civi::$statics[__CLASS__]['fields'] = array(
{foreach from=$table.fields item=field}

{if $field.uniqueName}
  '{$field.uniqueName}'
{else}
                                            '{$field.name}'
{/if}
               => array(
                 'name'      => '{$field.name}',
                                                                      'type'      => {$field.crmType},
{if $field.title}
                                                                      'title'     => {$tsFunctionName}('{$field.title}'),
{/if}
{if $field.comment}
                                                                      'description'     => {$tsFunctionName}('{$field.comment|replace:"'":"\'"}'),
{/if}
{if $field.required}
                                        'required'  => {$field.required|strtoupper},
{/if} {* field.required *}
{if isset($field.length)}
                      'maxlength' => {$field.length},
{/if} {* field.length *}
{if isset($field.precision)}
                      'precision'      => array({$field.precision}),
{/if}
{if isset($field.size)}
                      'size'      => {$field.size},
{/if} {* field.size *}
{if isset($field.rows)}
                      'rows'      => {$field.rows},
{/if} {* field.rows *}
{if isset($field.cols)}
                      'cols'      => {$field.cols},
{/if} {* field.cols *}

{if $field.import}
                      'import'    => {$field.import|strtoupper},

{/if} {* field.import *}
  'where'     => '{$table.name}.{$field.name}',
  {if $field.headerPattern}'headerPattern' => '{$field.headerPattern}',{/if}
  {if $field.dataPattern}'dataPattern' => '{$field.dataPattern}',{/if}
{if $field.export}
                      'export'    => {$field.export|strtoupper},
{/if} {* field.export *}
{if $field.contactType}
                      'contactType' => {if $field.contactType == 'null'}NULL{else}'{$field.contactType}'{/if},
{/if}
{if $field.rule}
                      'rule'      => '{$field.rule}',
{/if} {* field.rule *}
{if !empty($field.permission)}
                      'permission'      => {$field.permission|@print_array},
{/if}
{if $field.default || $field.default === '0'}
  {capture assign=unquotedDefault}{if ($field.default[0]=="'" or $field.default[0]=='"')}{$field.default|substring:1:-1}{else}{$field.default}{/if}{/capture}
                         'default'   => {if ($unquotedDefault==='NULL')}NULL{else}'{$unquotedDefault}'{/if},
{/if} {* field.default *}
  'table_name' => '{$table.name}',
  'entity' => '{$table.entity}',
  'bao' => '{$table.bao}',
  'localizable' => {if $field.localizable}1{else}0{/if},
  {if isset($field.localize_context)}'localize_context' => '{$field.localize_context}',{/if}

{if isset($field.FKClassName)}
                      'FKClassName' => '{$field.FKClassName}',
{/if}
{if !empty($field.component)}
                      'component' => '{$field.component}',
{/if}
{if $field.serialize}
  'serialize' => self::SERIALIZE_{$field.serialize|strtoupper},
{/if}
{if $field.uniqueTitle}
  'unique_title' => {$tsFunctionName}('{$field.uniqueTitle}'),
{/if}
{if $field.html}
  'html' => array(
  {foreach from=$field.html item=val key=key}
    '{$key}' => {if $key eq 'label'}{$tsFunctionName}("{$val}"){else}'{$val}'{/if},
  {/foreach}
  ),
{/if}
{if $field.pseudoconstant}
  'pseudoconstant' => {$field.pseudoconstant|@print_array},
{/if}
{if $field.readonly || $field.name === $table.primaryKey.name}
  'readonly' => TRUE,
{/if}
  'add' => {if $field.add}'{$field.add}'{else}NULL{/if},
),
{/foreach} {* table.fields *}
                                      );
            CRM_Core_DAO_AllCoreTables::invoke(__CLASS__, 'fields_callback', Civi::$statics[__CLASS__]['fields']);
          {rdelim}
          return Civi::$statics[__CLASS__]['fields'];
      {rdelim}

      /**
       * Return a mapping from field-name to the corresponding key (as used in fields()).
       *
       * @return array
       *   Array(string $name => string $uniqueName).
       */
      public static function &fieldKeys( ) {ldelim}
        if (!isset(Civi::$statics[__CLASS__]['fieldKeys'])) {ldelim}
          Civi::$statics[__CLASS__]['fieldKeys'] = array_flip(CRM_Utils_Array::collect('name', self::fields()));
        {rdelim}
        return Civi::$statics[__CLASS__]['fieldKeys'];
      {rdelim}

      /**
       * Returns the names of this table
       *
       * @return string
       */
      public static function getTableName( ) {ldelim}
        {if $table.localizable}
          return CRM_Core_DAO::getLocaleTableName( self::$_tableName );
        {else}
          return self::$_tableName;
        {/if}
      {rdelim}

      /**
       * Returns if this table needs to be logged
       *
       * @return bool
       */
      public function getLog( ) {ldelim}
          return self::$_log;
      {rdelim}

      /**
       * Returns the list of fields that can be imported
       *
       * @param bool $prefix
       *
       * @return array
       */
       public static function &import( $prefix = FALSE ) {ldelim}
            $r = CRM_Core_DAO_AllCoreTables::getImports(__CLASS__, '{$table.labelName}', $prefix, array(
            {if isset($table.foreignKey)}{foreach from=$table.foreignKey item=foreign}
              {if $foreign.import}'{$foreign.className}',{/if}
            {/foreach}{/if}
            ));
            return $r;
      {rdelim}

       /**
        * Returns the list of fields that can be exported
        *
        * @param bool $prefix
        *
        * @return array
        */
       public static function &export( $prefix = FALSE ) {ldelim}
            $r = CRM_Core_DAO_AllCoreTables::getExports(__CLASS__, '{$table.labelName}', $prefix, array(
            {if isset($table.foreignKey)}{foreach from=$table.foreignKey item=foreign}
              {if $foreign.export}'{$foreign.className}',{/if}
            {/foreach}{/if}
            ));
            return $r;
      {rdelim}

      /**
       * Returns the list of indices
       *
       * @param bool $localize
       *
       * @return array
       */
      public static function indices($localize = TRUE) {ldelim}
        $indices = {$indicesPhp};
        return ($localize && !empty($indices)) ? CRM_Core_DAO_AllCoreTables::multilingualize(__CLASS__, $indices) : $indices;
      {rdelim}

{rdelim}

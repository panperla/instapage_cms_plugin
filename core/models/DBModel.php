<?php
class DBModel
{
  private static $dbModel = null;
  public $prefix = null;
  public $charsetCollate = null;
  public $optionsTable = null;
  public $pagesTable = null;
  public $debugTable = null;
  
  function __construct()
  {
    $this->prefix = Connector::getSelectedConnector()->getDBPrefix();
    $this->charsetCollate = Connector::getSelectedConnector()->getCharsetCollate();
    $this->optionsTable = $this->prefix . 'instapage_options';
    $this->pagesTable = $this->prefix . 'instapage_pages';
    $this->debugTable = $this->prefix . 'instapage_debug';
  }

  public static function getInstance()
  {
    if( self::$dbModel === null )
    {
      self::$dbModel = new DBModel();
    }

    return self::$dbModel;
  }

  public function query( $sql )
  {
    $args = func_get_args();
    array_shift( $args );
    
    if( isset( $args[ 0 ] ) && is_array( $args[ 0 ] )  )
    {
      $args = $args[ 0 ];
    }

    return Connector::getSelectedConnector()->query( $sql, $args );
  }

  public function lastInsertId()
  {
    return Connector::getSelectedConnector()->lastInsertId();
  }

  public function getRow( $sql )
  {
    $args = func_get_args();
    array_shift( $args );
    
    if( isset( $args[ 0 ] ) && is_array( $args[ 0 ] )  )
    {
      $args = $args[ 0 ];
    }

    return Connector::getSelectedConnector()->getRow( $sql, $args ); 
  }

  public static function getResults( $sql )
  {
    $args = func_get_args();
    array_shift( $args );
    
    if( isset( $args[ 0 ] ) && is_array( $args[ 0 ] )  )
    {
      $args = $args[ 0 ];
    }

    return Connector::getSelectedConnector()->getResults( $sql, $args ); 
  }

  public function initPluginTables()
  {
    $this->initOptionsTable();
    $this->initPagesTable();
    $this->initDebugTable();
  }

  private function initOptionsTable()
  {
    $sql = sprintf( 'CREATE TABLE IF NOT EXISTS %s(' . 
    'id MEDIUMINT(9) UNSIGNED NOT NULL AUTO_INCREMENT, ' . 
    'plugin_hash VARCHAR(255) DEFAULT \'\', ' . 
    'api_keys TEXT NOT NULL, ' .
    'user_name VARCHAR(255) DEFAULT \'\', ' . 
    'config TEXT NOT NULL, ' . 
    'UNIQUE KEY id (id)) %s', $this->optionsTable, $this->charsetCollate );
    
    $this->query( $sql );
  }

  private function initPagesTable()
  {
    $sql = sprintf( 'CREATE TABLE IF NOT EXISTS %s(' .
    'id MEDIUMINT(9) UNSIGNED NOT NULL AUTO_INCREMENT, ' . 
    'instapage_id INT UNSIGNED NOT NULL, ' . 
    'slug VARCHAR(50) DEFAULT \'\' NOT NULL, ' .
    'type VARCHAR(4) DEFAULT \'page\' NOT NULL, ' .
    'time TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL, ' .
    'stats_cache TEXT NOT NULL, ' . 
    'stats_cache_expires INT UNSIGNED, ' . 
    'UNIQUE KEY id (id)) %s', $this->pagesTable, $this->charsetCollate  );

    $this->query( $sql );

    $sql = sprintf( 'ALTER TABLE %s MODIFY time TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL', $this->pagesTable );

    $this->query( $sql );
  }

  private function initDebugTable()
  {
    $sql = sprintf( 'CREATE TABLE IF NOT EXISTS %s(' .
    'id MEDIUMINT(9) UNSIGNED NOT NULL AUTO_INCREMENT, ' . 
    'time TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL, ' .
    'text TEXT, ' . 
    'caller VARCHAR(255) DEFAULT \'\' NOT NULL, ' . 
    'name VARCHAR(50) DEFAULT \'\' NOT NULL, ' .
    'UNIQUE KEY id (id)) %s', $this->debugTable, $this->charsetCollate  );

    $this->query( $sql );

    $sql = sprintf( 'ALTER TABLE %s MODIFY time TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL', $this->debugTable );
    
    $this->query( $sql );
  }

  public function removePluginTables()
  {
    $this->query( 'DROP TABLE IF EXISTS ' . $this->optionsTable );
    $this->query( 'DROP TABLE IF EXISTS ' . $this->pagesTable );
    $this->query( 'DROP TABLE IF EXISTS ' . $this->debugTable );
  }
}

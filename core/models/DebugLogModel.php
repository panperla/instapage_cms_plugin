<?php
class DebugLogModel
{
  private static $debugLogModel = null;
  
  public static function getInstance()
  {
     if( self::$debugLogModel === null )
    {
      self::$debugLogModel = new DebugLogModel();
    }

    return self::$debugLogModel;
  }

  public function isDiagnosticMode()
  {
    return InstapageHelper::getOption( 'diagnostics', false );
  }

  public function write( $value, $name = '', $add_caller = true )
  {
    try
    {
      if ( is_array( $value ) || is_object( $value ) )
      {
        $value = print_r( $value, true );
      }

      $caller = '';

      if( $add_caller )
      {
        $trace = debug_backtrace();
        $trace_length = 3;
        $caller_arr = array();

        for( $i = 1; $i <= $trace_length; ++$i )
        {
          $caller = isset( $trace[ $i ] ) ? $trace[ $i ] : null;
          $caller_function = isset( $caller[ 'function' ] ) ? $caller[ 'function' ] : null;

          if( $caller_function == 'writeLog' || $caller_function == 'writeDiagnostics' )
          {
            $trace_length = 4;

            continue;
          }

          $caller_class = isset( $caller[ 'class' ] ) ? $caller[ 'class' ] . ' :: ' : null;

          if( $caller === null )
          {
            break;
          }

          $caller_arr[] = $caller_class . $caller_function;
        }
      }

      $caller = implode( "\r\n", $caller_arr );
      $db = DBModel::getInstance();
      $sql = 'INSERT INTO ' . $db->debugTable . ' VALUES(NULL, %s, %s, %s, %s)';
      $db->query( $sql, date( 'Y-m-d H:i:s' ), $value, $caller, $name );
    }
    catch ( Exception $e )
    {
      echo $e->getMessage();
    }
  }

  public function clear()
  {
    $db = DBModel::getInstance();
    $sql = 'DELETE FROM ' . $db->debugTable;
    $db->query( $sql );
  }

  public function read()
  {
    $db = DBModel::getInstance();
    $sql = 'SELECT * FROM ' . $db->debugTable;
    $results = $db->getResults( $sql );

    return $results;
  }

  public function getLogHTML()
  {
    if( Connector::currentUserCanManage() && $this->isDiagnosticMode() )
    {
      try
      {
        $plugins_html = Connector::getSelectedConnector()->getPluginsDebugHTML();
        $options_html = Connector::getSelectedConnector()->getOptionsDebugHTML();
        $phpinfo_html = $this->getPhpInfoHTML();
        $rows = $this->read();
        $view = ViewModel::getInstance();
        $view->init( INSTAPAGE_PLUGIN_PATH . '/templates/log.php' );
        $view->rows = $rows;
        $view->current_date = date( "Ymd_His" );
        $view->plugins_html = $plugins_html;
        $view->options_html = $options_html;
        $view->phpinfo_html = $phpinfo_html;
        $html = $view->fetch();

        return $html;

      } catch ( Exception $e )
      {
        throw $e;
      }
    }
    else
    {
      throw new Exception( __( 'Instapage log can be downloaded only in diagnostic mode.' ) );
    }
  }

  private function getPhpInfoHTML()
  {
    ob_start();
    phpinfo( INFO_GENERAL | INFO_CREDITS | INFO_CONFIGURATION | INFO_MODULES | INFO_ENVIRONMENT | INFO_VARIABLES );
    $contents = ob_get_contents();
    ob_end_clean();

    $pattern = '/<style.*?style>/s';
    $contents = preg_replace( $pattern, '', $contents );
    $contents = '<div class="phpinfo">' . $contents . '</div>';

    return $contents;
  }
}

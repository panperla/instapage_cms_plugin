<?php

class ViewModel
{
  private static $viewModel = null;
  protected $template_data = array();
  var $templates = null;

  public static function getInstance()
  {
    
    if( self::$viewModel === null )
    {
      self::$viewModel = new ViewModel();
    }

    return self::$viewModel;
  }

  public function __construct( $templates = null, $attributes = null )
  {
    if( $attributes )
    {
      foreach( $attributes as $key => $value )
      {
        $this->template_data[ $key ] = $value;
      }
    }

    $this->templates = $templates;
  }

  public function init( $templates = null, $attributes = null )
  {
    if( $attributes )
    {
      foreach( $attributes as $key => $value )
      {
        $this->template_data[ $key ] = $value;
      }
    }

    $this->templates = $templates;
  }

  public function __set( $name, $value )
  {
    $this->template_data[ $name ] = $value;
  }

  public function __toString()
  {
    return 'use $view->fetch() instead';
  }

  public function assign( $key, $value )
  {
    $this->template_data[ $name ] = $value;

    return $this;
  }

  public function fetch( $templates = null )
  {
    $templates = $templates ? $templates : $this->templates;

    if( !$templates || empty( $templates ) )
    {
      throw new Exception( "Templates can not be null." );
    }

    if( !is_array( $templates ) )
    {
      $templates = array( $templates );
    }

    foreach( $templates as $template )
    {
      if( !file_exists( $template ) )
      {
        throw new Exception( "Template {$template} not found." );
      }

      if( $this->template_data )
      {
        foreach( $this->template_data as $variable_name => $variable_value )
        {
          $$variable_name = $variable_value;
          unset( $variable_name );
          unset( $variable_value );
        }
      }

      ob_start();
      include( $template );
      $contents = ob_get_contents();
      ob_end_clean();
    }

    return $contents;
  }

  public static function get( $template, $variables = null )
  {
    $view = new View( $template );

    if( $variables )
    {
      foreach( $variables as $key => $value )
      {
        $view->$key = $value;
      }
    }

    return $view->fetch();
  }

  public static function _( $template, $variables = null )
  {
    return self::get( $template, $variables );
  }
}

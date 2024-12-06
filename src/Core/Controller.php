<?php

namespace ADIOS\Core;

/**
 * Core implementation of ADIOS controller
 * 
 * 'Controller' is fundamendal class for generating HTML content of each ADIOS call. Controllers can
 * be rendered using Twig template or using custom render() method.
 * 
 */
class Controller {
  /**
   * Reference to ADIOS object
   */
  protected ?\ADIOS\Core\Loader $app = null;
    
  /**
   * Shorthand for "global table prefix"
   */
  protected string $gtp = "";

  /**
   * DEPRECATED Array of parameters (arguments) passed to the controller
   */
  public array $params = [];

  /**
   * TRUE/FALSE array with permissions for the user role
   */
  public static array $permissionsByUserRole = [];
  
  /**
   * If set to FALSE, the rendered content of controller is available to public
   */
  public bool $requiresUserAuthentication = TRUE;

  /**
   * If set to TRUE, the default ADIOS desktop will not be added to the rendered content
   */
  public bool $hideDefaultDesktop = FALSE;

  /**
   * If set to FALSE, the controller will not be rendered in CLI
   */
  public static bool $cliSAPIEnabled = TRUE;

  /**
   * If set to FALSE, the controller will not be rendered in WEB
   */
  public static bool $webSAPIEnabled = TRUE;

  public array $dictionary = [];
  protected array $viewParams = [];

  public string $name = "";
  public string $shortName = "";
  public string $permission = "";
  public null|string $view = null;

  public Object $renderer;

  function __construct(\ADIOS\Core\Loader $app, array $params = [])
  {
    $this->name = str_replace("\\", "/", str_replace("ADIOS\\", "", get_class($this)));
    $this->app = $app;
    $this->renderer = $this->app->twig;

    $this->shortName = $this->name;
    $this->shortName = str_replace('Controllers/', '', $this->shortName);

    $this->permission = $this->shortName;

  }

  public function prepareParams(): array
  {
    return [];
  }

  /**
    * Validates inputs ($this->app->params) used for the TWIG template.
    *
    * return bool True if inputs are valid, otherwise false.
    */
  public function validateInputs(): bool
  {
    return TRUE;
  }

  /**
   * 1st phase of controller's initialization phase.
   *
   * @throws Exception Should throw an exception on error.
   */
  public function preInit()
  {
    //
  }

  /**
   * 2nd phase of controller's initialization phase.
   *
   * @throws Exception Should throw an exception on error.
   */
  public function init()
  {
    //
  }

  /**
   * 3rd phase of controller's initialization phase.
   *
   * @throws Exception Should throw an exception on error.
   */
  public function postInit()
  {
    //
  }

  /**
   * If the controller shall only return JSON, this method must be overriden.
   *
   * @return array Array to be returned as a JSON.
   */
  public function renderJson(): ?array
  {
    return null;
  }

  /**
   * If the controller shall return the HTML of the view, this method must be overriden.
   *
   * @return array View to be used to render the HTML.
   */
  public function prepareViewParams()
  {
    $this->viewParams = $this->app->params ?? [];
  }

  public function prepareView(): void
  {
    $this->prepareViewParams(); // 2024-12-04 prepareViewParams is deprecated, thus this wrapper
  }
  
  /**
   * Shorthand for ADIOS core translate() function. Uses own language dictionary.
   *
   * @param  string $string String to be translated
   * @param  string $context Context where the string is used
   * @param  string $toLanguage Output language
   * @return string Translated string.
   */
  public function translate(string $string, array $vars = []): string
  {
    return $this->app->translate($string, $vars, $this);
  }

  public function setView(null|string $view, array|null $viewParams = null)
  {
    $this->view = $view;
    if (is_array($viewParams)) $this->viewParams = $viewParams;
  }

  public function setRenderer(Object $renderer)
  {
    $this->renderer = $renderer;
  }

  public function getView(): null|string
  {
    return $this->view;
  }

  public function getViewParams(): array
  {
    return $this->viewParams;
  }

}


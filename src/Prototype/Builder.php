<?php

namespace ADIOS\Prototype;

class Builder {

  const REGENERATE_ALLOWED_TAG = "# prototypeBuilderRegenerateAllowed";

  protected string $inputPath = '';
  protected string $inputFile = '';
  protected string $outputFolder = '';
  protected string $sessionSalt = '';
  protected string $logFile = '';
  protected string $adminPassword = '';

  protected array $prototype = [];
  protected ?\Twig\Loader\ArrayLoader $twigArrayLoader = NULL;
  protected ?\Twig\Loader\FilesystemLoader $twigFilesystemLoader = NULL;
  protected ?\Twig\Environment $twig = NULL;
  protected $logHandle = NULL;

  public function __construct(string $inputFile, string $outputFolder, string $sessionSalt, string $logFile) {
    $this->inputFile = $inputFile;
    $this->outputFolder = $outputFolder;
    $this->sessionSalt = $sessionSalt;
    $this->logFile = $logFile;

    if (empty($this->outputFolder)) {
      throw new \Exception("No output folder for the prototype project provided.");
    }

    if (!is_dir($this->outputFolder)) {
      throw new \Exception("Output folder does not exist.");
    }

    if (!is_file($this->inputFile)) {
      $this->inputPath = $this->inputFile;
      $this->inputFile = $this->inputFile . 'index.json';
    } else {
      $this->inputPath = pathinfo($this->inputFile, PATHINFO_DIRNAME) . '/';
    }

    if (!is_file($this->inputFile)) throw new \Exception('Input file not found: ' . $this->inputFile);

    $this->prototype = $this->parsePrototypeFile($this->inputFile);

    $this->logHandle = fopen($this->logFile, "w");

    if (!is_array($this->prototype["ConfigApp"])) throw new \Exception("ConfigApp is missing in prototype definition.");

    $this->prototype["ConfigApp"]["sessionSalt"] = $this->sessionSalt;

    $this->twigArrayLoader = new \Twig\Loader\ArrayLoader([]);
    $this->twigFilesystemLoader = new \Twig\Loader\FilesystemLoader(__DIR__.'/Templates');
    $twigLoader = new \Twig\Loader\ChainLoader([$this->twigFilesystemLoader, $this->twigArrayLoader]);
    $this->twig = new \Twig\Environment($twigLoader, [
      'cache' => FALSE,
      'debug' => TRUE,
    ]);
    $this->twig->addFunction(new \Twig\TwigFunction(
      'getVariableType',
      function ($var) {
        if (is_numeric($var)) $type = "numeric";
        else if (is_bool($var)) $type = "bool";
        else $type = "string";

        return $type;
      }
    ));
    $this->twig->addFunction(new \Twig\TwigFunction(
      'varExport',
      function ($var, $indent = "") {
        if (is_string($var)) {
          $var = $var;
        } else {
          $var = $indent.str_replace("\n", "\n{$indent}", var_export($var, TRUE));
        }

        $var = preg_replace_callback(
          '/\'{php (.*?) php}\'/',
          function ($m) {
            return str_replace('\\\'', '\'', $m[1]);
          },
          $var
        );

        return $var;
      }
    ));

    $this->checkPrototype();
  }

  public function __destruct() {
    fclose($this->logHandle);
  }

  public function log($msg) {
    fwrite($this->logHandle, $msg."\n");
  }

  public function checkPrototype() {
    if (!is_array($this->prototype)) throw new \Exception("Prototype definition must be an array.");
  }

  public function setConfigEnv($configEnv) {
    $this->prototype['ConfigEnv'] = $configEnv;
  }

  public function setAdminPassword($adminPassword) {
    $this->adminPassword = $adminPassword;
  }

  public function createFolder($folder) {
    $this->log("Creating folder {$folder}.");
    @mkdir($this->outputFolder."/".$folder);
  }

  public function removeFolder($dir) { 
   if (is_dir($dir)) { 
      $objects = scandir($this->outputFolder.DIRECTORY_SEPARATOR.$dir);
      foreach ($objects as $object) {
        if (in_array($object, [".", ".."])) continue;
        if (
          is_dir($dir.DIRECTORY_SEPARATOR.$object)
          && !is_link($dir.DIRECTORY_SEPARATOR.$object)
        ) {
          $this->removeFolder($dir.DIRECTORY_SEPARATOR.$object);
        } else {
          unlink($dir.DIRECTORY_SEPARATOR.$object);
        }
      }
      rmdir($this->outputFolder.DIRECTORY_SEPARATOR.$dir);
    }
  }

  public function renderFile($fileName, $template, $twigParams = NULL) {
    $this->log("Rendering file {$fileName} from {$template}.");

    $outputFile = $this->outputFolder."/".$fileName;
    $canRender = FALSE;

    if (file_exists($outputFile)) {
      $canRender = (strpos(file_get_contents($outputFile), self::REGENERATE_ALLOWED_TAG) !== FALSE);
    } else {
      $canRender = TRUE;
    }

    if ($canRender) {
      $params = $twigParams ?? $this->prototype;
      $params['builderInfo'] = [
        'php' => 
          "/**\n"
          ." *\n"
          ." * This file was generated by the ADIOS prototype builder on " . date('Y-m-d H:i:s') . ".\n"
          ." *\n"
          ." * If you do not want to re-generate this file again, delete the following tag: \n"
          ." *\n"
          ." * " . self::REGENERATE_ALLOWED_TAG . "\n"
          ." *\n"
          ." */"
        ,
        'html' => 
          "<!--\n"
          ."  \n"
          ."  This file was generated by the ADIOS prototype builder on " . date('Y-m-d H:i:s') . ".\n"
          ."\n"
          ."  If you do not want to re-generate this file again, delete the following tag: \n"
          ."\n"
          ."  " . self::REGENERATE_ALLOWED_TAG . "\n"
          ."  \n"
          ."-->"
        ,
      ];

      file_put_contents(
        $outputFile,
        $this->twig->render($template, $params)
      );
    }
  }

  public function copyFile($srcFile, $destFile) {
    $this->log("Copying file {$srcFile} to {$destFile}.");
    if (!file_exists(__DIR__."/Templates/".$srcFile)) {
      throw new \Exception("File ".__DIR__."/Templates/{$srcFile} does not exist.");
    } else {
      copy(
        __DIR__."/Templates/".$srcFile,
        $this->outputFolder."/".$destFile
      );
    }

  }

  public function parsePrototypeFile(string $file): array {

    if (!is_file($file)) {
      throw new \Exception("Parse file: File not found ({$file})");
    }

    $format = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    switch ($format) {
      case 'json':
        $prototype = json_decode(file_get_contents($file), TRUE);
      break;
      case 'yml':
        $prototype = \Symfony\Component\Yaml\Yaml::parse(file_get_contents($file));
      break;
      default:
        $prototype = [];
      break;
    }

    return $prototype;
  }

  public function buildPrototype() {

    // delete folders if they exist
    $this->removeFolder('src/Widgets');
    $this->removeFolder('log');
    $this->removeFolder('tmp');
    $this->removeFolder('upload');

    // create folder structure
    $this->createFolder('src');
    $this->createFolder('src/Assets');
    $this->createFolder('src/Assets/images');
    $this->createFolder('src/Widgets');
    $this->createFolder('log');
    $this->createFolder('tmp');
    $this->createFolder('upload');

    // render files
    $this->copyFile('src/Assets/images/favicon.png', 'src/Assets/images/favicon.png');
    $this->copyFile('src/Assets/images/logo.png', 'src/Assets/images/logo.png');
    $this->copyFile('src/Assets/images/login-screen.jpg', 'src/Assets/images/login-screen.jpg');
    $this->copyFile('.htaccess', '.htaccess');
    $this->copyFile('.htaccess-subfolder', 'log/.htaccess');
    $this->copyFile('.htaccess-subfolder', 'tmp/.htaccess');
    $this->copyFile('.htaccess-subfolder', 'upload/.htaccess');

    $this->renderFile('src/Init.php', 'src/Init.php.twig');

    $this->renderFile('index.php', 'index.php.twig');
    $this->renderFile('web.php', 'web.php.twig');
    $this->renderFile('ConfigEnv.php', 'ConfigEnv.php.twig');

    $this->renderFile(
      'install.php',
      'install.php.twig',
      [
        'adminPassword' => $this->adminPassword ?? 'admin.'.rand(1000, 9999),
      ]
    );


    $configWidgetsEnabled = [];
    foreach ($this->prototype['Widgets'] as $widgetName => $widgetConfig) {
      if (strpos($widgetName, '/') !== FALSE) {
        $tmpCfg = &$configWidgetsEnabled;
        $tmpDirs = explode('/', $widgetName);
        foreach ($tmpDirs as $tmpLevel => $tmpDir) {
          if ($tmpLevel == count($tmpDirs) - 1) {
            $tmpCfg[$tmpDir]['enabled'] = TRUE;
          } else {
            if (!isset($tmpCfg[$tmpDir])) {
              $tmpCfg[$tmpDir] = NULL;
            }
            $tmpCfg = &$tmpCfg[$tmpDir];
          }
        }

      } else {
        $configWidgetsEnabled[$widgetName]['enabled'] = TRUE;
      }
    }

    $this->renderFile("src/ConfigApp.php", "src/ConfigApp.php.twig", array_merge(
      $this->prototype,
      [
        'configWidgetsEnabled' => $configWidgetsEnabled,
      ]
    ));

    // TODO: spravit @import univerzalny, nie iba pre importovanie do Widgets.

    // render widgets
    foreach ($this->prototype['Widgets'] as $widgetName => $widgetConfig) {
      $this->log('Building widget ' . $widgetName);

      $widgetNamespace = 'ADIOS\Widgets';
      $widgetClassName = '';

      if (strpos($widgetName, '/') !== FALSE) {
        $widgetRootDir = 'src/Widgets';

        $tmpDirs = explode('/', $widgetName);
        
        $widgetClassName = end($tmpDirs);

        foreach ($tmpDirs as $level => $tmpDir) {
          $widgetRootDir .= '/' . $tmpDir;

          if ($level != count($tmpDirs) - 1) {
            $widgetNamespace .= '\\' . $tmpDir;
          }

          $this->createFolder($widgetRootDir);
        }
      } else {
        $widgetRootDir = 'src/Widgets/' . $widgetName;
        $widgetClassName = $widgetName;
      }

      if (is_string($widgetConfig) && strpos($widgetConfig, "@import") !== false) {
        $filePath = trim(str_replace("@import", "", $widgetConfig));
        $this->log('Importing ' . $filePath);
        $widgetConfig = $this->parsePrototypeFile($this->inputPath . $filePath);
      }

      $this->createFolder("src/Widgets/{$widgetName}");
      $this->renderFile(
        $widgetRootDir . '/Main.php',
        'src/Widgets/WidgetMain.php.twig',
        array_merge(
          $this->prototype,
          [
            'thisWidget' => [
              'name' => $widgetName,
              'namespace' => $widgetNamespace,
              'class' => $widgetClassName,
              'config' => $widgetConfig
            ]
          ]
        )
      );

      if (is_array($widgetConfig['models'] ?? NULL)) {
        $this->createFolder($widgetRootDir . '/Models');
        $this->createFolder($widgetRootDir . '/Models/Callbacks');

        foreach ($widgetConfig['models'] as $modelName => $modelConfig) {
          $tmpModelParams = array_merge(
            $this->prototype,
            [
              'thisWidget' => [
                'name' => $widgetName,
                'namespace' => $widgetNamespace,
                'class' => $widgetClassName,
                'config' => $widgetConfig
              ],
              'thisModel' => [
                'namespace' => $widgetNamespace . '\\' . $widgetClassName . '\Models',
                'class' => $modelName,
                'config' => $modelConfig
              ]
            ]
          );

          $this->renderFile(
            $widgetRootDir . '/Models/' . $modelName . '.php',
            'src/Widgets/Model.php.twig',
            $tmpModelParams
          );

          $this->renderFile(
            $widgetRootDir . '/Models/Callbacks/' . $modelName . '.php',
            'src/Widgets/ModelCallbacks.php.twig',
            $tmpModelParams
          );
        }
      }

      if (is_array($widgetConfig['actions'] ?? NULL)) {
        $this->createFolder($widgetRootDir . '/Actions');
        $this->createFolder($widgetRootDir . '/Templates');

        foreach ($widgetConfig['actions'] as $actionName => $actionConfig) {
          if (isset($actionConfig['phpTemplate'])) {
            $actionPhpFileTemplate = 'src/Widgets/Actions/' . $actionConfig['phpTemplate'] . '.php.twig';
            $actionHtmlFileTemplate = '';
          } else {
            $actionPhpFileTemplate = 'src/Widgets/ActionWithTemplate.php.twig';
            $actionHtmlFileTemplate = 'src/Widgets/ActionTemplates/' . $actionConfig['template'] . '.html.twig';
          }

          $tmpActionConfig = $actionConfig;
          unset($tmpActionConfig['template']);
          unset($tmpActionConfig['phpTemplate']);

          $actionNamespace = $widgetNamespace . '\\' . $widgetClassName . '\Actions';
          $actionClassName = str_replace('/', '\\', $actionName);

          if (strpos($actionName, '/') !== FALSE) {
            $actionRootDir = $widgetRootDir . '/Actions';

            $tmpDirs = explode('/', $actionName);
            
            $actionClassName = end($tmpDirs);

            foreach ($tmpDirs as $level => $tmpDir) {
              $actionRootDir .= '/' . $tmpDir;

              if ($level != count($tmpDirs) - 1) {
                $actionNamespace .= '\\' . $tmpDir;
                $this->createFolder($actionRootDir);
              }

            }
          } else {
            $actionClassName = $actionName;
          }


          $tmpActionParams = array_merge(
            $this->prototype,
            [
              'thisWidget' => [
                'name' => $widgetName,
                'namespace' => $widgetNamespace,
                'class' => $widgetClassName,
                'config' => $widgetConfig
              ],
              'thisAction' => [
                'name' => $actionName,
                'namespace' => $actionNamespace,
                'class' => $actionClassName,
                'config' => $tmpActionConfig
              ]
            ]
          );

          $this->renderFile(
            $widgetRootDir . '/Actions/' . $actionName . '.php',
            $actionPhpFileTemplate,
            $tmpActionParams
          );

          if (!empty($actionHtmlFileTemplate)) {
            $this->renderFile(
              $widgetRootDir . '/Templates/' . $actionName . '.twig',
              $actionHtmlFileTemplate,
              $tmpActionParams
            );
          }
        }
      }
    }
  }

  // public function createEmptyDatabase() {
  //   $this->log("Creating empty database.");

  //   $dbCfg = $this->prototype['ConfigEnv']['db'];

  //   $db = new \mysqli(
  //     $dbCfg['host'],
  //     $dbCfg['user'],
  //     $dbCfg['password'],
  //     "",
  //     (int) ($dbCfg['port'] ?? 0)
  //   );

  //   $multiQuery = $this->twig->render("emptyDatabase.sql.twig", $this->prototype);
  //   $db->multi_query($multiQuery);
  // }
}
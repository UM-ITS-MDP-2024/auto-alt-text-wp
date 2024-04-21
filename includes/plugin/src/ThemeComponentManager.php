<?php

namespace Drupal\plugin\Plugin;

use ...

class ThemeComponentManager extends DefaultPluginManager{


    /**
     * Constructs a new ThemeComponentManager object.
     * 
     * @param \Traversable $namespaces
     *  An object that implements \Traversable which contains the root paths
     *  keyed by the corresponding namespace to look for plugin implementations.
     * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
     *  Cache backend instance to use.
     * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
     *  The module handler to invoke the alter hook with.
     */
    public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler){
        parent::__construct(
            subdir: 'Plugin/ThemeComponent',
            $namespaces,
            $module_handler,
            plugin_interface: 'Drupal\plugin\Plugin\ThemeComponentInterface',
        );
        $this->themeHandler = $theme_handler;
        $this->directories = $this->moduleHandler->getModuleDirectories() + $this->themeHandler->getThemeDirectories();
    }

}

?>
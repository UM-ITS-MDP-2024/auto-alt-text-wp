<?php

namespace Drupal\plugin\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for Theme component plugins.
 */

interface ThemeComponentInterface extends PluginInspectionInterface{

    /**
     * @param string $content
     */
    public function setContent(string $content);

    /**
     * @return string|array
     */
    public function getContent();

    /**
     * @return mixed
     */
    public function buildContent();
}

?>
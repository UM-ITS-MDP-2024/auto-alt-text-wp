<?php

namespace Drupal\plugin\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Theme component item annotation object.
 * 
 * @see \Drupal\plugin_demo\Plugin\ThemeComponentManager
 * @see plugin_api
 * 
 * @Annotation
 */

 class ThemeComponent extends Plugin {
    /**
     * The plugin ID.
     * 
     * @var string
     */
    public $id;

    /**
     * The label of the plugin.
     * 
     * @var \Drupal\Core\Annotation\Translation
     * 
     * @ingroup plugin_translatable
     */
    public $label;

    /**
     * The plugin attributes.
     * 
     * @var array
     */
    public $attributes = [];

    /**
     * The plugin variables.
     * 
     * @var array
     */
    public $variables = [];

    /**
     * The plugin closing tag.
     * 
     * @var bool
     */
    public $closing_tag = TRUE;
 }

 ?>

<?php

namespace Drupal\plugin\Plugin\ThemeComponent;

use ...

/**
 * Provides a 'Button' ThemeComponent.
 * 
 * @ThemeComponent(
 *  id = "btn",
 *  label = @Translation("Button"),
 *  attributes = {
 *      "class" = {
 *          "btn",
 *      },
 *  },
 *  element = "button",
 *  deriver = "Drupal\plugin\Plugin\Derivative\ThemeComponentColor"
 * )
 */

 class Button extends ThemeComponentBase{}
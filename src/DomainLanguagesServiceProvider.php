<?php

namespace Drupal\domain_languages;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Modifies the language manager service.
 */
class DomainLanguagesServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $definition = $container->getDefinition('language_manager');
    $definition->setClass('Drupal\domain_languages\DomainLanguagesLanguageManager')
      ->setArguments([
        new Reference('language.default'),
        new Reference('config.factory'),
        new Reference('module_handler'),
        new Reference('language.config_factory_override'),
        new Reference('request_stack'),
        new Reference('domain.negotiator'),
        new Reference('domain.loader'),
      ]);
  }

}

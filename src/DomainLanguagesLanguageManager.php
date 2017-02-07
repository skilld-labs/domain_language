<?php

namespace Drupal\domain_languages;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageDefault;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\Language;
use Drupal\domain\DomainLoaderInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\Entity\Domain;
use Drupal\language\Config\LanguageConfigFactoryOverrideInterface;
use Drupal\language\ConfigurableLanguageManager;
use Symfony\Component\HttpFoundation\RequestStack;

class DomainLanguagesLanguageManager extends ConfigurableLanguageManager {

  /**
   * The Domain Negotiator service.
   *
   * @var \Drupal\domain\DomainNegotiatorInterface
   */
  protected $domainNegotiator;

  /**
   * The Domain Loader service.
   *
   * @var \Drupal\domain\DomainLoaderInterface
   */
  protected $domainLoader;

  /**
   * The language state used when referring to all languages.
   */
  const STATE_ALL = 9;

  /**
   * Constructs a new ConfigurableLanguageManager object.
   *
   * @param \Drupal\Core\Language\LanguageDefault $default_language
   *   The default language service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\language\Config\LanguageConfigFactoryOverrideInterface $config_override
   *   The language configuration override service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack object.
   * @param \Drupal\domain\DomainNegotiatorInterface $domain_negotiator
   *   The Domain Negotiator service.
   * @param \Drupal\domain\DomainLoaderInterface $domain_loader
   *   The Domain Loader service.
   */
  public function __construct(LanguageDefault $default_language, ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler, LanguageConfigFactoryOverrideInterface $config_override, RequestStack $request_stack, DomainNegotiatorInterface $domain_negotiator, DomainLoaderInterface $domain_loader) {
    parent::__construct($default_language, $config_factory, $module_handler, $config_override, $request_stack);
    $this->domainNegotiator = $domain_negotiator;
    $this->domainLoader = $domain_loader;
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguages($flags = LanguageInterface::STATE_CONFIGURABLE, $domain_specific = TRUE) {
    // If a config override is set, cache using that language's ID.
    if ($override_language = $this->getConfigOverrideLanguage()) {
      $static_cache_id = $override_language->getId();
    }
    else {
      $static_cache_id = $this->getCurrentLanguage()->getId();
    }

    if (!isset($this->languages[$static_cache_id][$flags])) {
      // Initialize the language list with the default language and default
      // locked languages. These cannot be removed. This serves as a fallback
      // list if this method is invoked while the language module is installed
      // and the configuration entities for languages are not yet fully
      // imported.
      $default = $this->getDefaultLanguage();
      $languages = array($default->getId() => $default);
      $languages += $this->getDefaultLockedLanguages($default->getWeight());

      // Load configurable languages on top of the defaults. Ideally this could
      // use the entity API to load and instantiate ConfigurableLanguage
      // objects. However the entity API depends on the language system, so that
      // would result in infinite loops. We use the configuration system
      // directly and instantiate runtime Language objects. When language
      // entities are imported those cover the default and locked languages, so
      // site-specific configuration will prevail over the fallback values.
      // Having them in the array already ensures if this is invoked in the
      // middle of importing language configuration entities, the defaults are
      // always present.
      $config_ids = $this->configFactory->listAll('language.entity.');
      $domain_languages = $this->domainGrantedLanguages();

      foreach ($this->configFactory->loadMultiple($config_ids) as $config) {
        $data = $config->get();

        // Remove language from the list if it's not granted for current domain.
        if ($domain_specific && $domain_languages && !in_array($data['id'], $domain_languages)) {
          continue;
        }

        $data['name'] = $data['label'];
        $languages[$data['id']] = new Language($data);
      }
      Language::sort($languages);

      // Filter the full list of languages based on the value of $flags.
      $this->languages[$static_cache_id][$flags] = $this->filterLanguages($languages, $flags);
    }

    return $this->languages[$static_cache_id][$flags];
  }

  /**
   * Return languages list granted for current active domain.
   *
   * @return array
   *   Languages list granted for current active domain.
   */
  private function domainGrantedLanguages() {
    $domain = NULL;

    // Getting "domain" param from the request for using
    // only granted languages for currently edited domain.
    // @TODO: Add check "is admin path" if needed.
    $domain_from_request = $this->requestStack->getCurrentRequest()->get('domain');

    if ($domain_from_request && $domain_from_request instanceof Domain) {
      $domain = $domain_from_request;
    }
    elseif ($domain_from_request && is_string($domain_from_request)) {
      $domain = $this->domainLoader->load($domain_from_request);
    }

    // If "domain" param not available in current
    // request - get currently active domain object.
    if (!$domain) {
      $domain = $this->domainNegotiator->getActiveDomain();
    }
    if (!$domain) {
      $domain = $this->domainNegotiator->getActiveDomain(TRUE);
    }

    return $domain ? $domain->getThirdPartySetting('domain_languages', 'languages', []) : [];
  }
}

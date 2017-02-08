<?php

namespace Drupal\domain_languages\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain_languages\DomainLanguagesLanguageManager;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements an example form.
 */
class DomainLanguagesAdministerLanguagesForm extends FormBase {

  /**
   * The configurable language manager.
   *
   * @var \Drupal\language\ConfigurableLanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\language\ConfigurableLanguageManagerInterface $language_manager
   *   The configurable language manager.
   */
  public function __construct(ConfigurableLanguageManagerInterface $language_manager) {
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'domain_languages_administer_languages_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, DomainInterface $domain = NULL) {

    $options = [];
    foreach ($this->languageManager->getLanguages(DomainLanguagesLanguageManager::STATE_ALL, FALSE) as $key => $language) {
      $options[$key] = $language->getName();
    }

    $default_values = $domain->getThirdPartySetting('domain_languages', 'languages', array_keys($options));

    $form['languages'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Granted languages'),
      '#description' => $this->t('Languages list that should be available for current domain.'),
      '#options' => $options,
      '#default_value' => $default_values,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $errors = [];
    $not_selected_languages = 0;
    $languages = $form_state->getValue('languages');
    $default_language = $this->languageManager->getDefaultLanguage();

    foreach ($languages as $code => $language) {
      if (!$language) {
        $not_selected_languages++;

        if ($default_language->getId() === $code) {
          $errors[] = $this->t('"@language" language should be selected because it\'s current domain default language.', ['@language' => $default_language->getName()]);
        }
      }
    }

    if (count($languages) === $not_selected_languages) {
      $errors[] = $this->t('As minimum 1 language should be selected.');
    }
    if ($errors) {
      $form_state->setErrorByName('languages', implode(' ', $errors));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $build_info = $form_state->getBuildInfo();
    $domain = empty($build_info['args']) ? NULL : reset($build_info['args']);

    if (!$domain) {
      return FALSE;
    }

    $domain_languages = [];
    $languages = $form_state->getValue('languages');
    foreach ($languages as $language) {
      if ($language) {
        $domain_languages[] = $language;
      }
    }

    $domain->setThirdPartySetting('domain_languages', 'languages', $domain_languages);
    $domain->save();
    drupal_set_message($this->t('Domain languages set was saved.'));
  }

}

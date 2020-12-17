<?php

namespace Drupal\dyniva_content_agent\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Yaml\Yaml;

class ContentSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dyniva_content_agent_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('dyniva_content_agent.settings');

    $form['server_domain'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Hub URL'),
      '#description' => $this->t('Hub url without end slash.'),
      '#default_value' => $config->get('server_domain'),
    );
    $form['site_id'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Site ID'),
      '#description' => $this->t('Site ID in Hub.'),
      '#default_value' => $config->get('site_id'),
    );
    $form['push_to_hub'] = [
      '#title' => $this->t('Push to Hub'),
      '#type' => 'checkbox',
      '#description' => $this->t('Is default push content to hub.'),
      '#default_value' => $config->get('push_to_hub'),
    ];
    $form['user'] = [
      '#title' => $this->t('Import content author'),
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user'
    ];
    if($config->get('user')) {
      $form['user']['#default_value'] = user_load($config->get('user'));
    }

    $form['skipped_fields'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Skipped fields'),
      '#description' => $this->t('Please enter a field name per line.'),
      '#default_value' => $config->get('skipped_fields') ?: 'sync_sites'
    ];

    $form['default_fields'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Default fields (YAML)'),
      '#description' => "eg:<br/>node:<br/>&nbsp;&nbsp;article:<br/>&nbsp;&nbsp;&nbsp;&nbsp;category:<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;target_id: 1<br/>&nbsp;&nbsp;&nbsp;&nbsp;field_subtitle:<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;value: 'subtitle content'",
      '#default_value' => $config->get('default_fields') ?: '',
      '#attributes' => [
        'data-action' => 'codemirror-yaml'
      ]
    ];

    $form['enabled_content'] = [
      '#type' => 'details',
      '#title' => $this->t('Enabled Push content'),
      '#tree' => true,
      '#open' => true,
    ];

    $entity_types = [
      'node' => 'Node',
      'taxonomy_term' => 'Taxonomy'
    ];
    $enabled_content = $config->get('enabled_content');
    foreach ($entity_types as $entity_type_id => $label) {
      $options = [];
      $bundles = \Drupal::service('entity.manager')->getBundleInfo($entity_type_id);
      foreach ($bundles as $key => $type){
       $options[$key] = $type['label'];
      }

      $form['enabled_content'][$entity_type_id] = [
       '#type' => 'checkboxes',
       '#title' => $this->t($label),
       '#options' => $options,
       '#default_value' => empty($enabled_content[$entity_type_id]) ? []:$enabled_content[$entity_type_id],
      ];
    }
    $form['#attached'] = [
      'library' => [
        'dyniva_content_agent/codemirror.yaml'
      ]
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $default_fields = $form_state->getValue('default_fields');
    if($default_fields) {
      try {
        Yaml::parse($default_fields);
      } catch(\Exception $e) {
        $form_state->setError($form['default_fields'], t($e->getMessage()));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('dyniva_content_agent.settings');
    $config->set('server_domain', $form_state->getValue('server_domain'));
    $config->set('site_id', $form_state->getValue('site_id'));
    $config->set('user', $form_state->getValue('user'));
    $config->set('push_to_hub', $form_state->getValue('push_to_hub'));
    $config->set('skipped_fields', $form_state->getValue('skipped_fields'));
    $config->set('default_fields', $form_state->getValue('default_fields'));
    $enabled_content = $form_state->getValue('enabled_content');
    foreach ($enabled_content as $key => $items) {
      $enabled_content[$key] = array_filter($items);
    }
    $config->set('enabled_content', $enabled_content);
    $config->save();

    // 更新node type的同步字段
    $entity_types = _dyniva_content_agent_field_definition();
    foreach($entity_types as $type => $fields) {
      foreach($fields as $name => $field) {
        \Drupal::entityDefinitionUpdateManager()
          ->installFieldStorageDefinition($name, $type, 'dyniva_content_agent', $field);
      }
    }
    return parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'dyniva_content_agent.settings',
    ];
  }

}

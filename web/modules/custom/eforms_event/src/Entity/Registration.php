<?php

declare(strict_types=1);

namespace Drupal\eforms_event\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * A rendezvény-regisztráció entitás.
 *
 * @ContentEntityType(
 *   id = "eforms_registration",
 *   label = @Translation("eForms regisztráció"),
 *   label_collection = @Translation("eForms regisztrációk"),
 *   label_singular = @Translation("regisztráció"),
 *   label_plural = @Translation("regisztrációk"),
 *   handlers = {
 *     "list_builder" = "Drupal\eforms_event\RegistrationListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "default" = "Drupal\eforms_event\Form\RegistrationEditForm",
 *       "edit" = "Drupal\eforms_event\Form\RegistrationEditForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     }
 *   },
 *   base_table = "eforms_registration",
 *   admin_permission = "administer eforms registrations",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "name"
 *   },
 *   links = {
 *     "collection" = "/admin/content/eforms-registrations",
 *     "edit-form" = "/admin/content/eforms-registrations/{eforms_registration}/edit",
 *     "delete-form" = "/admin/content/eforms-registrations/{eforms_registration}/delete"
 *   }
 * )
 */
class Registration extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Teljes név'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => -5]);

    $fields['email'] = BaseFieldDefinition::create('email')
      ->setLabel(t('E-mail-cím'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', ['type' => 'email_default', 'weight' => -4]);

    $fields['phone'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Telefonszám'))
      ->setSetting('max_length', 64)
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => -3]);

    $fields['occasion'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Alkalom'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', [
        'szemelyes' => 'Személyes részvétel',
        'online' => 'Online részvétel',
      ]);

    $fields['gdpr'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Adatkezelési tájékoztató elfogadva'))
      ->setRequired(TRUE)
      ->setDefaultValue(FALSE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Beküldve'));

    $fields['teams_invite_sent'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Teams-meghívó kiküldve'))
      ->setDefaultValue(0)
      ->setInitialValue(0);

    $fields['reminder_sent'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Emlékeztető kiküldve'))
      ->setDefaultValue(0)
      ->setInitialValue(0);

    $fields['photo_consent'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Hozzájárult fotó készítéséhez'))
      ->setDefaultValue(FALSE)
      ->setInitialValue(FALSE);

    $fields['photo_publish_consent'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Hozzájárult a fotók közzétételéhez'))
      ->setDefaultValue(FALSE)
      ->setInitialValue(FALSE);

    $fields['admin_note'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Megjegyzés'))
      ->setDescription(t('Belső, adminisztrátori megjegyzés — a regisztráló nem látja.'))
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 0,
        'settings' => ['rows' => 4],
      ]);

    return $fields;
  }

}

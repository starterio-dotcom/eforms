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
      ->setSetting('max_length', 255);

    $fields['email'] = BaseFieldDefinition::create('email')
      ->setLabel(t('E-mail-cím'))
      ->setRequired(TRUE);

    $fields['phone'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Telefonszám'))
      ->setSetting('max_length', 64);

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

    return $fields;
  }

}

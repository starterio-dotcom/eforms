<?php

declare(strict_types=1);

namespace Drupal\eforms_event;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * A beérkezett regisztrációk admin listája.
 */
class RegistrationListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header = [
      'name' => $this->t('Teljes név'),
      'email' => $this->t('E-mail-cím'),
      'phone' => $this->t('Telefonszám'),
      'occasion' => $this->t('Alkalom'),
      'created' => $this->t('Beküldve'),
      'teams' => $this->t('Teams-meghívó'),
      'reminder' => $this->t('Emlékeztető'),
      'photo' => $this->t('Fotó (készítés / közzététel)'),
      'note' => $this->t('Megjegyzés'),
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\eforms_event\Entity\Registration $entity */
    $occasions = [
      'szemelyes' => 'Személyes részvétel',
      'online' => 'Online részvétel',
    ];
    $teams_sent = (int) $entity->get('teams_invite_sent')->value;
    $row = [
      'name' => $entity->get('name')->value,
      'email' => $entity->get('email')->value,
      'phone' => $entity->get('phone')->value ?: '—',
      'occasion' => $occasions[$entity->get('occasion')->value] ?? $entity->get('occasion')->value,
      'created' => \Drupal::service('date.formatter')->format((int) $entity->get('created')->value, 'short'),
      'teams' => $entity->get('occasion')->value !== 'online'
        ? '—'
        : ($teams_sent > 0 ? \Drupal::service('date.formatter')->format($teams_sent, 'short') : 'függőben'),
      'reminder' => ((int) $entity->get('reminder_sent')->value) > 0
        ? \Drupal::service('date.formatter')->format((int) $entity->get('reminder_sent')->value, 'short')
        : '—',
      'photo' => $entity->get('occasion')->value !== 'szemelyes'
        ? '—'
        : (($entity->get('photo_consent')->value ? 'igen' : 'nem') . ' / ' . ($entity->get('photo_publish_consent')->value ? 'igen' : 'nem')),
      'note' => ($note = (string) $entity->get('admin_note')->value) === ''
        ? '—'
        : (mb_strlen($note) > 40 ? mb_substr($note, 0, 40) . '…' : $note),
    ];
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity): array {
    $operations = parent::getDefaultOperations($entity);
    if (isset($operations['edit'])) {
      $operations['edit']['title'] = $this->t('Szerkesztés');
    }
    if (isset($operations['delete'])) {
      $operations['delete']['title'] = $this->t('Törlés');
    }
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build = parent::render();
    $build['table']['#empty'] = $this->t('Még nem érkezett regisztráció.');

    // Foglaltsági összegző a lista fölé.
    $summary_items = [];
    foreach (\Drupal::service('eforms_event.capacity')->getOccasions() as $occasion) {
      $summary_items[] = '<strong>' . $occasion['label'] . ':</strong> '
        . $occasion['taken'] . ' / ' . $occasion['capacity'] . ' foglalt'
        . ' (regisztráció: ' . $occasion['registered'] . ', induló: ' . $occasion['base_taken'] . ')'
        . ' · szabad: <strong>' . $occasion['free'] . '</strong>'
        . ($occasion['full'] ? ' — BETELT' : '');
    }
    $build['capacity_summary'] = [
      '#markup' => '<p>' . implode('<br>', $summary_items) . '</p>',
      '#weight' => -10,
      '#cache' => [
        'tags' => ['eforms_registration_list', 'config:eforms_event.settings'],
      ],
    ];
    return $build;
  }

}

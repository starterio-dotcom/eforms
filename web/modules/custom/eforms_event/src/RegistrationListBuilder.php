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
    $row = [
      'name' => $entity->get('name')->value,
      'email' => $entity->get('email')->value,
      'phone' => $entity->get('phone')->value ?: '—',
      'occasion' => $occasions[$entity->get('occasion')->value] ?? $entity->get('occasion')->value,
      'created' => \Drupal::service('date.formatter')->format((int) $entity->get('created')->value, 'short'),
    ];
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build = parent::render();
    $build['table']['#empty'] = $this->t('Még nem érkezett regisztráció.');
    return $build;
  }

}

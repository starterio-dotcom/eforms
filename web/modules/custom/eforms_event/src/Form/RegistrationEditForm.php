<?php

declare(strict_types=1);

namespace Drupal\eforms_event\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Regisztráció szerkesztése — adminisztrátori megjegyzés hozzáfűzése.
 *
 * Csak a megjegyzés-mező szerkeszthető; a regisztráció adatai
 * tájékoztatásul, olvasásra jelennek meg.
 */
class RegistrationEditForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    /** @var \Drupal\eforms_event\Entity\Registration $registration */
    $registration = $this->getEntity();
    $occasions = [
      'szemelyes' => 'Személyes részvétel',
      'online' => 'Online részvétel',
    ];
    $rows = [
      ['Teljes név', $registration->get('name')->value],
      ['E-mail-cím', $registration->get('email')->value],
      ['Telefonszám', $registration->get('phone')->value ?: '—'],
      ['Alkalom', $occasions[$registration->get('occasion')->value] ?? $registration->get('occasion')->value],
      ['Beküldve', \Drupal::service('date.formatter')->format((int) $registration->get('created')->value, 'short')],
    ];
    $items = '';
    foreach ($rows as [$label, $value]) {
      $items .= '<li><strong>' . $label . ':</strong> ' . htmlspecialchars((string) $value, ENT_QUOTES) . '</li>';
    }
    $form['attekintes'] = [
      '#markup' => '<ul>' . $items . '</ul>',
      '#weight' => -10,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $this->messenger()->addStatus('A megjegyzés mentve.');
    $form_state->setRedirect('entity.eforms_registration.collection');
    return $result;
  }

}

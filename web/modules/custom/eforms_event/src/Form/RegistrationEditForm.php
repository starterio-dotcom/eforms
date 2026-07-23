<?php

declare(strict_types=1);

namespace Drupal\eforms_event\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Regisztráció szerkesztése — az admin módosíthatja a résztvevő nevét,
 * e-mail-címét, telefonszámát és a belső megjegyzést.
 *
 * Az alkalom és a beküldés ideje csak tájékoztatásul, olvasásra jelenik meg:
 * az alkalom módosítása kapacitás- és levélautomatika-mellékhatásokkal járna.
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
    $this->messenger()->addStatus('A regisztráció módosításai mentve.');
    $form_state->setRedirect('entity.eforms_registration.collection');
    return $result;
  }

}

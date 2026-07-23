<?php

declare(strict_types=1);

namespace Drupal\eforms_event\Form;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eforms_event\Service\Capacity;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Az alkalmak kapacitásának adminisztrációja.
 */
class CapacitySettingsForm extends ConfigFormBase {

  protected Capacity $capacity;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->capacity = $container->get('eforms_event.capacity');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'eforms_capacity_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['eforms_event.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $occasions = $this->capacity->getOccasions();

    $form['info'] = [
      '#markup' => '<p>A <em>szabad helyek</em> számítása: kapacitás − (induló foglaltság + beérkezett regisztrációk). A módosítás mentés után azonnal megjelenik az eseményoldalon és a regisztrációs űrlapon.</p>',
    ];

    // Regisztrációs határidő.
    $deadline_raw = (string) $this->config('eforms_event.settings')->get('registration_deadline');
    $is_open = $this->capacity->isOpen();
    $form['deadline'] = [
      '#type' => 'details',
      '#title' => 'Regisztrációs határidő',
      '#open' => TRUE,
    ];
    $form['deadline']['allapot'] = [
      '#markup' => '<p><strong>A regisztráció jelenleg: ' . ($is_open ? 'NYITVA' : 'LEZÁRVA') . '</strong>'
      . ($this->capacity->getDeadlineLabel() ? ' · határidő: ' . $this->capacity->getDeadlineLabel() : ' · nincs határidő beállítva') . '</p>',
    ];
    $form['deadline']['registration_deadline'] = [
      '#type' => 'datetime',
      '#title' => 'Határidő',
      '#default_value' => $deadline_raw !== '' ? new DrupalDateTime($deadline_raw) : NULL,
      '#description' => 'Eddig az időpontig lehet regisztrálni; utána a regisztráció automatikusan lezár minden felületen. Üresen hagyva a regisztráció nyitva marad.',
    ];

    foreach ($occasions as $key => $occasion) {
      $form[$key] = [
        '#type' => 'details',
        '#title' => $occasion['label'],
        '#open' => TRUE,
        '#tree' => TRUE,
      ];
      $form[$key]['allapot'] = [
        '#markup' => '<p><strong>Jelenlegi állapot:</strong> ' . $occasion['taken'] . ' / ' . $occasion['capacity']
        . ' foglalt (ebből beérkezett regisztráció: ' . $occasion['registered'] . ') · szabad helyek: <strong>'
        . $occasion['free'] . '</strong>' . ($occasion['full'] ? ' — <strong>BETELT</strong>' : '') . '</p>',
      ];
      $form[$key]['capacity'] = [
        '#type' => 'number',
        '#title' => 'Kapacitás (' . $occasion['label'] . ')',
        '#default_value' => $occasion['capacity'],
        '#min' => 1,
        '#required' => TRUE,
        '#description' => 'A maximális létszám ezen az alkalmon.',
      ];
      $form[$key]['base_taken'] = [
        '#type' => 'number',
        '#title' => 'Induló foglaltság',
        '#default_value' => $occasion['base_taken'],
        '#min' => 0,
        '#required' => TRUE,
        '#description' => 'A rendszeren kívül (pl. más csatornán) már lefoglalt helyek száma — ez a beérkezett regisztrációkon felül számít foglaltnak.',
      ];
    }

    $form = parent::buildForm($form, $form_state);
    $form['actions']['submit']['#value'] = 'Mentés';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    foreach (array_keys($this->capacity->getOccasions()) as $key) {
      $values = $form_state->getValue($key);
      if ((int) $values['base_taken'] > (int) $values['capacity']) {
        $form_state->setErrorByName($key . '][base_taken', 'Az induló foglaltság nem lehet nagyobb a kapacitásnál.');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('eforms_event.settings');
    foreach (array_keys($this->capacity->getOccasions()) as $key) {
      $values = $form_state->getValue($key);
      $config->set('occasions.' . $key . '.capacity', (int) $values['capacity']);
      $config->set('occasions.' . $key . '.base_taken', (int) $values['base_taken']);
    }
    $deadline = $form_state->getValue('registration_deadline');
    $config->set('registration_deadline', $deadline instanceof DrupalDateTime ? $deadline->format('Y-m-d\TH:i:s') : '');
    $config->save();
    // A nyitva/lezárva állapotjelző szinkronban tartása a cron-logikával.
    \Drupal::state()->set('eforms_event.reg_open', $this->capacity->isOpen());
    $this->messenger()->addStatus('A beállítások mentve. A nyilvános oldalak azonnal frissültek.');
  }

}

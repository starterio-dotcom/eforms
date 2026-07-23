<?php

declare(strict_types=1);

namespace Drupal\eforms_event\Form;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eforms_event\Service\Capacity;
use Drupal\eforms_event\Service\TeamsInvite;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Az alkalmak kapacitásának adminisztrációja.
 */
class CapacitySettingsForm extends ConfigFormBase {

  protected Capacity $capacity;
  protected TeamsInvite $teamsInvite;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->capacity = $container->get('eforms_event.capacity');
    $instance->teamsInvite = $container->get('eforms_event.teams_invite');
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

    // Microsoft Teams meghívó (online alkalom).
    $teams_link = (string) $this->config('eforms_event.settings')->get('teams_link');
    $pending = $this->teamsInvite->pendingCount();
    $form['teams'] = [
      '#type' => 'details',
      '#title' => 'Microsoft Teams meghívó (online alkalom)',
      '#open' => TRUE,
    ];
    $form['teams']['allapot'] = [
      '#markup' => '<p>' . ($teams_link === ''
        ? '<strong>Nincs Teams-link beállítva.</strong>' . ($pending ? ' Jelenleg ' . $pending . ' online regisztráció vár meghívóra — a link mentésekor automatikusan kiküldjük.' : '')
        : '<strong>Teams-link beállítva.</strong>' . ($pending ? ' Függőben lévő meghívók: ' . $pending . ' (mentéskor kiküldjük).' : ' Minden online regisztráció megkapta a meghívót; az új regisztrációk beküldéskor automatikusan kapják.')) . '</p>',
    ];
    $form['teams']['teams_link'] = [
      '#type' => 'url',
      '#title' => 'Teams értekezlet csatlakozási linkje',
      '#default_value' => $teams_link,
      '#maxlength' => 2048,
      '#description' => 'A Microsoft Teams értekezlet meghívólinkje. Amíg üres, a meghívók nem mennek ki; beállítás után az új online regisztrációk azonnal, a korábbiak a mentéskor kapják meg a meghívót és a csatlakozási segédletet.',
    ];

    // Emlékeztető e-mailek állapota.
    $reminder_status = \Drupal::service('eforms_event.reminder')->getStatus();
    $occasion_labels = ['szemelyes' => 'Személyes részvétel', 'online' => 'Online részvétel'];
    $lines = [];
    foreach ($reminder_status as $key => $info) {
      $lines[] = '<strong>' . ($occasion_labels[$key] ?? $key) . ':</strong> emlékeztető napja '
        . ($info['reminder_day'] ?: 'nincs dátum beállítva')
        . ' · kiküldve: ' . $info['sent'] . ' / ' . $info['total']
        . ($info['active'] ? ' · <strong>az ablak most aktív</strong>' : '');
    }
    $form['reminder'] = [
      '#type' => 'details',
      '#title' => 'Emlékeztető e-mailek',
      '#open' => TRUE,
    ];
    $form['reminder']['allapot'] = [
      '#markup' => '<p>Az emlékeztetőt a rendszer automatikusan (cronból) küldi az esemény előtti napon minden regisztrálónak.</p><p>' . implode('<br>', $lines) . '</p>',
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
    $config->set('teams_link', trim((string) $form_state->getValue('teams_link')));
    $config->save();
    // A nyitva/lezárva állapotjelző szinkronban tartása a cron-logikával.
    \Drupal::state()->set('eforms_event.reg_open', $this->capacity->isOpen());
    $this->messenger()->addStatus('A beállítások mentve. A nyilvános oldalak azonnal frissültek.');

    // Függőben lévő Teams-meghívók kiküldése az újonnan mentett linkkel.
    $sent = $this->teamsInvite->sendPending();
    if ($sent > 0) {
      $this->messenger()->addStatus($sent . ' Teams-meghívót kiküldtünk a korábbi online regisztrálóknak.');
    }
  }

}

<?php

declare(strict_types=1);

namespace Drupal\eforms_event\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\eforms_event\Form\RegistrationForm;
use Drupal\eforms_event\Service\Capacity;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A rendezvényoldal, a regisztrációs oldal és a visszaigazoló oldal.
 */
class EventController extends ControllerBase {

  public function __construct(
    protected Capacity $capacity,
    protected PrivateTempStoreFactory $tempStoreFactory,
    protected KillSwitch $pageCacheKillSwitch,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('eforms_event.capacity'),
      $container->get('tempstore.private'),
      $container->get('page_cache_kill_switch'),
    );
  }

  /**
   * Eseményoldal (címlap).
   */
  public function eventPage(): array {
    $config = $this->config('eforms_event.settings');
    return [
      '#theme' => 'eforms_event_page',
      '#occasions' => $this->capacity->getOccasions(),
      '#program' => $config->get('program') ?: [],
      '#contact_email' => $config->get('contact_email') ?: '',
      '#attached' => [
        'library' => ['eforms_event/map'],
      ],
      '#cache' => [
        'tags' => ['eforms_registration_list', 'config:eforms_event.settings'],
      ],
    ];
  }

  /**
   * Regisztrációs oldal.
   */
  public function registerPage(): array {
    return [
      '#theme' => 'eforms_register_page',
      '#form' => $this->formBuilder()->getForm(RegistrationForm::class),
      '#cache' => [
        'tags' => ['eforms_registration_list', 'config:eforms_event.settings'],
        'contexts' => ['url.query_args:alkalom'],
      ],
    ];
  }

  /**
   * Sikeres regisztráció oldal.
   */
  public function donePage(): array|object {
    // Munkamenet-függő tartalom — nem kerülhet az anonim oldalgyorsítótárba.
    $this->pageCacheKillSwitch->trigger();

    $reg = $this->tempStoreFactory->get('eforms_event')->get('done');
    if (!$reg || empty($reg['esemeny'])) {
      return $this->redirect('eforms_event.page');
    }

    $config = $this->config('eforms_event.settings');
    $occasions = $config->get('occasions') ?: [];
    $occasion = $occasions[$reg['esemeny']] ?? NULL;
    if (!$occasion) {
      return $this->redirect('eforms_event.page');
    }

    return [
      '#theme' => 'eforms_done',
      '#reg' => [
        'nev' => $reg['nev'] ?: 'kedves Résztvevő',
        'email' => $reg['email'],
        'date_label' => $occasion['date_label'],
        'mode' => $occasion['label'] . ' — ' . $occasion['detail'],
        'time_label' => $occasion['time_label'],
        'note' => $occasion['done_note'] ?? '',
      ],
      '#contact_email' => $config->get('contact_email') ?: '',
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Adatkezelési tájékoztató oldal.
   */
  public function privacyPage(): array {
    return [
      '#theme' => 'eforms_privacy',
      '#contact_email' => $this->config('eforms_event.settings')->get('contact_email') ?: '',
      '#cache' => [
        'tags' => ['config:eforms_event.settings'],
      ],
    ];
  }

}

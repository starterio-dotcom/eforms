<?php

declare(strict_types=1);

namespace Drupal\eforms_event\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleExtensionList;
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
    protected ModuleExtensionList $moduleList,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('eforms_event.capacity'),
      $container->get('tempstore.private'),
      $container->get('page_cache_kill_switch'),
      $container->get('extension.list.module'),
    );
  }

  /**
   * Előadói portré URL-je.
   *
   * A helyőrző SVG mellé azonos néven elhelyezett fotó (jpg/jpeg/png/webp)
   * automatikusan felülírja a helyőrzőt — csere után elég egy cache-ürítés.
   */
  protected function speakerImage(string $slug): string {
    $dir = $this->moduleList->getPath('eforms_event') . '/images/eloadok/';
    foreach (['jpg', 'jpeg', 'png', 'webp', 'svg'] as $ext) {
      if (file_exists(DRUPAL_ROOT . '/' . $dir . $slug . '.' . $ext)) {
        return \Drupal::request()->getBasePath() . '/' . $dir . $slug . '.' . $ext;
      }
    }
    return '';
  }

  /**
   * A határidő közelében (±1 óra) kikapcsolja az anonim oldalgyorsítótárat,
   * hogy a lezárás pontosan a határidőnél jelenjen meg. Egyéb esetben a
   * cron-alapú érvénytelenítés gondoskodik az átbillenésről.
   */
  protected function handleDeadlineCaching(): void {
    foreach ($this->capacity->getDeadlines() as $deadline) {
      if (abs($deadline->getTimestamp() - time()) < 3600) {
        $this->pageCacheKillSwitch->trigger();
        return;
      }
    }
  }

  /**
   * Eseményoldal (címlap).
   */
  public function eventPage(): array {
    $this->handleDeadlineCaching();
    $config = $this->config('eforms_event.settings');
    return [
      '#theme' => 'eforms_event_page',
      '#reg_open' => $this->capacity->isOpen(),
      '#deadline_label' => $this->capacity->getDeadlineLabel(),
      '#occasions' => $this->capacity->getOccasions(),
      '#program' => $config->get('program') ?: [],
      '#speakers' => [
        [
          'name' => 'Tótka Tamás',
          'lines' => ['szakmai igazgató · Új Világ Nonprofit Szolgáltató Kft.'],
          'image' => $this->speakerImage('totka-tamas'),
        ],
        [
          'name' => 'dr. Zámbó Ákos',
          'lines' => ['főosztályvezető · Nemzeti Fejlesztési Központ,', 'Közbeszerzési Monitoring Főosztály'],
          'image' => $this->speakerImage('zambo-akos'),
        ],
        [
          'name' => 'dr. Poroszkai-German Gabriella',
          'lines' => ['EKR termékfelelős · TIGRA Zrt.'],
          'image' => $this->speakerImage('poroszkai-german-gabriella'),
        ],
      ],
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
   * Regisztrációs oldal — lejárt határidő után lezárt állapotot mutat.
   */
  public function registerPage(): array {
    $this->handleDeadlineCaching();

    if (!$this->capacity->isOpen()) {
      return [
        '#theme' => 'eforms_register_closed',
        '#deadline_label' => $this->capacity->getDeadlineLabel(),
        '#contact_email' => $this->config('eforms_event.settings')->get('contact_email') ?: '',
        '#cache' => [
          'tags' => ['config:eforms_event.settings'],
        ],
      ];
    }

    // Alkalmankénti határidő-összefoglaló az űrlap fejlécéhez.
    $deadline_parts = [];
    foreach ($this->capacity->getOccasions() as $occasion) {
      if (!$occasion['reg_open']) {
        $deadline_parts[] = $occasion['label'] . ': a regisztráció lezárult';
      }
      elseif ($occasion['deadline_label']) {
        $deadline_parts[] = $occasion['label'] . ': ' . $occasion['deadline_label'] . '-ig';
      }
    }

    return [
      '#theme' => 'eforms_register_page',
      '#form' => $this->formBuilder()->getForm(RegistrationForm::class),
      '#deadline_info' => implode(' · ', $deadline_parts),
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
        'mode' => $occasion['done_mode'] ?? ($occasion['label'] . ' — ' . $occasion['detail']),
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

  /**
   * Impresszum oldal.
   */
  public function imprintPage(): array {
    return [
      '#theme' => 'eforms_imprint',
      '#contact_email' => $this->config('eforms_event.settings')->get('contact_email') ?: '',
      '#cache' => [
        'tags' => ['config:eforms_event.settings'],
      ],
    ];
  }

  /**
   * Microsoft Teams csatlakozási segédlet — képes lépéssorral.
   */
  public function teamsGuidePage(): array {
    return [
      '#theme' => 'eforms_teams_guide',
      '#contact_email' => $this->config('eforms_event.settings')->get('contact_email') ?: '',
      '#base' => $this->moduleList->getPath('eforms_event') . '/images/segedlet',
      '#cache' => [
        'tags' => ['config:eforms_event.settings'],
      ],
    ];
  }

}

<?php

declare(strict_types=1);

namespace Drupal\eforms_event\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;

/**
 * Emlékeztető e-mailek az esemény előtti napon.
 *
 * Cron hívja: alkalmanként az esemény előtti nap 00:00-tól az esemény
 * napjának 10:00-jáig (a program kezdetéig) tartó ablakban küldi ki az
 * adott alkalom még emlékeztető nélküli regisztrálóinak a levelet.
 */
class Reminder {

  /**
   * A program kezdete az esemény napján — az ablak vége.
   */
  protected const EVENT_START_HOUR = 10;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected MailManagerInterface $mailManager,
  ) {}

  /**
   * Az emlékeztető-ablak határai egy alkalomhoz [kezdet, vég), vagy NULL.
   *
   * @return array{0: int, 1: int}|null
   */
  protected function getWindow(string $occasion_key): ?array {
    $date = (string) $this->configFactory->get('eforms_event.settings')->get('occasions.' . $occasion_key . '.date');
    if ($date === '') {
      return NULL;
    }
    try {
      $event_day = new \DateTimeImmutable($date . ' 00:00:00', new \DateTimeZone(date_default_timezone_get()));
    }
    catch (\Exception) {
      return NULL;
    }
    return [
      $event_day->modify('-1 day')->getTimestamp(),
      $event_day->setTime(self::EVENT_START_HOUR, 0)->getTimestamp(),
    ];
  }

  /**
   * Esedékes emlékeztetők kiküldése minden alkalomra.
   *
   * @return int
   *   A most kiküldött emlékeztetők száma.
   */
  public function sendDue(): int {
    $config = $this->configFactory->get('eforms_event.settings');
    $storage = $this->entityTypeManager->getStorage('eforms_registration');
    $now = time();
    $sent = 0;

    foreach (array_keys($config->get('occasions') ?: []) as $key) {
      $window = $this->getWindow($key);
      if (!$window || $now < $window[0] || $now >= $window[1]) {
        continue;
      }
      $ids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('occasion', $key)
        ->condition('reminder_sent', 0)
        ->range(0, 200)
        ->execute();
      $occasion = $config->get('occasions.' . $key) ?: [];
      foreach ($storage->loadMultiple($ids) as $registration) {
        try {
          $result = $this->mailManager->mail('eforms_event', 'reminder', (string) $registration->get('email')->value, 'hu', [
            'occasion' => $key,
            'nev' => (string) $registration->get('name')->value,
            'date_label' => $occasion['date_label'] ?? '',
          ]);
          if (!empty($result['result'])) {
            $registration->set('reminder_sent', time());
            $registration->save();
            $sent++;
          }
        }
        catch (\Throwable $e) {
          \Drupal::logger('eforms_event')->error('Az emlékeztető küldése nem sikerült (@id): @error', [
            '@id' => $registration->id(),
            '@error' => $e->getMessage(),
          ]);
        }
      }
    }
    return $sent;
  }

  /**
   * Áttekintés az admin felülethez alkalmanként.
   *
   * @return array<string, array{reminder_day: string, sent: int, total: int, active: bool}>
   */
  public function getStatus(): array {
    $config = $this->configFactory->get('eforms_event.settings');
    $storage = $this->entityTypeManager->getStorage('eforms_registration');
    $now = time();
    $status = [];
    foreach (array_keys($config->get('occasions') ?: []) as $key) {
      $window = $this->getWindow($key);
      $total = (int) $storage->getQuery()->accessCheck(FALSE)->condition('occasion', $key)->count()->execute();
      $sent = (int) $storage->getQuery()->accessCheck(FALSE)->condition('occasion', $key)->condition('reminder_sent', 0, '>')->count()->execute();
      $status[$key] = [
        'reminder_day' => $window ? date('Y-m-d', $window[0]) : '',
        'sent' => $sent,
        'total' => $total,
        'active' => $window && $now >= $window[0] && $now < $window[1],
      ];
    }
    return $status;
  }

}

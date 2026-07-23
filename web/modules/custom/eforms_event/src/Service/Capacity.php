<?php

declare(strict_types=1);

namespace Drupal\eforms_event\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Alkalmankénti kapacitás- és foglaltságszámítás.
 */
class Capacity {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Határidő-string értelmezése a site időzónájában.
   */
  protected function parseDeadline(string $raw): ?\DateTimeImmutable {
    if ($raw === '') {
      return NULL;
    }
    try {
      // A Drupal kérésenként a site időzónájára állítja a PHP alapértelmezést.
      return new \DateTimeImmutable($raw, new \DateTimeZone(date_default_timezone_get()));
    }
    catch (\Exception) {
      return NULL;
    }
  }

  /**
   * Határidő magyar formátumban (pl. „2026. augusztus 10. 23:59”).
   */
  public function formatDeadline(?\DateTimeImmutable $deadline): string {
    if ($deadline === NULL) {
      return '';
    }
    $months = [1 => 'január', 'február', 'március', 'április', 'május', 'június', 'július', 'augusztus', 'szeptember', 'október', 'november', 'december'];
    return $deadline->format('Y') . '. ' . $months[(int) $deadline->format('n')] . ' ' . $deadline->format('j') . '. ' . $deadline->format('H:i');
  }

  /**
   * Az alkalmankénti határidők (csak a beállítottak).
   *
   * @return array<string, \DateTimeImmutable>
   */
  public function getDeadlines(): array {
    $occasions = $this->configFactory->get('eforms_event.settings')->get('occasions') ?: [];
    $deadlines = [];
    foreach ($occasions as $key => $occasion) {
      $deadline = $this->parseDeadline((string) ($occasion['deadline'] ?? ''));
      if ($deadline !== NULL) {
        $deadlines[$key] = $deadline;
      }
    }
    return $deadlines;
  }

  /**
   * Van-e még legalább egy alkalom, amelyre lehet regisztrálni (határidő
   * szerint).
   */
  public function isOpen(): bool {
    $occasions = $this->configFactory->get('eforms_event.settings')->get('occasions') ?: [];
    if (!$occasions) {
      return TRUE;
    }
    $now = time();
    foreach ($occasions as $occasion) {
      $deadline = $this->parseDeadline((string) ($occasion['deadline'] ?? ''));
      if ($deadline === NULL || $now <= $deadline->getTimestamp()) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * A legkésőbbi határidő felirata (hero-chip, lezárt oldal).
   */
  public function getDeadlineLabel(): string {
    $deadlines = $this->getDeadlines();
    if (!$deadlines) {
      return '';
    }
    return $this->formatDeadline(max($deadlines));
  }

  /**
   * Az alkalmak adatai kapacitás-információkkal kiegészítve.
   *
   * @return array<string, array<string, mixed>>
   *   Alkalom-kulcsonként: label, date_label, time_label, detail, capacity,
   *   taken, free, full, badge_type, badge_text.
   */
  public function getOccasions(): array {
    $config = $this->configFactory->get('eforms_event.settings');
    $occasions = $config->get('occasions') ?: [];
    $storage = $this->entityTypeManager->getStorage('eforms_registration');

    $result = [];
    foreach ($occasions as $key => $occasion) {
      $count = (int) $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('occasion', $key)
        ->count()
        ->execute();
      $taken = min((int) $occasion['capacity'], (int) $occasion['base_taken'] + $count);
      $free = max(0, (int) $occasion['capacity'] - $taken);

      $deadline = $this->parseDeadline((string) ($occasion['deadline'] ?? ''));
      $reg_open = $deadline === NULL || time() <= $deadline->getTimestamp();

      // A nyilvános jelvények számot nem árulnak el, csak minőségi állapotot.
      if (!$reg_open) {
        $badge_type = 'negative';
        $badge_text = 'Lezárva';
      }
      elseif ($free === 0) {
        $badge_type = 'negative';
        $badge_text = 'Betelt';
      }
      elseif ($free <= 10) {
        $badge_type = 'warning';
        $badge_text = 'Utolsó helyek';
      }
      else {
        $badge_type = 'positive';
        $badge_text = 'Van szabad hely';
      }

      $result[$key] = $occasion + [
        'taken' => $taken,
        'free' => $free,
        'registered' => $count,
        'full' => $free === 0,
        'reg_open' => $reg_open,
        'selectable' => $reg_open && $free > 0,
        'deadline_label' => $this->formatDeadline($deadline),
        'badge_type' => $badge_type,
        'badge_text' => $badge_text,
        'card_badge_text' => $badge_text,
      ];
    }
    return $result;
  }

}

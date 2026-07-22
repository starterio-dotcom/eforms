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

      if ($free === 0) {
        $badge_type = 'negative';
        $badge_text = 'Betelt';
      }
      elseif ($free <= 10) {
        $badge_type = 'warning';
        $badge_text = 'Már csak ' . $free . ' szabad hely';
      }
      else {
        $badge_type = 'positive';
        $badge_text = 'Van szabad hely';
      }

      $result[$key] = $occasion + [
        'taken' => $taken,
        'free' => $free,
        'full' => $free === 0,
        'badge_type' => $badge_type,
        'badge_text' => $badge_text,
        // Az eseményoldali kártya rövidebb badge-e.
        'card_badge_text' => $free === 0 ? 'Betelt' : ($free <= 10 ? 'Utolsó helyek' : 'Van szabad hely'),
      ];
    }
    return $result;
  }

}

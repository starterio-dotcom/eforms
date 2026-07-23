<?php

declare(strict_types=1);

namespace Drupal\eforms_event\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\eforms_event\Entity\Registration;

/**
 * Microsoft Teams meghívók automatikus kiküldése online regisztrációkhoz.
 *
 * A meghívó a konfigurált Teams-linkkel és csatlakozási segédlettel megy ki:
 * regisztrációkor azonnal (ha a link már be van állítva), egyébként a link
 * beállításakor, illetve cronból a függőben maradt címzetteknek.
 */
class TeamsInvite {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected MailManagerInterface $mailManager,
  ) {}

  /**
   * A konfigurált Teams értekezlet-link (üres, ha még nincs beállítva).
   */
  public function getLink(): string {
    return (string) $this->configFactory->get('eforms_event.settings')->get('teams_link');
  }

  /**
   * Meghívó küldése egy online regisztrációnak, ha még nem kapott.
   */
  public function sendFor(Registration $registration): bool {
    $link = $this->getLink();
    if ($link === ''
      || $registration->get('occasion')->value !== 'online'
      || (int) $registration->get('teams_invite_sent')->value > 0) {
      return FALSE;
    }

    $config = $this->configFactory->get('eforms_event.settings');
    $occasion = $config->get('occasions.online') ?: [];
    try {
      $result = $this->mailManager->mail('eforms_event', 'teams_invite', (string) $registration->get('email')->value, 'hu', [
        'nev' => (string) $registration->get('name')->value,
        'date_label' => $occasion['date_label'] ?? '',
        'link' => $link,
      ]);
      if (empty($result['result'])) {
        return FALSE;
      }
      $registration->set('teams_invite_sent', time());
      $registration->save();
      return TRUE;
    }
    catch (\Throwable $e) {
      \Drupal::logger('eforms_event')->error('A Teams-meghívó küldése nem sikerült (@id): @error', [
        '@id' => $registration->id(),
        '@error' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * A még meghívó nélküli online regisztrációk kiszolgálása.
   *
   * @return int
   *   A most kiküldött meghívók száma.
   */
  public function sendPending(int $limit = 100): int {
    if ($this->getLink() === '') {
      return 0;
    }
    $storage = $this->entityTypeManager->getStorage('eforms_registration');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('occasion', 'online')
      ->condition('teams_invite_sent', 0)
      ->range(0, $limit)
      ->execute();

    $sent = 0;
    foreach ($storage->loadMultiple($ids) as $registration) {
      if ($this->sendFor($registration)) {
        $sent++;
      }
    }
    return $sent;
  }

  /**
   * Hány online regisztráció vár még meghívóra.
   */
  public function pendingCount(): int {
    return (int) $this->entityTypeManager->getStorage('eforms_registration')->getQuery()
      ->accessCheck(FALSE)
      ->condition('occasion', 'online')
      ->condition('teams_invite_sent', 0)
      ->count()
      ->execute();
  }

}

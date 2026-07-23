<?php

declare(strict_types=1);

namespace Drupal\eforms_event\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;

/**
 * A beérkezett regisztrációk CSV-exportja.
 */
class RegistrationExportController extends ControllerBase {

  /**
   * CSV-letöltés (pontosvesszős, UTF-8 BOM-mal — magyar Excelhez).
   */
  public function csv(): Response {
    $storage = $this->entityTypeManager()->getStorage('eforms_registration');
    $ids = $storage->getQuery()->accessCheck(FALSE)->sort('id')->execute();

    $occasions = [
      'szemelyes' => 'Személyes részvétel',
      'online' => 'Online részvétel',
    ];
    $date_formatter = \Drupal::service('date.formatter');

    $handle = fopen('php://temp', 'r+');
    fputcsv($handle, ['Azonosító', 'Teljes név', 'E-mail-cím', 'Telefonszám', 'Alkalom', 'Adatkezelés elfogadva', 'Beküldve', 'Teams-meghívó kiküldve', 'Emlékeztető kiküldve', 'Megjegyzés'], ';');
    foreach ($storage->loadMultiple($ids) as $registration) {
      $teams_sent = (int) $registration->get('teams_invite_sent')->value;
      $reminder_sent = (int) $registration->get('reminder_sent')->value;
      fputcsv($handle, [
        $registration->id(),
        $this->sanitizeCell((string) $registration->get('name')->value),
        $this->sanitizeCell((string) $registration->get('email')->value),
        $this->sanitizeCell((string) $registration->get('phone')->value),
        $occasions[$registration->get('occasion')->value] ?? $registration->get('occasion')->value,
        $registration->get('gdpr')->value ? 'igen' : 'nem',
        $date_formatter->format((int) $registration->get('created')->value, 'custom', 'Y-m-d H:i:s'),
        $registration->get('occasion')->value !== 'online'
          ? ''
          : ($teams_sent > 0 ? $date_formatter->format($teams_sent, 'custom', 'Y-m-d H:i:s') : 'függőben'),
        $reminder_sent > 0 ? $date_formatter->format($reminder_sent, 'custom', 'Y-m-d H:i:s') : '',
        $this->sanitizeCell((string) $registration->get('admin_note')->value),
      ], ';');
    }
    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    $response = new Response("\xEF\xBB\xBF" . $csv);
    $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="eforms-regisztraciok-' . date('Y-m-d') . '.csv"');
    // Személyes adatok — semmilyen köztes gyorsítótárban ne ragadjon bent.
    $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
    return $response;
  }

  /**
   * Excel-képletinjektálás elleni védelem a felhasználói cellákban.
   */
  protected function sanitizeCell(string $value): string {
    if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], TRUE)) {
      return "'" . $value;
    }
    return $value;
  }

}

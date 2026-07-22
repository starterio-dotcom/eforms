<?php

declare(strict_types=1);

namespace Drupal\eforms_event\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\eforms_event\Entity\Registration;
use Drupal\eforms_event\Service\Capacity;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Rendezvény-regisztrációs űrlap a DÁP design szerinti hibakezeléssel.
 *
 * A validálás a submit fázisban történik, hogy a hibák pontosan a design
 * szerint (felső Callout + mezőnkénti FeedbackMessage) jelenjenek meg,
 * a Drupal alapértelmezett üzenetsora helyett.
 */
class RegistrationForm extends FormBase {

  public function __construct(
    protected Capacity $capacity,
    protected PrivateTempStoreFactory $tempStoreFactory,
    protected MailManagerInterface $mailManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('eforms_event.capacity'),
      $container->get('tempstore.private'),
      $container->get('plugin.manager.mail'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'eforms_registration_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $occasions = $this->capacity->getOccasions();
    $errors = $form_state->get('eforms_errors') ?? [];
    $input = $form_state->getUserInput();
    $selected = (string) ($input['esemeny'] ?? $this->getRequest()->query->get('alkalom', ''));
    if (!isset($occasions[$selected])) {
      $selected = '';
    }

    $form['#attributes']['class'][] = 'panel';
    $form['#attributes']['novalidate'] = 'novalidate';

    if ($errors) {
      $form['error_callout'] = [
        '#markup' => Markup::create(
          '<div class="dap-callout dap-callout--negative" role="alert"><div class="dap-callout__body">'
          . '<div class="dap-callout__title">Hiányzó adatok</div>'
          . '<div class="dap-callout__text">Kérjük, ellenőrizze a pirossal jelölt mezőket, majd küldje be újra az űrlapot.</div>'
          . '</div></div>'
        ),
      ];
    }

    // 1 — Alkalom kiválasztása (kártyás rádiógombok).
    $cards = '';
    foreach ($occasions as $key => $occasion) {
      $is_selected = $selected === $key;
      $cards .= '<label class="evopt' . ($is_selected ? ' sel' : '') . ($occasion['full'] ? ' evopt--disabled' : '') . '">'
        . '<input type="radio" name="esemeny" value="' . Html::escape($key) . '"'
        . ($is_selected ? ' checked="checked"' : '')
        . ($occasion['full'] ? ' disabled="disabled"' : '')
        . '>'
        . '<span class="evopt-top"><b>' . Html::escape($occasion['label']) . '</b><span class="radio-dot"></span></span>'
        . '<span class="d">' . Html::escape($occasion['date_label']) . ' · ' . Html::escape($occasion['time_label'])
        . '<br>' . Html::escape($occasion['detail']) . '</span>'
        . '<span class="dap-badge dap-badge--' . Html::escape($occasion['badge_type']) . '">' . Html::escape($occasion['badge_text']) . '</span>'
        . '</label>';
    }
    $group_aria = ' role="radiogroup" aria-labelledby="eforms-esemeny-cim" aria-required="true"';
    if (isset($errors['esemeny'])) {
      $group_aria .= ' aria-invalid="true" aria-describedby="eforms-esemeny-error"';
    }
    $form['fs_esemeny'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['fs']],
      'title' => ['#markup' => '<h2 class="fs-h" id="eforms-esemeny-cim"><span class="num">1</span>Melyik alkalmon venne részt? *</h2>'],
      'options' => ['#markup' => Markup::create('<div class="evsel"' . $group_aria . '>' . $cards . '</div>')],
    ];
    if (isset($errors['esemeny'])) {
      $form['fs_esemeny']['error'] = $this->feedback($errors['esemeny'], 'eforms-esemeny-error');
    }

    $form['divider1'] = ['#markup' => '<hr class="dap-divider">'];

    // 2 — Személyes adatok.
    $form['fs_adatok'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['fs']],
      'title' => ['#markup' => '<h2 class="fs-h"><span class="num">2</span>Személyes adatok</h2>'],
      'grid' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['grid2']],
      ],
    ];

    $form['fs_adatok']['grid']['nev_wrap'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['err-wrap']],
    ];
    $form['fs_adatok']['grid']['nev_wrap']['nev'] = [
      '#type' => 'textfield',
      '#title' => 'Teljes név',
      '#maxlength' => 255,
      '#attributes' => [
        'placeholder' => 'pl. Kiss Anna',
        'autocomplete' => 'name',
      ],
      '#wrapper_attributes' => ['class' => ['dap-field--req']],
    ];
    if (isset($errors['nev'])) {
      $form['fs_adatok']['grid']['nev_wrap']['nev']['#attributes']['class'][] = 'error';
      $form['fs_adatok']['grid']['nev_wrap']['nev']['#attributes']['aria-invalid'] = 'true';
      $form['fs_adatok']['grid']['nev_wrap']['nev']['#attributes']['aria-describedby'] = 'eforms-nev-error';
      $form['fs_adatok']['grid']['nev_wrap']['nev_error'] = $this->feedback($errors['nev'], 'eforms-nev-error');
    }

    $form['fs_adatok']['grid']['email_wrap'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['err-wrap']],
    ];
    // A súgót nem a core #description-nel rendereljük, mert az felülírná az
    // aria-describedby-t — így hibánál a súgó és a hibaüzenet is kötve marad.
    $email_describedby = 'eforms-email-helper' . (isset($errors['email']) ? ' eforms-email-error' : '');
    $form['fs_adatok']['grid']['email_wrap']['email'] = [
      '#type' => 'textfield',
      '#title' => 'E-mail-cím',
      '#maxlength' => 254,
      '#attributes' => [
        'placeholder' => 'nev@szervezet.hu',
        'autocomplete' => 'email',
        'inputmode' => 'email',
        'aria-describedby' => $email_describedby,
      ],
      '#wrapper_attributes' => ['class' => ['dap-field--req']],
    ];
    $form['fs_adatok']['grid']['email_wrap']['email_helper'] = [
      '#markup' => Markup::create('<div class="dap-helper" id="eforms-email-helper">Erre a címre küldjük a visszaigazolást.</div>'),
    ];
    if (isset($errors['email'])) {
      $form['fs_adatok']['grid']['email_wrap']['email']['#attributes']['class'][] = 'error';
      $form['fs_adatok']['grid']['email_wrap']['email']['#attributes']['aria-invalid'] = 'true';
      $form['fs_adatok']['grid']['email_wrap']['email_error'] = $this->feedback($errors['email'], 'eforms-email-error');
    }

    $form['fs_adatok']['grid']['telefon'] = [
      '#type' => 'textfield',
      '#title' => 'Telefonszám',
      '#maxlength' => 64,
      '#attributes' => [
        'placeholder' => '+36 30 123 4567',
        'autocomplete' => 'tel',
        'inputmode' => 'tel',
      ],
      '#wrapper_attributes' => ['class' => ['dap-field--opt']],
    ];

    $form['divider2'] = ['#markup' => '<hr class="dap-divider">'];

    // 3 — Adatkezelés.
    $form['fs_gdpr'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['fs']],
      'title' => ['#markup' => '<h2 class="fs-h"><span class="num">3</span>Adatkezelés</h2>'],
      'box' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['gdpr']],
      ],
    ];
    $form['fs_gdpr']['box']['gdpr'] = [
      '#type' => 'checkbox',
      '#title' => 'Elolvastam és elfogadom az adatkezelési tájékoztatót. *',
    ];
    if (isset($errors['gdpr'])) {
      $form['fs_gdpr']['box']['gdpr']['#attributes']['class'][] = 'error';
      $form['fs_gdpr']['box']['gdpr']['#attributes']['aria-invalid'] = 'true';
      $form['fs_gdpr']['box']['gdpr']['#attributes']['aria-describedby'] = 'eforms-gdpr-error';
    }
    $privacy_url = Url::fromRoute('eforms_event.privacy')->toString();
    $form['fs_gdpr']['box']['note'] = [
      '#markup' => Markup::create('<p>A megadott adatokat kizárólag a rendezvény szervezésével összefüggésben, a regisztráció kezelése és a kapcsolattartás céljából kezeljük. Részletek az <a href="' . $privacy_url . '" target="_blank" rel="noopener">adatkezelési tájékoztatóban</a>.</p>'),
    ];
    if (isset($errors['gdpr'])) {
      $form['fs_gdpr']['box']['gdpr_error'] = $this->feedback($errors['gdpr'], 'eforms-gdpr-error');
    }

    // Küldés.
    $form['actions'] = [
      '#type' => 'actions',
      '#attributes' => ['class' => ['subrow']],
      'submit' => [
        '#type' => 'submit',
        '#value' => 'Regisztráció elküldése',
        '#attributes' => ['class' => ['dap-btn']],
      ],
      'note' => [
        '#markup' => '<span class="note">A beküldés után e-mailben visszaigazolást küldünk.</span>',
      ],
    ];

    $form['#cache']['tags'][] = 'eforms_registration_list';
    $form['#cache']['contexts'][] = 'url.query_args:alkalom';

    return $form;
  }

  /**
   * Mezőhiba a DÁP FeedbackMessage komponens markupjával.
   */
  protected function feedback(string $message, string $id = ''): array {
    return [
      '#markup' => Markup::create(
        '<span class="dap-feedback"' . ($id !== '' ? ' id="' . Html::escape($id) . '"' : '') . '>'
        . '<span class="dap-feedback__icon" aria-hidden="true">!</span>' . Html::escape($message) . '</span>'
      ),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // A hibakezelés a submitForm()-ban történik, hogy a megjelenítés a
    // design szerinti maradjon (Callout + mezőnkénti visszajelzés).
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $occasions = $this->capacity->getOccasions();
    $input = $form_state->getUserInput();
    $esemeny = (string) ($input['esemeny'] ?? '');
    $nev = trim((string) $form_state->getValue('nev', ''));
    $email = trim((string) $form_state->getValue('email', ''));
    $telefon = trim((string) $form_state->getValue('telefon', ''));
    $gdpr = (bool) $form_state->getValue('gdpr');

    $errors = [];
    if (!isset($occasions[$esemeny])) {
      $errors['esemeny'] = 'Válasszon egy alkalmat a részvételhez.';
    }
    elseif ($occasions[$esemeny]['full']) {
      $errors['esemeny'] = 'Sajnáljuk, ez az alkalom időközben betelt. Kérjük, válassza a másik alkalmat.';
    }
    if ($nev === '') {
      $errors['nev'] = 'Adja meg a teljes nevét.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $errors['email'] = 'Adjon meg érvényes e-mail-címet.';
    }
    if (!$gdpr) {
      $errors['gdpr'] = 'A regisztrációhoz el kell fogadnia az adatkezelési tájékoztatót.';
    }

    if ($errors) {
      $form_state->set('eforms_errors', $errors);
      $form_state->setRebuild();
      return;
    }

    $registration = Registration::create([
      'name' => $nev,
      'email' => $email,
      'phone' => $telefon,
      'occasion' => $esemeny,
      'gdpr' => TRUE,
    ]);
    $registration->save();

    // Visszaigazoló e-mail.
    $occasion = $occasions[$esemeny];
    try {
      $this->mailManager->mail('eforms_event', 'confirmation', $email, 'hu', [
        'nev' => $nev,
        'date_label' => $occasion['date_label'],
        'time_label' => $occasion['time_label'],
        'mode' => $occasion['done_mode'] ?? ($occasion['label'] . ' — ' . $occasion['detail']),
        'contact_email' => $this->configFactory()->get('eforms_event.settings')->get('contact_email'),
      ]);
    }
    catch (\Throwable $e) {
      \Drupal::logger('eforms_event')->error('A visszaigazoló e-mail küldése nem sikerült: @error', ['@error' => $e->getMessage()]);
    }

    $this->tempStoreFactory->get('eforms_event')->set('done', [
      'nev' => $nev,
      'email' => $email,
      'esemeny' => $esemeny,
    ]);
    $form_state->setRedirect('eforms_event.done');
  }

}

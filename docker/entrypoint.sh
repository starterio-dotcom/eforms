#!/usr/bin/env bash
# Első indításkor telepíti a Drupalt (SQLite), aztán Apache-ot futtat.
set -euo pipefail

cd /var/www/html
FILES=web/sites/default/files
DB="$FILES/.ht.sqlite"
SETTINGS=web/sites/default/settings.php

mkdir -p "$FILES"
chown -R www-data:www-data web/sites/default

drush_run() {
  su -s /bin/bash www-data -c "cd /var/www/html && vendor/bin/drush $*"
}

# A settings.php a konténer rétegében él; ha a kötet (adatbázis) megvan, de a
# settings.php elveszett (új konténer), a mentett példányt állítjuk vissza.
if [ ! -f "$SETTINGS" ] && [ -f "$FILES/settings.backup.php" ]; then
  cp "$FILES/settings.backup.php" "$SETTINGS"
  chown www-data:www-data "$SETTINGS"
fi

if [ ! -f "$DB" ] || [ ! -f "$SETTINGS" ]; then
  echo ">>> Drupal telepítése (SQLite) — első indításkor néhány percig tarthat."
  drush_run -y site:install standard \
    --db-url="sqlite://localhost//var/www/html/$DB" \
    --site-name="eForms felkészítő szakmai tájékoztató nap" \
    --site-mail="oktatas@ujvilag.gov.hu" \
    --account-name=admin \
    --account-pass="${EFORMS_ADMIN_PASSWORD:-admin}" \
    --locale=hu
  echo ">>> Egyedi modul és téma bekapcsolása."
  drush_run -y pm:enable eforms_event
  drush_run -y theme:enable eforms_theme
  drush_run -y config:set system.theme default eforms_theme
  drush_run -y config:set system.site page.front /esemeny
  # Demó környezet: a levelek a mail-gyűjtőbe kerülnek (nincs SMTP a konténerben).
  drush_run -y config:set system.mail interface.default test_mail_collector
  drush_run -y cache:rebuild
  cp "$SETTINGS" "$FILES/settings.backup.php"
  chown www-data:www-data "$FILES/settings.backup.php"
  echo ">>> Kész. Belépés: admin / ${EFORMS_ADMIN_PASSWORD:-admin}"
fi

exec apache2-foreground

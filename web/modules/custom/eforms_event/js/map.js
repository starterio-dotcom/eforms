/**
 * @file
 * Leaflet térkép a helyszín szekcióhoz (Kéthly Anna tér 1.).
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.eformsMap = {
    attach(context) {
      once('eforms-map', '#eforms-map', context).forEach(function (el) {
        var pos = [47.4981, 19.0656];
        var map = L.map(el, { scrollWheelZoom: false }).setView(pos, 16);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
          attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        L.marker(pos, {
          icon: L.divIcon({ className: '', html: '<div class="venue-pin"></div>', iconSize: [18, 18], iconAnchor: [9, 9] })
        })
          .addTo(map)
          .bindPopup('<b>Kéthly Anna tér 1.</b><br>II. emelet, 261-es tárgyaló')
          .openPopup();
      });
    }
  };
})(Drupal, once);

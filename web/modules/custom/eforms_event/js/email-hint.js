/**
 * @file
 * „Erre gondolt?” — e-mail domain-elütés felismerés és egykattintásos javítás.
 *
 * Nem blokkol: csak javaslatot tesz, ha a beírt domain 1–2 karakterre van egy
 * ismert szolgáltatótól (pl. gmial.com → gmail.com).
 */
(function (Drupal, once) {
  'use strict';

  var DOMAINS = [
    'gmail.com',
    'freemail.hu',
    'citromail.hu',
    't-online.hu',
    'outlook.com',
    'hotmail.com',
    'yahoo.com',
    'icloud.com',
    'indamail.hu',
    'ujvilag.gov.hu',
  ];

  function levenshtein(a, b) {
    if (Math.abs(a.length - b.length) > 2) {
      return 3;
    }
    var prev = [];
    var curr = [];
    var i;
    var j;
    for (j = 0; j <= b.length; j++) {
      prev[j] = j;
    }
    for (i = 1; i <= a.length; i++) {
      curr[0] = i;
      for (j = 1; j <= b.length; j++) {
        curr[j] = Math.min(
          prev[j] + 1,
          curr[j - 1] + 1,
          prev[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1)
        );
      }
      prev = curr.slice();
    }
    return prev[b.length];
  }

  function suggestDomain(domain) {
    if (DOMAINS.indexOf(domain) !== -1) {
      return null;
    }
    var best = null;
    var bestDistance = 3;
    for (var i = 0; i < DOMAINS.length; i++) {
      var d = levenshtein(domain, DOMAINS[i]);
      if (d < bestDistance) {
        bestDistance = d;
        best = DOMAINS[i];
      }
    }
    return bestDistance > 0 && bestDistance <= 2 ? best : null;
  }

  Drupal.behaviors.eformsEmailHint = {
    attach: function (context) {
      once('eforms-email-hint', 'input[name="email"]', context).forEach(function (input) {
        var hint = document.createElement('div');
        hint.className = 'email-hint';
        hint.setAttribute('aria-live', 'polite');
        hint.hidden = true;
        var wrapper = input.closest('.dap-field') || input.parentNode;
        wrapper.insertAdjacentElement('afterend', hint);

        function update() {
          var value = input.value.trim();
          var at = value.lastIndexOf('@');
          hint.hidden = true;
          hint.textContent = '';
          if (at < 1) {
            return;
          }
          var local = value.slice(0, at);
          var domain = value.slice(at + 1).toLowerCase();
          if (!domain || domain.indexOf('.') === -1) {
            return;
          }
          var suggested = suggestDomain(domain);
          if (!suggested) {
            return;
          }
          var suggestion = local + '@' + suggested;
          var button = document.createElement('button');
          button.type = 'button';
          button.className = 'email-hint__apply';
          button.textContent = suggestion;
          button.addEventListener('click', function () {
            input.value = suggestion;
            hint.hidden = true;
            hint.textContent = '';
            input.dispatchEvent(new Event('change', { bubbles: true }));
            input.focus();
          });
          hint.appendChild(document.createTextNode('Erre gondolt: '));
          hint.appendChild(button);
          hint.appendChild(document.createTextNode('?'));
          hint.hidden = false;
        }

        input.addEventListener('blur', update);
        input.addEventListener('input', Drupal.debounce(update, 600));
      });
    },
  };
})(Drupal, once);

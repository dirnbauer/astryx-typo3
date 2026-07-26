/*
 * Desiderio Grande — the whole client-side runtime.
 *
 * Everything native HTML can do is left to native HTML: disclosures are
 * <details>, modals are <dialog>, menus and tooltips use the popover
 * attribute, carousels are CSS scroll-snap. What remains are the behaviours
 * the platform has no element for, and they are wired declaratively through
 * data-g-* attributes rather than by importing anything.
 *
 * Runs once on load and again whenever the visual editor swaps content in
 * (initialisation is idempotent: every handler marks its element).
 */
(function () {
  'use strict';

  var SCHEME_KEY = 'g-scheme';
  var MARK = 'gBound';

  /** Attach once per element, whatever calls us again later. */
  function bind(element, kind, attach) {
    var flag = MARK + kind;
    if (element.dataset[flag]) return;
    element.dataset[flag] = '1';
    attach(element);
  }

  function each(selector, callback) {
    Array.prototype.forEach.call(document.querySelectorAll(selector), callback);
  }

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  /* ------------------------------------------------------- colour scheme */
  /*
   * The scheme lives as a class on <html>; the stylesheet turns that into a
   * color-scheme declaration and every light-dark() token follows. No class
   * means "system", which is why the cycle has three steps rather than two.
   *
   * The initial class is set by an inline script in the document head, before
   * first paint — a visitor who chose dark must never see a white flash.
   */
  function currentScheme() {
    var root = document.documentElement;
    if (root.classList.contains('dark')) return 'dark';
    if (root.classList.contains('light')) return 'light';
    return 'system';
  }

  /**
   * What the visitor is actually looking at right now.
   *
   * "system" is a preference, not an appearance: on a machine set to dark it
   * looks exactly like "dark". The button has to flip what is on screen, so it
   * asks the media query what "system" currently resolves to.
   */
  function visibleScheme() {
    var scheme = currentScheme();
    if (scheme !== 'system') return scheme;
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }

  function applyScheme(scheme) {
    var root = document.documentElement;
    root.classList.remove('light', 'dark');
    if (scheme === 'light' || scheme === 'dark') root.classList.add(scheme);

    try {
      window.localStorage.setItem(SCHEME_KEY, scheme);
    } catch (error) {
      /* Private mode or a storage quota: the toggle still works for this
         page view, it just will not be remembered. */
    }

    each('[data-g-scheme-toggle]', function (button) {
      button.setAttribute('data-g-scheme-state', scheme);
    });
  }

  function initSchemeToggles() {
    each('[data-g-scheme-toggle]', function (button) {
      bind(button, 'Scheme', function (element) {
        element.setAttribute('data-g-scheme-state', currentScheme());
        element.addEventListener('click', function () {
          // Flip what is on screen, never the stored preference. A three-way
          // cycle through "system" spends one click in three changing nothing
          // a visitor can see — and on a machine already set to dark, that
          // silent click is the first one, which is precisely when someone
          // decides the button is broken.
          applyScheme(visibleScheme() === 'dark' ? 'light' : 'dark');
        });
      });
    });
  }

  /* --------------------------------------------------------- mobile menu */
  /*
   * The menu itself is a <details>. What is missing natively is closing it
   * when the visitor clicks away or widens the window past the breakpoint,
   * where the panel would otherwise stay open behind the desktop navigation.
   */
  function initMenus() {
    each('[data-g-menu]', function (menu) {
      bind(menu, 'Menu', function (element) {
        document.addEventListener('click', function (event) {
          if (element.open && !element.contains(event.target)) element.open = false;
        });

        document.addEventListener('keydown', function (event) {
          if (event.key === 'Escape' && element.open) {
            element.open = false;
            var summary = element.querySelector('summary');
            if (summary) summary.focus();
          }
        });
      });
    });

    var desktop = window.matchMedia('(min-width: 768px)');
    desktop.addEventListener('change', function (event) {
      if (!event.matches) return;
      each('[data-g-menu][open]', function (menu) {
        menu.open = false;
      });
    });
  }

  /* ---------------------------------------------------------- dismissal */
  function initDismiss() {
    each('[data-g-dismiss]', function (button) {
      bind(button, 'Dismiss', function (element) {
        element.addEventListener('click', function () {
          var target = element.closest('[data-g-dismissible]');
          if (target) target.remove();
        });
      });
    });
  }

  /* --------------------------------------------------------------- tabs */
  /*
   * Tabs need roving focus and arrow keys, which no native element provides.
   * The markup is a plain list of buttons plus panels, so without JavaScript
   * every panel is simply visible — degraded, never broken.
   */
  function initTabs() {
    each('[data-g-tabs]', function (tabs) {
      bind(tabs, 'Tabs', function (element) {
        var triggers = Array.prototype.slice.call(element.querySelectorAll('[data-g-tab]'));
        if (triggers.length === 0) return;

        var panels = triggers.map(function (trigger) {
          return element.querySelector('#' + trigger.getAttribute('aria-controls'));
        });

        function select(index, moveFocus) {
          triggers.forEach(function (trigger, position) {
            var active = position === index;
            trigger.setAttribute('aria-selected', active ? 'true' : 'false');
            trigger.setAttribute('tabindex', active ? '0' : '-1');
            if (panels[position]) panels[position].hidden = !active;
          });
          if (moveFocus) triggers[index].focus();
        }

        triggers.forEach(function (trigger, index) {
          trigger.addEventListener('click', function () {
            select(index, false);
          });

          trigger.addEventListener('keydown', function (event) {
            var last = triggers.length - 1;
            var next = null;
            if (event.key === 'ArrowRight') next = index === last ? 0 : index + 1;
            else if (event.key === 'ArrowLeft') next = index === 0 ? last : index - 1;
            else if (event.key === 'Home') next = 0;
            else if (event.key === 'End') next = last;
            if (next === null) return;
            event.preventDefault();
            select(next, true);
          });
        });

        select(0, false);
      });
    });
  }

  /* ----------------------------------------------------------- carousel */
  /*
   * Scrolling and snapping are CSS. The buttons are the affordance a mouse
   * user needs, and they disable themselves at either end so they never lie
   * about what they will do.
   */
  function initCarousels() {
    each('[data-g-carousel]', function (carousel) {
      bind(carousel, 'Carousel', function (element) {
        var track = element.querySelector('[data-g-carousel-track]');
        if (!track) return;

        var previous = element.querySelector('[data-g-carousel-prev]');
        var next = element.querySelector('[data-g-carousel-next]');

        function page(direction) {
          var first = track.firstElementChild;
          var step = first ? first.getBoundingClientRect().width + 16 : track.clientWidth;
          track.scrollBy({
            left: step * direction,
            behavior: reduceMotion.matches ? 'auto' : 'smooth',
          });
        }

        function sync() {
          var atStart = track.scrollLeft <= 1;
          var atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 1;
          if (previous) previous.disabled = atStart;
          if (next) next.disabled = atEnd;
        }

        if (previous) previous.addEventListener('click', function () { page(-1); });
        if (next) next.addEventListener('click', function () { page(1); });
        track.addEventListener('scroll', sync, {passive: true});
        window.addEventListener('resize', sync);
        sync();
      });
    });
  }

  /* -------------------------------------------------------------- dialog */
  /* <dialog> handles focus trapping and Escape; it only needs opening. */
  function initDialogs() {
    each('[data-g-dialog-open]', function (button) {
      bind(button, 'DialogOpen', function (element) {
        element.addEventListener('click', function () {
          var dialog = document.getElementById(element.getAttribute('data-g-dialog-open'));
          if (dialog && typeof dialog.showModal === 'function') dialog.showModal();
        });
      });
    });

    each('[data-g-dialog-close]', function (button) {
      bind(button, 'DialogClose', function (element) {
        element.addEventListener('click', function () {
          var dialog = element.closest('dialog');
          if (dialog) dialog.close();
        });
      });
    });
  }

  function init() {
    initSchemeToggles();
    initMenus();
    initDismiss();
    initTabs();
    initCarousels();
    initDialogs();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // The visual editor replaces content elements in place; re-running init
  // binds whatever arrived without touching what is already bound.
  window.addEventListener('message', function (event) {
    if (event.data && event.data.type === 'reloadFrames') init();
  });

  window.grandeInit = init;
})();

/*
 * Astryx for TYPO3 — the whole client-side runtime.
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

  /** Smooth where motion is welcome, an instant jump where it is not. */
  function scrollBehavior() {
    return reduceMotion.matches ? 'auto' : 'smooth';
  }

  /** Whether the inline axis runs right to left at this element. */
  function isRtl(element) {
    return window.getComputedStyle(element).direction === 'rtl';
  }

  /**
   * Fill `%1$s`, `%2$s` (and bare `%s`, in order) the way f:translate's
   * arguments would, so a label is written once in the XLIFF file with its
   * placeholders and the runtime only supplies the numbers.
   */
  function format(template, values) {
    var next = 0;
    return String(template).replace(/%(?:(\d+)\$)?[sd]/g, function (match, position) {
      var index = position ? parseInt(position, 10) - 1 : next++;
      return values[index] === undefined ? match : String(values[index]);
    });
  }

  /**
   * A polite live region of its own beside a control.
   *
   * Beside it and not one shared region on <body>: a control inside an open
   * modal dialog is the only live thing on the page, everything outside the
   * dialog is inert, and an inert region is never read out. The region exists
   * from the moment the control is bound, because a region that appears
   * together with its message is not announced by every screen reader.
   */
  function liveRegion(after) {
    var region = document.createElement('span');
    region.className = 'astryx-visually-hidden';
    region.setAttribute('aria-live', 'polite');
    region.setAttribute('aria-atomic', 'true');
    after.insertAdjacentElement('afterend', region);
    return region;
  }

  /** Say something through a live region, even when it is the same thing again. */
  function announce(region, message) {
    region.textContent = '';
    window.setTimeout(function () { region.textContent = message; }, 60);
  }

  var FOCUSABLE = 'a[href], area[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), '
    + 'select:not([disabled]), textarea:not([disabled]), summary, iframe, [contenteditable="true"], '
    + '[tabindex]:not([tabindex="-1"])';

  /** The first control after `node` in document order that a keyboard can reach. */
  function nextFocusable(node) {
    var candidates = document.querySelectorAll(FOCUSABLE);
    for (var i = 0; i < candidates.length; i++) {
      var candidate = candidates[i];
      if (node.contains(candidate)) continue;
      if (!(node.compareDocumentPosition(candidate) & Node.DOCUMENT_POSITION_FOLLOWING)) continue;
      if (candidate.closest('[inert], [hidden]')) continue;
      if (candidate.getClientRects().length === 0) continue;
      return candidate;
    }
    return null;
  }

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
  /*
   * A dismissible band goes when a data-g-dismiss control inside it is
   * pressed. Two things follow it out. The keyboard: focus was on the close
   * control, and a removed control drops focus on <body>, so it moves on to
   * the next control after the band (or to <main>) instead. And the decision:
   * a band with data-g-dismiss-key is remembered for thirty days in this
   * browser, and taken out again on every page that carries the same key.
   *
   * Not inside a frame. The visual editor and the element library show the
   * page in one, and an editor who once closed an offer on the live site
   * would otherwise find the element missing from the page they are editing.
   */
  var DISMISS_PREFIX = 'g-dismissed:';
  var DISMISS_FOR = 30 * 24 * 60 * 60 * 1000;

  function framed() {
    try {
      return window.self !== window.top;
    } catch (error) {
      return true;
    }
  }

  function rememberDismissal(key) {
    try {
      window.localStorage.setItem(DISMISS_PREFIX + key, String(Date.now()));
    } catch (error) {
      /* Private mode or a full quota: the band goes for this page view only. */
    }
  }

  function wasDismissed(key) {
    try {
      var stamp = parseInt(window.localStorage.getItem(DISMISS_PREFIX + key) || '', 10);
      if (!stamp) return false;
      if (Date.now() - stamp < DISMISS_FOR) return true;
      window.localStorage.removeItem(DISMISS_PREFIX + key);
    } catch (error) {
      /* Unreadable storage forgets, which shows the band again: the safe way round. */
    }
    return false;
  }

  function initDismiss() {
    if (!framed()) {
      each('[data-g-dismissible][data-g-dismiss-key]', function (band) {
        bind(band, 'DismissKey', function (element) {
          if (wasDismissed(element.getAttribute('data-g-dismiss-key'))) element.remove();
        });
      });
    }

    each('[data-g-dismiss]', function (button) {
      bind(button, 'Dismiss', function (element) {
        element.addEventListener('click', function () {
          var target = element.closest('[data-g-dismissible]');
          if (!target) return;

          var hadFocus = target.contains(document.activeElement);
          var next = hadFocus ? nextFocusable(target) : null;
          var key = target.getAttribute('data-g-dismiss-key');
          if (key) rememberDismissal(key);
          target.remove();

          if (!hadFocus) return;
          if (!next) {
            next = document.querySelector('main');
            if (next && !next.hasAttribute('tabindex')) next.setAttribute('tabindex', '-1');
          }
          if (next) next.focus();
        });
      });
    });
  }

  /* --------------------------------------------------------------- tabs */
  /*
   * Tabs need roving focus and arrow keys, which no native element provides.
   * The markup is a plain list of buttons plus panels, so without JavaScript
   * every panel is simply visible — degraded, never broken.
   *
   * The tab that starts selected is the one the markup marks aria-selected,
   * or the one whose panel the address names; a strip too wide for its column
   * scrolls, so the selected tab is scrolled into it — the strip only, never
   * the page.
   */

  /** Scroll a tab strip, and nothing else, until `tab` is inside its padding. */
  function revealTab(tab, smooth) {
    var list = tab.closest('[role="tablist"]');
    if (!list || list.scrollWidth <= list.clientWidth) return;
    var style = window.getComputedStyle(list);
    var box = list.getBoundingClientRect();
    var start = box.left + (parseFloat(style.borderLeftWidth) || 0) + (parseFloat(style.paddingLeft) || 0);
    var end = box.right - (parseFloat(style.borderRightWidth) || 0) - (parseFloat(style.paddingRight) || 0);
    var edges = tab.getBoundingClientRect();
    var delta = edges.left < start ? edges.left - start : (edges.right > end ? edges.right - end : 0);
    if (delta !== 0) list.scrollBy({left: delta, behavior: smooth ? scrollBehavior() : 'auto'});
  }

  function initTabs() {
    each('[data-g-tabs]', function (tabs) {
      bind(tabs, 'Tabs', function (element) {
        // Only this set's own triggers: a tab set nested in a panel binds itself.
        var triggers = Array.prototype.filter.call(element.querySelectorAll('[data-g-tab]'), function (trigger) {
          return trigger.closest('[data-g-tabs]') === element;
        });
        if (triggers.length === 0) return;

        var panels = triggers.map(function (trigger) {
          var panel = document.getElementById(trigger.getAttribute('aria-controls') || '');
          if (panel) {
            if (!panel.hasAttribute('role')) panel.setAttribute('role', 'tabpanel');
            if (!panel.hasAttribute('aria-labelledby') && trigger.id) panel.setAttribute('aria-labelledby', trigger.id);
          }
          return panel;
        });

        var list = triggers[0].closest('[role="tablist"]');
        var vertical = list !== null && list.getAttribute('aria-orientation') === 'vertical';

        function select(index, moveFocus, smooth) {
          triggers.forEach(function (trigger, position) {
            var active = position === index;
            trigger.setAttribute('aria-selected', active ? 'true' : 'false');
            trigger.setAttribute('tabindex', active ? '0' : '-1');
            if (panels[position]) panels[position].hidden = !active;
          });
          if (moveFocus) triggers[index].focus();
          revealTab(triggers[index], smooth);
        }

        triggers.forEach(function (trigger, index) {
          trigger.addEventListener('click', function () {
            select(index, false, true);
          });

          trigger.addEventListener('keydown', function (event) {
            var last = triggers.length - 1;
            var forward = vertical ? 'ArrowDown' : (isRtl(trigger) ? 'ArrowLeft' : 'ArrowRight');
            var backward = vertical ? 'ArrowUp' : (isRtl(trigger) ? 'ArrowRight' : 'ArrowLeft');
            var next = null;
            if (event.key === forward) next = index === last ? 0 : index + 1;
            else if (event.key === backward) next = index === 0 ? last : index - 1;
            else if (event.key === 'Home') next = 0;
            else if (event.key === 'End') next = last;
            if (next === null) return;
            event.preventDefault();
            select(next, true, true);
          });
        });

        var initial = 0;
        triggers.some(function (trigger, index) {
          if (trigger.getAttribute('aria-selected') !== 'true') return false;
          initial = index;
          return true;
        });
        var named = '';
        try {
          named = decodeURIComponent(window.location.hash.slice(1));
        } catch (error) {
          /* A malformed fragment names nothing. */
        }
        if (named) {
          triggers.forEach(function (trigger, index) {
            if (trigger.id === named || trigger.getAttribute('aria-controls') === named) initial = index;
          });
        }

        select(initial, false, false);
      });
    });
  }

  /* ----------------------------------------------------------- carousel */
  /*
   * Scrolling and snapping are CSS. The buttons are the affordance a mouse
   * user needs, and they switch themselves off at either end so they never
   * lie about what they will do.
   *
   * Off is aria-disabled, not disabled. A disabled button drops focus on
   * <body> the moment it switches off, which is exactly what happened to a
   * keyboard user paging to the end of a rail: "Next" disabled itself while
   * focused. An aria-disabled control keeps focus and ignores the press; it
   * leaves the tab order only once focus has moved on, as a disabled one would.
   *
   * A rail that fits its column has nothing to page through, and is marked
   * data-g-carousel-fits so the stylesheet takes both arrows away. A rail
   * whose component was given a status label says where the reader is —
   * "Quote 3 of 8" — once a scroll has come to rest, never on page load.
   */
  function initCarousels() {
    each('[data-g-carousel]', function (carousel) {
      bind(carousel, 'Carousel', function (element) {
        var track = element.querySelector('[data-g-carousel-track]');
        if (!track) return;

        var previous = element.querySelector('[data-g-carousel-prev]');
        var next = element.querySelector('[data-g-carousel-next]');
        var status = element.querySelector('[data-g-carousel-status]');
        var statusTemplate = status ? status.getAttribute('data-g-carousel-status') : '';

        function off(control) {
          return control.getAttribute('aria-disabled') === 'true';
        }

        function setOff(control, value) {
          if (!control || off(control) === value) return;
          if (value) {
            control.setAttribute('aria-disabled', 'true');
            if (document.activeElement !== control) control.setAttribute('tabindex', '-1');
          } else {
            control.removeAttribute('aria-disabled');
            control.removeAttribute('tabindex');
          }
        }

        function page(direction) {
          var first = track.firstElementChild;
          // The gap is the track's own: a rail at gap 6 used to be paged as
          // though it were at gap 4, and drifted a little further per press.
          var gap = parseFloat(window.getComputedStyle(track).columnGap) || 0;
          var step = first ? first.getBoundingClientRect().width + gap : track.clientWidth;
          track.scrollBy({left: step * direction * (isRtl(track) ? -1 : 1), behavior: scrollBehavior()});
        }

        // Math.abs: a right-to-left track scrolls from 0 towards negative values.
        function sync() {
          var offset = Math.abs(track.scrollLeft);
          setOff(previous, offset <= 1);
          setOff(next, offset + track.clientWidth >= track.scrollWidth - 1);
          element.toggleAttribute('data-g-carousel-fits', track.scrollWidth <= track.clientWidth + 1);
        }

        /** The first item at least half in view: the one the reader is on. */
        function position() {
          var items = track.children;
          var view = track.getBoundingClientRect();
          for (var i = 0; i < items.length; i++) {
            var box = items[i].getBoundingClientRect();
            var visible = Math.min(box.right, view.right) - Math.max(box.left, view.left);
            if (box.width > 0 && visible >= box.width / 2) return i;
          }
          return 0;
        }

        var announced = position();
        var settleTimer = 0;

        // Only a reader who has touched the rail is told where it went: a
        // browser restoring a scroll position on load is not news.
        var engaged = false;
        ['pointerdown', 'keydown', 'wheel', 'touchstart', 'focusin'].forEach(function (type) {
          element.addEventListener(type, function () { engaged = true; }, {passive: true});
        });

        function settle() {
          window.clearTimeout(settleTimer);
          sync();
          if (!status || !statusTemplate) return;
          var index = position();
          if (index === announced) return;
          announced = index;
          if (engaged) status.textContent = format(statusTemplate, [index + 1, track.children.length]);
        }

        [previous, next].forEach(function (control) {
          if (!control) return;
          control.addEventListener('click', function () {
            if (!off(control)) page(control === next ? 1 : -1);
          });
          control.addEventListener('blur', function () {
            if (off(control)) control.setAttribute('tabindex', '-1');
          });
        });

        track.addEventListener('scroll', function () {
          sync();
          // scrollend where the browser has it, a quiet moment where it has not.
          window.clearTimeout(settleTimer);
          settleTimer = window.setTimeout(settle, 120);
        }, {passive: true});
        track.addEventListener('scrollend', settle);
        window.addEventListener('resize', sync);
        if ('ResizeObserver' in window) {
          // Images arriving late change what fits without resizing the window.
          var observer = new ResizeObserver(sync);
          observer.observe(track);
          Array.prototype.forEach.call(track.children, function (item) { observer.observe(item); });
        }
        sync();
      });
    });
  }

  /* ----------------------------------------------------------- slideshow */
  /*
   * A slideshow works without script: its dots are fragment links and
   * `:target` shows the slide. The runtime keeps the page still when a dot is
   * used, keeps aria-current on the dot of the slide on show, and rotates the
   * slides when the component asks for it — never under reduced motion,
   * waiting while the slideshow is hovered, focused, off screen or in a
   * background tab, and stopping for good once a visitor picks a slide.
   */
  function initSlideshows() {
    each('[data-g-slideshow]', function (slideshow) {
      bind(slideshow, 'Slideshow', function (element) {
        var slides = Array.prototype.slice.call(element.querySelectorAll('[data-g-slide]'));
        var dots = [];
        var index = 0;
        slides.forEach(function (slide, n) {
          if (!slide.id) return;
          if (window.location.hash === '#' + slide.id) index = n;
          each('a[href="#' + slide.id + '"]', function (dot) {
            dots.push({ dot: dot, index: n });
          });
        });

        function show(n) {
          index = (n + slides.length) % slides.length;
          slides.forEach(function (slide, i) {
            slide.toggleAttribute('data-active', i === index);
            if (i === index) slide.removeAttribute('aria-hidden');
            else slide.setAttribute('aria-hidden', 'true');
          });
          dots.forEach(function (entry) {
            if (entry.index === index) entry.dot.setAttribute('aria-current', 'true');
            else entry.dot.removeAttribute('aria-current');
          });
        }

        show(index);
        if (slides.length < 2) return;

        var toggle = element.querySelector('[data-g-slideshow-toggle]');
        var interval = parseInt(element.getAttribute('data-g-slideshow-interval') || '0', 10) * 1000;
        var rotates = interval > 0 && toggle;
        var region = element.closest('.astryx-overlay') || element;
        var timer = 0;
        var paused = reduceMotion.matches;
        var holds = {};

        function held() {
          return Object.keys(holds).some(function (reason) { return holds[reason]; });
        }

        function schedule() {
          window.clearTimeout(timer);
          if (rotates && !paused && !held()) {
            timer = window.setTimeout(function () {
              show(index + 1);
              schedule();
            }, interval);
          }
        }

        function setPaused(value) {
          if (!rotates) return;
          paused = value;
          element.toggleAttribute('data-g-paused', paused);
          toggle.setAttribute('aria-label', element.getAttribute(paused ? 'data-g-slideshow-play-label' : 'data-g-slideshow-pause-label') || '');
          schedule();
        }

        function hold(reason, on) {
          holds[reason] = on;
          schedule();
        }

        dots.forEach(function (entry) {
          entry.dot.addEventListener('click', function (event) {
            event.preventDefault();
            show(entry.index);
            setPaused(true);
          });
        });

        if (!rotates) return;

        // Play means play now, although the pointer and the focus are still
        // on the slideshow: the button lifts those holds until they recur.
        toggle.addEventListener('click', function () {
          holds.hover = false;
          holds.focus = false;
          setPaused(!paused);
        });
        region.addEventListener('focusin', function (event) {
          hold('focus', event.target !== toggle);
        });
        region.addEventListener('focusout', function (event) {
          if (!region.contains(event.relatedTarget)) hold('focus', false);
        });
        if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
          region.addEventListener('pointerenter', function () { hold('hover', true); });
          region.addEventListener('pointerleave', function () { hold('hover', false); });
        }
        document.addEventListener('visibilitychange', function () {
          hold('page', document.hidden);
        });
        if ('IntersectionObserver' in window) {
          holds.view = true;
          new IntersectionObserver(function (entries) {
            hold('view', !entries[entries.length - 1].isIntersecting);
          }, { threshold: 0.35 }).observe(element);
        }
        setPaused(paused);
      });
    });
  }

  /* -------------------------------------------------------------- dialog */
  /*
   * <dialog> handles focus trapping and Escape; it only needs opening.
   *
   * And quietening. A closed dialog is out of sight, not out of earshot: a
   * film playing in one went on playing after Escape. So on close every
   * <video> and <audio> inside is paused, and every iframe is parked — its
   * address moved to data-g-src and the frame pointed at about:blank, which is
   * the one way to stop a third-party player from outside. The address goes
   * back when the dialog opens again, however it is opened.
   */
  function quieten(dialog) {
    Array.prototype.forEach.call(dialog.querySelectorAll('video, audio'), function (media) {
      if (!media.paused) media.pause();
    });
    Array.prototype.forEach.call(dialog.querySelectorAll('iframe'), function (frame) {
      var source = frame.getAttribute('src');
      if (!source || source === 'about:blank') return;
      frame.setAttribute('data-g-src', source);
      frame.setAttribute('src', 'about:blank');
    });
  }

  function wake(dialog) {
    Array.prototype.forEach.call(dialog.querySelectorAll('iframe[data-g-src]'), function (frame) {
      frame.setAttribute('src', frame.getAttribute('data-g-src'));
      frame.removeAttribute('data-g-src');
    });
  }

  function initDialogs() {
    each('[data-g-dialog-open]', function (button) {
      bind(button, 'DialogOpen', function (element) {
        element.addEventListener('click', function () {
          var dialog = document.getElementById(element.getAttribute('data-g-dialog-open'));
          if (dialog && typeof dialog.showModal === 'function' && !dialog.open) dialog.showModal();
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

    each('dialog', function (dialog) {
      bind(dialog, 'DialogMedia', function (element) {
        element.addEventListener('close', function () { quieten(element); });
        // The open attribute is the one thing every way of opening a dialog
        // sets — showModal(), show(), an invoker command or another script.
        if ('MutationObserver' in window) {
          new MutationObserver(function () {
            if (element.open) wake(element);
            else quieten(element);
          }).observe(element, {attributes: true, attributeFilter: ['open']});
        }
      });
    });
  }

  /* -------------------------------------------------------- header search */
  /*
   * The field is markup that works on its own: a form that submits to the
   * results page. What is added here is the collapse — the loupe — and that is
   * why the form is only marked ready once this runs. Before that the
   * stylesheet keeps the field visible and the trigger hidden, so a visitor
   * without this script gets a search box rather than a dead icon.
   */
  function initHeaderSearch() {
    each('[data-g-search]', function (form) {
      bind(form, 'Search', function (element) {
        var trigger = element.querySelector('[data-g-search-toggle]');
        var input = element.querySelector('input[type="search"]');
        if (!trigger || !input) return;

        element.setAttribute('data-g-search-ready', '');

        function open(state) {
          if (state) element.setAttribute('data-g-search-open', '');
          else element.removeAttribute('data-g-search-open');

          trigger.setAttribute('aria-expanded', state ? 'true' : 'false');
          if (state) input.focus();
        }

        trigger.addEventListener('click', function () {
          open(!element.hasAttribute('data-g-search-open'));
        });

        // Escape closes and hands focus back to the trigger, so the keyboard
        // does not end up parked inside a field that is no longer on screen.
        element.addEventListener('keydown', function (event) {
          if (event.key !== 'Escape') return;
          if (!element.hasAttribute('data-g-search-open')) return;
          event.stopPropagation();
          open(false);
          trigger.focus();
        });

        document.addEventListener('click', function (event) {
          if (!element.hasAttribute('data-g-search-open')) return;
          if (element.contains(event.target)) return;
          if (input.value.trim() !== '') return; // a typed query is not abandoned by a stray click
          open(false);
        });

        // An empty submit would send the visitor to an empty results page.
        element.addEventListener('submit', function (event) {
          if (input.value.trim() !== '') return;
          event.preventDefault();
          input.focus();
        });
      });
    });
  }

  /* ------------------------------------------------------------- suggest */
  /*
   * The dropdown under a search field.
   *
   * It reads one attribute — data-suggest, the URL of the tx_solr_suggest page
   * type — which EXT:solr's own search form already emits, so the header field
   * and the results-page field share this implementation without either knowing
   * about the other.
   *
   * The rows are Astryx Items, built from the same class names a Fluid template
   * would write. Keyboard traversal moves an is-active class rather than focus:
   * focus stays in the input so the visitor can keep typing, and
   * aria-activedescendant is what tells a screen reader where they are.
   */
  var SUGGEST_TYPE_LABELS = {
    pages: 'labelPages',
    tx_news_domain_model_news: 'labelNews',
    tt_address: 'labelAddresses',
  };

  function suggestNumber(form, name, fallback) {
    var value = parseInt(form.getAttribute('data-g-suggest-' + name), 10);
    return isNaN(value) ? fallback : value;
  }

  /** Split text on the query and wrap each hit in <mark>, escaping by construction. */
  function appendHighlighted(target, text, query) {
    var source = String(text == null ? '' : text);
    var needle = String(query || '').trim();

    if (needle === '') {
      target.textContent = source;
      return;
    }

    var haystack = source.toLowerCase();
    var lower = needle.toLowerCase();
    var offset = 0;

    while (offset < source.length) {
      var at = haystack.indexOf(lower, offset);
      if (at === -1) {
        target.appendChild(document.createTextNode(source.slice(offset)));
        return;
      }
      if (at > offset) target.appendChild(document.createTextNode(source.slice(offset, at)));

      var mark = document.createElement('mark');
      mark.className = 'astryx-typeahead-mark';
      mark.textContent = source.slice(at, at + needle.length);
      target.appendChild(mark);
      offset = at + needle.length;
    }
  }

  function Suggest(form) {
    this.form = form;
    this.input = form.querySelector('[data-g-suggest-input]');
    this.anchor = form.querySelector('[data-g-suggest-anchor]');
    this.endpoint = form.getAttribute('data-suggest');
    if (!this.input || !this.anchor || !this.endpoint) return;

    this.minChars = suggestNumber(form, 'min-chars', 2);
    this.maxItems = suggestNumber(form, 'max-items', 8);
    this.debounceMs = suggestNumber(form, 'debounce', 180);
    this.groupHeading = form.getAttribute('data-g-suggest-header') || 'Top results';
    this.emptyText = form.getAttribute('data-g-suggest-empty') || '';
    this.labels = {
      labelPages: form.getAttribute('data-g-suggest-label-pages') || 'Page',
      labelNews: form.getAttribute('data-g-suggest-label-news') || 'News',
      labelAddresses: form.getAttribute('data-g-suggest-label-addresses') || 'Address',
    };

    this.timer = null;
    this.controller = null;
    this.rows = [];
    this.active = -1;

    this.anchor.classList.add('astryx-typeahead-anchor');

    this.list = document.createElement('ul');
    this.list.className = 'astryx-typeahead';
    this.list.id = 'g-typeahead-' + (this.input.id || String(this.rows.length)) + '-' + Suggest.counter++;
    this.list.setAttribute('role', 'listbox');
    this.list.hidden = true;
    this.anchor.appendChild(this.list);

    this.input.setAttribute('role', 'combobox');
    this.input.setAttribute('aria-autocomplete', 'list');
    this.input.setAttribute('aria-expanded', 'false');
    this.input.setAttribute('aria-controls', this.list.id);
    this.input.setAttribute('autocomplete', 'off');

    var self = this;
    this.input.addEventListener('input', function () { self.schedule(); });
    this.input.addEventListener('focus', function () { self.schedule(); });
    this.input.addEventListener('keydown', function (event) { self.onKeydown(event); });
    document.addEventListener('click', function (event) {
      if (!self.form.contains(event.target)) self.close();
    });
  }

  Suggest.counter = 0;

  Suggest.prototype.schedule = function () {
    window.clearTimeout(this.timer);

    var query = this.input.value.trim();
    if (query.length < this.minChars) {
      this.close();
      return;
    }

    var self = this;
    this.timer = window.setTimeout(function () { self.fetch(query); }, this.debounceMs);
  };

  Suggest.prototype.fetch = function (query) {
    // Every keystroke supersedes the one before it: the older request is
    // aborted, so a slow response can never overwrite a newer one.
    if (this.controller) this.controller.abort();
    this.controller = new AbortController();

    var self = this;
    var url = new URL(this.endpoint, window.location.href);
    url.searchParams.set('tx_solr[queryString]', query);

    window
      .fetch(url.toString(), {headers: {Accept: 'application/json'}, signal: this.controller.signal})
      .then(function (response) {
        if (!response.ok) throw new Error('suggest ' + response.status);
        return response.json();
      })
      .then(function (data) {
        if (query !== self.input.value.trim()) return;
        self.render(data, query);
      })
      .catch(function (error) {
        if (error && error.name === 'AbortError') return;
        self.close();
      });
  };

  Suggest.prototype.typeLabel = function (type) {
    var key = SUGGEST_TYPE_LABELS[String(type || '')];
    return key ? this.labels[key] : String(type || '');
  };

  Suggest.prototype.render = function (data, query) {
    this.list.replaceChildren();
    this.rows = [];
    this.active = -1;

    var self = this;
    var terms = Object.keys((data && data.suggestions) || {}).slice(0, this.maxItems);
    terms.forEach(function (label) {
      self.addTerm(label, data.suggestions[label], query);
    });

    // EXT:solr answers with an object keyed by document id, not an array.
    var documents = (data && data.documents) || [];
    if (!Array.isArray(documents)) documents = Object.keys(documents).map(function (key) { return documents[key]; });
    documents = documents.filter(function (document) { return document && document.title && document.link; });

    if (documents.length > 0) {
      var heading = document.createElement('li');
      heading.className = 'astryx-typeahead-group';
      heading.setAttribute('role', 'presentation');
      heading.textContent = this.groupHeading;
      this.list.appendChild(heading);

      documents.forEach(function (item) { self.addDocument(item, query); });
    }

    // The header field is about fourteen characters wide; a page title in a
    // column that narrow wraps to four lines. Measured rather than guessed,
    // because the same menu hangs from the full-width field on the results page.
    this.list.dataset.width = this.anchor.getBoundingClientRect().width < 288 ? 'wider' : '';

    if (this.rows.length === 0) {
      if (this.emptyText === '') {
        this.close();
        return;
      }
      var empty = document.createElement('li');
      empty.className = 'astryx-typeahead-empty';
      empty.setAttribute('role', 'presentation');
      empty.textContent = this.emptyText;
      this.list.appendChild(empty);
    }

    this.list.hidden = false;
    this.input.setAttribute('aria-expanded', 'true');
  };

  /** One option shell, shared by both row kinds. */
  Suggest.prototype.addRow = function (payload) {
    var option = document.createElement('li');
    option.className = 'astryx-item astryx-typeahead-option';
    option.dataset.density = 'compact';
    option.dataset.interactive = 'interactive';
    option.id = this.list.id + '-option-' + this.rows.length;
    option.setAttribute('role', 'option');
    option.setAttribute('aria-selected', 'false');

    // pointerdown, not click: mousedown would blur the input first and the
    // blur handler would close the menu out from under the pointer.
    var self = this;
    option.addEventListener('pointerdown', function (event) {
      event.preventDefault();
      self.choose(payload);
    });

    this.rows.push({payload: payload, element: option});
    this.list.appendChild(option);
    return option;
  };

  Suggest.prototype.addTerm = function (label, count, query) {
    var option = this.addRow({kind: 'term', label: label});

    var content = document.createElement('span');
    content.className = 'astryx-item-content';
    var text = document.createElement('span');
    text.className = 'astryx-item-label';
    text.dataset.truncate = 'truncate';
    appendHighlighted(text, label, query);
    content.appendChild(text);
    option.appendChild(content);

    if (count !== undefined && count !== null) {
      var end = document.createElement('span');
      end.className = 'astryx-item-end astryx-typeahead-count';
      var badge = document.createElement('span');
      badge.className = 'astryx-badge';
      badge.dataset.variant = 'neutral';
      badge.textContent = String(count);
      end.appendChild(badge);
      option.appendChild(end);
    }
  };

  Suggest.prototype.addDocument = function (item, query) {
    var option = this.addRow({kind: 'document', label: item.title, link: item.link});
    option.dataset.align = 'start';

    var content = document.createElement('span');
    content.className = 'astryx-item-content';

    var label = document.createElement('span');
    label.className = 'astryx-item-label';
    label.dataset.truncate = 'truncate';
    appendHighlighted(label, item.title, query);
    content.appendChild(label);

    if (item.content) {
      var description = document.createElement('span');
      description.className = 'astryx-item-description';
      description.dataset.truncate = 'truncate';
      description.textContent = item.content;
      content.appendChild(description);
    }

    option.appendChild(content);

    var typeLabel = this.typeLabel(item.type);
    if (typeLabel) {
      var end = document.createElement('span');
      end.className = 'astryx-item-end';
      var badge = document.createElement('span');
      badge.className = 'astryx-badge';
      badge.dataset.variant = 'neutral';
      badge.textContent = typeLabel;
      end.appendChild(badge);
      option.appendChild(end);
    }
  };

  Suggest.prototype.onKeydown = function (event) {
    if (this.list.hidden) return;

    if (event.key === 'ArrowDown') {
      event.preventDefault();
      this.move(1);
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      this.move(-1);
    } else if (event.key === 'Enter' && this.active >= 0) {
      event.preventDefault();
      this.choose(this.rows[this.active].payload);
    } else if (event.key === 'Escape') {
      // Swallowed here so the header's own Escape handler does not also close
      // the field: one key press, one thing closed.
      event.stopPropagation();
      this.close();
    }
  };

  Suggest.prototype.move = function (direction) {
    if (this.rows.length === 0) return;

    this.active = (this.active + direction + this.rows.length) % this.rows.length;

    var activeIndex = this.active;
    this.rows.forEach(function (row, index) {
      var on = index === activeIndex;
      row.element.dataset.state = on ? 'active' : '';
      row.element.setAttribute('aria-selected', on ? 'true' : 'false');
    });

    var element = this.rows[this.active].element;
    this.input.setAttribute('aria-activedescendant', element.id);
    element.scrollIntoView({block: 'nearest'});
  };

  Suggest.prototype.choose = function (payload) {
    if (payload.kind === 'document' && payload.link) {
      window.location.href = payload.link;
      return;
    }

    // A term is not a destination — it is what the visitor meant to type, so
    // it goes into the field and runs as a full search.
    this.input.value = payload.label;
    this.close();

    if (typeof this.form.requestSubmit === 'function') this.form.requestSubmit();
    else this.form.submit();
  };

  Suggest.prototype.close = function () {
    this.list.hidden = true;
    this.list.replaceChildren();
    this.rows = [];
    this.active = -1;
    this.input.setAttribute('aria-expanded', 'false');
    this.input.removeAttribute('aria-activedescendant');
  };

  function initSuggest() {
    each('form[data-suggest]', function (form) {
      bind(form, 'Suggest', function (element) {
        if (!element.querySelector('[data-g-suggest-input]')) return;
        new Suggest(element);
      });
    });
  }

  /* ---------------------------------------------------------------- copy */
  /*
   * A control carrying data-g-copy="<id>" copies the text of that element —
   * a code listing, a licence key, an address. The clipboard API where the
   * page is allowed it; otherwise the text is selected (and the old copy
   * command tried), so Ctrl+C or a long press finishes the job.
   *
   * For two seconds afterwards the control carries data-g-copy-state —
   * "copied", or "selected" when only the selection worked — which the
   * stylesheet turns into a tick, and a polite live region beside it says the
   * control's data-g-copied-label ("Copied" when none is given). Without the
   * script the control does nothing, and the text is still there to select.
   */
  function initCopy() {
    each('[data-g-copy]', function (control) {
      bind(control, 'Copy', function (element) {
        var region = liveRegion(element);
        var timer = 0;

        function finish(state) {
          element.setAttribute('data-g-copy-state', state);
          if (state === 'copied') announce(region, element.getAttribute('data-g-copied-label') || 'Copied');
          window.clearTimeout(timer);
          timer = window.setTimeout(function () { element.removeAttribute('data-g-copy-state'); }, 2000);
        }

        function selectSource(source) {
          var selection = window.getSelection();
          var range = document.createRange();
          range.selectNodeContents(source);
          selection.removeAllRanges();
          selection.addRange(range);
          var copied = false;
          try {
            copied = document.execCommand('copy');
          } catch (error) {
            copied = false;
          }
          if (copied) selection.removeAllRanges();
          finish(copied ? 'copied' : 'selected');
        }

        element.addEventListener('click', function () {
          var target = document.getElementById(element.getAttribute('data-g-copy') || '');
          if (!target) return;
          // The listing itself, not the indentation a template wrote around
          // its <pre>; and without the newline before </code>, so a pasted
          // command does not run on its own.
          var source = target.matches('pre, code') ? target : (target.querySelector('pre') || target.querySelector('code') || target);
          var text = source.textContent.replace(/\n$/, '');

          if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(
              function () { finish('copied'); },
              function () { selectSource(source); }
            );
          } else {
            selectSource(source);
          }
        });
      });
    });
  }

  /* ------------------------------------------------------------- compare */
  /*
   * A before/after pair with a range input in it. The pair is marked
   * data-g-compare-ready once bound, which is when the stylesheet shows the
   * slider at all, and --g-compare follows the thumb as a percentage of the
   * range, ready for a clip-path to read.
   */
  function initCompare() {
    each('[data-g-compare]', function (pair) {
      bind(pair, 'Compare', function (element) {
        var input = element.querySelector('input[type="range"]');
        if (!input) return;

        function update() {
          var min = parseFloat(input.min);
          var max = parseFloat(input.max);
          if (isNaN(min)) min = 0;
          if (isNaN(max)) max = 100;
          var value = parseFloat(input.value);
          var share = max > min && !isNaN(value) ? (value - min) / (max - min) * 100 : 50;
          element.style.setProperty('--g-compare', Math.min(100, Math.max(0, share)) + '%');
        }

        input.addEventListener('input', update);
        update();
        element.setAttribute('data-g-compare-ready', '');
      });
    });
  }

  function init() {
    initSchemeToggles();
    initMenus();
    initDismiss();
    initTabs();
    initCarousels();
    initSlideshows();
    initDialogs();
    initHeaderSearch();
    initSuggest();
    initCopy();
    initCompare();
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

  window.astryxInit = init;
})();

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
    this.list.classList.toggle('wider-than-field', this.anchor.getBoundingClientRect().width < 288);

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
    option.className = 'astryx-item compact interactive astryx-typeahead-option';
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
    text.className = 'astryx-item-label truncate';
    appendHighlighted(text, label, query);
    content.appendChild(text);
    option.appendChild(content);

    if (count !== undefined && count !== null) {
      var end = document.createElement('span');
      end.className = 'astryx-item-end astryx-typeahead-count';
      var badge = document.createElement('span');
      badge.className = 'astryx-badge neutral';
      badge.textContent = String(count);
      end.appendChild(badge);
      option.appendChild(end);
    }
  };

  Suggest.prototype.addDocument = function (item, query) {
    var option = this.addRow({kind: 'document', label: item.title, link: item.link});
    option.classList.add('align-start');

    var content = document.createElement('span');
    content.className = 'astryx-item-content';

    var label = document.createElement('span');
    label.className = 'astryx-item-label truncate';
    appendHighlighted(label, item.title, query);
    content.appendChild(label);

    if (item.content) {
      var description = document.createElement('span');
      description.className = 'astryx-item-description truncate';
      description.textContent = item.content;
      content.appendChild(description);
    }

    option.appendChild(content);

    var typeLabel = this.typeLabel(item.type);
    if (typeLabel) {
      var end = document.createElement('span');
      end.className = 'astryx-item-end';
      var badge = document.createElement('span');
      badge.className = 'astryx-badge neutral';
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
      row.element.classList.toggle('is-active', on);
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

  function init() {
    initSchemeToggles();
    initMenus();
    initDismiss();
    initTabs();
    initCarousels();
    initDialogs();
    initHeaderSearch();
    initSuggest();
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

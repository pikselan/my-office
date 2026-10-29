/* ==========================================================================
   MO (MyOffice) — JavaScript vanilla
   Tidak ada dependensi eksternal. Semua interaksi diikat dengan data-*.
   ========================================================================== */
(function () {
  'use strict';

  var CSRF = document.querySelector('meta[name="csrf-token"]');
  var token = CSRF ? CSRF.getAttribute('content') : '';

  /* ------------------------------------------------------------ Utilitas */
  function $(selector, root) { return (root || document).querySelector(selector); }
  function $$(selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }
  function on(el, event, handler) { if (el) { el.addEventListener(event, handler); } }
  function text(el) { return el ? el.textContent.trim() : ''; }

  function debounce(fn, wait) {
    var timer = null;
    return function () {
      var args = arguments, self = this;
      clearTimeout(timer);
      timer = setTimeout(function () { fn.apply(self, args); }, wait || 300);
    };
  }

  /* -------------------------------------------------------------- Toast */
  function toast(message, type) {
    var container = $('.mo-toasts');
    if (!container) {
      container = document.createElement('div');
      container.className = 'mo-toasts';
      document.body.appendChild(container);
    }
    var el = document.createElement('div');
    el.className = 'mo-toast' + (type ? ' mo-toast--' + type : '');
    el.textContent = message;
    container.appendChild(el);
    setTimeout(function () {
      el.style.transition = 'opacity .25s ease';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 260);
    }, 4200);
  }

  /* --------------------------------------------------------------- Fetch */
  function request(url, options) {
    options = options || {};
    var method = (options.method || 'GET').toUpperCase();
    var headers = { 'Accept': 'application/json' };
    if (token) { headers['X-CSRF-Token'] = token; }
    if (options.body && !(options.body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
    }
    Object.keys(options.headers || {}).forEach(function (key) { headers[key] = options.headers[key]; });

    return fetch(url, {
      method: method,
      headers: headers,
      body: options.body instanceof FormData ? options.body : (options.body ? JSON.stringify(options.body) : undefined),
      credentials: 'same-origin'
    }).then(function (response) {
      var contentType = response.headers.get('content-type') || '';
      if (!contentType.indexOf('application/json') || contentType.indexOf('application/json') !== -1) {
        return response.json().then(function (payload) {
          payload.__status = response.status;
          if (!response.ok && !payload.message) { payload.message = 'Permintaan gagal.'; }
          return payload;
        });
      }
      return response.text().then(function (html) {
        return { success: response.ok, html: html, __status: response.status };
      });
    });
  }

  /* -------------------------------------------------------------- Sidebar */
  function initSidebar() {
    var sidebar = $('.mo-sidebar');
    var toggle = $('[data-mo-toggle="sidebar"]');
    var backdrop = $('.mo-sidebar-backdrop');

    function closeDrawer() {
      if (!sidebar) { return; }
      sidebar.classList.remove('is-open');
      if (backdrop) { backdrop.classList.remove('is-open'); }
    }

    on(toggle, 'click', function () {
      if (!sidebar) { return; }
      if (window.innerWidth <= 860) {
        sidebar.classList.toggle('is-open');
        if (backdrop) { backdrop.classList.toggle('is-open'); }
      } else {
        sidebar.classList.toggle('is-collapsed');
        try { localStorage.setItem('mo.sidebar', sidebar.classList.contains('is-collapsed') ? '1' : '0'); } catch (e) { /* abaikan */ }
      }
    });

    on(backdrop, 'click', closeDrawer);

    try {
      if (localStorage.getItem('mo.sidebar') === '1' && window.innerWidth > 860 && sidebar) {
        sidebar.classList.add('is-collapsed');
      }
    } catch (e) { /* abaikan */ }
  }

  /* ------------------------------------------------------------ Dropdown */
  function initDropdowns() {
    on(document, 'click', function (event) {
      $$('.mo-dropdown').forEach(function (dropdown) {
        if (!dropdown.contains(event.target)) { dropdown.classList.remove('is-open'); }
      });
    });

    $$('[data-mo-dropdown]').forEach(function (trigger) {
      on(trigger, 'click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        var dropdown = trigger.closest('.mo-dropdown');
        var isOpen = dropdown && dropdown.classList.contains('is-open');
        $$('.mo-dropdown').forEach(function (item) { item.classList.remove('is-open'); });
        if (dropdown && !isOpen) { dropdown.classList.add('is-open'); }
      });
    });
  }

  /* --------------------------------------------------------------- Modal */
  function openModal(selector) {
    var modal = $(selector);
    if (!modal) { return; }
    modal.classList.add('is-open');
    document.body.style.overflow = 'hidden';
    var firstInput = modal.querySelector('input, select, textarea');
    if (firstInput) { firstInput.focus(); }
  }

  function closeModal(selector) {
    var modal = selector instanceof Element ? selector : $(selector);
    if (!modal) { return; }
    modal.classList.remove('is-open');
    if (!document.querySelector('.mo-modal.is-open')) { document.body.style.overflow = ''; }
  }

  function initModals() {
    $$('[data-mo-modal-open]').forEach(function (trigger) {
      on(trigger, 'click', function (event) {
        event.preventDefault();
        openModal(trigger.getAttribute('data-mo-modal-open'));
      });
    });
    $$('[data-mo-modal-close]').forEach(function (button) {
      on(button, 'click', function (event) {
        event.preventDefault();
        closeModal(button.closest('.mo-modal'));
      });
    });
    $$('.mo-modal').forEach(function (modal) {
      on(modal, 'click', function (event) {
        if (event.target === modal || event.target.classList.contains('mo-modal__backdrop')) {
          closeModal(modal);
        }
      });
    });
    on(document, 'keydown', function (event) {
      if (event.key === 'Escape') { closeModal(document.querySelector('.mo-modal.is-open')); }
    });
  }

  /* --------------------------------------------------------- Konfirmasi */
  function confirmAction(options) {
    return new Promise(function (resolve) {
      var modal = $('#mo-confirm-modal');
      if (!modal) { resolve(window.confirm(options.message || 'Lanjutkan?')); return; }

      $('#mo-confirm-message').textContent = options.message || 'Apakah Anda yakin?';
      var confirmButton = $('#mo-confirm-accept');
      confirmButton.textContent = options.confirmLabel || 'Ya, lanjutkan';
      confirmButton.className = 'mo-btn ' + (options.danger ? 'mo-btn--danger' : 'mo-btn--primary');
      openModal('#mo-confirm-modal');

      function finish(result) {
        closeModal('#mo-confirm-modal');
        confirmButton.removeEventListener('click', onAccept);
        cancelButton.removeEventListener('click', onCancel);
        resolve(result);
      }
      function onAccept() { finish(true); }
      function onCancel() { finish(false); }

      var cancelButton = $('#mo-confirm-cancel');
      confirmButton.addEventListener('click', onAccept);
      cancelButton.addEventListener('click', onCancel);
    });
  }

  function initConfirmTriggers() {
    $$('[data-mo-confirm]').forEach(function (trigger) {
      on(trigger, 'click', function (event) {
        event.preventDefault();
        var message = trigger.getAttribute('data-mo-confirm') || 'Apakah Anda yakin?';
        confirmAction({ message: message, danger: trigger.hasAttribute('data-mo-danger') }).then(function (ok) {
          if (!ok) { return; }
          var form = trigger.closest('form');
          if (trigger.tagName === 'BUTTON' && trigger.form) { form = trigger.form; }
          if (form) { form.submit(); return; }
          if (trigger.tagName === 'A') { window.location.href = trigger.getAttribute('href'); }
        });
      });
    });
  }

  /* ------------------------------------------------------------- Formulir */
  function initForms() {
    $$('form[data-mo-form]').forEach(function (form) {
      on(form, 'submit', function () {
        var button = form.querySelector('[type="submit"]');
        if (button && !form.hasAttribute('data-mo-submitting')) {
          form.setAttribute('data-mo-submitting', '1');
          button.classList.add('is-loading');
          button.disabled = true;
        }
      });
    });
  }

  /* -------------------------------------------------------------- Pencarian */
  function initSearch() {
    $$('[data-mo-search]').forEach(function (input) {
      var target = input.getAttribute('data-mo-search');
      on(input, 'input', debounce(function () {
        var url = new URL(window.location.href);
        url.searchParams.set('q', input.value.trim());
        if (input.value.trim() === '') { url.searchParams.delete('q'); }
        url.searchParams.delete('page');
        window.location.href = url.pathname + '?' + url.searchParams.toString() + (target ? '#' + target : '');
      }, 400));
    });
  }

  /* ------------------------------------------------------- Panel terhubung */
  function initPanels() {
    var container = $('[data-mo-panels]');
    if (!container) { return; }

    function select(panel, id) {
      $$('.mo-panel__item', panel).forEach(function (item) { item.classList.remove('is-selected'); });
      var selected = $('.mo-panel__item[data-id="' + id + '"]', panel);
      if (selected) { selected.classList.add('is-selected'); }

      var event = new CustomEvent('mo:panel-selected', {
        bubbles: true,
        detail: { panel: panel.getAttribute('data-panel'), id: id }
      });
      container.dispatchEvent(event);
    }

    $$('.mo-panel').forEach(function (panel) {
      $$('.mo-panel__item', panel).forEach(function (item) {
        on(item, 'click', function () { select(panel, item.getAttribute('data-id')); });
      });
    });

    var firstUnit = $('.mo-panel[data-panel="units"] .mo-panel__item');
    if (firstUnit) {
      var panel = firstUnit.closest('.mo-panel');
      select(panel, firstUnit.getAttribute('data-id'));
    }
  }

  /* ------------------------------------------------------------ Auto-dismiss */
  function initAlerts() {
    $$('[data-mo-dismiss]').forEach(function (button) {
      on(button, 'click', function () {
        var alert = button.closest('.mo-alert');
        if (alert) { alert.remove(); }
      });
    });
  }

  /* ------------------------------------------------- Aksi berbasis fetch */
  function initAjaxActions() {
    $$('[data-mo-action]').forEach(function (trigger) {
      on(trigger, 'click', function (event) {
        event.preventDefault();
        var url = trigger.getAttribute('data-mo-action');
        var method = trigger.getAttribute('data-mo-method') || 'POST';
        var message = trigger.getAttribute('data-mo-confirm');

        var run = function () {
          trigger.classList.add('is-loading');
          return request(url, { method: method }).then(function (payload) {
            trigger.classList.remove('is-loading');
            if (payload.success === false) {
              toast(payload.message || 'Permintaan gagal.', 'danger');
              return payload;
            }
            toast(payload.message || 'Berhasil.', 'success');
            var redirect = payload.redirect || trigger.getAttribute('data-mo-redirect');
            if (redirect) {
              setTimeout(function () { window.location.href = redirect; }, 450);
            } else if (trigger.hasAttribute('data-mo-reload')) {
              setTimeout(function () { window.location.reload(); }, 450);
            }
            return payload;
          }).catch(function () {
            trigger.classList.remove('is-loading');
            toast('Terjadi kesalahan jaringan.', 'danger');
          });
        };

        if (message) {
          confirmAction({ message: message, danger: trigger.hasAttribute('data-mo-danger') }).then(function (ok) {
            if (ok) { run(); }
          });
          return;
        }

        run();
      });
    });
  }

  /* ------------------------------------------------------------- Inisialisasi */
  function init() {
    initSidebar();
    initDropdowns();
    initModals();
    initConfirmTriggers();
    initForms();
    initSearch();
    initPanels();
    initAlerts();
    initAjaxActions();

    var flash = $('[data-mo-flash]');
    if (flash) { toast(flash.getAttribute('data-mo-flash'), flash.getAttribute('data-mo-flash-type') || 'success'); }
  }

  window.MO = {
    request: request,
    toast: toast,
    openModal: openModal,
    closeModal: closeModal,
    confirm: confirmAction
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

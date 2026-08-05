/*!
 * Navbar notification bell — admin panel.
 *
 * The admin panel is server-rendered PHP and has no other AJAX anywhere; this
 * is the first and only XHR client in it. It is therefore written to be
 * boring on purpose:
 *
 *   * No framework, no jQuery. jQuery is present (loaded by layout/footer.php)
 *     but it loads AFTER this file's <script defer> is fetched, and depending
 *     on it would make the bell fail silently on any page whose footer changes.
 *     Everything here is plain DOM.
 *   * Progressive enhancement. layout/header.php server-renders the badge and
 *     the ten newest items on first paint, so with JS disabled — or if this
 *     file 404s — the bell still shows a correct snapshot and the "See all"
 *     link still works. This script only keeps that snapshot fresh.
 *   * Every failure is silent for the operator but logged to the console. A
 *     transient network blip must not throw a modal at someone in the middle
 *     of approving a wire transfer.
 *
 * The CSRF token is read from <meta name="admin-csrf"> and sent in an
 * X-Admin-Csrf header, which admin/notifications-feed.php checks with
 * hash_equals(). A custom header cannot be set by a cross-origin <form>, so
 * this is strictly stronger than the hidden-input scheme the rest of the
 * panel uses for full-page POSTs.
 */
(function () {
    'use strict';

    var POLL_MS = 60000;          // one minute — the panel is not a chat app
    var RETRY_BACKOFF_MAX = 8;    // consecutive failures before we give up

    var root = document.getElementById('adminNotifyBell');
    if (!root) {
        // Not on an admin page with a navbar (login page, download stream).
        return;
    }

    var endpoint = root.getAttribute('data-feed-url') || './notifications-feed.php';
    var csrfMeta = document.querySelector('meta[name="admin-csrf"]');
    var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

    var badge = root.querySelector('[data-notify-badge]');
    var header = root.querySelector('[data-notify-header]');
    var list = root.querySelector('[data-notify-list]');
    var readAllBtn = root.querySelector('[data-notify-read-all]');

    var failures = 0;
    var timer = null;
    var inFlight = false;

    function setBadge(count) {
        if (!badge) { return; }
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.hidden = false;
            badge.style.display = '';
        } else {
            badge.textContent = '0';
            badge.hidden = true;
            badge.style.display = 'none';
        }
        if (header) {
            header.textContent = count === 1
                ? '1 unread notification'
                : count + ' unread notifications';
        }
        root.setAttribute('data-unread', String(count));
    }

    /**
     * Build one dropdown row.
     *
     * Note every piece of server data goes in through textContent, never
     * innerHTML. Notification titles and bodies are admin-typed free text —
     * `<img src=x onerror=alert(1)>` is a realistic value — and the whole
     * point of storing markdown source rather than HTML is that nothing
     * downstream treats it as markup. The only strings that reach a class
     * attribute (severity_class, severity_icon) are produced by PHP
     * allowlists, not by the stored value.
     */
    function buildItem(n) {
        var a = document.createElement('a');
        a.href = './notifications.php?highlight=' + encodeURIComponent(n.id);
        a.className = 'dropdown-item' + (n.is_read ? '' : ' bg-light');

        var icon = document.createElement('i');
        icon.className = (n.severity_icon || 'far fa-bell') + ' text-' + (n.severity_class || 'secondary') + ' mr-2';
        a.appendChild(icon);

        var strong = document.createElement('span');
        strong.className = 'font-weight-' + (n.is_read ? 'normal' : 'bold');
        strong.textContent = n.title;
        a.appendChild(strong);

        var time = document.createElement('span');
        time.className = 'float-right text-muted text-sm';
        time.textContent = n.relative_time;
        a.appendChild(time);

        if (n.excerpt) {
            var p = document.createElement('p');
            p.className = 'text-sm text-muted mb-0 text-truncate';
            p.textContent = n.excerpt;
            a.appendChild(p);
        }

        return a;
    }

    function renderList(notifications) {
        if (!list) { return; }
        while (list.firstChild) {
            list.removeChild(list.firstChild);
        }
        if (!notifications.length) {
            var empty = document.createElement('span');
            empty.className = 'dropdown-item text-muted text-sm';
            empty.textContent = 'No notifications yet.';
            list.appendChild(empty);
            return;
        }
        for (var i = 0; i < notifications.length; i++) {
            list.appendChild(buildItem(notifications[i]));
            if (i < notifications.length - 1) {
                var divider = document.createElement('div');
                divider.className = 'dropdown-divider';
                list.appendChild(divider);
            }
        }
    }

    function request(options) {
        var opts = options || {};
        var init = {
            method: opts.method || 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        };
        if (init.method === 'POST') {
            init.headers['X-Admin-Csrf'] = csrfToken;
            init.headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
            init.body = opts.body || '';
        }
        return fetch(endpoint, init).then(function (response) {
            if (response.status === 401) {
                // Signed out or idle-timed-out. Stop polling rather than
                // hammering a dead session; the next navigation will bounce
                // the operator to the login page anyway.
                stopPolling();
                var err = new Error('unauthenticated');
                err.unauthenticated = true;
                throw err;
            }
            return response.json().then(function (payload) {
                if (!response.ok || !payload || payload.ok !== true) {
                    var message = payload && payload.error && payload.error.message
                        ? payload.error.message
                        : ('HTTP ' + response.status);
                    throw new Error(message);
                }
                return payload.data;
            });
        });
    }

    function refresh() {
        if (inFlight || document.hidden) { return; }
        inFlight = true;
        request({ method: 'GET' }).then(function (data) {
            failures = 0;
            renderList(data.notifications || []);
            setBadge(parseInt(data.unread_count, 10) || 0);
        }).catch(function (error) {
            if (!error.unauthenticated) {
                failures += 1;
                if (console && console.warn) {
                    console.warn('[admin-notifications] refresh failed:', error.message);
                }
                if (failures >= RETRY_BACKOFF_MAX) {
                    stopPolling();
                }
            }
        }).then(function () {
            inFlight = false;
        });
    }

    function markAllRead(event) {
        if (event) { event.preventDefault(); }
        request({ method: 'POST', body: 'action=read_all' }).then(function (data) {
            setBadge(parseInt(data.unread_count, 10) || 0);
            // Re-pull so the bold/unbold state of the ten rows matches.
            refresh();
        }).catch(function (error) {
            if (!error.unauthenticated && console && console.warn) {
                console.warn('[admin-notifications] mark-all-read failed:', error.message);
            }
        });
    }

    function startPolling() {
        if (timer !== null) { return; }
        timer = window.setInterval(refresh, POLL_MS);
    }

    function stopPolling() {
        if (timer === null) { return; }
        window.clearInterval(timer);
        timer = null;
    }

    // Stop polling while the tab is in the background. A panel left open on a
    // second monitor overnight would otherwise fire ~500 authenticated
    // requests, all of which hit the database, for nobody to read.
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stopPolling();
        } else if (failures < RETRY_BACKOFF_MAX) {
            refresh();
            startPolling();
        }
    });

    if (readAllBtn) {
        readAllBtn.addEventListener('click', markAllRead);
    }

    // fetch() is the only modern API used; on anything that lacks it the
    // server-rendered snapshot simply stays as it was, which is a correct
    // (if frozen) view rather than a broken one.
    if (typeof window.fetch !== 'function') {
        return;
    }

    if (!document.hidden) {
        startPolling();
    }
}());

/**
 * Client-side equipment readiness checks for local_proctopus preparation page.
 * Does not call Proctopus; honest labels only (camera/mic available to browser).
 */
(function () {
    'use strict';

    function parseConfig(root) {
        try {
            return JSON.parse(root.getAttribute('data-fps-config') || '{}');
        } catch (e) {
            return {};
        }
    }

    function setCardState(id, state, detail, strings) {
        var card = document.getElementById('fps-card-' + id);
        if (!card) {
            return;
        }
        var status = document.getElementById('fps-card-' + id + '-status');
        var detailEl = document.getElementById('fps-card-' + id + '-detail');
        var label = strings['status_' + state] || strings.status_pending || state;
        card.setAttribute('data-state', state);
        card.className = 'fps-card is-' + state;
        if (status) {
            status.textContent = label;
            status.className = 'fps-card-status is-' + state;
            status.setAttribute('data-state', state);
        }
        if (detailEl && detail) {
            detailEl.textContent = detail;
        }
    }

    function stopTracks(stream) {
        if (!stream || !stream.getTracks) {
            return;
        }
        stream.getTracks().forEach(function (t) {
            try {
                t.stop();
            } catch (e) {
                // ignore
            }
        });
    }

    function checkHttps(cfg, strings) {
        if (location.protocol === 'https:' || location.hostname === 'localhost') {
            return { state: 'success', detail: strings.conn_ok };
        }
        return { state: 'error', detail: strings.err_https };
    }

    function checkBrowser(strings) {
        var md = navigator.mediaDevices && navigator.mediaDevices.getUserMedia;
        if (!md) {
            return { state: 'error', detail: strings.err_browser };
        }
        return { state: 'success', detail: null };
    }

    function checkCamera(strings) {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            return Promise.resolve({ state: 'error', detail: strings.camera_fail });
        }
        return navigator.mediaDevices
            .getUserMedia({ video: true, audio: false })
            .then(function (stream) {
                stopTracks(stream);
                return { state: 'success', detail: strings.camera_ok };
            })
            .catch(function (err) {
                var name = (err && err.name) || '';
                if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
                    return { state: 'warning', detail: strings.camera_need };
                }
                return { state: 'error', detail: strings.camera_fail };
            });
    }

    function checkMic(strings) {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            return Promise.resolve({ state: 'error', detail: strings.mic_fail });
        }
        return navigator.mediaDevices
            .getUserMedia({ video: false, audio: true })
            .then(function (stream) {
                stopTracks(stream);
                return { state: 'success', detail: strings.mic_ok };
            })
            .catch(function (err) {
                var name = (err && err.name) || '';
                if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
                    return { state: 'warning', detail: strings.mic_need };
                }
                return { state: 'error', detail: strings.mic_fail };
            });
    }

    function checkScreen(strings) {
        // Capability only — do not open share dialog (provider may require later).
        var ok = !!(navigator.mediaDevices && typeof navigator.mediaDevices.getDisplayMedia === 'function');
        if (ok) {
            return { state: 'success', detail: strings.screen_ok };
        }
        return { state: 'warning', detail: strings.screen_warn };
    }

    function checkConnection(cfg, strings) {
        if (!navigator.onLine) {
            return Promise.resolve({ state: 'error', detail: strings.conn_fail });
        }
        // Lightweight same-origin ping (no secrets).
        var url = (cfg.wwwroot || '') + '/local/proctopus/styles.css?ping=' + Date.now();
        return fetch(url, { method: 'GET', credentials: 'same-origin', cache: 'no-store' })
            .then(function (res) {
                if (res && res.ok) {
                    return { state: 'success', detail: strings.conn_ok };
                }
                return { state: 'warning', detail: strings.conn_fail };
            })
            .catch(function () {
                return { state: 'error', detail: strings.conn_fail };
            });
    }

    function checkExtension(strings) {
        // Cannot trust fake detection; real check is inside provider after launch.
        return {
            state: 'warning',
            detail: strings.ext_note,
        };
    }

    function allBlockingOk(results) {
        // Camera + mic + connection must not be error; warning on camera/mic blocks go-proctor
        // (user must grant or retry). Extension stays warning intentionally.
        var cam = results.camera;
        var mic = results.mic;
        var conn = results.connection;
        var https = results.https;
        if (https && https.state === 'error') {
            return false;
        }
        if (cam.state === 'error' || cam.state === 'warning') {
            return false;
        }
        if (mic.state === 'error' || mic.state === 'warning') {
            return false;
        }
        if (conn.state === 'error') {
            return false;
        }
        return true;
    }

    function setCta(mode, cta, strings) {
        if (!cta) {
            return;
        }
        cta.setAttribute('data-mode', mode);
        cta.disabled = false;
        if (mode === 'check') {
            cta.innerHTML =
                '<i class="icon fa fa-check-circle fa-fw" aria-hidden="true"></i> ' +
                (strings.cta_check || '');
            cta.classList.remove('btn-success');
            cta.classList.add('btn-primary');
        } else if (mode === 'go') {
            cta.innerHTML =
                '<i class="icon fa fa-shield fa-fw" aria-hidden="true"></i> ' +
                (strings.cta_go_proctor || '');
            cta.classList.remove('btn-primary');
            cta.classList.add('btn-primary');
        } else if (mode === 'busy') {
            cta.disabled = true;
            cta.innerHTML =
                '<i class="icon fa fa-spinner fa-spin fa-fw" aria-hidden="true"></i> ' +
                (strings.status_checking || '');
        }
    }

    function runChecks(root, cfg) {
        var strings = cfg.strings || {};
        var cta = document.getElementById('fps-cta');
        var banner = document.getElementById('fps-banner');
        var retry = document.getElementById('fps-retry');
        var ids = ['camera', 'mic', 'screen', 'connection', 'extension'];

        setCta('busy', cta, strings);
        if (retry) {
            retry.classList.add('d-none');
        }
        if (banner) {
            banner.textContent = '';
            banner.className = 'fps-banner';
        }

        ids.forEach(function (id) {
            setCardState(id, 'checking', null, strings);
        });

        var https = checkHttps(cfg, strings);
        var browser = checkBrowser(strings);
        if (browser.state === 'error') {
            ids.forEach(function (id) {
                setCardState(id, 'error', browser.detail, strings);
            });
            setCta('check', cta, strings);
            if (retry) {
                retry.classList.remove('d-none');
            }
            if (banner) {
                banner.textContent = browser.detail;
                banner.className = 'fps-banner is-error';
            }
            return Promise.resolve(false);
        }

        return Promise.all([
            checkCamera(strings),
            checkMic(strings),
            Promise.resolve(checkScreen(strings)),
            checkConnection(cfg, strings),
            Promise.resolve(checkExtension(strings)),
        ]).then(function (parts) {
            var results = {
                https: https,
                camera: parts[0],
                mic: parts[1],
                screen: parts[2],
                connection: parts[3],
                extension: parts[4],
            };

            setCardState('camera', results.camera.state, results.camera.detail, strings);
            setCardState('mic', results.mic.state, results.mic.detail, strings);
            setCardState('screen', results.screen.state, results.screen.detail, strings);
            setCardState('connection', results.connection.state, results.connection.detail, strings);
            setCardState('extension', results.extension.state, results.extension.detail, strings);

            var ok = allBlockingOk(results);
            if (ok) {
                if (banner) {
                    banner.textContent = strings.banner_ready || '';
                    banner.className = 'fps-banner is-success';
                }
                setCta('go', cta, strings);
                if (retry) {
                    retry.classList.remove('d-none');
                }
            } else {
                if (banner) {
                    banner.textContent = strings.banner_need || '';
                    banner.className = 'fps-banner is-warning';
                }
                setCta('check', cta, strings);
                if (retry) {
                    retry.classList.remove('d-none');
                }
            }
            return ok;
        });
    }

    function boot() {
        var root = document.getElementById('fps-prep');
        if (!root) {
            return;
        }
        var cfg = parseConfig(root);
        var strings = cfg.strings || {};
        var cta = document.getElementById('fps-cta');
        var retry = document.getElementById('fps-retry');
        var form = document.getElementById('fps-launch-form');

        // Initial pending labels already server-rendered.
        if (cta) {
            cta.addEventListener('click', function () {
                var mode = cta.getAttribute('data-mode') || 'check';
                if (mode === 'go') {
                    if (form) {
                        form.submit();
                    }
                    return;
                }
                runChecks(root, cfg);
            });
        }
        if (retry) {
            retry.addEventListener('click', function () {
                runChecks(root, cfg);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();

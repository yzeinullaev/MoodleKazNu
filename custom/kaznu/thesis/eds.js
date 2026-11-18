(function () {
  function esc(s) {
    return String(s == null ? '' : s);
  }

  function readFileBase64(file) {
    return new Promise(function (resolve, reject) {
      var reader = new FileReader();
      reader.onload = function () {
        var res = reader.result || '';
        var b64 = String(res).split(',')[1] || '';
        resolve(b64);
      };
      reader.onerror = reject;
      reader.readAsDataURL(file);
    });
  }

  function ncalayerSign(xml) {
    return new Promise(function (resolve, reject) {
      var ws;
      try {
        ws = new WebSocket('wss://127.0.0.1:13579/');
      } catch (e) {
        reject(new Error('NCALayer не запущен. Установите NCALayer с портала НУЦ РК.'));
        return;
      }
      var done = false;
      var t = setTimeout(function () {
        if (!done) {
          done = true;
          try { ws.close(); } catch (e) {}
          reject(new Error('Таймаут NCALayer. Откройте NCALayer и выберите сертификат.'));
        }
      }, 120000);
      ws.onopen = function () {
        ws.send(JSON.stringify({
          module: 'kz.gov.pki.knca.commonUtils',
          method: 'signXml',
          args: ['PKCS12', 'SIGN', xml, '', ''],
        }));
      };
      ws.onmessage = function (ev) {
        if (done) return;
        done = true;
        clearTimeout(t);
        try {
          var data = JSON.parse(ev.data);
          if (data.code === '200' && data.responseObject) {
            resolve(data.responseObject);
          } else if (data.result && data.result.length) {
            resolve(data.result[0]);
          } else {
            reject(new Error(data.message || 'Подпись отменена или ошибка NCALayer'));
          }
        } catch (e) {
          reject(e);
        }
        try { ws.close(); } catch (err) {}
      };
      ws.onerror = function () {
        if (done) return;
        done = true;
        clearTimeout(t);
        reject(new Error('Не удалось подключиться к NCALayer (127.0.0.1:13579).'));
      };
    });
  }

  function postSign(cfg, payload) {
    var body = new URLSearchParams({
      sesskey: cfg.sesskey,
      action: 'eds_sign',
      id: String(cfg.id),
      sigtype: payload.sigtype,
      signature: payload.signature,
      comment: payload.comment || '',
    });
    return fetch(cfg.api, {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: body, credentials: 'same-origin'})
      .then(function (r) { return r.json(); });
  }

  window.kzThesisEdsInit = function (cfg) {
    var form = document.getElementById('kz-eds-form');
    if (!form || !cfg) return;

    var loader = document.getElementById('kz-loader-eds');
    var commentEl = form.querySelector('textarea[name="comment"]');

    var btnNca = document.getElementById('kz-eds-nca');
    var btnEgov = document.getElementById('kz-eds-egov');
    var egovFile = document.getElementById('kz-eds-egov-file');

    function setBusy(on) {
      if (loader) loader.hidden = !on;
      if (btnNca) btnNca.disabled = on;
      if (btnEgov) btnEgov.disabled = on;
    }

    if (btnNca) {
      btnNca.addEventListener('click', function () {
        setBusy(true);
        fetch(cfg.api + '?' + new URLSearchParams({sesskey: cfg.sesskey, action: 'eds_payload', id: cfg.id}), {credentials: 'same-origin'})
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (!data.ok) throw new Error(data.error || 'Ошибка');
            return ncalayerSign(data.payload.xml);
          })
          .then(function (sig) {
            return postSign(cfg, {sigtype: 'ncalayer', signature: sig, comment: commentEl ? commentEl.value : ''});
          })
          .then(function (res) {
            if (!res.ok) throw new Error(res.error || 'Ошибка');
            window.location.href = cfg.redirect;
          })
          .catch(function (e) {
            alert(esc(e.message || e));
          })
          .finally(function () { setBusy(false); });
      });
    }

    if (btnEgov && egovFile) {
      btnEgov.addEventListener('click', function () {
        if (!egovFile.files || !egovFile.files[0]) {
          alert('Выберите файл подписи (.p7s / .cms), полученный в eGov mobile.');
          egovFile.focus();
          return;
        }
        setBusy(true);
        readFileBase64(egovFile.files[0])
          .then(function (b64) {
            return postSign(cfg, {sigtype: 'egov_mobile', signature: b64, comment: commentEl ? commentEl.value : ''});
          })
          .then(function (res) {
            if (!res.ok) throw new Error(res.error || 'Ошибка');
            window.location.href = cfg.redirect;
          })
          .catch(function (e) {
            alert(esc(e.message || e));
          })
          .finally(function () { setBusy(false); });
      });
    }
  };
})();
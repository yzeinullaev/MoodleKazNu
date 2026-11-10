(function () {
    function fitShellToViewport() {
        const nav = document.querySelector(
            '#page-header .navbar, nav.navbar.fixed-top, header .navbar, .navbar'
        );
        let navh = 56;
        if (nav) {
            const r = nav.getBoundingClientRect();
            navh = Math.max(0, Math.round(r.bottom));
        }
        document.documentElement.style.setProperty('--kaznu-moodle-nav', navh + 'px');
        const shell = document.querySelector('.kaznu-ai-shell');
        if (shell) {
            shell.style.top = navh + 'px';
            shell.style.height = `calc(100dvh - ${navh}px)`;
        }
    }
    fitShellToViewport();
    window.addEventListener('resize', fitShellToViewport);

    const root = document.getElementById('kaznu-ai-root');
    if (!root) return;

    const cfg = JSON.parse(root.dataset.config || '{}');
    let conversationId = 0;
    let busy = false;

    root.innerHTML = `
    <div class="kaznu-ai-backdrop" aria-hidden="true"></div>
    <aside class="kaznu-ai-sidebar" aria-label="${esc(cfg.strings.history)}">
      <div class="kaznu-ai-sidebar-top">
        <div class="kaznu-ai-brand">
          <div class="kaznu-ai-brand-mark" aria-hidden="true">AI</div>
          <div class="kaznu-ai-brand-text">
            <strong>Farabi IBS</strong>
            <span>${esc(cfg.strings.assistant || 'Умный помощник')}</span>
          </div>
        </div>
        <button type="button" class="kaznu-ai-new">
          <span aria-hidden="true">+</span> ${esc(cfg.strings.newchat)}
        </button>
      </div>
      <div class="kaznu-ai-history-label">${esc(cfg.strings.history)}</div>
      <div class="kaznu-ai-conv-list"></div>
      <div class="kaznu-ai-sidebar-foot">Enter — отправить · Shift+Enter — новая строка</div>
    </aside>
    <section class="kaznu-ai-main">
      <header class="kaznu-ai-topbar">
        <div>
          <button type="button" class="kaznu-ai-icon-btn kaznu-ai-menu-toggle" title="Меню">☰</button>
          <h1>${esc(cfg.strings.pagetitle || 'ИИ-помощник')}</h1>
          <p>${esc(cfg.strings.subtitle || 'Диалоги сохраняются в вашем аккаунте')}</p>
        </div>
        <div class="kaznu-ai-topbar-actions">
          <button type="button" class="kaznu-ai-icon-btn danger kaznu-ai-del" title="${esc(cfg.strings.deletechat)}">${esc(cfg.strings.deletechat)}</button>
        </div>
      </header>
      <div class="kaznu-ai-messages" aria-live="polite">
        <div class="kaznu-ai-empty">
          <div class="kaznu-ai-empty-icon" aria-hidden="true">✦</div>
          <h2>${esc(cfg.strings.welcome_title || 'Чем могу помочь?')}</h2>
          <p>${esc(cfg.strings.welcome_body || 'Спросите про учёбу, курсы или расписание. Можно включить поиск в интернете для актуальных новостей.')}</p>
        </div>
      </div>
      <div class="kaznu-ai-compose">
        <div class="kaznu-ai-compose-inner">
          ${cfg.webenabled ? `<div class="kaznu-ai-web"><label><input type="checkbox" class="kaznu-ai-useweb" checked> ${esc(cfg.strings.websearch)}</label></div>` : ''}
          <div class="kaznu-ai-input-row">
            <textarea class="kaznu-ai-input" rows="1" placeholder="${esc(cfg.strings.typemessage)}"></textarea>
            <button type="button" class="kaznu-ai-send" title="${esc(cfg.strings.send)}" aria-label="${esc(cfg.strings.send)}">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 11l18-7-7 18-2-8-9-3z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
            </button>
          </div>
        </div>
        <p class="kaznu-ai-compose-hint">${esc(cfg.strings.disclaimer || 'Ответы ИИ носят справочный характер. Проверяйте важные данные в кабинете.')}</p>
      </div>
    </section>`;

    const sidebar = root.querySelector('.kaznu-ai-sidebar');
    const backdrop = root.querySelector('.kaznu-ai-backdrop');
    const convList = root.querySelector('.kaznu-ai-conv-list');
    const messagesEl = root.querySelector('.kaznu-ai-messages');
    const input = root.querySelector('.kaznu-ai-input');
    const sendBtn = root.querySelector('.kaznu-ai-send');
    const useWeb = root.querySelector('.kaznu-ai-useweb');
    const emptyTpl = root.querySelector('.kaznu-ai-empty');

    root.querySelector('.kaznu-ai-new').onclick = () => {
        closeSidebar();
        newChat();
    };
    sendBtn.onclick = () => send();
    root.querySelector('.kaznu-ai-del').onclick = () => deleteChat();
    root.querySelector('.kaznu-ai-menu-toggle').onclick = () => toggleSidebar();

    backdrop.onclick = () => closeSidebar();

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            send();
        }
    });
    input.addEventListener('input', autoGrow);

    function esc(s) {
        return String(s || '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        }[c]));
    }

    function autoGrow() {
        input.style.height = 'auto';
        input.style.height = Math.min(200, input.scrollHeight) + 'px';
    }

    function toggleSidebar() {
        sidebar.classList.toggle('open');
        backdrop.classList.toggle('show', sidebar.classList.contains('open'));
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        backdrop.classList.remove('show');
    }

    function setEmptyVisible(show) {
        let empty = messagesEl.querySelector('.kaznu-ai-empty');
        if (show && !empty) {
            empty = emptyTpl.cloneNode(true);
            messagesEl.appendChild(empty);
        } else if (!show && empty) {
            empty.remove();
        }
    }

    async function api(action, params = {}) {
        const body = new URLSearchParams({sesskey: cfg.sesskey, action, ...params});
        const res = await fetch(cfg.ajaxurl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body,
            credentials: 'same-origin',
        });
        return res.json();
    }

    function renderMessages(list) {
        messagesEl.querySelectorAll('.kaznu-ai-row').forEach((n) => n.remove());
        if (!list || !list.length) {
            setEmptyVisible(true);
            return;
        }
        setEmptyVisible(false);
        list.forEach((m) => {
            if (m.role === 'tool') return;
            appendBubble(m.role, m.content);
        });
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function appendBubble(role, content) {
        setEmptyVisible(false);
        const row = document.createElement('div');
        row.className = `kaznu-ai-row ${role === 'user' ? 'user' : 'assistant'}`;
        const av = document.createElement('div');
        av.className = 'kaznu-ai-avatar';
        av.textContent = role === 'user' ? (cfg.strings.you || 'Вы').slice(0, 2) : 'AI';
        const bubble = document.createElement('div');
        bubble.className = 'kaznu-ai-bubble';
        bubble.textContent = content;
        row.appendChild(av);
        row.appendChild(bubble);
        messagesEl.appendChild(row);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    async function loadConversations() {
        const data = await api('list');
        convList.innerHTML = '';
        (data.conversations || []).forEach((c) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'kaznu-ai-conv' + (c.id === conversationId ? ' active' : '');
            b.textContent = c.title;
            b.onclick = () => {
                closeSidebar();
                openChat(c.id);
            };
            convList.appendChild(b);
        });
    }

    async function newChat() {
        conversationId = 0;
        messagesEl.querySelectorAll('.kaznu-ai-row').forEach((n) => n.remove());
        setEmptyVisible(true);
        input.value = '';
        autoGrow();
        await loadConversations();
    }

    async function openChat(id) {
        conversationId = id;
        const data = await api('messages', {conversationid: String(id)});
        renderMessages(data.messages || []);
        await loadConversations();
    }

    async function deleteChat() {
        if (!conversationId) {
            await newChat();
            return;
        }
        if (!window.confirm(cfg.strings.deleteconfirm || 'Удалить этот чат?')) {
            return;
        }
        await api('delete', {conversationid: String(conversationId)});
        conversationId = 0;
        await newChat();
    }

    async function send() {
        if (busy) return;
        const text = (input.value || '').trim();
        if (!text) return;
        busy = true;
        sendBtn.disabled = true;
        appendBubble('user', text);
        input.value = '';
        autoGrow();

        const thinkingRow = document.createElement('div');
        thinkingRow.className = 'kaznu-ai-row assistant';
        thinkingRow.innerHTML = '<div class="kaznu-ai-avatar">AI</div><div class="kaznu-ai-bubble">' + esc(cfg.strings.thinking) + '</div>';
        messagesEl.appendChild(thinkingRow);

        try {
            const params = {
                message: text,
                conversationid: String(conversationId || 0),
            };
            if (cfg.webenabled && useWeb && useWeb.checked) {
                params.useweb = '1';
            }
            const data = await api('send', params);
            thinkingRow.remove();
            if (data.error) {
                appendBubble('assistant', data.error);
            } else {
                conversationId = data.conversationid;
                appendBubble('assistant', data.assistant);
                if (data.used_web) {
                    const row = document.createElement('div');
                    row.className = 'kaznu-ai-row assistant';
                    row.innerHTML = '<div class="kaznu-ai-avatar">AI</div><div class="kaznu-ai-bubble meta">ℹ️ ' +
                        esc(cfg.strings.webused || 'Использованы данные из интернета') + '</div>';
                    messagesEl.appendChild(row);
                }
            }
            await loadConversations();
        } catch (e) {
            thinkingRow.remove();
            appendBubble('assistant', cfg.strings.error_generic || 'Ошибка сети.');
        } finally {
            busy = false;
            sendBtn.disabled = false;
        }
    }

    loadConversations();
})();

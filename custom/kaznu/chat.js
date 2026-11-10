(function () {
    const root = document.getElementById('kaznu-ai-root');
    if (!root) return;

    const cfg = JSON.parse(root.dataset.config || '{}');
    let conversationId = 0;
    let busy = false;

    root.innerHTML = `
    <aside class="kaznu-ai-sidebar">
      <button type="button" class="btn btn-primary kaznu-ai-new">${esc(cfg.strings.newchat)}</button>
      <h3>${esc(cfg.strings.history)}</h3>
      <div class="kaznu-ai-conv-list"></div>
    </aside>
    <section class="kaznu-ai-main">
      <div class="kaznu-ai-messages" aria-live="polite"></div>
      <div class="kaznu-ai-compose">
        ${cfg.webenabled ? `<div class="kaznu-ai-web"><label><input type="checkbox" class="kaznu-ai-useweb" checked> ${esc(cfg.strings.websearch)}</label></div>` : ''}
        <textarea class="kaznu-ai-input" placeholder="${esc(cfg.strings.typemessage)}"></textarea>
        <div class="kaznu-ai-toolbar">
          <button type="button" class="btn btn-secondary kaznu-ai-del" title="${esc(cfg.strings.deletechat)}">${esc(cfg.strings.deletechat)}</button>
          <button type="button" class="btn btn-primary kaznu-ai-send">${esc(cfg.strings.send)}</button>
        </div>
      </div>
    </section>`;

    const convList = root.querySelector('.kaznu-ai-conv-list');
    const messagesEl = root.querySelector('.kaznu-ai-messages');
    const input = root.querySelector('.kaznu-ai-input');
    const useWeb = root.querySelector('.kaznu-ai-useweb');

    root.querySelector('.kaznu-ai-new').onclick = () => newChat();
    root.querySelector('.kaznu-ai-send').onclick = () => send();
    root.querySelector('.kaznu-ai-del').onclick = () => deleteChat();
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            send();
        }
    });

    function esc(s) {
        return String(s || '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        }[c]));
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
        messagesEl.innerHTML = '';
        list.forEach((m) => appendBubble(m.role, m.content));
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function appendBubble(role, content) {
        const div = document.createElement('div');
        div.className = `kaznu-ai-bubble ${role}`;
        div.textContent = content;
        messagesEl.appendChild(div);
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
            b.onclick = () => openChat(c.id);
            convList.appendChild(b);
        });
    }

    async function newChat() {
        conversationId = 0;
        messagesEl.innerHTML = '';
        input.value = '';
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
        await api('delete', {conversationid: String(conversationId)});
        conversationId = 0;
        messagesEl.innerHTML = '';
        await loadConversations();
    }

    async function send() {
        if (busy) return;
        const text = (input.value || '').trim();
        if (!text) return;
        busy = true;
        appendBubble('user', text);
        input.value = '';
        const thinking = document.createElement('div');
        thinking.className = 'kaznu-ai-bubble assistant';
        thinking.textContent = cfg.strings.thinking;
        messagesEl.appendChild(thinking);

        try {
            const params = {
                message: text,
                conversationid: String(conversationId || 0),
            };
            if (cfg.webenabled && useWeb && useWeb.checked) {
                params.useweb = '1';
            }
            const data = await api('send', params);
            thinking.remove();
            if (data.error) {
                appendBubble('assistant', data.error);
            } else {
                conversationId = data.conversationid;
                appendBubble('assistant', data.assistant);
                if (data.used_web) {
                    appendBubble('assistant', 'ℹ️ Использованы данные из интернета.');
                }
            }
            await loadConversations();
        } catch (e) {
            thinking.remove();
            appendBubble('assistant', 'Ошибка сети.');
        } finally {
            busy = false;
        }
    }

    loadConversations();
})();

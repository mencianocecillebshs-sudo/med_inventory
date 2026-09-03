document.addEventListener('DOMContentLoaded', () => {
    const chatContainer = document.getElementById('chat-container');
    const chatInput = document.getElementById('chat-input');
    const sendChatBtn = document.getElementById('send-chat-btn');
    const newChatBtn = document.getElementById('new-chat-btn');
    const suggestionChips = document.getElementById('suggestion-chips');
    const voiceBtn = document.getElementById('voice-btn');

    let conversationHistory = [];
    let isSending = false;
    let activeTopic = null;

    const greeting = 'Hello! Choose a main question on the right, then I will show related follow-up questions. You can also type naturally using keywords like stock, expiry, supplier, order, sales, purchase, or report.';

    const guidedQuestions = [
        {
            id: 'system',
            label: 'What can you help me with?',
            icon: 'bi-stars',
            answer: {
                intent: 'guided_system',
                confidence: 'high',
                response: 'I can guide you through inventory decisions and system workflows. I understand stock, expiry, suppliers, orders, sales, purchases, notifications, reports, and user activity keywords. Choose a follow-up below when you want a live database check.',
                actions: [{ label: 'Open Dashboard', href: 'dashboard.php', icon: 'bi-speedometer2' }],
            },
            followups: [
                { label: 'Show live system status', query: 'what is the current system status', icon: 'bi-speedometer2' },
                { label: 'Where are urgent risks?', query: 'where are the urgent inventory risks', icon: 'bi-exclamation-triangle' },
                { label: 'What can you do?', query: 'what can you do', icon: 'bi-question-circle' },
            ],
            keywords: ['help', 'capability', 'chatbot', 'assistant', 'system'],
        },
        {
            id: 'stock',
            label: 'What should I check in stock?',
            icon: 'bi-capsule',
            answer: {
                intent: 'guided_stock',
                confidence: 'high',
                response: 'For stock, start with low-stock and critical items, then check supplier availability and pending orders. If an item is below threshold or usage is high, prioritize reorder planning before it reaches zero.',
                actions: [{ label: 'Open Medicines', href: 'medicine.php', icon: 'bi-capsule' }],
            },
            followups: [
                { label: 'Show low stock medicines', query: 'show low stock medicines', icon: 'bi-graph-down-arrow' },
                { label: 'Why is stock low?', query: 'why are medicines running low', icon: 'bi-patch-question' },
                { label: 'What should I reorder?', query: 'what should I reorder today', icon: 'bi-cart-plus' },
            ],
            keywords: ['stock', 'quantity', 'medicine', 'medicines', 'available', 'low', 'critical', 'reorder', 'restock'],
        },
        {
            id: 'expiry',
            label: 'When should I worry about expiry?',
            icon: 'bi-calendar-event',
            answer: {
                intent: 'guided_expiry',
                confidence: 'high',
                response: 'Expiry risk starts with items inside the configured expiry-alert window. Check soonest expiry first, separate expired items, and use valid near-expiry medicines before newer batches when appropriate.',
                actions: [{ label: 'Open Medicines', href: 'medicine.php', icon: 'bi-capsule' }],
            },
            followups: [
                { label: 'Show expiring soon', query: 'show medicines expiring soon', icon: 'bi-calendar-event' },
                { label: 'Show expired only', query: 'show expired medicines', icon: 'bi-trash3' },
                { label: 'How do I handle expiry?', query: 'how do I handle expiring medicines', icon: 'bi-list-ol' },
            ],
            keywords: ['expiry', 'expire', 'expired', 'expiring', 'disposal', 'dispose'],
        },
        {
            id: 'supplier',
            label: 'Who should I order from?',
            icon: 'bi-truck',
            answer: {
                intent: 'guided_supplier',
                confidence: 'high',
                response: 'For supplier decisions, compare reliability, lead time, unit price, and whether the supplier is linked to the medicine you need. Prefer reliable suppliers for urgent or critical stock.',
                actions: [{ label: 'Open Suppliers', href: 'suppliers.php', icon: 'bi-truck' }],
            },
            followups: [
                { label: 'Show best suppliers', query: 'who are the best suppliers to order from', icon: 'bi-award' },
                { label: 'Who supplies low stock?', query: 'who supplies the low stock medicines', icon: 'bi-truck' },
                { label: 'Show pending orders', query: 'show pending orders', icon: 'bi-box-seam' },
            ],
            keywords: ['supplier', 'suppliers', 'vendor', 'provide', 'provides', 'order from'],
        },
        {
            id: 'order',
            label: 'How do I manage orders?',
            icon: 'bi-box-seam',
            answer: {
                intent: 'guided_order',
                confidence: 'high',
                response: 'Orders should connect the medicine, supplier, quantity, expected delivery date, and status. Use orders to track pending purchases and update them when delivered or cancelled.',
                actions: [{ label: 'Open Orders', href: 'orders.php', icon: 'bi-box-seam' }],
            },
            followups: [
                { label: 'How do I create an order?', query: 'how do I create an order', icon: 'bi-list-ol' },
                { label: 'Show pending orders', query: 'show pending orders', icon: 'bi-box-seam' },
                { label: 'When are deliveries due?', query: 'when are pending orders expected to arrive', icon: 'bi-calendar-check' },
            ],
            keywords: ['order', 'orders', 'purchase', 'pending', 'delivery', 'deliveries', 'delivered'],
        },
        {
            id: 'sales',
            label: 'What should I know about sales?',
            icon: 'bi-cash-stack',
            answer: {
                intent: 'guided_sales',
                confidence: 'high',
                response: 'Sales questions can show totals, invoice counts, payment breakdowns, and top-moving medicines. Use date keywords like today, this week, this month, or last month for better answers.',
                actions: [{ label: 'Open Sales', href: 'sales.php', icon: 'bi-cart-check' }],
            },
            followups: [
                { label: 'Sales this month', query: 'summarize sales this month', icon: 'bi-cash-stack' },
                { label: 'Sales this week', query: 'summarize sales this week', icon: 'bi-calendar-week' },
                { label: 'What sold most?', query: 'what medicines sold the most this month', icon: 'bi-bar-chart' },
            ],
            keywords: ['sale', 'sales', 'sold', 'invoice', 'revenue', 'payment', 'profit'],
        },
        {
            id: 'workflow',
            label: 'How do I use the system?',
            icon: 'bi-list-ol',
            answer: {
                intent: 'guided_workflow',
                confidence: 'high',
                response: 'For workflow questions, ask “how do I...” or “where can I...” plus the task. I can guide you through orders, medicines, suppliers, reports, sales, purchases, and settings.',
                actions: [{ label: 'Open Dashboard', href: 'dashboard.php', icon: 'bi-speedometer2' }],
            },
            followups: [
                { label: 'How do I create an order?', query: 'how do I create an order', icon: 'bi-box-seam' },
                { label: 'How do I generate a report?', query: 'how do I generate a report', icon: 'bi-file-earmark-bar-graph' },
                { label: 'Where can I edit medicines?', query: 'where can I edit medicines', icon: 'bi-capsule' },
            ],
            keywords: ['how', 'where', 'guide', 'workflow', 'steps', 'navigate', 'report', 'settings'],
        },
    ];

    const intentToTopic = {
        overview: 'system',
        help: 'system',
        low_stock: 'stock',
        medicine_lookup: 'stock',
        expiry: 'expiry',
        suppliers: 'supplier',
        orders: 'order',
        sales: 'sales',
        workflow: 'workflow',
        purchases: 'workflow',
        transactions: 'stock',
        notifications: 'system',
        activity: 'system',
    };

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = String(value ?? '');
        return div.innerHTML;
    }

    function showToast(message, type = 'success') {
        const wrap = document.createElement('div');
        wrap.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        wrap.innerHTML = `
            <div class="toast align-items-center text-bg-${type}" role="alert">
                <div class="d-flex">
                    <div class="toast-body">${escapeHtml(message)}</div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                </div>
            </div>`;
        document.body.appendChild(wrap);
        new bootstrap.Toast(wrap.querySelector('.toast')).show();
        setTimeout(() => wrap.remove(), 4000);
    }

    function getTopicById(id) {
        return guidedQuestions.find(item => item.id === id) || null;
    }

    function inferTopicFromKeywords(message) {
        const normalized = String(message || '').toLowerCase();
        return guidedQuestions.find(item => item.keywords.some(keyword => normalized.includes(keyword))) || null;
    }

    function renderSidebarTitle(title, subtitle = '') {
        if (!suggestionChips) return;
        const header = document.createElement('div');
        header.className = 'quick-section-title';
        header.innerHTML = `<strong>${escapeHtml(title)}</strong>${subtitle ? `<small>${escapeHtml(subtitle)}</small>` : ''}`;
        suggestionChips.appendChild(header);
    }

    function renderMainQuestions() {
        if (!suggestionChips) return;
        activeTopic = null;
        suggestionChips.innerHTML = '';
        renderSidebarTitle('Main Questions', 'Pick one to start a guided conversation.');
        guidedQuestions.forEach(item => {
            const chip = document.createElement('button');
            chip.className = 'suggestion-chip main-question';
            chip.type = 'button';
            chip.innerHTML = `<i class="bi ${escapeHtml(item.icon)}"></i><span>${escapeHtml(item.label)}</span>`;
            chip.addEventListener('click', () => answerMainQuestion(item.id));
            suggestionChips.appendChild(chip);
        });
    }

    function renderFollowUps(topicId, apiSuggestions = []) {
        if (!suggestionChips) return;
        const topic = getTopicById(topicId) || guidedQuestions[0];
        const options = apiSuggestions.length ? apiSuggestions : topic.followups;

        activeTopic = topic.id;
        suggestionChips.innerHTML = '';
        renderSidebarTitle('Follow-Up Questions', topic.label);

        const backBtn = document.createElement('button');
        backBtn.className = 'suggestion-chip back-question';
        backBtn.type = 'button';
        backBtn.innerHTML = '<i class="bi bi-arrow-left"></i><span>Main questions</span>';
        backBtn.addEventListener('click', renderMainQuestions);
        suggestionChips.appendChild(backBtn);

        options.forEach(opt => {
            const chip = document.createElement('button');
            chip.className = 'suggestion-chip follow-question';
            chip.type = 'button';
            chip.innerHTML = `<i class="bi ${escapeHtml(opt.icon || 'bi-chat-left-text')}"></i><span>${escapeHtml(opt.label)}</span>`;
            chip.addEventListener('click', () => handleQuery(opt.query, { topicId: topic.id }));
            suggestionChips.appendChild(chip);
        });
    }

    function renderCards(cards) {
        if (!Array.isArray(cards) || !cards.length) return '';
        return `<div class="assistant-result-grid">${cards.map(c => `
            <div class="assistant-result-card">
                <div class="assistant-result-label">${escapeHtml(c.label)}</div>
                <div class="assistant-result-value">${escapeHtml(c.value)}</div>
                ${c.meta ? `<div class="assistant-result-meta">${escapeHtml(c.meta)}</div>` : ''}
            </div>`).join('')}</div>`;
    }

    function renderTable(table) {
        if (!table || !table.columns?.length || !table.rows?.length) return '';
        return `<div class="assistant-table-wrap">
            <table class="table table-sm assistant-table">
                <thead><tr>${table.columns.map(c => `<th>${escapeHtml(c)}</th>`).join('')}</tr></thead>
                <tbody>
                    ${table.rows.map(row => `<tr>${table.columns.map(c =>
            `<td title="${escapeHtml(row[c])}">${escapeHtml(row[c])}</td>`).join('')}</tr>`).join('')}
                </tbody>
            </table>
        </div>`;
    }

    function renderActions(actions) {
        if (!Array.isArray(actions) || !actions.length) return '';
        return `<div class="assistant-actions">
            ${actions.map(a => `
                <a class="assistant-action-btn" href="${escapeHtml(a.href || '#')}">
                    <i class="bi ${escapeHtml(a.icon || 'bi-arrow-right-circle')}"></i>
                    <span>${escapeHtml(a.label)}</span>
                </a>`).join('')}
        </div>`;
    }

    function formatMessage(text) {
        return escapeHtml(text)
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\n/g, '<br>');
    }

    function createTypingIndicator() {
        const loadingEl = document.createElement('div');
        loadingEl.className = 'chat-message bot typing-message';
        loadingEl.innerHTML = `
            <div class="chat-message-header">
                <span><i class="bi bi-robot"></i> Assistant</span>
                <span class="chat-timestamp">Thinking</span>
            </div>
            <div class="typing-dots" aria-label="Assistant is typing">
                <span></span><span></span><span></span>
            </div>`;
        return loadingEl;
    }

    function setSendingState(active) {
        isSending = active;
        sendChatBtn.disabled = active;
        chatInput.disabled = active;
        if (voiceBtn) voiceBtn.disabled = active;
        sendChatBtn.innerHTML = active
            ? '<span class="spinner-border spinner-border-sm"></span>'
            : '<i class="bi bi-send-fill"></i>';
    }

    function addMessage(sender, payload, isBot = false) {
        const ts = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const div = document.createElement('div');
        div.className = `chat-message ${isBot ? 'bot' : 'user'}`;

        const text = typeof payload === 'string' ? payload : (payload.response || '');
        const cards = typeof payload === 'object' ? payload.cards || [] : [];
        const table = typeof payload === 'object' ? payload.table : null;
        const actions = typeof payload === 'object' ? payload.actions || [] : [];
        const aiEnhanced = typeof payload === 'object' ? payload.ai_enhanced || false : false;
        const confidence = typeof payload === 'object' ? payload.confidence || '' : '';
        const intent = typeof payload === 'object' ? payload.intent || '' : '';

        div.innerHTML = `
            <div class="chat-message-header">
                <span>${isBot ? `<i class="bi bi-robot"></i> Assistant ${aiEnhanced ? '<span class="ai-badge"><i class="bi bi-stars"></i> AI</span>' : ''}` : '<i class="bi bi-person-fill"></i> You'}</span>
                <span class="chat-timestamp">${ts}</span>
            </div>
            <p class="mb-0">${formatMessage(text)}</p>
            ${isBot && (intent || confidence) ? `<div class="assistant-meta">${intent ? escapeHtml(intent.replace(/_/g, ' ')) : 'answer'}${confidence ? ` - ${escapeHtml(confidence)} confidence` : ''}</div>` : ''}
            ${renderCards(cards)}
            ${renderTable(table)}
            ${renderActions(actions)}
        `;

        chatContainer.appendChild(div);
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }

    function answerMainQuestion(topicId) {
        const topic = getTopicById(topicId);
        if (!topic || isSending) return;

        addMessage('You', topic.label);
        addMessage('Assistant', topic.answer, true);
        conversationHistory.push({ role: 'user', content: topic.label });
        conversationHistory.push({ role: 'assistant', content: topic.answer.response });
        renderFollowUps(topic.id);
        chatInput.focus();
    }

    async function handleQuery(message, options = {}) {
        const cleanMessage = String(message || '').trim();
        if (!cleanMessage || isSending) return;

        const inferredTopic = getTopicById(options.topicId) || inferTopicFromKeywords(cleanMessage) || getTopicById(activeTopic);

        conversationHistory.push({ role: 'user', content: cleanMessage });
        addMessage('You', cleanMessage);
        chatInput.value = '';
        setSendingState(true);

        const loadingEl = createTypingIndicator();
        chatContainer.appendChild(loadingEl);
        chatContainer.scrollTop = chatContainer.scrollHeight;

        try {
            const res = await fetch('api/chatbot.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    query: cleanMessage,
                    topic: inferredTopic?.id || null,
                    history: conversationHistory.slice(-12)
                })
            });

            const data = await res.json();
            loadingEl.remove();

            if (!res.ok && !data.response) {
                throw new Error(data.message || 'Request failed');
            }

            if (data.success === false && !data.response) {
                throw new Error(data.message || 'Request failed');
            }

            conversationHistory.push({ role: 'assistant', content: data.response });
            addMessage('Assistant', data, true);

            const topicFromIntent = getTopicById(intentToTopic[data.intent]);
            const nextTopic = topicFromIntent || inferredTopic || getTopicById('system');
            renderFollowUps(nextTopic.id, data.suggestions || []);
        } catch (e) {
            loadingEl.remove();
            const topic = inferredTopic || getTopicById('system');
            addMessage('Assistant', {
                intent: 'fallback',
                confidence: 'medium',
                response: 'I could not find a clear answer for that. Use the suggestions on the right, or include a keyword like stock, expiry, supplier, order, sales, or purchase.',
                actions: [
                    { label: 'Open Dashboard', href: 'dashboard.php', icon: 'bi-speedometer2' },
                    { label: 'Open Medicines', href: 'medicine.php', icon: 'bi-capsule' },
                ],
            }, true);
            renderFollowUps(topic.id);
        } finally {
            setSendingState(false);
            chatInput.focus();
        }
    }

    sendChatBtn.addEventListener('click', () => handleQuery(chatInput.value));
    chatInput.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleQuery(chatInput.value);
        }
    });

    newChatBtn.addEventListener('click', () => {
        chatContainer.innerHTML = '';
        conversationHistory = [];
        addMessage('Assistant', { intent: 'greeting', confidence: 'high', response: greeting }, true);
        renderMainQuestions();
        chatInput.focus();
    });

    let isVoiceActive = false;
    if (voiceBtn) {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (SpeechRecognition) {
            voiceBtn.addEventListener('click', () => {
                if (isVoiceActive || isSending) return;
                isVoiceActive = true;
                voiceBtn.innerHTML = '<i class="bi bi-mic-fill text-danger"></i>';
                voiceBtn.classList.add('is-listening');
                voiceBtn.disabled = true;

                const rec = new SpeechRecognition();
                rec.lang = 'en-PH';
                rec.start();

                rec.onresult = e => {
                    handleQuery(e.results[0][0].transcript);
                    reset();
                };
                rec.onerror = () => { showToast('Voice recognition failed.', 'danger'); reset(); };
                rec.onend = reset;

                function reset() {
                    isVoiceActive = false;
                    voiceBtn.innerHTML = '<i class="bi bi-mic"></i>';
                    voiceBtn.classList.remove('is-listening');
                    voiceBtn.disabled = isSending;
                }
            });
        }
    }

    addMessage('Assistant', { intent: 'greeting', confidence: 'high', response: greeting }, true);
    renderMainQuestions();
    chatInput.focus();
});

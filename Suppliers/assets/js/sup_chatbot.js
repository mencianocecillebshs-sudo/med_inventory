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

    const greeting = 'Hello! I can answer questions about your supplier workspace only — your inventory, orders, sales, and transactions. Pick a main question on the right or type naturally.';

    const guidedQuestions = [
        {
            id: 'overview',
            label: 'How is my business doing?',
            icon: 'bi-speedometer2',
            answer: {
                intent: 'guided_overview',
                confidence: 'high',
                response: 'Your overview combines stocked items, low-stock alerts, pending orders, and monthly sales — all scoped to your supplier account.',
                actions: [{ label: 'Open Dashboard', href: 'sup_dashboard.php', icon: 'bi-speedometer2' }],
            },
            followups: [
                { label: 'Show my overview', query: 'show my supplier overview', icon: 'bi-speedometer2' },
                { label: 'Pending orders', query: 'show my pending orders', icon: 'bi-box-seam' },
                { label: 'Sales this month', query: 'summarize my sales this month', icon: 'bi-cash-stack' },
            ],
            keywords: ['overview', 'dashboard', 'summary', 'status', 'business'],
        },
        {
            id: 'inventory',
            label: 'What is in my inventory?',
            icon: 'bi-box-seam',
            answer: {
                intent: 'guided_inventory',
                confidence: 'high',
                response: 'Your inventory shows medicines you stock as a supplier, including quantities and unit prices in your workspace.',
                actions: [{ label: 'My Inventory', href: 'sup_medicine.php', icon: 'bi-box-seam' }],
            },
            followups: [
                { label: 'Show my stock', query: 'show my inventory summary', icon: 'bi-box-seam' },
                { label: 'Low stock items', query: 'show my low stock medicines', icon: 'bi-graph-down-arrow' },
                { label: 'Expiring soon', query: 'show my expiring medicines', icon: 'bi-calendar-event' },
            ],
            keywords: ['stock', 'inventory', 'medicine', 'medicines', 'catalog', 'product'],
        },
        {
            id: 'orders',
            label: 'What orders need attention?',
            icon: 'bi-bag-check',
            answer: {
                intent: 'guided_orders',
                confidence: 'high',
                response: 'Orders are purchase requests sent to your supplier account. Review pending and accepted orders to plan fulfillment.',
                actions: [{ label: 'My Orders', href: 'sup_orders.php', icon: 'bi-bag-check' }],
            },
            followups: [
                { label: 'Pending orders', query: 'show my pending orders', icon: 'bi-box-seam' },
                { label: 'Accepted orders', query: 'show my accepted orders', icon: 'bi-check-circle' },
                { label: 'Delivered orders', query: 'show my delivered orders', icon: 'bi-truck' },
            ],
            keywords: ['order', 'orders', 'delivery', 'pending', 'accepted'],
        },
        {
            id: 'sales',
            label: 'How are my sales performing?',
            icon: 'bi-cash-stack',
            answer: {
                intent: 'guided_sales',
                confidence: 'high',
                response: 'Sales answers show revenue and units sold from your supplier sales records. Use date keywords like today, this week, or this month.',
                actions: [{ label: 'My Sales', href: 'sup_sales.php', icon: 'bi-cart-check' }],
            },
            followups: [
                { label: 'Sales this month', query: 'summarize my sales this month', icon: 'bi-cash-stack' },
                { label: 'Sales this week', query: 'summarize my sales this week', icon: 'bi-calendar-week' },
                { label: 'Top sellers', query: 'what medicines sold the most this month', icon: 'bi-bar-chart' },
            ],
            keywords: ['sale', 'sales', 'revenue', 'invoice', 'sold'],
        },
        {
            id: 'workflow',
            label: 'How do I use the supplier portal?',
            icon: 'bi-list-ol',
            answer: {
                intent: 'guided_workflow',
                confidence: 'high',
                response: 'Ask “how do I...” for inventory updates, order handling, sales review, reports, or settings within your supplier workspace.',
                actions: [{ label: 'Help & Support', href: 'sup_help.php', icon: 'bi-question-circle' }],
            },
            followups: [
                { label: 'Manage inventory', query: 'how do I manage my inventory', icon: 'bi-box-seam' },
                { label: 'Handle orders', query: 'how do I manage orders', icon: 'bi-bag-check' },
                { label: 'Generate reports', query: 'how do I generate a report', icon: 'bi-file-earmark-bar-graph' },
            ],
            keywords: ['how', 'where', 'guide', 'workflow', 'steps', 'navigate'],
        },
    ];

    const intentToTopic = {
        overview: 'overview',
        help: 'overview',
        inventory: 'inventory',
        medicine_lookup: 'inventory',
        low_stock: 'inventory',
        expiry: 'inventory',
        orders: 'orders',
        sales: 'sales',
        transactions: 'sales',
        workflow: 'workflow',
        restricted: 'overview',
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
        renderSidebarTitle('Main Questions', 'Scoped to your supplier account only.');
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
            const res = await fetch('api/sup_chatbot.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    query: cleanMessage,
                    topic: inferredTopic?.id || null,
                    history: conversationHistory.slice(-12),
                }),
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
            const nextTopic = topicFromIntent || inferredTopic || getTopicById('overview');
            renderFollowUps(nextTopic.id, data.suggestions || []);
        } catch (e) {
            loadingEl.remove();
            const topic = inferredTopic || getTopicById('overview');
            addMessage('Assistant', {
                intent: 'fallback',
                confidence: 'medium',
                response: 'I could not find a clear answer for that. Try one of the suggestions on the right, or ask about your inventory, orders, sales, or transactions.',
                actions: [
                    { label: 'My Inventory', href: 'sup_medicine.php', icon: 'bi-box-seam' },
                    { label: 'My Orders', href: 'sup_orders.php', icon: 'bi-bag-check' },
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

    if (voiceBtn) {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (SpeechRecognition) {
            let isVoiceActive = false;
            voiceBtn.addEventListener('click', () => {
                if (isVoiceActive || isSending) return;
                isVoiceActive = true;
                voiceBtn.classList.add('is-listening');
                voiceBtn.disabled = true;

                const rec = new SpeechRecognition();
                rec.lang = 'en-PH';
                rec.start();

                const reset = () => {
                    isVoiceActive = false;
                    voiceBtn.classList.remove('is-listening');
                    voiceBtn.disabled = isSending;
                };

                rec.onresult = e => {
                    handleQuery(e.results[0][0].transcript);
                    reset();
                };
                rec.onerror = () => {
                    showToast('Voice recognition failed.', 'danger');
                    reset();
                };
                rec.onend = reset;
            });
        } else {
            voiceBtn.style.display = 'none';
        }
    }

    addMessage('Assistant', { intent: 'greeting', confidence: 'high', response: greeting }, true);
    renderMainQuestions();
    chatInput.focus();
});

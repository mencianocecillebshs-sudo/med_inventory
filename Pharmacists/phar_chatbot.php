<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Assistant - Medicine Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1b5e3f;
            --primary-light: #2e8b57;
            --ink: #172033;
            --muted: #64748b;
            --line: #e2e8f0;
            --surface: #ffffff;
            --soft: #f8fafc;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            overflow: hidden;
        }

        body.chatbot-page .main-content {
            display: flex !important;
            flex-direction: row !important;
            align-items: stretch;
            gap: 1rem;
            height: calc(100vh - 124px);
            min-height: 0 !important;
            width: 100% !important;
            max-width: 1760px !important;
            margin-left: auto !important;
            margin-right: auto !important;
            padding: 1rem 1.5rem 1.5rem !important;
            overflow: hidden;
            box-sizing: border-box;
        }

        body.chatbot-page.sidebar-collapsed .main-content {
            width: 100% !important;
            margin-left: auto !important;
            margin-right: auto !important;
        }

        body.chatbot-page .admin-breadcrumb-header {
            display: block !important;
            position: relative;
            z-index: 20;
        }

        /* Header */
        .page-header {
            padding: 1rem 1.5rem;
            background: white;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-header h2 {
            margin: 0;
            font-weight: 700;
            color: var(--primary);
        }

        /* Chat Container - Wider for better readability */
        .chat-panel {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            background: var(--surface);
            border-radius: 16px;
            margin: 0 !important;
            border: 1px solid rgba(27, 94, 63, 0.10);
            box-shadow: 0 18px 42px rgba(15, 63, 40, 0.10);
            overflow: hidden;
            min-width: 0;
            min-height: 0;
        }

        .chat-toolbar {
            flex: 0 0 auto;
            padding: 0.9rem 1.2rem;
            border-bottom: 1px solid var(--line);
            background:
                linear-gradient(135deg, rgba(27, 94, 63, 0.08), rgba(46, 204, 113, 0.05)),
                #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 4;
        }

        .chat-toolbar strong {
            color: var(--ink);
            font-size: 1rem;
        }

        .assistant-toolbar-meta {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.45rem;
            margin-top: 0.25rem;
        }

        .assistant-status-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            min-height: 24px;
            padding: 0.18rem 0.5rem;
            border: 1px solid rgba(27, 94, 63, 0.14);
            border-radius: 999px;
            color: #0f3f28;
            background: rgba(27, 94, 63, 0.08);
            font-size: 0.72rem;
            font-weight: 800;
        }

        .assistant-status-chip.is-busy {
            color: #92400e;
            border-color: rgba(245, 158, 11, 0.25);
            background: rgba(245, 158, 11, 0.12);
        }

        .chat-container {
            flex: 1 1 auto;
            overflow-y: auto;
            padding: 1.5rem;
            background:
                linear-gradient(180deg, rgba(248, 250, 252, 0.82), rgba(241, 245, 249, 0.82)),
                #f8fafc;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            min-height: 0 !important;
            max-height: none !important;
            border: 0 !important;
            border-radius: 0 !important;
            box-shadow: none !important;
        }

        .chat-message {
            max-width: 78%;
            padding: 0.95rem 1.1rem;
            border-radius: 18px;
            line-height: 1.55;
            box-shadow: 0 8px 22px rgba(15, 63, 40, 0.07);
            animation: messageIn 0.2s ease-out;
        }

        .chat-message.bot {
            align-self: flex-start;
            background: white;
            border: 1px solid var(--line);
            border-bottom-left-radius: 6px;
            color: var(--ink);
        }

        .chat-message.user {
            align-self: flex-end;
            background: linear-gradient(135deg, var(--primary), #1e3a2f);
            color: white;
            border-bottom-right-radius: 6px;
        }

        .chat-message-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 0.45rem;
            font-size: 0.82rem;
            font-weight: 800;
            color: #334155;
        }

        .chat-message.user .chat-message-header {
            color: rgba(255, 255, 255, 0.86);
        }

        .chat-timestamp {
            color: #94a3b8;
            font-size: 0.74rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .chat-message.user .chat-timestamp {
            color: rgba(255, 255, 255, 0.74);
        }

        .assistant-meta {
            display: inline-flex;
            margin-top: 0.65rem;
            padding: 0.22rem 0.55rem;
            border-radius: 999px;
            background: rgba(27, 94, 63, 0.08);
            color: #0f3f28;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: capitalize;
        }

        /* Right Sidebar - Quick Questions */
        .quick-sidebar {
            flex: 0 0 310px;
            width: 310px;
            background: var(--surface);
            border: 1px solid rgba(27, 94, 63, 0.10);
            padding: 0 1rem 1.5rem;
            overflow-y: auto;
            margin: 0 !important;
            border-radius: 16px;
            box-shadow: 0 18px 42px rgba(15, 63, 40, 0.10);
            min-height: 0;
            order: 2;
        }

        .quick-sidebar h6 {
            font-weight: 600;
            color: #374151;
            margin: 0 -1rem 1rem;
            padding: 1.5rem 1rem 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--surface);
            border-bottom: 1px solid rgba(27, 94, 63, 0.08);
            position: sticky;
            top: 0;
            z-index: 3;
        }

        .quick-section-title {
            padding: 0.2rem 0.5rem 0.75rem;
        }

        .quick-section-title strong,
        .quick-section-title small {
            display: block;
        }

        .quick-section-title strong {
            color: var(--ink);
            font-size: 0.86rem;
            font-weight: 850;
        }

        .quick-section-title small {
            margin-top: 0.15rem;
            color: var(--muted);
            font-size: 0.76rem;
            line-height: 1.35;
        }

        .suggestion-chip {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            width: 100%;
            text-align: left;
            padding: 0.8rem 1.1rem;
            margin-bottom: 0.6rem;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            color: #1f2937;
            font-size: 0.95rem;
            font-weight: 750;
            transition: all 0.2s ease;
        }

        .suggestion-chip.main-question {
            background: linear-gradient(135deg, rgba(27, 94, 63, 0.08), rgba(46, 204, 113, 0.05));
            border-color: rgba(27, 94, 63, 0.16);
        }

        .suggestion-chip.follow-question {
            background: #ffffff;
        }

        .suggestion-chip.back-question {
            background: #f1f5f9;
            color: #475569;
            border-style: dashed;
        }

        .suggestion-chip i {
            width: 1.1rem;
            color: var(--primary);
            flex: 0 0 auto;
        }

        .suggestion-chip:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: translateX(6px);
        }

        .suggestion-chip:hover i {
            color: white;
        }

        /* Input Area */
        .assistant-compose {
            padding: 1.25rem 1.5rem;
            border-top: 1px solid var(--line);
            background: white;
        }

        .input-row {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        .input-row .form-control {
            flex: 1;
            border-radius: 9999px;
            padding: 0.85rem 1.35rem;
            border: 2px solid #e5e7eb;
            font-size: 1rem;
        }

        .input-row .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(27, 94, 63, 0.15);
        }

        .btn-send, .btn-voice {
            width: 52px;
            height: 52px;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-send {
            background: linear-gradient(135deg, var(--primary), #1e3a2f);
            border: none;
            color: white;
        }

        .btn-send:disabled,
        .btn-voice:disabled {
            opacity: 0.72;
        }

        .btn-voice.is-listening {
            border-color: #dc2626;
            background: #fef2f2;
        }

        .ai-badge {
            background: linear-gradient(135deg, #7c3aed, #4f46e5);
            color: white;
            font-size: 0.75rem;
            padding: 2px 9px;
            border-radius: 9999px;
        }

        .assistant-result-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.75rem;
            margin-top: 0.9rem;
        }

        .assistant-result-card {
            padding: 0.85rem;
            border: 1px solid #dbe7df;
            border-radius: 8px;
            background: #f8fafc;
            min-width: 0;
        }

        .assistant-result-label {
            color: var(--muted);
            font-size: 0.72rem;
            font-weight: 850;
            text-transform: uppercase;
            letter-spacing: 0;
        }

        .assistant-result-value {
            margin-top: 0.2rem;
            color: #0f3f28;
            font-size: 1.05rem;
            font-weight: 850;
            overflow-wrap: anywhere;
        }

        .assistant-result-meta {
            margin-top: 0.15rem;
            color: var(--muted);
            font-size: 0.78rem;
            font-weight: 650;
        }

        .assistant-table-wrap {
            margin-top: 0.9rem;
            border: 1px solid var(--line);
            border-radius: 10px;
            overflow: auto;
            max-height: 320px;
        }

        .assistant-table {
            margin-bottom: 0;
            min-width: 680px;
            font-size: 0.86rem;
        }

        .assistant-table th {
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
            color: #ffffff;
            white-space: nowrap;
            border: 0;
        }

        .assistant-table td {
            vertical-align: middle;
            white-space: nowrap;
            max-width: 280px;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #334155;
        }

        .assistant-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.55rem;
            margin-top: 0.9rem;
        }

        .assistant-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.38rem;
            min-height: 36px;
            padding: 0.45rem 0.75rem;
            border-radius: 8px;
            border: 1px solid rgba(27, 94, 63, 0.18);
            color: #0f3f28;
            background: rgba(27, 94, 63, 0.08);
            font-weight: 800;
            font-size: 0.84rem;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .assistant-action-btn:hover {
            color: #ffffff;
            border-color: #1b5e3f;
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
            transform: translateY(-1px);
        }

        .typing-dots {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            min-height: 1.2rem;
        }

        .typing-dots span {
            width: 0.45rem;
            height: 0.45rem;
            border-radius: 999px;
            background: #1b5e3f;
            animation: typingPulse 1s infinite ease-in-out;
        }

        .typing-dots span:nth-child(2) {
            animation-delay: 0.15s;
        }

        .typing-dots span:nth-child(3) {
            animation-delay: 0.3s;
        }

        @keyframes typingPulse {
            0%, 80%, 100% { opacity: 0.35; transform: translateY(0); }
            40% { opacity: 1; transform: translateY(-3px); }
        }

        @keyframes messageIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Scrollbar */
        .chat-container::-webkit-scrollbar {
            width: 6px;
        }
        .chat-container::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 20px;
        }

        @media (max-width: 991.98px) {
            body.chatbot-page {
                overflow: auto;
            }

            body.chatbot-page .main-content,
            body.chatbot-page.sidebar-collapsed .main-content {
                flex-direction: column !important;
                height: auto;
                min-height: 100vh !important;
                width: 100% !important;
                margin-left: 0 !important;
                padding: 5rem 1rem 1rem !important;
                overflow: visible;
            }

            .chat-panel {
                min-height: 68vh;
            }

            .quick-sidebar {
                flex: 0 0 auto;
                width: 100%;
                max-height: none;
                border-left: 0;
                border-top: 1px solid #e2e8f0;
            }

            .chat-message {
                max-width: 92%;
            }
        }
    </style>
</head>
<body class="chatbot-page">
    <?php include 'includes/nav.php'; ?>

    <div class="main-content">
        <!-- Main Chat Area - Wider for better readability -->
        <div class="chat-panel flex-grow-1 d-flex flex-column">
            <div class="chat-toolbar">
                <div class="d-flex align-items-center gap-3">
                    <i class="bi bi-robot fs-4 text-success"></i>
                    <div>
                        <strong>Inventory Copilot</strong>
                        <div class="assistant-toolbar-meta">
                            <small class="text-muted">Pharmacy admin workspace</small>
                            <span class="assistant-status-chip"><i class="bi bi-shield-check"></i> Pharmacy & supplier profiles</span>
                        </div>
                    </div>
                </div>
                <button class="btn btn-outline-secondary btn-sm" id="new-chat-btn">
                    <i class="bi bi-plus-circle"></i> New Chat
                </button>
            </div>

            <div class="chat-container" id="chat-container"></div>

            <div class="assistant-compose">
                <div class="input-row">
                    <input type="text" class="form-control" id="chat-input"
                           placeholder="Ask anything: stock levels, suppliers, how to reorder..." autocomplete="off">
                    <button class="btn btn-send" id="send-chat-btn">
                        <i class="bi bi-send-fill"></i>
                    </button>
                    <button class="btn btn-outline-secondary btn-voice" id="voice-btn">
                        <i class="bi bi-mic"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Right Sidebar - Quick Questions -->
        <div class="quick-sidebar">
            <h6><i class="bi bi-lightning-charge-fill text-warning"></i> Quick Questions</h6>
            <div id="suggestion-chips"></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/phar_chatbot.js"></script>
</body>
</html>

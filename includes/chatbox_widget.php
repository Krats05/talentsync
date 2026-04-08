<link rel="stylesheet" href="assets/chatbox.css">
<?php if (!defined('JQUERY_LOADED')): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<?php endif; ?>

<!-- Floating Action Button -->
<button id="ai-fab" title="AI Job Assistant">✦</button>

<!-- Chat Panel -->
<div id="ai-panel" role="dialog" aria-label="AI Job Assistant">

    <!-- Header -->
    <div id="ai-panel-header">
        <div class="ai-header-info">
            <span class="ai-header-icon">✦</span>
            <div>
                <div class="ai-header-title">AI Job Assistant</div>
                <div class="ai-header-sub">Powered by Claude</div>
            </div>
        </div>
        <button id="ai-panel-close" title="Close" aria-label="Close AI panel">✕</button>
    </div>

    <!-- Messages -->
    <div id="ai-messages" aria-live="polite">
        <!-- Typing indicator (hidden by default) -->
        <div id="ai-typing">
            <span></span><span></span><span></span>
        </div>
    </div>

    <!-- Input Row -->
    <div id="ai-input-row">
        <input
            id="ai-user-input"
            type="text"
            placeholder='e.g. "I want a Project Manager"'
            autocomplete="off"
            maxlength="300"
        >
        <button id="ai-send-btn">Send</button>
    </div>

</div>

<script src="assets/js/chatbox.js"></script>

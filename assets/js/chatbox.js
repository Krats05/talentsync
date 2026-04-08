(function ($) {
    'use strict';

    // ── XSS-safe escape ──────────────────────────────────────────────────────
    function esc(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ── Scroll messages to bottom ────────────────────────────────────────────
    function scrollBottom() {
        var $msgs = $('#ai-messages');
        $msgs.scrollTop($msgs[0].scrollHeight);
    }

    // ── Append message bubbles ───────────────────────────────────────────────
    function appendUserBubble(text) {
        $('#ai-typing').before(
            '<div class="ai-msg ai-msg-user">' + esc(text) + '</div>'
        );
        scrollBottom();
    }

    function appendAgentBubble(html) {
        $('#ai-typing').before(
            '<div class="ai-msg ai-msg-agent">' + html + '</div>'
        );
        scrollBottom();
    }

    // ── Typing indicator ─────────────────────────────────────────────────────
    function showTyping() {
        $('#ai-typing').addClass('show');
        scrollBottom();
    }
    function hideTyping() {
        $('#ai-typing').removeClass('show');
    }

    // ── Input enable / disable ───────────────────────────────────────────────
    function setInputBusy(busy) {
        $('#ai-user-input, #ai-send-btn').prop('disabled', busy);
    }

    // ── Panel open / close ───────────────────────────────────────────────────
    $('#ai-fab').on('click', function () {
        var isOpen = $('#ai-panel').hasClass('open');
        if (isOpen) {
            $('#ai-panel').removeClass('open');
            $('#ai-fab').removeClass('panel-open');
        } else {
            $('#ai-panel').addClass('open');
            $('#ai-fab').addClass('panel-open');
            $('#ai-user-input').focus();
            scrollBottom();
        }
    });

    $('#ai-panel-close').on('click', function () {
        $('#ai-panel').removeClass('open');
        $('#ai-fab').removeClass('panel-open');
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $('#ai-panel').hasClass('open')) {
            $('#ai-panel').removeClass('open');
            $('#ai-fab').removeClass('panel-open');
        }
    });

    // ── Handle API response ──────────────────────────────────────────────────
    function handleResponse(resp) {
        hideTyping();
        setInputBusy(false);
        $('#ai-user-input').focus();

        if (resp.status === 'success') {
            appendAgentBubble(
                '✓ Draft job created: <strong>' + esc(resp.job_title) + '</strong><br>' +
                '<span style="color:#64748b;font-size:12px;">O*NET: ' + esc(resp.onet_title) + '</span><br><br>' +
                '<a href="' + esc(resp.draft_url) + '">→ Review &amp; Edit Draft</a>'
            );

        } else if (resp.status === 'clarify') {
            var buttons = '';
            $.each(resp.matches, function (_i, m) {
                buttons +=
                    '<button class="ai-clarify-btn" data-code="' + esc(m.code) + '">' +
                    esc(m.title) +
                    ' <span class="ai-clarify-code">(' + esc(m.code) + ')</span>' +
                    '</button>';
            });
            appendAgentBubble(
                'I found multiple O*NET matches for <strong>' + esc(resp.job_title) + '</strong>. ' +
                'Which role did you mean?<br><br>' + buttons
            );

        } else if (resp.status === 'no_match') {
            var searched = resp.searched_for
                ? ' for "<strong>' + esc(resp.searched_for) + '</strong>"'
                : '';
            appendAgentBubble(
                'I couldn\'t find an O*NET match' + searched + '. ' +
                'Try rephrasing (e.g. "Software Developer", "Data Analyst"), or ' +
                '<a href="create_job.php">create the job manually →</a>'
            );

        } else if (resp.status === 'not_job') {
            appendAgentBubble(esc(resp.message));

        } else {
            appendAgentBubble(
                '⚠ Something went wrong: ' + esc(resp.message || 'Unknown error') + '. Please try again.'
            );
        }
    }

    function handleError() {
        hideTyping();
        setInputBusy(false);
        appendAgentBubble('⚠ Network error. Please check your connection and try again.');
    }

    // ── Clarify button — re-submit with chosen O*NET code ────────────────────
    $(document).on('click', '.ai-clarify-btn', function () {
        var code  = $(this).data('code');
        var label = $(this).clone().find('.ai-clarify-code').remove().end().text().trim();

        appendUserBubble(label || code);
        setInputBusy(true);
        showTyping();

        $.ajax({
            url         : 'api/chatbox.php',
            method      : 'POST',
            contentType : 'application/json',
            headers     : { 'X-CSRF-Token': window.CSRF_TOKEN || '' },
            data        : JSON.stringify({ message: '', onet_soc_code: code }),
            dataType    : 'json',
            success     : handleResponse,
            error       : handleError,
        });
    });

    // ── Send message ─────────────────────────────────────────────────────────
    function sendMessage() {
        var text = $.trim($('#ai-user-input').val());
        if (!text) return;

        appendUserBubble(text);
        $('#ai-user-input').val('');
        setInputBusy(true);
        showTyping();

        $.ajax({
            url         : 'api/chatbox.php',
            method      : 'POST',
            contentType : 'application/json',
            headers     : { 'X-CSRF-Token': window.CSRF_TOKEN || '' },
            data        : JSON.stringify({ message: text, onet_soc_code: '' }),
            dataType    : 'json',
            success     : handleResponse,
            error       : handleError,
        });
    }

    $('#ai-send-btn').on('click', sendMessage);

    $('#ai-user-input').on('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // ── Seed welcome message ─────────────────────────────────────────────────
    $(function () {
        appendAgentBubble(
            'Hi! Tell me what position you\'d like to hire for — ' +
            'in any language or even just a description — and I\'ll automatically ' +
            'create a draft job posting using O*NET data.<br><br>' +
            '<span style="color:#64748b;font-size:12px;">e.g. "I want a Project Manager" or "I need someone to write code"</span>'
        );
    });

}(jQuery));

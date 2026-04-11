<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Meeting Notes</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        padding: 20px;
        font-family: Arial, sans-serif;
        background: #f5f7fb;
        color: #222;
    }

    .page-wrap {
        max-width: 1200px;
        margin: 0 auto;
    }

    .page-title {
        font-size: 26px;
        font-weight: 700;
        margin: 0 0 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .meeting-title-input {
        width: 100%;
        height: 40px;
        border: 1px solid #cfd6df;
        border-radius: 6px;
        padding: 8px 10px;
        margin-bottom: 18px;
        background: #fff;
    }

    .top-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px;
        margin-bottom: 18px;
    }

    .card {
        background: #fff;
        border: 1px solid #dde3ea;
        border-radius: 10px;
        padding: 14px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }

    .section-title {
        font-size: 20px;
        font-weight: 700;
        margin: 0 0 12px;
    }

    .notes-area,
    .transcript-box {
        width: 100%;
        border: 1px solid #cfd6df;
        border-radius: 6px;
        background: #fff;
        padding: 10px;
    }

    .notes-area {
        height: 255px;
        resize: vertical;
        font-family: Arial, sans-serif;
        font-size: 14px;
    }

    .transcript-box {
        height: 255px;
        overflow-y: auto;
        overflow-x: hidden;
        white-space: pre-wrap;
        line-height: 1.45;
        scrollbar-width: auto;
        scrollbar-color: #9ca3af #f1f5f9;
    }

    .transcript-box::-webkit-scrollbar,
    .summary-content-box::-webkit-scrollbar,
    .saved-notes-content::-webkit-scrollbar {
        width: 14px;
    }

    .transcript-box::-webkit-scrollbar-track,
    .summary-content-box::-webkit-scrollbar-track,
    .saved-notes-content::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-left: 1px solid #d1d5db;
        border-radius: 8px;
    }

    .transcript-box::-webkit-scrollbar-thumb,
    .summary-content-box::-webkit-scrollbar-thumb,
    .saved-notes-content::-webkit-scrollbar-thumb {
        background: #9ca3af;
        border-radius: 8px;
        border: 2px solid #f1f5f9;
    }

    .transcript-box::-webkit-scrollbar-thumb:hover,
    .summary-content-box::-webkit-scrollbar-thumb:hover,
    .saved-notes-content::-webkit-scrollbar-thumb:hover {
        background: #6b7280;
    }

    .status-line {
        margin: 10px 0 10px;
        font-size: 14px;
        color: #444;
    }

    .controls {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 8px;
    }

    button {
        border: none;
        border-radius: 6px;
        padding: 8px 14px;
        font-size: 13px;
        cursor: pointer;
        font-weight: 600;
    }

    .btn-start {
        background: #2563eb;
        color: #fff;
    }

    .btn-stop {
        background: #ea580c;
        color: #fff;
    }

    .btn-ai {
        background: #16a34a;
        color: #fff;
    }

    .btn-save {
        background: #475569;
        color: #fff;
    }

    .summary-wrapper {
        display: none;
        margin-top: 8px;
    }

    .summary-title {
        font-size: 22px;
        font-weight: 700;
        margin: 0 0 12px;
    }

    .summary-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px;
        align-items: start;
    }

    .mini-box-title {
        font-size: 18px;
        font-weight: 700;
        margin: 0 0 10px;
    }

    .summary-content-box {
        overflow-y: auto;
        overflow-x: hidden;
        border: 1px solid #cfd6df;
        border-radius: 6px;
        background: #fff;
        padding: 12px;
        line-height: 1.5;
        scrollbar-width: auto;
        scrollbar-color: #9ca3af #f1f5f9;
    }

    #dialogue_box {
        height: 260px;
    }

    #summary_box {
        height: 320px;
    }

    .summary-content-box ul {
        margin-top: 6px;
        padding-left: 18px;
    }

    .summary-save-row {
        margin-top: 12px;
    }

    .saved-notes-card {
        margin-top: 18px;
        background: #fff;
        border: 2px solid #d9dee6;
        border-radius: 10px;
        padding: 14px;
    }

    .saved-notes-title {
        font-size: 20px;
        font-weight: 700;
        margin: 0 0 10px;
    }

    .saved-notes-content {
        min-height: 120px;
        max-height: 420px;
        overflow-y: auto;
        border: 1px solid #cfd6df;
        border-radius: 6px;
        padding: 12px;
        background: #fafafa;
        line-height: 1.5;
        white-space: normal;
        scrollbar-width: auto;
        scrollbar-color: #9ca3af #f1f5f9;
    }

    .saved-notes-actions {
        margin-top: 12px;
        display: flex;
        justify-content: flex-end;
    }

    .muted {
        color: #6b7280;
        font-size: 14px;
    }

    .transcript-line {
        margin-bottom: 8px;
    }

    .saved-section {
        margin-bottom: 16px;
    }

    .saved-section:last-child {
        margin-bottom: 0;
    }

    .saved-section-title {
        font-size: 16px;
        font-weight: 700;
        margin-bottom: 6px;
    }

    .saved-section-body {
        padding: 8px 0 0;
    }

    @media (max-width: 900px) {
        .top-grid,
        .summary-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
</head>
<body>

<div class="page-wrap">
    <h2 class="page-title">📝 Meeting Notes</h2>

    <input
        id="note_title"
        class="meeting-title-input"
        type="text"
        value="Untitled Meeting"
        placeholder="Meeting title"
    >

    <div class="top-grid">
        <div class="card">
            <h3 class="section-title">Your Notes</h3>
            <textarea id="user_notes" class="notes-area" placeholder="Write your own notes here..."></textarea>

            <div class="controls">
                <button id="save_left" class="btn-save">Save Notes</button>
            </div>
        </div>

        <div class="card">
            <h3 class="section-title">Live Transcript</h3>

            <div id="live_box" class="transcript-box"></div>
            <input type="hidden" id="raw_transcript">

            <p class="status-line">
                Status: <span id="status">Idle</span> |
                Timer: <span id="timer">00:00</span>
            </p>

            <div class="controls">
                <button id="start" class="btn-start">Start</button>
                <button id="stop" class="btn-stop">Stop</button>
            </div>

            <div class="controls">
                <button id="summarize" class="btn-ai">AI Summarize</button>
            </div>
        </div>
    </div>

    <div id="summary_wrapper" class="card summary-wrapper">
        <h3 class="summary-title">AI Summary</h3>

        <div class="summary-grid">
            <div>
                <h4 class="mini-box-title">Dialogue</h4>
                <div id="dialogue_box" class="summary-content-box">
                    <span class="muted">Dialogue will appear here after AI summary.</span>
                </div>
            </div>

            <div>
                <h4 class="mini-box-title">Summary</h4>
                <div id="summary_box" class="summary-content-box">
                    <span class="muted">Summary will appear here after AI summary.</span>
                </div>

                <div class="summary-save-row">
                    <button id="save_summary" class="btn-save">Save Summary</button>
                </div>
            </div>
        </div>
    </div>

    <div class="saved-notes-card">
        <h3 class="saved-notes-title">Saved Notes</h3>

        <div id="saved_notes_preview" class="saved-notes-content">
            <span class="muted">No saved notes yet.</span>
        </div>

        <div class="saved-notes-actions">
            <button id="update_notes" class="btn-save">Update Notes</button>
        </div>
    </div>
</div>

<script>
const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

let recognition = null;
let lines = [];
let seconds = 0;
let timer = null;
let recording = false;

let savedUserNotesHtml = '';
let savedSummaryHtml = '';

const liveBox = document.getElementById('live_box');
const raw = document.getElementById('raw_transcript');
const statusEl = document.getElementById('status');
const timerEl = document.getElementById('timer');

const dialogueBox = document.getElementById('dialogue_box');
const summaryBox = document.getElementById('summary_box');
const summaryWrapper = document.getElementById('summary_wrapper');
const savedNotesPreview = document.getElementById('saved_notes_preview');

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function renderTranscript() {
    if (!lines.length) {
        liveBox.innerHTML = '';
        raw.value = '';
        return;
    }

    liveBox.innerHTML = lines
        .map(line => `<div class="transcript-line">${escapeHtml(line)}</div>`)
        .join('');

    raw.value = lines.join("\\n");
    liveBox.scrollTop = liveBox.scrollHeight;
}

function startTimer() {
    stopTimer();
    timer = setInterval(() => {
        seconds++;
        const m = String(Math.floor(seconds / 60)).padStart(2, '0');
        const s = String(seconds % 60).padStart(2, '0');
        timerEl.textContent = `${m}:${s}`;
    }, 1000);
}

function stopTimer() {
    if (timer) {
        clearInterval(timer);
        timer = null;
    }
}

function resetMeeting() {
    lines = [];
    seconds = 0;
    timerEl.textContent = '00:00';
    renderTranscript();
}

function extractDialogueAndSummaryFromHtml(html) {
    const temp = document.createElement('div');
    temp.innerHTML = html;

    let dialogueHtml = '';
    let summaryHtml = '';
    let mode = '';

    Array.from(temp.childNodes).forEach(node => {
        const text = (node.textContent || '').trim().toLowerCase();

        if (text === 'dialogue' || text.includes('dialogue')) {
            mode = 'dialogue';
            return;
        }

        if (text === 'summary' || text.includes('summary')) {
            mode = 'summary';
            return;
        }

        if (mode === 'dialogue') {
            dialogueHtml += node.outerHTML ? node.outerHTML : escapeHtml(node.textContent || '');
        } else if (mode === 'summary') {
            summaryHtml += node.outerHTML ? node.outerHTML : escapeHtml(node.textContent || '');
        }
    });

    return {
        dialogueHtml: dialogueHtml.trim(),
        summaryHtml: summaryHtml.trim()
    };
}

function renderSavedNotes() {
    let finalHtml = '';

    if (savedUserNotesHtml) {
        finalHtml += `
            <div class="saved-section">
                <div class="saved-section-title">Your Notes</div>
                <div class="saved-section-body">${savedUserNotesHtml}</div>
            </div>
        `;
    }

    if (savedSummaryHtml) {
        finalHtml += `
            <div class="saved-section">
                <div class="saved-section-title">Summary</div>
                <div class="saved-section-body">${savedSummaryHtml}</div>
            </div>
        `;
    }

    if (!finalHtml.trim()) {
        finalHtml = '<span class="muted">No saved notes yet.</span>';
    }

    savedNotesPreview.innerHTML = finalHtml;
}

async function saveNotesToBackend(contentToSave, saveType) {
    try {
        const fd = new FormData();
        fd.append('title', document.getElementById('note_title').value.trim());
        fd.append('raw_transcript', raw.value);
        fd.append('user_notes', document.getElementById('user_notes').value);
        fd.append('dialogue_html', dialogueBox.innerHTML);
        fd.append('summary_html', summaryBox.innerHTML);
        fd.append('ai_summary', summaryBox.innerHTML);
        fd.append('duration_seconds', seconds);
        fd.append('saved_content', contentToSave);
        fd.append('save_type', saveType);

        const res = await fetch('api/save_notes.php', {
            method: 'POST',
            body: fd
        });

        return await res.json();
    } catch (err) {
        return {
            success: false,
            error: 'Request failed'
        };
    }
}

if (SpeechRecognition) {
    recognition = new SpeechRecognition();
    recognition.continuous = true;
    recognition.interimResults = true;
    recognition.lang = 'en-US';

    recognition.onresult = (e) => {
        for (let i = e.resultIndex; i < e.results.length; i++) {
            if (e.results[i].isFinal) {
                const text = e.results[i][0].transcript.trim();
                if (text) {
                    lines.push(text);
                    renderTranscript();
                }
            }
        }
    };

    recognition.onstart = () => {
        statusEl.textContent = 'Recording...';
        startTimer();
    };

    recognition.onerror = (e) => {
        statusEl.textContent = 'Error: ' + e.error;
    };

    recognition.onend = () => {
        if (recording) {
            try {
                recognition.start();
            } catch (err) {}
        }
    };

    window.addEventListener('load', () => {
        try {
            recognition.start();
            recognition.stop();
        } catch (err) {}
    });
} else {
    statusEl.textContent = 'Speech recognition not supported in this browser.';
}

document.getElementById('start').onclick = () => {
    if (!recognition) {
        alert('Speech recognition is not supported in this browser.');
        return;
    }

    resetMeeting();
    recording = true;
    statusEl.textContent = 'Initializing...';

    setTimeout(() => {
        try {
            recognition.start();
            statusEl.textContent = 'Recording...';
        } catch (err) {
            statusEl.textContent = 'Error starting microphone';
        }
    }, 300);
};

document.getElementById('stop').onclick = () => {
    recording = false;

    if (recognition) {
        try {
            recognition.stop();
        } catch (err) {}
    }

    stopTimer();
    statusEl.textContent = 'Stopped';
};

document.getElementById('save_left').onclick = async () => {
    const userNotesValue = document.getElementById('user_notes').value.trim();

    if (!userNotesValue) {
        alert('Your Notes is empty.');
        return;
    }

    savedUserNotesHtml = `<div>${escapeHtml(userNotesValue).replace(/\\n/g, '<br>')}</div>`;
    renderSavedNotes();

    const combinedHtml = savedNotesPreview.innerHTML;
    const data = await saveNotesToBackend(combinedHtml, 'notes');

    if (data.success) {
        alert('Your notes saved.');
    } else {
        alert(data.error || 'Save failed');
    }
};

document.getElementById('save_summary').onclick = async () => {
    const summaryHtml = summaryBox.innerHTML.trim();

    if (!summaryHtml || summaryBox.innerText.includes('will appear here')) {
        alert('No summary to save yet.');
        return;
    }

    savedSummaryHtml = summaryHtml;
    renderSavedNotes();

    const combinedHtml = savedNotesPreview.innerHTML;
    const data = await saveNotesToBackend(combinedHtml, 'summary');

    if (data.success) {
        alert('Summary saved.');
    } else {
        alert(data.error || 'Save failed');
    }
};

document.getElementById('update_notes').onclick = async () => {
    const combinedHtml = savedNotesPreview.innerHTML.trim();

    if (!combinedHtml || savedNotesPreview.innerText.includes('No saved notes yet')) {
        alert('There is no saved note to update.');
        return;
    }

    const data = await saveNotesToBackend(combinedHtml, 'update');

    if (data.success) {
        alert('Saved notes updated.');
    } else {
        alert(data.error || 'Update failed');
    }
};

document.getElementById('summarize').onclick = async () => {
    try {
        const transcriptValue = raw.value.trim();
        const notesValue = document.getElementById('user_notes').value.trim();

        if (!transcriptValue && !notesValue) {
            alert('Please record transcript or write notes first.');
            return;
        }

        const fd = new FormData();
        fd.append('title', document.getElementById('note_title').value.trim());
        fd.append('raw_transcript', raw.value);
        fd.append('user_notes', document.getElementById('user_notes').value);

        const res = await fetch('api/ai_summarize_notes.php', {
            method: 'POST',
            body: fd
        });

        const data = await res.json();

        if (!data.success) {
            alert(data.error || 'AI summary failed.');
            return;
        }

        let dialogueHtml = '';
        let finalSummaryHtml = '';

        if (data.dialogue_html || data.summary_html_only) {
            dialogueHtml = data.dialogue_html || '';
            finalSummaryHtml = data.summary_html_only || '';
        } else if (data.summary_html) {
            const parsed = extractDialogueAndSummaryFromHtml(data.summary_html);
            dialogueHtml = parsed.dialogueHtml;
            finalSummaryHtml = parsed.summaryHtml;
        } else if (data.summary) {
            finalSummaryHtml = `<div>${escapeHtml(data.summary).replace(/\\n/g, '<br>')}</div>`;
        }

        dialogueBox.innerHTML = dialogueHtml || '<span class="muted">No dialogue section returned.</span>';
        summaryBox.innerHTML = finalSummaryHtml || '<span class="muted">No summary section returned.</span>';

        summaryWrapper.style.display = 'block';
    } catch (err) {
        alert('An error occurred while generating AI summary.');
    }
};

renderSavedNotes();
</script>

</body>
</html>
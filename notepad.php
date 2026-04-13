<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

$userId = (int)$_SESSION['user_id'];
$jobId  = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;

// Fetch ALL jobs of this user for the dropdown
$allJobs = [];
$jstmt = $conn->prepare("SELECT job_id, job_title FROM jobs WHERE user_id = ? AND deleted_at IS NULL ORDER BY created_at DESC");
$jstmt->bind_param("i", $userId);
$jstmt->execute();
$jres = $jstmt->get_result();
while ($row = $jres->fetch_assoc()) $allJobs[] = $row;
$jstmt->close();

// Fetch ALL applicants grouped by job_id (for JS dynamic update)
$allApplicants = [];
$astmt = $conn->prepare("
    SELECT a.application_id, a.job_id, a.full_name
    FROM applications a
    INNER JOIN jobs j ON j.job_id = a.job_id
    WHERE j.user_id = ? AND a.deleted_at IS NULL
    ORDER BY a.full_name
");
$astmt->bind_param("i", $userId);
$astmt->execute();
$ares = $astmt->get_result();
while ($row = $ares->fetch_assoc()) {
    $allApplicants[(int)$row['job_id']][] = [
        'application_id' => (int)$row['application_id'],
        'full_name'      => $row['full_name'],
    ];
}
$astmt->close();
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

    /* Meta card — always 2 columns */
    .meta-card {
        background: #fff;
        border: 1px solid #dde3ea;
        border-radius: 10px;
        padding: 16px 18px;
        margin-bottom: 18px;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }

    .meta-card label {
        font-size: 12px;
        font-weight: 700;
        color: #374151;
        display: block;
        margin-bottom: 5px;
    }

    .meta-card select,
    .meta-card input[type="text"] {
        width: 100%;
        height: 36px;
        border: 1px solid #cfd6df;
        border-radius: 6px;
        padding: 0 10px;
        font-size: 13px;
        background: #fff;
        color: #222;
    }

    .meta-card select:disabled {
        background: #f1f5f9;
        color: #94a3b8;
        cursor: not-allowed;
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

    .btn-start { background: #2563eb; color: #fff; }
    .btn-stop  { background: #ea580c; color: #fff; }
    .btn-ai    { background: #16a34a; color: #fff; }
    .btn-save  { background: #475569; color: #fff; }

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

    #dialogue_box { height: 260px; }
    #summary_box  { height: 320px; }

    .summary-content-box ul {
        margin-top: 6px;
        padding-left: 18px;
    }

    .summary-save-row { margin-top: 12px; }

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

    .muted { color: #6b7280; font-size: 14px; }

    .transcript-line { margin-bottom: 8px; }

    .saved-section          { margin-bottom: 16px; }
    .saved-section:last-child { margin-bottom: 0; }
    .saved-section-title    { font-size: 16px; font-weight: 700; margin-bottom: 6px; }
    .saved-section-body     { padding: 8px 0 0; }

    .live-interim { opacity: 0.65; font-style: italic; }

    /* recording pulse */
    .rec-dot {
        display: inline-block;
        width: 8px; height: 8px;
        border-radius: 50%;
        background: #ef4444;
        margin-right: 4px;
        animation: blink 1s infinite;
    }
    @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.2} }

    .back-link {
        display: inline-block;
        margin-bottom: 14px;
        font-size: 14px;
        color: #64748b;
        text-decoration: none;
    }
    .back-link:hover { text-decoration: underline; }

    @media (max-width: 900px) {
        .top-grid, .summary-grid, .meta-card { grid-template-columns: 1fr; }
    }
</style>
</head>
<body>

<div class="page-wrap">

    <?php if ($jobId > 0): ?>
        <a class="back-link" href="job_applications.php?job_id=<?= $jobId ?>">&larr; Back to Applications</a>
    <?php else: ?>
        <a class="back-link" href="dashboard_hr.php">&larr; Back to Dashboard</a>
    <?php endif; ?>

    <h2 class="page-title">📝 Meeting Notes</h2>

    <input
        id="note_title"
        class="meeting-title-input"
        type="text"
        value="Untitled Meeting"
        placeholder="Meeting title"
    >

    <!-- Meta: Job & Candidate -->
    <div class="meta-card">
        <div>
            <label for="meta_job_select">Related Job</label>
            <select id="meta_job_select" onchange="onJobChange(this.value)">
                <option value="">— Select a job —</option>
                <?php foreach ($allJobs as $j): ?>
                    <option value="<?= (int)$j['job_id'] ?>"
                        <?= ($jobId > 0 && (int)$j['job_id'] === $jobId) ? 'selected' : '' ?>>
                        <?= e($j['job_title'] ?: 'Job #'.$j['job_id']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" id="meta_job_id" value="<?= $jobId ?>">
        </div>
        <div>
            <label for="meta_candidate_select">Candidate Name</label>
            <select id="meta_candidate_select" <?= ($jobId <= 0) ? 'disabled' : '' ?>>
                <option value="">— Select candidate —</option>
                <?php if ($jobId > 0 && !empty($allApplicants[$jobId])): ?>
                    <?php foreach ($allApplicants[$jobId] as $ap): ?>
                        <option value="<?= (int)$ap['application_id'] ?>" data-name="<?= e($ap['full_name']) ?>">
                            <?= e($ap['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
    </div>

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

<!-- Pass PHP data to JS -->
<script>
const JOB_ID         = <?= (int)$jobId ?>;
const ALL_APPLICANTS = <?= json_encode($allApplicants, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>

<script>
// ── Job dropdown → update candidate list ──────────────────────────────────────
function onJobChange(jobId) {
    document.getElementById('meta_job_id').value = jobId;

    const sel  = document.getElementById('meta_candidate_select');
    sel.innerHTML = '<option value="">— Select candidate —</option>';

    const list = ALL_APPLICANTS[jobId] || [];
    if (!list.length) {
        sel.disabled = true;
        return;
    }
    list.forEach(ap => {
        const opt        = document.createElement('option');
        opt.value        = ap.application_id;
        opt.dataset.name = ap.full_name;
        opt.textContent  = ap.full_name;
        sel.appendChild(opt);
    });
    sel.disabled = false;
}

function getMetaFields() {
    const jobId     = document.getElementById('meta_job_id').value || 0;
    const candSel   = document.getElementById('meta_candidate_select');
    const selOpt    = candSel.options[candSel.selectedIndex];
    const applicationId  = candSel.value || 0;
    const candidateName  = (selOpt && selOpt.dataset.name) ? selOpt.dataset.name : '';
    return { jobId, applicationId, candidateName };
}

// ── Speech Recognition — fast & continuous ────────────────────────────────────
const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

let recognition    = null;
let lines          = [];
let interimText    = '';
let seconds        = 0;
let timerInterval  = null;
let recording      = false;
let restartTimeout = null;

let savedUserNotesHtml = '';
let savedSummaryHtml   = '';

const liveBox           = document.getElementById('live_box');
const raw               = document.getElementById('raw_transcript');
const statusEl          = document.getElementById('status');
const timerEl           = document.getElementById('timer');
const dialogueBox       = document.getElementById('dialogue_box');
const summaryBox        = document.getElementById('summary_box');
const summaryWrapper    = document.getElementById('summary_wrapper');
const savedNotesPreview = document.getElementById('saved_notes_preview');

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function renderTranscript() {
    let html = lines.map(l => `<div class="transcript-line">${escapeHtml(l)}</div>`).join('');
    if (interimText.trim()) {
        html += `<div class="transcript-line live-interim">${escapeHtml(interimText.trim())}</div>`;
    }
    liveBox.innerHTML = html;
    raw.value = lines.join('\n');
    liveBox.scrollTop = liveBox.scrollHeight;
}

function startTimer() {
    stopTimer();
    timerInterval = setInterval(() => {
        seconds++;
        timerEl.textContent =
            String(Math.floor(seconds / 60)).padStart(2, '0') + ':' +
            String(seconds % 60).padStart(2, '0');
    }, 1000);
}
function stopTimer() {
    if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
}

function resetMeeting() {
    lines = []; interimText = ''; seconds = 0;
    timerEl.textContent = '00:00';
    renderTranscript();
}

function buildRecognition() {
    if (!SpeechRecognition) return null;
    const r            = new SpeechRecognition();
    r.continuous       = true;
    r.interimResults   = true;   // words appear immediately as spoken
    r.lang             = 'en-US';
    r.maxAlternatives  = 1;

    r.onresult = (e) => {
        interimText = '';
        for (let i = e.resultIndex; i < e.results.length; i++) {
            const txt = e.results[i][0].transcript.trim();
            if (e.results[i].isFinal) { if (txt) lines.push(txt); }
            else interimText += txt + ' ';
        }
        renderTranscript();
    };

    r.onstart = () => {
        statusEl.innerHTML = '<span class="rec-dot"></span>Recording';
        if (!timerInterval) startTimer();
    };

    r.onerror = (e) => {
        // silent restart on non-fatal errors
        if (e.error === 'no-speech' || e.error === 'network' || e.error === 'aborted') {
            if (recording) scheduleRestart();
        } else {
            statusEl.textContent = 'Error: ' + e.error;
            recording = false;
        }
    };

    r.onend = () => {
        interimText = '';
        renderTranscript();
        if (recording) scheduleRestart();
    };

    return r;
}

function scheduleRestart() {
    if (restartTimeout) return;
    restartTimeout = setTimeout(() => {
        restartTimeout = null;
        if (!recording) return;
        recognition = buildRecognition();
        try { recognition.start(); } catch (e) {}
    }, 100); // 100 ms — fast enough, avoids browser "already started" errors
}

document.getElementById('start').onclick = () => {
    if (!SpeechRecognition) { alert('Speech recognition not supported in this browser.'); return; }
    if (recording) return;

    resetMeeting();
    recording   = true;
    statusEl.textContent = 'Starting…';

    // Pre-request mic permission so the first start is instant
    navigator.mediaDevices.getUserMedia({ audio: true })
        .then(() => {
            recognition = buildRecognition();
            try { recognition.start(); } catch (e) { statusEl.textContent = 'Error starting microphone'; }
        })
        .catch(() => {
            statusEl.textContent = 'Microphone access denied.';
            recording = false;
        });
};

document.getElementById('stop').onclick = () => {
    recording   = false;
    interimText = '';
    if (restartTimeout) { clearTimeout(restartTimeout); restartTimeout = null; }
    if (recognition)    { try { recognition.stop(); } catch (e) {} recognition = null; }
    renderTranscript();
    stopTimer();
    statusEl.textContent = 'Stopped';
};

// ── Render helpers ────────────────────────────────────────────────────────────
function extractDialogueAndSummaryFromHtml(html) {
    const temp = document.createElement('div');
    temp.innerHTML = html;
    let dHtml = '', sHtml = '', mode = '';
    Array.from(temp.childNodes).forEach(node => {
        const text = (node.textContent || '').trim().toLowerCase();
        if (text === 'dialogue' || text.includes('dialogue')) { mode = 'dialogue'; return; }
        if (text === 'summary'  || text.includes('summary'))  { mode = 'summary';  return; }
        if (mode === 'dialogue') dHtml += node.outerHTML ? node.outerHTML : escapeHtml(node.textContent || '');
        else if (mode === 'summary') sHtml += node.outerHTML ? node.outerHTML : escapeHtml(node.textContent || '');
    });
    return { dialogueHtml: dHtml.trim(), summaryHtml: sHtml.trim() };
}

function renderSavedNotes() {
    let finalHtml = '';
    if (savedUserNotesHtml) finalHtml += `<div class="saved-section"><div class="saved-section-title">Your Notes</div><div class="saved-section-body">${savedUserNotesHtml}</div></div>`;
    if (savedSummaryHtml)   finalHtml += `<div class="saved-section"><div class="saved-section-title">Summary</div><div class="saved-section-body">${savedSummaryHtml}</div></div>`;
    savedNotesPreview.innerHTML = finalHtml.trim() || '<span class="muted">No saved notes yet.</span>';
}

async function saveNotesToBackend(saveType) {
    const { jobId, applicationId, candidateName } = getMetaFields();
    try {
        const fd = new FormData();
        fd.append('job_id',           jobId);
        fd.append('application_id',   applicationId);
        fd.append('candidate_name',   candidateName);
        fd.append('title',            document.getElementById('note_title').value.trim());
        fd.append('raw_transcript',   raw.value);
        fd.append('user_notes',       document.getElementById('user_notes').value);
        fd.append('ai_summary',       summaryBox.innerHTML);
        fd.append('duration_seconds', seconds);
        const res = await fetch('api/save_notes.php', { method: 'POST', body: fd });
        return await res.json();
    } catch (err) {
        return { success: false, error: 'Request failed' };
    }
}

document.getElementById('save_left').onclick = async () => {
    const val = document.getElementById('user_notes').value.trim();
    if (!val) { alert('Your Notes is empty.'); return; }
    savedUserNotesHtml = `<div>${escapeHtml(val).replace(/\n/g, '<br>')}</div>`;
    renderSavedNotes();
    const data = await saveNotesToBackend('notes');
    alert(data.success ? 'Your notes saved.' : (data.error || 'Save failed'));
};

document.getElementById('save_summary').onclick = async () => {
    if (!summaryBox.innerHTML.trim() || summaryBox.innerText.includes('will appear here')) {
        alert('No summary to save yet.'); return;
    }
    savedSummaryHtml = summaryBox.innerHTML;
    renderSavedNotes();
    const data = await saveNotesToBackend('summary');
    alert(data.success ? 'Summary saved.' : (data.error || 'Save failed'));
};

document.getElementById('update_notes').onclick = async () => {
    if (savedNotesPreview.innerText.includes('No saved notes yet')) {
        alert('There is no saved note to update.'); return;
    }
    const data = await saveNotesToBackend('update');
    alert(data.success ? 'Saved notes updated.' : (data.error || 'Update failed'));
};

document.getElementById('summarize').onclick = async () => {
    try {
        if (!raw.value.trim() && !document.getElementById('user_notes').value.trim()) {
            alert('Please record transcript or write notes first.'); return;
        }
        const fd = new FormData();
        fd.append('title',          document.getElementById('note_title').value.trim());
        fd.append('raw_transcript', raw.value);
        fd.append('user_notes',     document.getElementById('user_notes').value);

        const res  = await fetch('api/ai_summarize_notes.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.success) { alert(data.error || 'AI summary failed.'); return; }

        let dHtml = '', sHtml = '';
        if (data.dialogue_html || data.summary_html_only) {
            dHtml = data.dialogue_html || ''; sHtml = data.summary_html_only || '';
        } else if (data.summary_html) {
            const p = extractDialogueAndSummaryFromHtml(data.summary_html);
            dHtml = p.dialogueHtml; sHtml = p.summaryHtml;
        } else if (data.summary) {
            sHtml = `<div>${escapeHtml(data.summary).replace(/\n/g, '<br>')}</div>`;
        }

        dialogueBox.innerHTML        = dHtml || '<span class="muted">No dialogue section returned.</span>';
        summaryBox.innerHTML         = sHtml  || '<span class="muted">No summary section returned.</span>';
        summaryWrapper.style.display = 'block';
    } catch (e) {
        alert('An error occurred while generating AI summary.');
    }
};

renderSavedNotes();
</script>

</body>
</html>
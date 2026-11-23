(() => {
    'use strict';

    if (window.pdfjsLib) {
        window.pdfjsLib.GlobalWorkerOptions.workerSrc =
            'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    }

    const state = {
        documents: [],
        currentDoc: null,
        activePageIndex: 0,
        zoom: 1.0,
        pdfDoc: null,
        pdfPageNum: 1,
        showingJson: false,
    };

    // DOM elements
    const el = {
        alertBox: document.getElementById('alert-box'),
        historyList: document.getElementById('history-list'),
        historyCount: document.getElementById('history-count'),
        btnNewUpload: document.getElementById('btn-new-upload'),

        uploadView: document.getElementById('upload-view'),
        uploadForm: document.getElementById('upload-form'),
        dropzone: document.getElementById('dropzone'),
        fileInput: document.getElementById('file-input'),
        selectedFilePill: document.getElementById('selected-file-pill'),
        modelSelect: document.getElementById('model-select'),
        customFieldsInput: document.getElementById('custom-fields-input'),
        btnSubmitUpload: document.getElementById('btn-submit-upload'),
        submitSpinner: document.getElementById('submit-spinner'),
        submitLabel: document.getElementById('submit-label'),

        reviewView: document.getElementById('review-view'),
        docTitle: document.getElementById('doc-title'),
        docStatusBadge: document.getElementById('doc-status-badge'),
        docMeta: document.getElementById('doc-meta'),
        btnSaveDoc: document.getElementById('btn-save-doc'),
        btnDownloadTxt: document.getElementById('btn-download-txt'),
        btnDownloadJson: document.getElementById('btn-download-json'),
        btnDeleteDoc: document.getElementById('btn-delete-doc'),

        previewImage: document.getElementById('preview-image'),
        previewPdfCanvas: document.getElementById('preview-pdf-canvas'),
        pdfNav: document.getElementById('pdf-nav'),
        btnPdfPrev: document.getElementById('btn-pdf-prev'),
        btnPdfNext: document.getElementById('btn-pdf-next'),
        pdfPageLabel: document.getElementById('pdf-page-label'),
        btnZoomIn: document.getElementById('btn-zoom-in'),
        btnZoomOut: document.getElementById('btn-zoom-out'),
        zoomLabel: document.getElementById('zoom-label'),

        pageTabs: document.getElementById('page-tabs'),
        btnToggleJson: document.getElementById('btn-toggle-json'),
        editorPanel: document.getElementById('editor-panel'),
        jsonPanel: document.getElementById('json-panel'),
        jsonPreview: document.getElementById('json-preview'),
        pageTextarea: document.getElementById('page-textarea'),
        btnResetRaw: document.getElementById('btn-reset-raw'),
        uncertainBox: document.getElementById('uncertain-box'),
        uncertainList: document.getElementById('uncertain-list'),
        notesBox: document.getElementById('notes-box'),
        pageNotes: document.getElementById('page-notes'),
        fieldsContainer: document.getElementById('fields-container'),
        btnAddField: document.getElementById('btn-add-field'),

        btnOpenSettings: document.getElementById('btn-open-settings'),
        btnBannerSettings: document.getElementById('btn-banner-settings'),
        apiStatusDot: document.getElementById('api-status-dot'),
        apiStatusText: document.getElementById('api-status-text'),
        setupBanner: document.getElementById('setup-banner'),
        settingsModal: document.getElementById('settings-modal'),
        settingsForm: document.getElementById('settings-form'),
        settingsCurrentStatus: document.getElementById('settings-current-status'),
        settingsApiKey: document.getElementById('settings-api-key'),
        btnToggleKeyVis: document.getElementById('btn-toggle-key-vis'),
        settingsDefaultModel: document.getElementById('settings-default-model'),
        btnClearApiKey: document.getElementById('btn-clear-api-key'),
        btnCloseSettings: document.getElementById('btn-close-settings'),
        btnCancelSettings: document.getElementById('btn-cancel-settings'),
        btnSaveSettings: document.getElementById('btn-save-settings'),
    };

    function showAlert(message, type = 'info') {
        const styles = {
            error: 'bg-rose-500/10 border-rose-500/30 text-rose-200',
            success: 'bg-emerald-500/10 border-emerald-500/30 text-emerald-200',
            info: 'bg-indigo-500/10 border-indigo-500/30 text-indigo-200',
        };
        el.alertBox.className = `mb-4 px-4 py-3 rounded-lg text-sm border ${styles[type] || styles.info}`;
        el.alertBox.textContent = message;
        el.alertBox.classList.remove('hidden');
    }

    function hideAlert() {
        el.alertBox.classList.add('hidden');
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function showUploadView() {
        hideAlert();
        state.currentDoc = null;
        el.reviewView.classList.add('hidden');
        el.uploadView.classList.remove('hidden');
        renderHistory();
    }

    function showReviewView() {
        el.uploadView.classList.add('hidden');
        el.reviewView.classList.remove('hidden');
    }

    // --- History ---
    async function loadHistory() {
        try {
            const res = await fetch('api/documents.php');
            const data = await res.json();
            if (!res.ok || !data.ok) {
                throw new Error(data.error || 'Failed to load document history.');
            }
            state.documents = data.documents || [];
            renderHistory();
        } catch (err) {
            showAlert(err.message, 'error');
        }
    }

    function renderHistory() {
        el.historyCount.textContent = String(state.documents.length);
        if (state.documents.length === 0) {
            el.historyList.innerHTML =
                '<div class="p-6 text-center text-sm text-slate-500">No processed documents yet.</div>';
            return;
        }

        el.historyList.innerHTML = state.documents
            .map((doc) => {
                const isActive = state.currentDoc && state.currentDoc.id === doc.id;
                const badgeClass =
                    doc.status === 'reviewed'
                        ? 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30'
                        : 'bg-indigo-500/15 text-indigo-300 border-indigo-500/30';
                const dateStr = doc.uploaded_at ? new Date(doc.uploaded_at).toLocaleString() : '';
                return `
                    <button type="button" data-doc-id="${escapeHtml(doc.id)}"
                        class="w-full text-left p-3.5 hover:bg-slate-800/70 transition ${isActive ? 'bg-slate-800/90 border-l-2 border-indigo-400' : ''}">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-medium text-slate-100 truncate">${escapeHtml(doc.original_filename)}</span>
                            <span class="text-[10px] px-1.5 py-0.5 rounded border shrink-0 ${badgeClass}">${escapeHtml(doc.status)}</span>
                        </div>
                        <p class="text-xs text-slate-400 truncate mt-1">${escapeHtml(doc.preview || '(No text)')}</p>
                        <div class="flex items-center justify-between text-[11px] text-slate-500 mt-1.5">
                            <span>${doc.page_count} page${doc.page_count === 1 ? '' : 's'}</span>
                            <span>${escapeHtml(dateStr)}</span>
                        </div>
                    </button>
                `;
            })
            .join('');

        el.historyList.querySelectorAll('button[data-doc-id]').forEach((btn) => {
            btn.addEventListener('click', () => openDocument(btn.dataset.docId));
        });
    }

    async function openDocument(id) {
        hideAlert();
        try {
            const res = await fetch(`api/documents.php?id=${encodeURIComponent(id)}`);
            const data = await res.json();
            if (!res.ok || !data.ok) {
                throw new Error(data.error || 'Could not load document.');
            }
            state.currentDoc = data.document;
            state.activePageIndex = 0;
            state.zoom = 1.0;
            state.showingJson = false;
            renderCurrentDocument();
            renderHistory();
            showReviewView();
        } catch (err) {
            showAlert(err.message, 'error');
        }
    }

    // --- Upload Handling ---
    function setSelectedFile(file) {
        if (!file) {
            el.selectedFilePill.classList.add('hidden');
            el.btnSubmitUpload.disabled = true;
            return;
        }
        const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
        el.selectedFilePill.textContent = `${file.name} (${sizeMb} MB)`;
        el.selectedFilePill.classList.remove('hidden');
        el.btnSubmitUpload.disabled = false;
    }

    el.dropzone.addEventListener('click', () => el.fileInput.click());
    el.fileInput.addEventListener('change', () => setSelectedFile(el.fileInput.files[0]));

    ['dragenter', 'dragover'].forEach((evt) => {
        el.dropzone.addEventListener(evt, (e) => {
            e.preventDefault();
            el.dropzone.classList.add('drag-active');
        });
    });
    ['dragleave', 'drop'].forEach((evt) => {
        el.dropzone.addEventListener(evt, (e) => {
            e.preventDefault();
            el.dropzone.classList.remove('drag-active');
        });
    });
    el.dropzone.addEventListener('drop', (e) => {
        const file = e.dataTransfer?.files?.[0];
        if (file) {
            const dt = new DataTransfer();
            dt.items.add(file);
            el.fileInput.files = dt.files;
            setSelectedFile(file);
        }
    });

    el.uploadForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideAlert();
        const file = el.fileInput.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('file', file);
        formData.append('model', el.modelSelect.value);
        formData.append('custom_fields', el.customFieldsInput.value);

        el.btnSubmitUpload.disabled = true;
        el.submitSpinner.classList.remove('hidden');
        el.submitLabel.textContent = 'Reading cursive handwriting...';

        try {
            const res = await fetch('api/upload.php', {
                method: 'POST',
                body: formData,
            });
            const data = await res.json();
            if (!res.ok || !data.ok) {
                throw new Error(data.error || 'Transcription failed.');
            }

            el.uploadForm.reset();
            setSelectedFile(null);

            state.currentDoc = data.document;
            state.activePageIndex = 0;
            state.zoom = 1.0;
            state.showingJson = false;

            await loadHistory();
            renderCurrentDocument();
            showReviewView();
            showAlert('Cursive handwriting transcribed and saved to JSON & TXT!', 'success');
        } catch (err) {
            showAlert(err.message, 'error');
        } finally {
            el.submitSpinner.classList.add('hidden');
            el.submitLabel.textContent = 'Transcribe Cursive';
            el.btnSubmitUpload.disabled = !el.fileInput.files[0];
        }
    });

    // --- Review Workspace Rendering ---
    function renderCurrentDocument() {
        const doc = state.currentDoc;
        if (!doc) return;

        el.docTitle.textContent = doc.original_filename || doc.id;
        el.docStatusBadge.textContent = doc.status || 'transcribed';
        el.docStatusBadge.className =
            doc.status === 'reviewed'
                ? 'text-xs px-2 py-0.5 rounded-full border bg-emerald-500/15 border-emerald-500/30 text-emerald-300'
                : 'text-xs px-2 py-0.5 rounded-full border bg-indigo-500/15 border-indigo-500/30 text-indigo-300';

        const uploadedAt = doc.uploaded_at ? new Date(doc.uploaded_at).toLocaleString() : '';
        el.docMeta.textContent = `Model: ${doc.provider || 'unknown'} • ${doc.pages.length} page(s) • Uploaded ${uploadedAt}`;

        el.btnDownloadTxt.href = `api/export.php?id=${encodeURIComponent(doc.id)}&format=txt`;
        el.btnDownloadJson.href = `api/export.php?id=${encodeURIComponent(doc.id)}&format=json`;

        updateJsonPanelVisibility();
        renderPageTabs();
        renderActivePage();
        renderExtractedFields();
        loadOriginalPreview(doc);
    }

    function renderPageTabs() {
        const pages = state.currentDoc?.pages || [];
        el.pageTabs.innerHTML = pages
            .map((p, idx) => {
                const active = idx === state.activePageIndex;
                return `
                    <button type="button" data-page-idx="${idx}"
                        class="text-xs px-3 py-1.5 rounded-lg font-medium transition shrink-0 ${
                            active
                                ? 'bg-indigo-600 text-white'
                                : 'bg-slate-800 text-slate-300 hover:bg-slate-700'
                        }">
                        Page ${p.page_number || idx + 1}
                    </button>
                `;
            })
            .join('');

        el.pageTabs.querySelectorAll('button[data-page-idx]').forEach((btn) => {
            btn.addEventListener('click', () => {
                syncCurrentPageTextToState();
                state.activePageIndex = Number(btn.dataset.pageIdx);
                renderPageTabs();
                renderActivePage();
                if (state.pdfDoc) {
                    const targetPdfPage = Math.min(state.activePageIndex + 1, state.pdfDoc.numPages);
                    renderPdfPage(targetPdfPage);
                }
            });
        });
    }

    function renderActivePage() {
        const page = state.currentDoc?.pages?.[state.activePageIndex];
        if (!page) return;

        el.pageTextarea.value = page.edited_text ?? page.raw_text ?? '';

        const uncertain = Array.isArray(page.uncertain_words) ? page.uncertain_words : [];
        if (uncertain.length > 0) {
            el.uncertainBox.classList.remove('hidden');
            el.uncertainList.innerHTML = uncertain
                .map(
                    (word) =>
                        `<span class="text-xs px-2 py-0.5 rounded bg-amber-500/20 border border-amber-500/30 text-amber-200 font-mono">${escapeHtml(word)}</span>`
                )
                .join('');
        } else {
            el.uncertainBox.classList.add('hidden');
        }

        if (page.notes && page.notes.trim() !== '') {
            el.notesBox.classList.remove('hidden');
            el.pageNotes.textContent = page.notes;
        } else {
            el.notesBox.classList.add('hidden');
        }

        updateJsonPreviewText();
    }

    function syncCurrentPageTextToState() {
        const page = state.currentDoc?.pages?.[state.activePageIndex];
        if (page) {
            page.edited_text = el.pageTextarea.value;
        }
    }

    el.pageTextarea.addEventListener('input', () => {
        syncCurrentPageTextToState();
        updateJsonPreviewText();
    });

    el.btnResetRaw.addEventListener('click', () => {
        const page = state.currentDoc?.pages?.[state.activePageIndex];
        if (!page) return;
        page.edited_text = page.raw_text || '';
        el.pageTextarea.value = page.edited_text;
        updateJsonPreviewText();
    });

    // --- Extracted Fields Editor ---
    function renderExtractedFields() {
        const fields = state.currentDoc?.extracted_fields || {};
        const entries = Object.entries(fields);

        if (entries.length === 0) {
            el.fieldsContainer.innerHTML =
                '<p class="text-xs text-slate-500 italic">No structured fields extracted. Click "+ Add Field" to add custom columns for Excel export.</p>';
            return;
        }

        el.fieldsContainer.innerHTML = entries
            .map(
                ([k, v]) => `
                <div class="field-row flex items-center gap-2">
                    <input type="text" value="${escapeHtml(k)}" placeholder="Field Name"
                           class="field-key w-1/3 rounded-lg bg-slate-950 border border-slate-700 px-2.5 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-indigo-500">
                    <input type="text" value="${escapeHtml(v)}" placeholder="Value"
                           class="field-val flex-1 rounded-lg bg-slate-950 border border-slate-700 px-2.5 py-1.5 text-xs text-slate-100 focus:outline-none focus:border-indigo-500">
                    <button type="button" class="btn-remove-field px-2 py-1.5 rounded-lg bg-slate-800 hover:bg-rose-500/20 text-slate-400 hover:text-rose-300 text-xs">&times;</button>
                </div>
            `
            )
            .join('');

        bindFieldRowEvents();
    }

    function bindFieldRowEvents() {
        el.fieldsContainer.querySelectorAll('.field-row').forEach((row) => {
            row.querySelectorAll('input').forEach((inp) => {
                inp.addEventListener('input', syncFieldsFromDom);
            });
            row.querySelector('.btn-remove-field').addEventListener('click', () => {
                row.remove();
                syncFieldsFromDom();
                if (el.fieldsContainer.querySelectorAll('.field-row').length === 0) {
                    renderExtractedFields();
                }
            });
        });
    }

    function syncFieldsFromDom() {
        if (!state.currentDoc) return;
        const map = {};
        el.fieldsContainer.querySelectorAll('.field-row').forEach((row) => {
            const k = row.querySelector('.field-key').value.trim();
            const v = row.querySelector('.field-val').value.trim();
            if (k !== '') {
                map[k] = v;
            }
        });
        state.currentDoc.extracted_fields = map;
        updateJsonPreviewText();
    }

    el.btnAddField.addEventListener('click', () => {
        if (el.fieldsContainer.querySelectorAll('.field-row').length === 0) {
            el.fieldsContainer.innerHTML = '';
        }
        const row = document.createElement('div');
        row.className = 'field-row flex items-center gap-2';
        row.innerHTML = `
            <input type="text" placeholder="Field Name (e.g. Date)"
                   class="field-key w-1/3 rounded-lg bg-slate-950 border border-slate-700 px-2.5 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-indigo-500">
            <input type="text" placeholder="Value"
                   class="field-val flex-1 rounded-lg bg-slate-950 border border-slate-700 px-2.5 py-1.5 text-xs text-slate-100 focus:outline-none focus:border-indigo-500">
            <button type="button" class="btn-remove-field px-2 py-1.5 rounded-lg bg-slate-800 hover:bg-rose-500/20 text-slate-400 hover:text-rose-300 text-xs">&times;</button>
        `;
        el.fieldsContainer.appendChild(row);
        bindFieldRowEvents();
        row.querySelector('.field-key').focus();
    });

    // --- JSON Toggle ---
    function updateJsonPreviewText() {
        if (state.currentDoc) {
            el.jsonPreview.textContent = JSON.stringify(state.currentDoc, null, 2);
        }
    }

    function updateJsonPanelVisibility() {
        if (state.showingJson) {
            el.editorPanel.classList.add('hidden');
            el.jsonPanel.classList.remove('hidden');
            el.btnToggleJson.textContent = 'Back to Editor';
            updateJsonPreviewText();
        } else {
            el.jsonPanel.classList.add('hidden');
            el.editorPanel.classList.remove('hidden');
            el.btnToggleJson.textContent = 'View Raw JSON';
        }
    }

    el.btnToggleJson.addEventListener('click', () => {
        state.showingJson = !state.showingJson;
        updateJsonPanelVisibility();
    });

    // --- Original Image / PDF Viewer ---
    async function loadOriginalPreview(doc) {
        const fileUrl = `api/file.php?id=${encodeURIComponent(doc.id)}`;
        state.pdfDoc = null;
        state.zoom = 1.0;
        el.zoomLabel.textContent = '100%';

        if (doc.mime_type === 'application/pdf' && window.pdfjsLib) {
            el.previewImage.classList.add('hidden');
            el.previewPdfCanvas.classList.remove('hidden');
            el.pdfNav.classList.remove('hidden');
            el.pdfNav.classList.add('flex');

            try {
                state.pdfDoc = await window.pdfjsLib.getDocument(fileUrl).promise;
                await renderPdfPage(1);
            } catch (err) {
                showAlert('Could not render PDF preview: ' + err.message, 'error');
            }
        } else {
            el.previewPdfCanvas.classList.add('hidden');
            el.pdfNav.classList.add('hidden');
            el.pdfNav.classList.remove('flex');
            el.previewImage.src = fileUrl;
            el.previewImage.style.transform = 'scale(1)';
            el.previewImage.classList.remove('hidden');
        }
    }

    async function renderPdfPage(pageNum) {
        if (!state.pdfDoc) return;
        state.pdfPageNum = Math.max(1, Math.min(pageNum, state.pdfDoc.numPages));
        el.pdfPageLabel.textContent = `${state.pdfPageNum} / ${state.pdfDoc.numPages}`;

        const page = await state.pdfDoc.getPage(state.pdfPageNum);
        const viewport = page.getViewport({ scale: 1.3 * state.zoom });
        const canvas = el.previewPdfCanvas;
        const ctx = canvas.getContext('2d');
        canvas.height = viewport.height;
        canvas.width = viewport.width;

        await page.render({ canvasContext: ctx, viewport }).promise;
    }

    el.btnPdfPrev.addEventListener('click', () => {
        if (state.pdfDoc && state.pdfPageNum > 1) {
            renderPdfPage(state.pdfPageNum - 1);
        }
    });

    el.btnPdfNext.addEventListener('click', () => {
        if (state.pdfDoc && state.pdfPageNum < state.pdfDoc.numPages) {
            renderPdfPage(state.pdfPageNum + 1);
        }
    });

    function applyZoom(delta) {
        state.zoom = Math.max(0.5, Math.min(2.5, Number((state.zoom + delta).toFixed(2))));
        el.zoomLabel.textContent = `${Math.round(state.zoom * 100)}%`;
        if (state.pdfDoc) {
            renderPdfPage(state.pdfPageNum);
        } else {
            el.previewImage.style.transform = `scale(${state.zoom})`;
        }
    }

    el.btnZoomIn.addEventListener('click', () => applyZoom(0.25));
    el.btnZoomOut.addEventListener('click', () => applyZoom(-0.25));

    // --- Save & Delete ---
    el.btnSaveDoc.addEventListener('click', async () => {
        if (!state.currentDoc) return;
        syncCurrentPageTextToState();
        syncFieldsFromDom();

        el.btnSaveDoc.disabled = true;
        el.btnSaveDoc.textContent = 'Saving...';

        try {
            const res = await fetch('api/documents.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: state.currentDoc.id,
                    pages: state.currentDoc.pages,
                    extracted_fields: state.currentDoc.extracted_fields,
                    status: 'reviewed',
                }),
            });
            const data = await res.json();
            if (!res.ok || !data.ok) {
                throw new Error(data.error || 'Failed to save changes.');
            }
            state.currentDoc = data.document;
            renderCurrentDocument();
            await loadHistory();
            showAlert('Changes saved to JSON and TXT files!', 'success');
        } catch (err) {
            showAlert(err.message, 'error');
        } finally {
            el.btnSaveDoc.disabled = false;
            el.btnSaveDoc.textContent = 'Save Changes';
        }
    });

    el.btnDeleteDoc.addEventListener('click', async () => {
        if (!state.currentDoc) return;
        if (!confirm(`Delete "${state.currentDoc.original_filename}" and its saved JSON/TXT files?`)) {
            return;
        }
        try {
            const res = await fetch(`api/documents.php?id=${encodeURIComponent(state.currentDoc.id)}`, {
                method: 'DELETE',
            });
            const data = await res.json();
            if (!res.ok || !data.ok) {
                throw new Error(data.error || 'Failed to delete document.');
            }
            await loadHistory();
            showUploadView();
            showAlert('Document deleted.', 'info');
        } catch (err) {
            showAlert(err.message, 'error');
        }
    });

    // --- Settings Modal ---
    function applyApiStatusUi(settings) {
        const ready = Boolean(settings.has_api_key);
        if (ready) {
            el.btnOpenSettings.className =
                'inline-flex items-center gap-1.5 text-xs px-3 py-2 rounded-lg border transition bg-emerald-500/10 border-emerald-500/30 text-emerald-300 hover:bg-emerald-500/20';
            el.apiStatusDot.className = 'w-2 h-2 rounded-full bg-emerald-400';
            el.apiStatusText.textContent = 'API Settings';
            el.setupBanner.classList.add('hidden');
        } else {
            el.btnOpenSettings.className =
                'inline-flex items-center gap-1.5 text-xs px-3 py-2 rounded-lg border transition bg-amber-500/10 border-amber-500/30 text-amber-300 hover:bg-amber-500/20';
            el.apiStatusDot.className = 'w-2 h-2 rounded-full bg-amber-400';
            el.apiStatusText.textContent = 'Set API Key';
            el.setupBanner.classList.remove('hidden');
        }

        if (settings.default_model && el.modelSelect) {
            el.modelSelect.value = settings.default_model;
        }
    }

    async function openSettingsModal() {
        el.settingsApiKey.value = '';
        el.settingsApiKey.type = 'password';
        el.btnToggleKeyVis.textContent = 'Show';
        el.settingsCurrentStatus.textContent = 'Loading current settings...';
        el.settingsModal.classList.remove('hidden');

        try {
            const res = await fetch('api/settings.php');
            const data = await res.json();
            if (!res.ok || !data.ok) {
                throw new Error(data.error || 'Could not load settings.');
            }
            const s = data.settings;
            el.settingsDefaultModel.value = s.default_model || 'gemini-3.8-flash';

            if (s.has_api_key) {
                const sourceLabel = s.source === 'ui' ? 'saved in UI settings' : 'loaded from .env';
                el.settingsCurrentStatus.textContent = `Active key: ${s.masked_api_key} (${sourceLabel})`;
            } else {
                el.settingsCurrentStatus.textContent = 'No API key configured yet.';
            }

            if (s.source === 'ui') {
                el.btnClearApiKey.classList.remove('hidden');
            } else {
                el.btnClearApiKey.classList.add('hidden');
            }
        } catch (err) {
            el.settingsCurrentStatus.textContent = err.message;
        }
    }

    function closeSettingsModal() {
        el.settingsModal.classList.add('hidden');
    }

    el.btnOpenSettings.addEventListener('click', openSettingsModal);
    if (el.btnBannerSettings) {
        el.btnBannerSettings.addEventListener('click', openSettingsModal);
    }
    el.btnCloseSettings.addEventListener('click', closeSettingsModal);
    el.btnCancelSettings.addEventListener('click', closeSettingsModal);
    el.settingsModal.addEventListener('click', (e) => {
        if (e.target === el.settingsModal) closeSettingsModal();
    });

    el.btnToggleKeyVis.addEventListener('click', () => {
        const isPass = el.settingsApiKey.type === 'password';
        el.settingsApiKey.type = isPass ? 'text' : 'password';
        el.btnToggleKeyVis.textContent = isPass ? 'Hide' : 'Show';
    });

    el.settingsForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        el.btnSaveSettings.disabled = true;
        el.btnSaveSettings.textContent = 'Saving...';

        try {
            const payload = {
                default_model: el.settingsDefaultModel.value,
            };
            const enteredKey = el.settingsApiKey.value.trim();
            if (enteredKey !== '') {
                payload.gemini_api_key = enteredKey;
            }

            const res = await fetch('api/settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (!res.ok || !data.ok) {
                throw new Error(data.error || 'Failed to save settings.');
            }

            applyApiStatusUi(data.settings);
            closeSettingsModal();
            showAlert('Gemini API settings saved!', 'success');
        } catch (err) {
            showAlert(err.message, 'error');
        } finally {
            el.btnSaveSettings.disabled = false;
            el.btnSaveSettings.textContent = 'Save Settings';
        }
    });

    el.btnClearApiKey.addEventListener('click', async () => {
        if (!confirm('Remove the Gemini API key saved in UI settings?')) return;
        try {
            const res = await fetch('api/settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ clear_api_key: true }),
            });
            const data = await res.json();
            if (!res.ok || !data.ok) {
                throw new Error(data.error || 'Failed to clear API key.');
            }
            applyApiStatusUi(data.settings);
            closeSettingsModal();
            showAlert('Saved UI API key removed.', 'info');
        } catch (err) {
            showAlert(err.message, 'error');
        }
    });

    el.btnNewUpload.addEventListener('click', showUploadView);

    // Init
    loadHistory();
})();

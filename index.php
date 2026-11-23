<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use CursiveTool\Bootstrap;
use CursiveTool\Storage\SettingsStore;

Bootstrap::init();
$settingsStore = new SettingsStore(Bootstrap::rootDir());
$apiKeyConfigured = $settingsStore->isApiKeyConfigured();
$defaultModel = $settingsStore->getDefaultModel();
$maxUploadMb = Bootstrap::env('MAX_UPLOAD_MB', '20');
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cursive Tool — Handwriting OCR to JSON &amp; TXT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col">

    <!-- Top Navigation -->
    <header class="border-b border-slate-800 bg-slate-900/90 backdrop-blur sticky top-0 z-20">
        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-indigo-500/20 border border-indigo-400/30 flex items-center justify-center text-indigo-300 font-serif text-xl italic">
                    C
                </div>
                <div>
                    <h1 class="text-base font-semibold tracking-tight text-white">Cursive Tool</h1>
                    <p class="text-xs text-slate-400">Cursive Handwriting OCR &rarr; JSON &amp; TXT (Excel-Ready)</p>
                </div>
            </div>

            <div class="flex items-center gap-2.5">
                <button id="btn-open-settings" type="button" class="inline-flex items-center gap-1.5 text-xs px-3 py-2 rounded-lg border transition <?= $apiKeyConfigured ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300 hover:bg-emerald-500/20' : 'bg-amber-500/10 border-amber-500/30 text-amber-300 hover:bg-amber-500/20' ?>">
                    <span id="api-status-dot" class="w-2 h-2 rounded-full <?= $apiKeyConfigured ? 'bg-emerald-400' : 'bg-amber-400' ?>"></span>
                    <span id="api-status-text"><?= $apiKeyConfigured ? 'API Settings' : 'Set API Key' ?></span>
                </button>
                <button id="btn-new-upload" type="button" class="px-3.5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-sm font-medium text-white transition">
                    + New Upload
                </button>
            </div>
        </div>
    </header>

    <div id="setup-banner" class="<?= $apiKeyConfigured ? 'hidden ' : '' ?>bg-amber-500/10 border-b border-amber-500/30 text-amber-200 px-4 py-2.5 text-sm">
        <div class="max-w-[1600px] mx-auto flex flex-wrap items-center justify-between gap-2">
            <span>
                <strong>Setup required:</strong> Configure your Google Gemini API key to start transcribing cursive handwriting.
            </span>
            <button id="btn-banner-settings" type="button" class="px-3 py-1 rounded-md bg-amber-500/20 hover:bg-amber-500/30 border border-amber-400/40 text-amber-200 text-xs font-semibold transition">
                Configure Gemini API Key &rarr;
            </button>
        </div>
    </div>

    <!-- Main Layout -->
    <div class="flex-1 flex flex-col lg:flex-row max-w-[1600px] w-full mx-auto">

        <!-- Left Sidebar: Document History -->
        <aside class="w-full lg:w-72 xl:w-80 border-b lg:border-b-0 lg:border-r border-slate-800 bg-slate-900/50 flex flex-col">
            <div class="p-4 border-b border-slate-800 flex items-center justify-between">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Saved Documents</h2>
                <span id="history-count" class="text-xs text-slate-500">0</span>
            </div>
            <div id="history-list" class="flex-1 overflow-y-auto divide-y divide-slate-800/70 max-h-64 lg:max-h-[calc(100vh-8rem)]">
                <div class="p-6 text-center text-sm text-slate-500">
                    No processed documents yet.
                </div>
            </div>
        </aside>

        <!-- Main Workspace -->
        <main class="flex-1 p-4 sm:p-6 overflow-y-auto">

            <!-- Toast / Alert Banner -->
            <div id="alert-box" class="hidden mb-4 px-4 py-3 rounded-lg text-sm border"></div>

            <!-- VIEW 1: Upload View -->
            <section id="upload-view" class="max-w-3xl mx-auto">
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-xl">
                    <h2 class="text-xl font-semibold text-white mb-1">Upload Cursive Document</h2>
                    <p class="text-sm text-slate-400 mb-6">
                        Upload a photo, scan, or multi-page PDF of cursive handwriting. The AI will transcribe it into editable text and Excel-ready JSON.
                    </p>

                    <form id="upload-form" class="space-y-5">
                        <!-- Dropzone -->
                        <div id="dropzone" class="border-2 border-dashed border-slate-700 hover:border-indigo-400 rounded-xl p-8 text-center cursor-pointer transition bg-slate-950/60">
                            <input id="file-input" type="file" name="file" accept=".pdf,.png,.jpg,.jpeg,.webp,application/pdf,image/png,image/jpeg,image/webp" class="hidden">
                            <div class="mx-auto w-12 h-12 rounded-full bg-indigo-500/15 text-indigo-300 flex items-center justify-center mb-3">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-slate-200">
                                Drag &amp; drop a PDF or image here, or <span class="text-indigo-400 underline">browse</span>
                            </p>
                            <p class="text-xs text-slate-500 mt-1">
                                Supports PDF, PNG, JPG, WEBP &bull; Max <?= htmlspecialchars((string) $maxUploadMb, ENT_QUOTES) ?> MB
                            </p>
                            <div id="selected-file-pill" class="hidden mt-4 inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-indigo-500/15 border border-indigo-500/30 text-indigo-200 text-xs font-medium"></div>
                        </div>

                        <!-- Options Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="model-select" class="block text-xs font-medium text-slate-300 mb-1.5">AI Model</label>
                                <select id="model-select" name="model" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                                    <option value="gemini-3.8-flash" <?= $defaultModel === 'gemini-3.8-flash' ? 'selected' : '' ?>>Gemini 3.8 Flash (Auto-Fallback Enabled)</option>
                                    <option value="gemini-3.7-flash" <?= $defaultModel === 'gemini-3.7-flash' ? 'selected' : '' ?>>Gemini 3.7 Flash (Lower Traffic)</option>
                                    <option value="gemini-3.5-flash" <?= $defaultModel === 'gemini-3.5-flash' ? 'selected' : '' ?>>Gemini 3.5 Flash (Stable)</option>
                                    <option value="gemini-pro-latest" <?= $defaultModel === 'gemini-pro-latest' ? 'selected' : '' ?>>Gemini Pro Latest (Hardest Cursive)</option>
                                </select>
                            </div>

                            <div>
                                <label for="custom-fields-input" class="block text-xs font-medium text-slate-300 mb-1.5">
                                    Structured Fields <span class="text-slate-500">(optional, for Excel)</span>
                                </label>
                                <input id="custom-fields-input" name="custom_fields" type="text" placeholder="e.g. Date, Name, Address, Amount"
                                       class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="flex items-center justify-end gap-3 pt-2">
                            <button id="btn-submit-upload" type="submit" disabled
                                    class="px-5 py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 disabled:cursor-not-allowed text-sm font-semibold text-white transition flex items-center gap-2">
                                <span id="submit-spinner" class="hidden w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                                <span id="submit-label">Transcribe Cursive</span>
                            </button>
                        </div>
                    </form>
                </div>
            </section>

            <!-- VIEW 2: Side-by-Side Review Workspace -->
            <section id="review-view" class="hidden space-y-4">
                <!-- Document Toolbar -->
                <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h2 id="doc-title" class="text-base font-semibold text-white truncate"></h2>
                            <span id="doc-status-badge" class="text-xs px-2 py-0.5 rounded-full border"></span>
                        </div>
                        <p id="doc-meta" class="text-xs text-slate-400 mt-0.5"></p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button id="btn-save-doc" type="button" class="px-3.5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-xs font-semibold text-white transition">
                            Save Changes
                        </button>
                        <a id="btn-download-txt" href="#" class="px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-xs font-medium text-slate-200 transition">
                            Download .TXT
                        </a>
                        <a id="btn-download-json" href="#" class="px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-xs font-medium text-slate-200 transition">
                            Download .JSON
                        </a>
                        <button type="button" disabled title="Coming in Phase 4 — JSON schema is already Excel-ready!"
                                class="px-3 py-2 rounded-lg bg-slate-800/50 border border-slate-800 text-xs font-medium text-slate-500 cursor-not-allowed">
                            Excel (.XLSX) Soon
                        </button>
                        <button id="btn-delete-doc" type="button" class="px-3 py-2 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/30 text-xs font-medium text-rose-300 transition">
                            Delete
                        </button>
                    </div>
                </div>

                <!-- Split Panes -->
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">

                    <!-- Left Pane: Original Cursive Viewer -->
                    <div class="bg-slate-900 border border-slate-800 rounded-xl flex flex-col overflow-hidden">
                        <div class="px-4 py-2.5 border-b border-slate-800 flex items-center justify-between gap-2 bg-slate-900/80">
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Original Document</span>
                            <div class="flex items-center gap-2">
                                <div id="pdf-nav" class="hidden items-center gap-1.5 text-xs">
                                    <button id="btn-pdf-prev" type="button" class="px-2 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-200">&larr;</button>
                                    <span id="pdf-page-label" class="text-slate-300 px-1">1 / 1</span>
                                    <button id="btn-pdf-next" type="button" class="px-2 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-200">&rarr;</button>
                                </div>
                                <div class="flex items-center gap-1 text-xs">
                                    <button id="btn-zoom-out" type="button" class="px-2 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-200" title="Zoom Out">-</button>
                                    <span id="zoom-label" class="text-slate-400 w-10 text-center">100%</span>
                                    <button id="btn-zoom-in" type="button" class="px-2 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-200" title="Zoom In">+</button>
                                </div>
                            </div>
                        </div>

                        <div id="preview-container" class="flex-1 bg-slate-950 overflow-auto p-4 flex items-start justify-center min-h-[480px] max-h-[72vh]">
                            <img id="preview-image" class="hidden max-w-full rounded shadow-lg origin-top-left transition-transform" alt="Cursive document preview">
                            <canvas id="preview-pdf-canvas" class="hidden rounded shadow-lg"></canvas>
                        </div>
                    </div>

                    <!-- Right Pane: Editable Transcription & Structured Fields -->
                    <div class="bg-slate-900 border border-slate-800 rounded-xl flex flex-col overflow-hidden">
                        <!-- Tabs Header -->
                        <div class="px-4 py-2.5 border-b border-slate-800 flex items-center justify-between gap-2">
                            <div id="page-tabs" class="flex items-center gap-1.5 overflow-x-auto"></div>
                            <button id="btn-toggle-json" type="button" class="text-xs px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 shrink-0">
                                View Raw JSON
                            </button>
                        </div>

                        <!-- Editor Body -->
                        <div id="editor-panel" class="p-4 space-y-4 flex-1 overflow-y-auto max-h-[72vh]">
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label for="page-textarea" class="text-xs font-medium text-slate-300">Transcribed Text (Editable)</label>
                                    <button id="btn-reset-raw" type="button" class="text-xs text-slate-400 hover:text-indigo-300 underline">
                                        Reset to AI Original
                                    </button>
                                </div>
                                <textarea id="page-textarea" rows="12"
                                          class="w-full rounded-lg bg-slate-950 border border-slate-700 p-3.5 text-sm leading-relaxed text-slate-100 font-mono focus:outline-none focus:border-indigo-500"></textarea>
                            </div>

                            <!-- Uncertain Words & Notes -->
                            <div id="uncertain-box" class="hidden rounded-lg bg-amber-500/10 border border-amber-500/25 p-3">
                                <p class="text-xs font-semibold text-amber-300 mb-1.5">Uncertain / Hard-to-Read Words</p>
                                <div id="uncertain-list" class="flex flex-wrap gap-1.5"></div>
                            </div>

                            <div id="notes-box" class="hidden rounded-lg bg-slate-950 border border-slate-800 p-3">
                                <p class="text-xs font-semibold text-slate-400 mb-1">AI Transcription Notes</p>
                                <p id="page-notes" class="text-xs text-slate-300"></p>
                            </div>

                            <!-- Structured Fields (Excel Columns Preview) -->
                            <div class="border-t border-slate-800 pt-4">
                                <div class="flex items-center justify-between mb-2">
                                    <div>
                                        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-300">Extracted Fields (Excel Columns)</h3>
                                        <p class="text-[11px] text-slate-500">Saved inside JSON so future Excel export maps these directly to columns.</p>
                                    </div>
                                    <button id="btn-add-field" type="button" class="text-xs px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-indigo-300 border border-slate-700">
                                        + Add Field
                                    </button>
                                </div>
                                <div id="fields-container" class="space-y-2"></div>
                            </div>
                        </div>

                        <!-- Raw JSON Preview Panel -->
                        <div id="json-panel" class="hidden p-4 flex-1 overflow-y-auto max-h-[72vh] bg-slate-950">
                            <pre id="json-preview" class="text-xs text-emerald-300 font-mono whitespace-pre-wrap break-words"></pre>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- API Settings Modal -->
    <div id="settings-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4">
        <div class="bg-slate-900 border border-slate-700 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-semibold text-white">Gemini API Settings</h3>
                    <p class="text-xs text-slate-400">Saved securely on the server — no <code class="text-slate-300">.env</code> editing required.</p>
                </div>
                <button id="btn-close-settings" type="button" class="text-slate-400 hover:text-white text-xl leading-none px-1">&times;</button>
            </div>

            <form id="settings-form" class="p-5 space-y-4">
                <div id="settings-current-status" class="text-xs px-3 py-2 rounded-lg bg-slate-950 border border-slate-800 text-slate-300"></div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="settings-api-key" class="text-xs font-medium text-slate-300">Gemini API Key</label>
                        <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener" class="text-[11px] text-indigo-400 hover:text-indigo-300 underline">
                            Get API key &rarr;
                        </a>
                    </div>
                    <div class="flex items-center gap-2">
                        <input id="settings-api-key" type="password" autocomplete="off" placeholder="Paste your AIza... key (leave blank to keep current)"
                               class="flex-1 rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                        <button id="btn-toggle-key-vis" type="button" class="px-2.5 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-xs text-slate-300">
                            Show
                        </button>
                    </div>
                </div>

                <div>
                    <label for="settings-default-model" class="block text-xs font-medium text-slate-300 mb-1.5">Default AI Model</label>
                    <select id="settings-default-model" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                        <option value="gemini-3.8-flash">Gemini 3.8 Flash (Auto-Fallback Enabled)</option>
                        <option value="gemini-3.7-flash">Gemini 3.7 Flash (Lower Traffic)</option>
                        <option value="gemini-3.5-flash">Gemini 3.5 Flash (Stable)</option>
                        <option value="gemini-pro-latest">Gemini Pro Latest (Hardest Cursive)</option>
                    </select>
                </div>

                <div class="flex items-center justify-between pt-2 border-t border-slate-800">
                    <button id="btn-clear-api-key" type="button" class="hidden text-xs text-rose-400 hover:text-rose-300 underline">
                        Remove saved key
                    </button>
                    <div class="flex items-center gap-2 ml-auto">
                        <button id="btn-cancel-settings" type="button" class="px-3.5 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-medium text-slate-200 transition">
                            Cancel
                        </button>
                        <button id="btn-save-settings" type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-xs font-semibold text-white transition">
                            Save Settings
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/app.js"></script>
</body>
</html>

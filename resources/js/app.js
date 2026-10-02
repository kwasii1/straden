import { Chart, registerables } from 'chart.js';
import { applyChartDefaults, fill, ink, series, status } from './charts/theme.js';
import './../../vendor/power-components/livewire-powergrid/dist/powergrid'
import * as monaco from 'monaco-editor';

Chart.register(...registerables);
applyChartDefaults(Chart);
window.Chart = Chart;
window.StradenCharts = { series, status, ink, fill };

import editorWorkerUrl from 'monaco-editor/esm/vs/editor/editor.worker?worker&url';
import jsonWorkerUrl from 'monaco-editor/esm/vs/language/json/json.worker?worker&url';
import tsWorkerUrl from 'monaco-editor/esm/vs/language/typescript/ts.worker?worker&url';

function createModuleWorker(workerUrl, label) {
    const url = new URL(workerUrl, import.meta.url);

    if (url.origin === self.location.origin) {
        return new Worker(url, { type: 'module', name: label });
    }

    const blobUrl = URL.createObjectURL(
        new Blob([`import ${JSON.stringify(url.href)};`], { type: 'text/javascript' })
    );

    const worker = new Worker(blobUrl, { type: 'module', name: label });
    worker.addEventListener('error', () => URL.revokeObjectURL(blobUrl), { once: true });

    return worker;
}

self.MonacoEnvironment = {
    getWorker(_workerId, label) {
        if (label === 'json') {
            return createModuleWorker(jsonWorkerUrl, label);
        }

        if (label === 'typescript' || label === 'javascript') {
            return createModuleWorker(tsWorkerUrl, label);
        }

        return createModuleWorker(editorWorkerUrl, label);
    },
};

const AUTOSAVE_DELAY_MS = 1000;
const AUTOSAVE_STORAGE_KEY = 'straden.editor.autosave';

const readAutosavePreference = () => {
    try {
        return localStorage.getItem(AUTOSAVE_STORAGE_KEY) !== 'off';
    } catch {
        return true;
    }
};

const currentMonacoTheme = () => (document.documentElement.classList.contains('dark') ? 'vs-dark' : 'vs');

// Keep every editor in step with the app's light/dark appearance.
new MutationObserver(() => monaco.editor.setTheme(currentMonacoTheme()))
    .observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

document.addEventListener('alpine:init', () => {
    Alpine.store('editor', {
        buffers: {},
        autosave: readAutosavePreference(),

        toggleAutosave() {
            this.autosave = ! this.autosave;

            try {
                localStorage.setItem(AUTOSAVE_STORAGE_KEY, this.autosave ? 'on' : 'off');
            } catch {
                // Preference simply won't persist (private mode, blocked storage).
            }
        },

        /**
         * Record the result of a save for a buffer. Content typed while the
         * request was in flight keeps the buffer dirty.
         */
        markSaved(path, savedContent, succeeded) {
            const buf = this.buffers[path];

            if (! buf) {
                return;
            }

            if (succeeded) {
                buf.savedContent = savedContent;
                buf.lastSavedAt = new Date();
            }

            buf.dirty = buf.content !== buf.savedContent;
            buf.status = succeeded ? (buf.dirty ? 'dirty' : 'saved') : 'error';
        },
    });

    Alpine.data('monacoEditor', (initialValue, language, editable, path) => {
        let editor = null;
        let autosaveTimer = null;

        const store = () => Alpine.store('editor').buffers[path] ?? (Alpine.store('editor').buffers[path] = {
            content: initialValue,
            savedContent: initialValue,
            dirty: false,
            status: 'saved',
            lastSavedAt: null,
        });

        const markDirty = () => {
            const buf = store();
            buf.content = editor.getValue();
            buf.dirty = buf.content !== buf.savedContent;

            if (buf.dirty && buf.status !== 'saving') {
                buf.status = 'dirty';
            }
        };

        return {
            content: initialValue,
            cursor: { line: 1, column: 1 },

            get buffer() {
                return path ? store() : null;
            },

            init() {
                const buf = store();
                const value = buf.content ?? initialValue;

                this.content = value;

                editor = monaco.editor.create(this.$refs.editorContainer, {
                    value: value,
                    language: language,
                    theme: currentMonacoTheme(),
                    automaticLayout: true,
                    minimap: { enabled: true },
                    fontSize: 13,
                    fontLigatures: true,
                    lineHeight: 20,
                    padding: { top: 12 },
                    scrollBeyondLastLine: false,
                    smoothScrolling: true,
                    cursorBlinking: 'smooth',
                    renderLineHighlight: 'all',
                    roundedSelection: false,
                    readOnly: !editable,
                });

                editor.onDidChangeModelContent(() => {
                    this.content = editor.getValue();
                    markDirty();
                    this.scheduleAutosave();
                });

                editor.onDidChangeCursorPosition((event) => {
                    this.cursor = { line: event.position.lineNumber, column: event.position.column };
                });

                if (editable && path) {
                    editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS, () => this.save());

                    // Like VS Code's "onFocusChange": leaving the editor saves.
                    editor.onDidBlurEditorText(() => {
                        if (Alpine.store('editor').autosave && store().dirty) {
                            this.save();
                        }
                    });
                }
            },

            scheduleAutosave() {
                if (! editable || ! path || ! Alpine.store('editor').autosave) {
                    return;
                }

                clearTimeout(autosaveTimer);
                autosaveTimer = setTimeout(() => {
                    if (store().dirty) {
                        this.save({ silent: true });
                    }
                }, AUTOSAVE_DELAY_MS);
            },

            save({ silent = false } = {}) {
                if (! editable || ! path) {
                    return;
                }

                clearTimeout(autosaveTimer);

                this.content = editor.getValue();
                store().status = 'saving';

                window.dispatchEvent(new CustomEvent('editor-save', {
                    detail: { path: path, content: this.content, silent: silent },
                }));
            },

            destroy() {
                if (editor) {
                    markDirty();

                    // Switching files shouldn't lose pending autosaved edits.
                    if (editable && path && Alpine.store('editor').autosave && store().dirty) {
                        this.save({ silent: true });
                    }

                    clearTimeout(autosaveTimer);
                    editor.dispose();
                    editor = null;
                }
            },

            setValue(val) {
                editor?.setValue(val);
            },
        };
    });
});

import './charts/activity-chart.js';
import './charts/status-doughnut.js';
import './charts/performance-trend.js';
import './charts/run-vus.js';
import './charts/run-request-rate.js';
import './charts/run-response-time.js';
import './charts/run-error-rate.js';
import './charts/run-response-codes.js';
import './charts/run-checks.js';
import './charts/run-data-transfer.js';
import './charts/run-http-timing.js';
import './charts/run-iteration-duration.js';
import './charts/run-iterations.js';
import './chat-stream.js';

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';

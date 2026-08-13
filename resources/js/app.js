import { Chart, registerables } from 'chart.js';
import './../../vendor/power-components/livewire-powergrid/dist/powergrid'
import * as monaco from 'monaco-editor';

Chart.register(...registerables);
window.Chart = Chart;

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

document.addEventListener('alpine:init', () => {
    Alpine.store('editor', { buffers: {} });

    Alpine.data('monacoEditor', (initialValue, language, editable, path) => {
        let editor = null;

        const store = () => Alpine.store('editor').buffers[path] ?? (Alpine.store('editor').buffers[path] = {
            content: initialValue,
            savedContent: initialValue,
            dirty: false,
        });

        const markDirty = () => {
            const buf = store();
            buf.content = editor.getValue();
            buf.dirty = buf.content !== buf.savedContent;
        };

        return {
            content: initialValue,

            init() {
                const buf = store();
                const value = buf.content ?? initialValue;

                this.content = value;

                editor = monaco.editor.create(this.$refs.editorContainer, {
                    value: value,
                    language: language,
                    theme: 'vs-dark',
                    automaticLayout: true,
                    minimap: { enabled: true },
                    fontSize: 13,
                    roundedSelection: false,
                    readOnly: !editable,
                });

                editor.onDidChangeModelContent(() => {
                    this.content = editor.getValue();
                    markDirty();
                });

                if (editable && path) {
                    editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS, () => this.save());
                }
            },

            save() {
                if (! editable || ! path) {
                    return;
                }

                this.content = editor.getValue();

                window.dispatchEvent(new CustomEvent('editor-save', {
                    detail: { path: path, content: this.content },
                }));
            },

            destroy() {
                if (editor) {
                    markDirty();
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
import './charts/project-doughnut.js';
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

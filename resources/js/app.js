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
    Alpine.data('monacoEditor', (initialValue, language) => {
        let editor = null;

        return {
            content: initialValue,

            init() {
                editor = monaco.editor.create(this.$refs.editorContainer, {
                    value: initialValue,
                    language: language,
                    theme: 'vs-dark',
                    automaticLayout: true,
                    minimap: { enabled: true },
                    fontSize: 13,
                    roundedSelection: false,
                });

                editor.onDidChangeModelContent(() => {
                    this.content = editor.getValue();
                });
            },

            destroy() {
                editor?.dispose();
                editor = null;
            },

            setValue(val) {
                editor?.setValue(val);
            },
        };
    });
});

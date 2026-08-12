document.addEventListener('alpine:init', () => {
    Alpine.data('chatStream', (channelName) => ({
        streaming: false,
        liveText: '',
        segments: [],
        currentMessageId: null,
        toolCount: 0,
        thinking: false,

        container: null,

        init() {
            this.container = document.getElementById('chat-messages');

            this.bindEcho(channelName);

            this._handler = (e) => this.onEvent(e.detail);
            window.addEventListener('agent-stream', this._handler);
        },

        // Bind the private channel once. Livewire may re-create this Alpine
        // component on re-renders, so Echo subscription is guarded and events
        // are forwarded as window events that any live instance can consume.
        // The SDK broadcasts each event under its own type (e.g. "text_delta",
        // "tool_call", "stream_failed"), so we listen to everything and route
        // by the payload's "type" field.
        bindEcho(channelName) {
            const key = `__chatStreamBound_${channelName}`;

            if (window[key]) {
                return;
            }

            window[key] = true;

            window.Echo.private(channelName).listenToAll((event, data) => {
                const type = data?.type ?? event;

                window.dispatchEvent(new CustomEvent('agent-stream', {
                    detail: { type, data: data ?? {} },
                }));
            });
        },

        onEvent({ type, data }) {
            const error = this.errorMessage(type, data);

            if (error !== null) {
                this.streaming = false;

                window.dispatchEvent(new CustomEvent('agent-error', {
                    detail: { error },
                }));

                this.scrollToBottom();

                return;
            }

            switch (type) {
                case 'stream_start':
                    this.reset();
                    this.streaming = true;
                    break;

                case 'reasoning_start':
                    this.thinking = true;
                    break;

                case 'reasoning_end':
                    this.thinking = false;
                    break;

                case 'text_start':
                    this.currentMessageId = data.message_id;
                    this.segments.push({ id: data.message_id, text: '' });
                    break;

                case 'text_delta':
                    const segment = this.segments.find((s) => s.id === data.message_id)
                        ?? this.segments[this.segments.length - 1];

                    if (segment) {
                        segment.text += data.delta;
                    }

                    this.recompute();
                    break;

                case 'text_end':
                    this.currentMessageId = null;
                    break;

                case 'tool_call':
                    this.toolCount++;
                    break;

                // The SDK emits these during the stream, BEFORE the messages
                // are persisted. Keep the live bubble visible; the agent
                // broadcasts "agent_approval_request" / "agent_completed" after
                // the conversation is stored, which is what triggers a reload.
                case 'tool_approval_request':
                    this.streaming = false;
                    break;

                case 'stream_end':
                    this.streaming = false;
                    break;

                case 'agent_completed':
                    this.streaming = false;
                    window.dispatchEvent(new CustomEvent('agent-done'));
                    break;

                case 'agent_approval_request':
                    this.streaming = false;
                    window.dispatchEvent(new CustomEvent('agent-approval-requested'));
                    break;
            }

            this.scrollToBottom();
        },

        // Extract an error message when the event is an error, otherwise null.
        errorMessage(type, data) {
            if (type === 'stream_failed') {
                return data.message ?? 'The agent failed.';
            }

            if (type === 'request_error' || type === 'unknown_error') {
                return data.message ?? 'The agent failed.';
            }

            if (String(type).toLowerCase().includes('error')) {
                return data.message ?? 'The agent failed.';
            }

            return null;
        },

        recompute() {
            this.liveText = this.segments
                .map((s) => s.text)
                .filter((text) => text.trim() !== '')
                .join('\n\n');
        },

        reset() {
            this.streaming = false;
            this.liveText = '';
            this.segments = [];
            this.currentMessageId = null;
            this.toolCount = 0;
            this.thinking = false;
        },

        scrollToBottom(behavior = 'auto') {
            const container = this.container;

            if (container) {
                container.scrollTo({ top: container.scrollHeight, behavior });
            }
        },

        destroy() {
            if (this._handler) {
                window.removeEventListener('agent-stream', this._handler);
            }
        },
    }));
});

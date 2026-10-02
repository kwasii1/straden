@props(['messages' => []])

<div class="flex h-full flex-col bg-white">
    <div class="flex-1 space-y-5 overflow-y-auto p-4 text-sm text-zinc-700 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        <div>
            <p class="mb-1 text-xs text-zinc-500">Agent</p>
            <p>Hello! I'm your AI assistant. How can I help you with your test scripts today?</p>
        </div>
        <div class="flex justify-end">
            <div class="max-w-[85%] rounded-lg bg-zinc-100 px-3 py-2 text-zinc-900">
                What does the entry.js file do?
            </div>
        </div>
        <div>
            <p class="mb-1 text-xs text-zinc-500">Agent</p>
            <p>The <code class="ui-code">entry.js</code> file is the main entry point for your test script. It initializes the test environment, loads all dependencies, and exports the test configuration.</p>
        </div>
        <div class="flex justify-end">
            <div class="max-w-[85%] rounded-lg bg-zinc-100 px-3 py-2 text-zinc-900">
                Can you help me write a test for the login flow?
            </div>
        </div>
        <div>
            <p class="mb-1 text-xs text-zinc-500">Agent</p>
            <p>Of course! Here's a basic structure to get started. You'll want to navigate to the login page, fill in the credentials, submit the form, and verify the redirect to the dashboard.</p>
        </div>
    </div>

    <div class="shrink-0 border-t border-zinc-200 p-3">
        <div class="flex items-end gap-2">
            <textarea
                rows="2"
                placeholder="Ask about your scripts"
                class="ui-input h-auto flex-1 resize-none py-2"
            ></textarea>
            <flux:button icon="paper-airplane" variant="primary" size="sm" square class="shrink-0" aria-label="Send" />
        </div>
    </div>
</div>

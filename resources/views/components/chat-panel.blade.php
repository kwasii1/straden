@props(['messages' => []])

<div class="flex flex-col h-full">
    <div class="flex-1 overflow-y-auto p-4 space-y-4">
        <div class="flex justify-start">
            <div class="max-w-[85%] rounded-lg rounded-bl-sm px-3 py-2 text-sm bg-zinc-800 text-zinc-200">
                <p class="text-xs text-zinc-500 mb-1">AI Assistant</p>
                Hello! I'm your AI assistant. How can I help you with your test scripts today?
            </div>
        </div>
        <div class="flex justify-end">
            <div class="max-w-[85%] rounded-lg rounded-br-sm px-3 py-2 text-sm bg-blue-600 text-white">
                <p class="text-xs text-blue-300 mb-1">You</p>
                What does the entry.js file do?
            </div>
        </div>
        <div class="flex justify-start">
            <div class="max-w-[85%] rounded-lg rounded-bl-sm px-3 py-2 text-sm bg-zinc-800 text-zinc-200">
                <p class="text-xs text-zinc-500 mb-1">AI Assistant</p>
                The <code class="text-xs bg-zinc-700 px-1 py-0.5 rounded">entry.js</code> file is the main entry point for your test script. It initializes the test environment, loads all dependencies, and exports the test configuration.
            </div>
        </div>
        <div class="flex justify-end">
            <div class="max-w-[85%] rounded-lg rounded-br-sm px-3 py-2 text-sm bg-blue-600 text-white">
                <p class="text-xs text-blue-300 mb-1">You</p>
                Can you help me write a test for the login flow?
            </div>
        </div>
        <div class="flex justify-start">
            <div class="max-w-[85%] rounded-lg rounded-bl-sm px-3 py-2 text-sm bg-zinc-800 text-zinc-200">
                <p class="text-xs text-zinc-500 mb-1">AI Assistant</p>
                Of course! Here's a basic structure to get started. You'll want to navigate to the login page, fill in the credentials, submit the form, and verify the redirect to the dashboard.
            </div>
        </div>
    </div>

    <div class="shrink-0 border-t border-zinc-800 p-3">
        <div class="flex items-end gap-2">
            <textarea
                rows="2"
                placeholder="Ask a question..."
                class="flex-1 bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-sm text-zinc-200 placeholder-zinc-500 resize-none focus:outline-none focus:border-zinc-600"
            ></textarea>
            <flux:button icon="paper-airplane" variant="primary" size="sm" class="shrink-0" />
        </div>
    </div>
</div>

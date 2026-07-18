<?php

it('renders the monaco editor container with alpine bindings', function () {
    $view = $this->blade(
        '<x-code-editor name="script_content" value="console.log(1);" language="javascript" height="500px" wire:key="editor-1" />'
    );

    $view->assertSee('x-data="monacoEditor(', false);
    $view->assertSee('wire:ignore', false);
    $view->assertSee('wire:key="editor-1"', false);
    $view->assertSee('x-ref="editorContainer"', false);
    $view->assertSee('height: 500px', false);
    $view->assertSee('name="script_content"', false);
});

it('does not manually invoke init so alpine controls the editor lifecycle', function () {
    $view = $this->blade('<x-code-editor name="content" />');

    $view->assertDontSee('x-init', false);
    $view->assertDontSee('init($refs', false);
});

it('escapes the initial value passed to the editor', function () {
    $view = $this->blade(
        '<x-code-editor name="content" :value="$value" />',
        ['value' => 'const html = "</div><script>alert(1)</script>";']
    );

    $view->assertDontSee('<script>alert(1)</script>', false);
});

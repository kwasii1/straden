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

it('renders a save toolbar when editable', function () {
    $view = $this->blade(
        '<x-code-editor name="content" value="x" editable="true" savePath="script.js" />'
    );

    $view->assertSee('Ctrl+S to save', false);
    $view->assertSee('x-on:click="save()"', false);
    $view->assertSee("monacoEditor('x', 'javascript', true, 'script.js')", false);
});

it('omits the save toolbar when not editable', function () {
    $view = $this->blade('<x-code-editor name="content" value="x" />');

    $view->assertDontSee('Ctrl+S to save', false);
    $view->assertDontSee('x-on:click="save()"', false);
});

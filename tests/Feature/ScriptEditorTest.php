<?php

use App\Models\Project;
use App\Models\Run;
use App\Models\Script;
use App\Models\Test;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
});

test('creating a script writes entry point file to disk', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user);

    $component = Livewire::test('pages::dashboard.view-test', [
        'project' => $project,
        'test' => $test,
    ])
        ->set('name', 'My Load Test')
        ->set('description', 'A test script')
        ->call('submit');

    $script = Script::first();
    $basePath = 'scripts/'.$test->id.'/'.$script->id;

    expect($script->name)->toBe('My Load Test');
    expect($script->script_path)->toBe($basePath.'/script.js');

    Storage::disk('local')->assertExists($basePath.'/script.js');

    $content = Storage::disk('local')->get($basePath.'/script.js');
    expect($content)->toContain("import http from 'k6/http'");
    expect($content)->toContain('export default function ()');
});

test('script editor page loads with file tree', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    $basePath = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->makeDirectory($basePath);
    Storage::disk('local')->put($basePath.'/script.js', '// entry point');
    Storage::disk('local')->put($basePath.'/helpers.ts', '// helpers');

    $this->actingAs($user)
        ->get(route('projects.view-test-script', [
            'project' => $project,
            'test' => $test,
            'script' => $script,
        ]))
        ->assertOk()
        ->assertSee('script.js')
        ->assertSee('helpers.ts');
});

test('selecting a file opens it and displays content', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    $basePath = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->makeDirectory($basePath);
    Storage::disk('local')->put($basePath.'/script.js', '// entry point content');
    Storage::disk('local')->put($basePath.'/helpers.ts', 'const x = 1;');

    $this->actingAs($user);

    Livewire::actingAs($user)
        ->test('pages::dashboard.view-test-script', [
            'project' => $project,
            'test' => $test,
            'script' => $script,
        ])
        ->assertSet('activeFilePath', 'script.js')
        ->assertSet('openTabs', ['script.js'])
        ->call('selectFile', 'helpers.ts')
        ->assertSet('activeFilePath', 'helpers.ts')
        ->assertSet('openTabs', ['script.js', 'helpers.ts'])
        ->call('readFile', 'helpers.ts')
        ->assertSee('const x = 1;');
});

test('closing a tab removes it and selects next available', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    $basePath = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->makeDirectory($basePath);
    Storage::disk('local')->put($basePath.'/script.js', '// entry');
    Storage::disk('local')->put($basePath.'/helpers.ts', '// helpers');

    $this->actingAs($user);

    Livewire::actingAs($user)
        ->test('pages::dashboard.view-test-script', [
            'project' => $project,
            'test' => $test,
            'script' => $script,
        ])
        ->call('selectFile', 'helpers.ts')
        ->assertSet('openTabs', ['script.js', 'helpers.ts'])
        ->assertSet('activeFilePath', 'helpers.ts')
        ->call('closeTab', 'helpers.ts')
        ->assertSet('openTabs', ['script.js'])
        ->assertSet('activeFilePath', 'script.js')
        ->call('closeTab', 'script.js')
        ->assertSet('openTabs', [])
        ->assertSet('activeFilePath', null);
});

test('creating a file persists to disk and appears in tree', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    $basePath = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->makeDirectory($basePath);
    Storage::disk('local')->put($basePath.'/script.js', '// entry');

    $this->actingAs($user);

    Livewire::actingAs($user)
        ->test('pages::dashboard.view-test-script', [
            'project' => $project,
            'test' => $test,
            'script' => $script,
        ])
        ->call('createFile', 'new-test.js', '// new content')
        ->assertSet('activeFilePath', 'new-test.js');

    Storage::disk('local')->assertExists($basePath.'/new-test.js');
    expect(Storage::disk('local')->get($basePath.'/new-test.js'))->toBe('// new content');
});

test('creating a file rejects duplicate names', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    $basePath = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->makeDirectory($basePath);
    Storage::disk('local')->put($basePath.'/script.js', '// entry');

    $this->actingAs($user);

    Livewire::actingAs($user)
        ->test('pages::dashboard.view-test-script', [
            'project' => $project,
            'test' => $test,
            'script' => $script,
        ])
        ->call('createFile', 'script.js', '// duplicate')
        ->assertSet('activeFilePath', 'script.js');
});

test('creating a folder persists to disk', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    $basePath = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->makeDirectory($basePath);
    Storage::disk('local')->put($basePath.'/script.js', '// entry');

    $this->actingAs($user);

    Livewire::actingAs($user)
        ->test('pages::dashboard.view-test-script', [
            'project' => $project,
            'test' => $test,
            'script' => $script,
        ])
        ->call('createFolder', 'utils')
        ->assertSet('fileTree', [
            ['name' => 'utils', 'children' => []],
            ['name' => 'script.js'],
        ]);

    Storage::disk('local')->assertExists($basePath.'/utils');
});

test('creating a file in a subfolder', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    $basePath = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->makeDirectory($basePath.'/utils');
    Storage::disk('local')->put($basePath.'/script.js', '// entry');

    $this->actingAs($user);

    Livewire::actingAs($user)
        ->test('pages::dashboard.view-test-script', [
            'project' => $project,
            'test' => $test,
            'script' => $script,
        ])
        ->call('createFile', 'helper.js', '// helper', 'utils')
        ->assertSet('activeFilePath', 'utils/helper.js');

    Storage::disk('local')->assertExists($basePath.'/utils/helper.js');
});

test('moving a file between folders on disk', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    $basePath = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->makeDirectory($basePath.'/utils');
    Storage::disk('local')->put($basePath.'/script.js', '// entry');
    Storage::disk('local')->put($basePath.'/helpers.ts', '// helpers');

    $this->actingAs($user);

    Livewire::actingAs($user)
        ->test('pages::dashboard.view-test-script', [
            'project' => $project,
            'test' => $test,
            'script' => $script,
        ])
        ->call('moveItem', 'helpers.ts', 'utils')
        ->assertSet('activeFilePath', 'script.js');

    Storage::disk('local')->assertMissing($basePath.'/helpers.ts');
    Storage::disk('local')->assertExists($basePath.'/utils/helpers.ts');
});

test('moving a file with open tabs updates tab paths', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    $basePath = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->makeDirectory($basePath.'/utils');
    Storage::disk('local')->put($basePath.'/script.js', '// entry');
    Storage::disk('local')->put($basePath.'/helpers.ts', '// helpers');

    $this->actingAs($user);

    Livewire::actingAs($user)
        ->test('pages::dashboard.view-test-script', [
            'project' => $project,
            'test' => $test,
            'script' => $script,
        ])
        ->call('selectFile', 'helpers.ts')
        ->assertSet('activeFilePath', 'helpers.ts')
        ->call('moveItem', 'helpers.ts', 'utils')
        ->assertSet('activeFilePath', 'utils/helpers.ts')
        ->assertSet('openTabs', ['script.js', 'utils/helpers.ts']);
});

test('moving a folder into itself is rejected', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    $basePath = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->makeDirectory($basePath.'/utils');
    Storage::disk('local')->put($basePath.'/script.js', '// entry');

    $this->actingAs($user);

    Livewire::actingAs($user)
        ->test('pages::dashboard.view-test-script', [
            'project' => $project,
            'test' => $test,
            'script' => $script,
        ])
        ->call('moveItem', 'utils', 'utils')
        ->assertSet('activeFilePath', 'script.js');

    Storage::disk('local')->assertExists($basePath.'/utils');
});

test('moving to a path where a file already exists is rejected', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    $basePath = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->makeDirectory($basePath);
    Storage::disk('local')->put($basePath.'/script.js', '// entry');
    Storage::disk('local')->put($basePath.'/helpers.ts', '// helpers');
    Storage::disk('local')->put($basePath.'/utils/helpers.ts', '// existing');

    $this->actingAs($user);

    Livewire::actingAs($user)
        ->test('pages::dashboard.view-test-script', [
            'project' => $project,
            'test' => $test,
            'script' => $script,
        ])
        ->call('moveItem', 'helpers.ts', 'utils')
        ->assertSet('activeFilePath', 'script.js');

    Storage::disk('local')->assertExists($basePath.'/helpers.ts');
    $content = Storage::disk('local')->get($basePath.'/utils/helpers.ts');
    expect($content)->toBe('// existing');
});

test('deleting a file removes it from disk and tabs', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    $basePath = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->makeDirectory($basePath);
    Storage::disk('local')->put($basePath.'/script.js', '// entry');
    Storage::disk('local')->put($basePath.'/helpers.ts', '// helpers');

    $this->actingAs($user);

    Livewire::actingAs($user)
        ->test('pages::dashboard.view-test-script', [
            'project' => $project,
            'test' => $test,
            'script' => $script,
        ])
        ->call('selectFile', 'helpers.ts')
        ->call('deleteItem', 'helpers.ts')
        ->assertSet('openTabs', ['script.js'])
        ->assertSet('activeFilePath', 'script.js');

    Storage::disk('local')->assertMissing($basePath.'/helpers.ts');
});

test('deleting a folder removes it and its contents', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    $basePath = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->makeDirectory($basePath.'/utils');
    Storage::disk('local')->put($basePath.'/utils/helpers.ts', '// helpers');
    Storage::disk('local')->put($basePath.'/script.js', '// entry');

    $this->actingAs($user);

    Livewire::actingAs($user)
        ->test('pages::dashboard.view-test-script', [
            'project' => $project,
            'test' => $test,
            'script' => $script,
        ])
        ->call('selectFile', 'utils/helpers.ts')
        ->call('deleteItem', 'utils')
        ->assertSet('openTabs', ['script.js'])
        ->assertSet('activeFilePath', 'script.js');

    Storage::disk('local')->assertMissing($basePath.'/utils');
    Storage::disk('local')->assertExists($basePath.'/script.js');
});

test('language detection by file extension', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    $basePath = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->makeDirectory($basePath);
    Storage::disk('local')->put($basePath.'/script.js', '// entry');

    $this->actingAs($user);

    $component = Livewire::actingAs($user)
        ->test('pages::dashboard.view-test-script', [
            'project' => $project,
            'test' => $test,
            'script' => $script,
        ])->instance();

    expect($component->detectLanguage('file.js'))->toBe('javascript');
    expect($component->detectLanguage('file.ts'))->toBe('typescript');
    expect($component->detectLanguage('file.json'))->toBe('json');
    expect($component->detectLanguage('file.css'))->toBe('css');
    expect($component->detectLanguage('file.html'))->toBe('html');
    expect($component->detectLanguage('file.txt'))->toBe('plaintext');
});

test('renaming a file moves it on disk and updates tabs', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    $basePath = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->makeDirectory($basePath);
    Storage::disk('local')->put($basePath.'/script.js', '// entry');
    Storage::disk('local')->put($basePath.'/helpers.ts', '// helpers');

    $this->actingAs($user);

    Livewire::actingAs($user)
        ->test('pages::dashboard.view-test-script', [
            'project' => $project,
            'test' => $test,
            'script' => $script,
        ])
        ->call('selectFile', 'helpers.ts')
        ->assertSet('activeFilePath', 'helpers.ts')
        ->call('renameItem', 'helpers.ts', 'utilities.ts')
        ->assertSet('activeFilePath', 'utilities.ts')
        ->assertSet('openTabs', ['script.js', 'utilities.ts']);

    Storage::disk('local')->assertMissing($basePath.'/helpers.ts');
    Storage::disk('local')->assertExists($basePath.'/utilities.ts');
    expect(Storage::disk('local')->get($basePath.'/utilities.ts'))->toBe('// helpers');
});

test('renaming a folder moves it on disk', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    $basePath = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->makeDirectory($basePath.'/lib');
    Storage::disk('local')->put($basePath.'/lib/helper.js', '// helper');
    Storage::disk('local')->put($basePath.'/script.js', '// entry');

    $this->actingAs($user);

    Livewire::actingAs($user)
        ->test('pages::dashboard.view-test-script', [
            'project' => $project,
            'test' => $test,
            'script' => $script,
        ])
        ->call('selectFile', 'lib/helper.js')
        ->call('renameItem', 'lib', 'shared')
        ->assertSet('activeFilePath', 'shared/helper.js')
        ->assertSet('openTabs', ['script.js', 'shared/helper.js']);

    Storage::disk('local')->assertMissing($basePath.'/lib');
    Storage::disk('local')->assertExists($basePath.'/shared/helper.js');
});

test('renaming script.js entry point is rejected', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    $basePath = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->makeDirectory($basePath);
    Storage::disk('local')->put($basePath.'/script.js', '// entry');

    $this->actingAs($user);

    Livewire::actingAs($user)
        ->test('pages::dashboard.view-test-script', [
            'project' => $project,
            'test' => $test,
            'script' => $script,
        ])
        ->call('renameItem', 'script.js', 'main.js')
        ->assertSet('activeFilePath', 'script.js');

    Storage::disk('local')->assertExists($basePath.'/script.js');
    Storage::disk('local')->assertMissing($basePath.'/main.js');
});

test('renaming to an existing name is rejected', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    $basePath = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->makeDirectory($basePath);
    Storage::disk('local')->put($basePath.'/script.js', '// entry');
    Storage::disk('local')->put($basePath.'/a.ts', '// a');
    Storage::disk('local')->put($basePath.'/b.ts', '// b');

    $this->actingAs($user);

    Livewire::actingAs($user)
        ->test('pages::dashboard.view-test-script', [
            'project' => $project,
            'test' => $test,
            'script' => $script,
        ])
        ->call('renameItem', 'a.ts', 'b.ts')
        ->assertSet('activeFilePath', 'script.js');

    Storage::disk('local')->assertExists($basePath.'/a.ts');
    expect(Storage::disk('local')->get($basePath.'/a.ts'))->toBe('// a');
});

test('deleting script.js entry point is rejected', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    $basePath = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->makeDirectory($basePath);
    Storage::disk('local')->put($basePath.'/script.js', '// entry');

    $this->actingAs($user);

    Livewire::actingAs($user)
        ->test('pages::dashboard.view-test-script', [
            'project' => $project,
            'test' => $test,
            'script' => $script,
        ])
        ->call('deleteItem', 'script.js')
        ->assertSet('activeFilePath', 'script.js');

    Storage::disk('local')->assertExists($basePath.'/script.js');
});

test('empty file tree displays placeholder message', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    $this->actingAs($user)
        ->get(route('projects.view-test-script', [
            'project' => $project,
            'test' => $test,
            'script' => $script,
        ]))
        ->assertOk()
        ->assertSee('No files yet');
});

test('saving a file persists edited content to disk', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    $basePath = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->makeDirectory($basePath);
    Storage::disk('local')->put($basePath.'/script.js', '// original');

    $this->actingAs($user);

    Livewire::actingAs($user)
        ->test('pages::dashboard.view-test-script', [
            'project' => $project,
            'test' => $test,
            'script' => $script,
        ])
        ->call('saveFile', 'script.js', 'console.log("edited");')
        ->assertHasNoErrors();

    expect(Storage::disk('local')->get($basePath.'/script.js'))->toBe('console.log("edited");');
});

test('saving a file in a subfolder persists content', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    $basePath = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->makeDirectory($basePath.'/lib');
    Storage::disk('local')->put($basePath.'/lib/helper.js', '// helper');
    Storage::disk('local')->put($basePath.'/script.js', '// entry');

    $this->actingAs($user);

    Livewire::actingAs($user)
        ->test('pages::dashboard.view-test-script', [
            'project' => $project,
            'test' => $test,
            'script' => $script,
        ])
        ->call('saveFile', 'lib/helper.js', 'export const x = 1;');

    expect(Storage::disk('local')->get($basePath.'/lib/helper.js'))->toBe('export const x = 1;');
});

test('saving a file rejects path traversal', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    $basePath = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->makeDirectory($basePath);
    Storage::disk('local')->put($basePath.'/script.js', '// entry');

    $this->actingAs($user);

    Livewire::actingAs($user)
        ->test('pages::dashboard.view-test-script', [
            'project' => $project,
            'test' => $test,
            'script' => $script,
        ])
        ->call('saveFile', '../../evil.js', 'bad')
        ->assertHasErrors();

    Storage::disk('local')->assertMissing('evil.js');
});

test('editor page renders save toolbar and dirty dot markup', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    $basePath = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->makeDirectory($basePath);
    Storage::disk('local')->put($basePath.'/script.js', '// entry');

    $this->actingAs($user)
        ->get(route('projects.view-test-script', [
            'project' => $project,
            'test' => $test,
            'script' => $script,
        ]))
        ->assertOk()
        ->assertSee('Ctrl+S to save', false)
        ->assertSee("Alpine.store('editor')", false);
});

test('script editor shows a view current run button when a run is active', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    $basePath = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->makeDirectory($basePath);
    Storage::disk('local')->put($basePath.'/script.js', '// entry');

    $run = Run::factory()->for($script)->running()->create();

    $this->actingAs($user)
        ->get(route('projects.view-test-script', [
            'project' => $project,
            'test' => $test,
            'script' => $script,
        ]))
        ->assertOk()
        ->assertSee('View Current Run')
        ->assertSee(route('projects.runs.view', ['project' => $project, 'run' => $run]));
});

test('script editor shows a view current run button for queued runs', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    $basePath = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->makeDirectory($basePath);
    Storage::disk('local')->put($basePath.'/script.js', '// entry');

    $run = Run::factory()->for($script)->queued()->create();

    $this->actingAs($user)
        ->get(route('projects.view-test-script', [
            'project' => $project,
            'test' => $test,
            'script' => $script,
        ]))
        ->assertOk()
        ->assertSee('View Current Run')
        ->assertSee(route('projects.runs.view', ['project' => $project, 'run' => $run]));
});

test('script editor hides the view current run button without an active run', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    $basePath = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->makeDirectory($basePath);
    Storage::disk('local')->put($basePath.'/script.js', '// entry');

    Run::factory()->for($script)->passed()->create();

    $this->actingAs($user)
        ->get(route('projects.view-test-script', [
            'project' => $project,
            'test' => $test,
            'script' => $script,
        ]))
        ->assertOk()
        ->assertDontSee('View Current Run');
});

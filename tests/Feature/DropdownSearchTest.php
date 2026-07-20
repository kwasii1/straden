<?php

use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

test('goToProject redirects to the selected project overview', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    Livewire::actingAs($user)
        ->test('dropdown-search')
        ->call('goToProject', $project->slug)
        ->assertRedirect(route('projects.overview', ['project' => $project]));
});

test('goToProject redirects to the chosen project even when another project is current', function () {
    $user = User::factory()->create();
    $current = Project::factory()->create();
    $other = Project::factory()->create();

    Livewire::actingAs($user)
        ->test('dropdown-search')
        ->set('currentProject', $current)
        ->call('goToProject', $other->slug)
        ->assertRedirect(route('projects.overview', ['project' => $other]));
});

test('the current project is resolved from the route so the check icon can render', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.overview', ['project' => $project]))
        ->assertOk()
        ->assertSee("currentProjectId: '{$project->slug}'", false);
});

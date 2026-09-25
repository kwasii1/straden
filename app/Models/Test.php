<?php

namespace App\Models;

use App\Enums\ConnectorType;
use App\Services\SlugGenerator;
use Database\Factories\TestFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;
use Laravel\Ai\Concerns\HasConversations;

class Test extends Model
{
    use HasConversations;

    /** @use HasFactory<TestFactory> */
    use HasFactory;

    use HasUuids;

    public $incrementing = true;

    protected $primaryKey = 'id';

    protected $fillable = [
        'name',
        'target_url',
        'project_id',
        'description',
        'slug',
    ];

    protected static function booted(): void
    {
        static::creating(function (Test $test) {
            $test->slug = SlugGenerator::unique($test->name, self::class);
        });
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<Script, $this> */
    public function scripts(): HasMany
    {
        return $this->hasMany(Script::class);
    }

    /** @return HasManyThrough<Run, Script, $this> */
    public function runs(): HasManyThrough
    {
        return $this->hasManyThrough(Run::class, Script::class);
    }

    /** @return BelongsToMany<Connector, $this> */
    public function connectors(): BelongsToMany
    {
        return $this->belongsToMany(Connector::class, 'connector_test');
    }

    /** @return BelongsToMany<Repository, $this> */
    public function repositories(): BelongsToMany
    {
        return $this->belongsToMany(Repository::class, 'repository_test')->withTimestamps();
    }

    /**
     * Repositories whose code is relevant to this test: the linked ones, or
     * every repository in the project when none are linked.
     *
     * @return Collection<int, Repository>
     */
    public function contextRepositories(): Collection
    {
        $linked = $this->repositories()->get();

        return $linked->isNotEmpty() ? $linked : $this->project->repositories()->get();
    }

    /**
     * Sync the test's repositories from user-selected IDs, keeping only
     * repositories that belong to the test's project.
     *
     * @param  array<int, string>  $ids
     */
    public function syncRepositories(array $ids): void
    {
        $ids = array_values(array_filter(
            array_map(strval(...), $ids),
            fn (string $id) => Str::isUuid($id)
        ));

        $valid = Repository::query()
            ->where('project_id', $this->project_id)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(strval(...))
            ->all();

        $this->repositories()->sync($valid);
    }

    /**
     * Sync the test's connectors from user-selected IDs.
     *
     * IDs are scoped to the test's project (or system connectors) and can
     * never include InfluxDB — it is always attached automatically.
     *
     * @param  array<int, string>  $ids
     */
    public function syncConnectors(array $ids): void
    {
        // Non-UUID input would crash the uuid comparison in Postgres —
        // drop it before querying.
        $ids = array_values(array_filter(
            array_map(strval(...), $ids),
            fn (string $id) => Str::isUuid($id)
        ));

        $scope = fn ($query) => $query
            ->where('project_id', $this->project_id)
            ->orWhere('is_system', true);

        $valid = Connector::query()
            ->where($scope)
            ->where('type', '!=', ConnectorType::InfluxDb->value)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(strval(...))
            ->all();

        $influx = Connector::query()
            ->where($scope)
            ->where('type', ConnectorType::InfluxDb->value)
            ->value('id');

        if ($influx !== null) {
            $valid[] = (string) $influx;
        }

        $this->connectors()->sync(array_values(array_unique($valid)));
    }
}

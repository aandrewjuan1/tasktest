<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProjectService
{
    public function createProject(array $data, int $userId): Project
    {
        $startDatetime = ! empty($data['startDatetime'])
            ? Carbon::parse($data['startDatetime'])
            : null;

        $endDatetime = ! empty($data['endDatetime'])
            ? Carbon::parse($data['endDatetime'])
            : null;

        return DB::transaction(function () use ($data, $startDatetime, $endDatetime, $userId) {
            $project = Project::create([
                'user_id' => $userId,
                'name' => $data['name'],
                'start_datetime' => $startDatetime,
                'end_datetime' => $endDatetime,
            ]);

            if (! empty($data['tagIds'])) {
                $project->tags()->attach($data['tagIds']);
            }

            return $project;
        });
    }

    public function updateProjectField(Project $project, string $field, mixed $value): void
    {
        DB::transaction(function () use ($project, $field, $value) {
            $updateData = [];

            switch ($field) {
                case 'name':
                    $updateData['name'] = $value;
                    break;
                case 'description':
                    $updateData['description'] = $value ?: null;
                    break;
                case 'startDatetime':
                    $updateData['start_datetime'] = $value ? Carbon::parse($value) : null;
                    break;
                case 'endDatetime':
                    $updateData['end_datetime'] = $value ? Carbon::parse($value) : null;
                    break;
            }

            if (! empty($updateData)) {
                $project->update($updateData);
            }
        });
    }

    public function deleteProject(Project $project): void
    {
        DB::transaction(function () use ($project) {
            $project->delete();
        });
    }

    public function updateProjectDateTime(Project $project, string $start, ?string $end = null): void
    {
        DB::transaction(function () use ($project, $start, $end) {
            if ($start) {
                $project->start_datetime = Carbon::parse($start);
            } else {
                $project->start_datetime = null;
            }
            if ($end) {
                $project->end_datetime = Carbon::parse($end);
            } else {
                $project->end_datetime = null;
            }

            $project->save();
        });
    }

    public function updateProjectTags(Project $project, array $tagIds): void
    {
        DB::transaction(function () use ($project, $tagIds) {
            $project->tags()->sync($tagIds);
            $project->refresh();
        });
    }
}

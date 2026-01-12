<?php

namespace App\Services;

use App\Models\Tag;
use Illuminate\Support\Facades\DB;

class TagService
{
    public function createTag(string $name): Tag
    {
        return DB::transaction(function () use ($name) {
            return Tag::create(['name' => $name]);
        });
    }

    public function deleteTag(Tag $tag): void
    {
        DB::transaction(function () use ($tag) {
            $tag->delete();
        });
    }

    public function findOrCreateTag(string $name): array
    {
        $existing = Tag::whereRaw('LOWER(name) = LOWER(?)', [$name])->first();

        if ($existing) {
            return ['tag' => $existing, 'wasRecentlyCreated' => false];
        }

        $tag = $this->createTag($name);
        return ['tag' => $tag, 'wasRecentlyCreated' => true];
    }
}

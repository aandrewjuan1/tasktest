<?php

namespace App\Traits;

use App\Models\Tag;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait HandlesWorkspaceTags
{
    public function availableTags(): Collection
    {
        return Tag::orderBy('name')->get();
    }

    /**
     * @return array{success: bool, message?: string, tagId?: int, tagName?: string, alreadyExists?: bool}
     */
    public function createTag(string $name): array
    {
        $name = trim($name);

        if ($name === '') {
            return ['success' => false, 'message' => 'Tag name cannot be empty'];
        }

        try {
            $result = $this->getTagService()->findOrCreateTag($name);
            $tag = $result['tag'];

            if (! in_array($tag->id, $this->filterTagIds, true)) {
                $this->filterTagIds[] = $tag->id;
            }

            return [
                'success' => true,
                'tagId' => $tag->id,
                'tagName' => $tag->name,
                'alreadyExists' => ! $result['wasRecentlyCreated'],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create tag', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'tag_name' => $name,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ['success' => false, 'message' => 'Failed to create tag'];
        }
    }

    /**
     * @return array{success: bool, message?: string}
     */
    public function deleteTag(int $tagId): array
    {
        try {
            $tag = Tag::findOrFail($tagId);
            $this->getTagService()->deleteTag($tag);

            $this->filterTagIds = array_values(
                array_filter($this->filterTagIds, fn ($id) => (int) $id !== $tagId)
            );

            return ['success' => true];
        } catch (\Exception $e) {
            Log::error('Failed to delete tag', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'tag_id' => $tagId,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ['success' => false, 'message' => 'Failed to delete tag'];
        }
    }
}

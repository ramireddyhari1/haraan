<?php

declare(strict_types=1);

namespace App\Filament\Resources\HomeFeedPosts\Pages;

use App\Filament\Resources\HomeFeedPosts\HomeFeedPostResource;
use App\Models\AdminAction;
use App\Models\PlayerPost;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Post as an account from /control. Stores the post exactly the way the app's upload does —
 * cover on player_posts.image_path, every photo as a post_images row in order — so the feed,
 * the profile grid and older app builds all read it like any other post.
 */
class CreateHomeFeedPost extends CreateRecord
{
    protected static string $resource = HomeFeedPostResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $paths = array_values(array_map(fn (string $p): string => '/storage/'.ltrim($p, '/'), (array) ($data['photos'] ?? [])));
        $caption = trim((string) ($data['caption'] ?? ''));

        $post = DB::transaction(function () use ($data, $paths, $caption): PlayerPost {
            $post = PlayerPost::create([
                'user_id' => (int) $data['user_id'],
                'image_path' => $paths[0],
                'caption' => $caption === '' ? null : $caption,
                'status' => PlayerPost::STATUS_VISIBLE,
                'featured' => (bool) ($data['featured'] ?? false),
                'pinned_at' => ($data['pin'] ?? false) ? now() : null,
                'pinned_until' => ($data['pin'] ?? false) ? ($data['pinned_until'] ?? null) : null,
            ]);
            foreach ($paths as $i => $path) {
                $post->images()->create(['image_path' => $path, 'position' => $i]);
            }

            return $post;
        });

        AdminAction::log('home_feed.post_created', [
            'post_id' => $post->id,
            'as_user_id' => $post->user_id,
            'photos' => count($paths),
            'pinned' => $post->pinned_at !== null,
            'featured' => $post->featured,
        ], $post);

        return $post;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}

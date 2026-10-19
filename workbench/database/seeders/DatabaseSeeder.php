<?php

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Workbench\App\Models\Comment;
use Workbench\App\Models\User;

/** The demo user the workbench signs in as (demo@example.test / password), a neighbour, and a few rows. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $demo = User::create(['name' => 'Demo', 'email' => 'demo@example.test', 'password' => Hash::make('password'), 'is_admin' => false]);
        $neighbour = User::create(['name' => 'Neighbour', 'email' => 'neighbour@example.test', 'password' => Hash::make('password')]);
        User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => Hash::make('password'), 'is_admin' => true]);

        $first = null;
        foreach ([['Hello, world', 'The first post. Edit me from /web, /spa, the API or an agent.', true], ['A draft', 'Only its owner sees a draft.', false], ['Notes on the grammar', 'Try /api/posts?filter[published]=1&sort=-id&fields=title', true]] as [$title, $body, $published]) {
            $post = $demo->posts()->create(['title' => $title, 'body' => $body, 'published' => $published]);
            $post->forceFill(['internal_note' => 'never shown'])->save();
            $first ??= $post;
        }
        $theirs = $neighbour->posts()->create(['title' => "The neighbour's post", 'body' => 'You cannot read this one as the demo user.', 'published' => true]);

        // Post's OwnedByUser scope hides every row from a guest, and a seeder is a guest: keep the handles
        Comment::forceCreate(['post_id' => $first->id, 'user_id' => $neighbour->id, 'body' => 'Nice first post.']);
        Comment::forceCreate(['post_id' => $theirs->id, 'user_id' => $demo->id, 'body' => 'A comment on a post I cannot read.']);
    }
}

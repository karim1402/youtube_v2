<?php

namespace App\Helpers;

use Google\Service\YouTube;
use Illuminate\Support\Facades\Log;

class YouTubePlaylistHelper
{
    /**
     * Add a video to all playlists in the authenticated channel
     *
     * @param \Google\Client $client Authenticated Google Client
     * @param string $videoId The ID of the video to add
     * @return void
     */
    public static function addVideoToAllPlaylists($client, $videoId)
    {
        try {
            $youtube = new YouTube($client);
            
            // 1. List all playlists (max 50 for now)
            $queryParams = [
                'mine' => true,
                'maxResults' => 50,
                'part' => 'snippet,contentDetails'
            ];
            
            $playlistsResponse = $youtube->playlists->listPlaylists('snippet,contentDetails', $queryParams);
            $playlists = $playlistsResponse->getItems();
            
            if (empty($playlists)) {
                Log::info("No playlists found to add video {$videoId}.");
                return;
            }
            
            Log::info("Found " . count($playlists) . " playlists. Adding video {$videoId} to all...");
            
            foreach ($playlists as $playlist) {
                $playlistId = $playlist->getId();
                $playlistTitle = $playlist->getSnippet()->getTitle();
                
                try {
                    // 2. Create Playlist Item
                    $playlistItemSnippet = new YouTube\PlaylistItemSnippet();
                    $playlistItemSnippet->setPlaylistId($playlistId);
                    
                    $resourceId = new YouTube\ResourceId();
                    $resourceId->setKind('youtube#video');
                    $resourceId->setVideoId($videoId);
                    
                    $playlistItemSnippet->setResourceId($resourceId);
                    
                    $playlistItem = new YouTube\PlaylistItem();
                    $playlistItem->setSnippet($playlistItemSnippet);
                    
                    // 3. Insert into Playlist
                    $youtube->playlistItems->insert('snippet', $playlistItem);
                    
                    Log::info("Added video {$videoId} to playlist: {$playlistTitle} ({$playlistId})");
                    
                } catch (\Exception $e) {
                    // Catch individual playlist errors (e.g., duplicate, limit reached) so others continue
                    Log::warning("Failed to add video {$videoId} to playlist {$playlistTitle}: " . $e->getMessage());
                }
            }
            
        } catch (\Exception $e) {
            Log::error("Failed to fetch playlists or process batch: " . $e->getMessage());
        }
    }
}

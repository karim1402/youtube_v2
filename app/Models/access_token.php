<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class access_token extends Model
{
    protected $table = 'access_tokens';

   protected $fillable = [
        'channel_id',
        'access_token',
        'refresh_token',
        'expires_at',
        'scope',
        'created',
        'token_type',
        'refresh_token_expires_in',
    ];

    public $timestamps = true;

    /**
     * Get the channel ID.
     *
     * @return string
     */
    public function getChannelId()
    {
        return $this->channel_id;
    }

    /**
     * Set the channel ID.
     *
     * @param string $channelId
     */
    public function setChannelId($channelId)
    {
        $this->channel_id = $channelId;
    }
}

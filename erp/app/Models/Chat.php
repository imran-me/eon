<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Chat extends Model
{
    use SoftDeletes;

    protected $fillable = ['sender_id', 'receiver_id', 'message', 'read_at', 'edited_at', 'file_path', 'file_type'];

    protected $appends = ['file_url'];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function getFileUrlAttribute(): ?string
    {
        if (!$this->file_path) {
            return null;
        }
        if (preg_match('/^https?:\/\//i', $this->file_path)) {
            return $this->file_path;
        }
        return Storage::url($this->file_path);
    }
}

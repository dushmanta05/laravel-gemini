<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ChatMessage extends Model
{
    use HasFactory, HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['chat_id', 'user', 'model'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (!$model->getKey()) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function chat()
    {
        return $this->belongsTo(Chat::class, 'chat_id');
    }
}

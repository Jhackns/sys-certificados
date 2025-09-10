<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Validation extends Model
{
    use HasFactory;

    protected $fillable = [
        'certificate_id',
        'user_id',
        'validated_at',
        'ip_address',
    ];

    protected $casts = [
        'validated_at' => 'datetime',
    ];

    /**
     * Relación con certificado
     */
    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }

    /**
     * Relación con usuario (opcional)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope para validaciones por IP
     */
    public function scopeByIp($query, $ip)
    {
        return $query->where('ip_address', $ip);
    }

    /**
     * Scope para validaciones recientes
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('validated_at', '>=', now()->subDays($days));
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'activity_id',
        'id_template',
        'signed_by',
        'nombre',
        'descripcion',
        'unique_code',
        'verification_code',
        'verification_token',
        'verification_url',
        'qr_url',
        'qr_image_path',
        'final_image_path',
        'validation_data',
        'verification_count',
        'last_verified_at',
        'fecha_emision',
        'fecha_vencimiento',
        'issued_at',
        'status',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'fecha_emision' => 'date',
        'fecha_vencimiento' => 'date',
        'last_verified_at' => 'datetime',
        'validation_data' => 'array',
    ];

    /**
     * Relación con plantilla de certificado
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class, 'id_template');
    }

    /**
     * Relación con usuario (receptor del certificado)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con actividad
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * Relación con usuario que firmó (signed_by)
     */
    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    /**
     * Relación con validaciones
     */
    public function validations(): HasMany
    {
        return $this->hasMany(Validation::class);
    }

    /**
     * Relación con documentos
     */
    public function documents(): HasMany
    {
        return $this->hasMany(CertificateDocument::class);
    }

    /**
     * Relación con envíos de correo
     */
    public function emailSends(): HasMany
    {
        return $this->hasMany(EmailSend::class);
    }

    /**
     * Scope para certificados por código único
     */
    public function scopeByCode($query, $code)
    {
        return $query->where('unique_code', $code);
    }

    /**
     * Scope para certificados por código de verificación
     */
    public function scopeByVerificationCode($query, $code)
    {
        return $query->where('verification_code', $code);
    }

    /**
     * Scope para certificados por token de verificación
     */
    public function scopeByVerificationToken($query, $token)
    {
        return $query->where('verification_token', $token);
    }

    /**
     * Generar código de verificación mixto
     */
    public function generateVerificationCode(): string
    {
        $prefix = 'CERT' . str_pad($this->id, 3, '0', STR_PAD_LEFT);
        $token = substr(bin2hex(random_bytes(8)), 0, 12);
        return $prefix . '-' . $token;
    }

    /**
     * Generar token de verificación seguro
     */
    public function generateVerificationToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Obtener URL de verificación completa
     */
    public function getVerificationUrl(): string
    {
        $baseUrl = config('app.url');
        return $baseUrl . '/verify/' . $this->verification_code;
    }

    /**
     * Verificar si el certificado está válido
     */
    public function isValid(): bool
    {
        if ($this->status === 'revoked' || $this->status === 'cancelled') {
            return false;
        }

        if ($this->status === 'expired') {
            return false;
        }

        if ($this->fecha_vencimiento && $this->fecha_vencimiento->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Incrementar contador de verificaciones
     */
    public function incrementVerificationCount(): void
    {
        $this->increment('verification_count');
        $this->update(['last_verified_at' => now()]);
    }

    /**
     * Obtener URL para el contenido del QR (externo)
     */
    public function getQrContentUrl(): string
    {
        // Retorna una URL que muestra el código único como texto (en una imagen)
        // Usamos dummyimage para mostrar el texto "CERT-XXXX" en una imagen blanca
        return "https://dummyimage.com/600x400/ffffff/000000&text=" . urlencode($this->unique_code);
    }
}

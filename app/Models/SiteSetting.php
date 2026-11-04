<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SiteSetting extends Model
{
    protected $guarded = ['id'];

    public static function current(): self
    {
        // Branding must also work before migrations and with minimal test databases.
        return Schema::hasTable('site_settings')
            ? (static::find(1) ?? new static(['app_name' => 'Visible']))
            : new static(['app_name' => 'Visible']);
    }

    public function logoLocalPath(): ?string
    {
        if (! $this->logo_path || ! preg_match('#^branding/[a-zA-Z0-9_-]+\.(png|jpe?g|webp)$#i', $this->logo_path)) {
            return null;
        }

        $path = Storage::disk('admin_media')->path($this->logo_path);

        return is_file($path) ? $path : null;
    }

    public function logoUrl(): ?string
    {
        return $this->logoLocalPath() ? Storage::disk('admin_media')->url($this->logo_path) : null;
    }
}

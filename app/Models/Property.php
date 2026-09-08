<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\UnitStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'type',
    'slug',
    'address',
    'address_url',
    'region_id',
    'city_id',
    'kecamatan',
    'postal_code',
    'phone',
    'description',
    'image',
    'images',
    'video',
    'is_active',
])]
class Property extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected array $auditableMask = ['phone'];

    protected $appends = ['type_label', 'image_url', 'image_urls', 'video_url'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'images' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function propertyType(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class, 'type', 'slug');
    }

    /**
     * Human-readable type name when the relation is explicitly loaded.
     */
    protected function typeLabel(): Attribute
    {
        return Attribute::get(fn () => $this->relationLoaded('propertyType')
            ? $this->propertyType?->label ?? $this->type
            : $this->type);
    }

    /**
     * Public storage URL for the primary property image.
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(function () {
            if ($this->image) {
                return str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')
                    ? $this->image
                    : Storage::disk('public')->url($this->image);
            }

            if (! empty($this->images) && is_array($this->images) && count($this->images) > 0) {
                $first = $this->images[0];

                return str_starts_with($first, 'http://') || str_starts_with($first, 'https://')
                    ? $first
                    : Storage::disk('public')->url($first);
            }

            return null;
        });
    }

    /**
     * Public storage or direct URLs for all property images.
     */
    protected function imageUrls(): Attribute
    {
        return Attribute::get(function () {
            $urls = [];

            if (! empty($this->images) && is_array($this->images)) {
                foreach ($this->images as $path) {
                    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                        $urls[] = $path;
                    } else {
                        $urls[] = Storage::disk('public')->url($path);
                    }
                }
            }

            if (empty($urls) && $this->image_url) {
                $urls[] = $this->image_url;
            }

            return array_values(array_unique($urls));
        });
    }

    /**
     * Public storage or direct URL for the property video.
     */
    protected function videoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->video
            ? (str_starts_with($this->video, 'http://') || str_starts_with($this->video, 'https://')
                ? $this->video
                : Storage::disk('public')->url($this->video))
            : null);
    }

    protected static function booted(): void
    {
        static::saving(function (Property $property) {
            if (empty($property->slug) && ! empty($property->name)) {
                $base = Str::slug($property->name);
                $slug = $base;
                $counter = 1;
                while (static::withTrashed()->where('id', '!=', $property->id)->where('slug', $slug)->exists()) {
                    $slug = $base.'-'.$counter++;
                }
                $property->slug = $slug;
            }
        });
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function leases(): HasManyThrough
    {
        return $this->hasManyThrough(Lease::class, Unit::class);
    }

    /**
     * Everything the property workspace header/tabs need.
     */
    public function scopeWithWorkspaceStats(Builder $query): void
    {
        $query->with(['city', 'region', 'propertyType'])
            ->withCount('units')
            ->withOccupiedUnitsCount()
            ->withTenantsCount();
    }

    public function scopeWithOccupiedUnitsCount(Builder $query): void
    {
        $query->withCount(['units as occupied_units_count' => fn (Builder $q) => $q->where(function (Builder $q) {
            $q->where('status', UnitStatus::Occupied)
                ->orWhereHas('leases', fn (Builder $q) => $q->where('status', 'active'));
        })]);
    }

    public function scopeWithTenantsCount(Builder $query): void
    {
        $query->addSelect([
            'tenants_count' => DB::table('leases')
                ->selectRaw('COALESCE(COUNT(DISTINCT lease_tenant.tenant_id), 0)')
                ->join('lease_tenant', 'lease_tenant.lease_id', '=', 'leases.id')
                ->join('units', 'units.id', '=', 'leases.unit_id')
                ->whereColumn('units.property_id', 'properties.id')
                ->where('leases.status', 'active'),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\LeaseStatus;
use App\Http\Requests\Property\StorePropertyRequest;
use App\Http\Requests\Property\UpdatePropertyRequest;
use App\Models\City;
use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\Region;
use App\Models\Setting;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PropertyController extends Controller
{
    public function show(Request $request, Property $property): Response
    {
        $this->authorize('view', $property);

        $property = Property::withWorkspaceStats()->findOrFail($property->id);

        return Inertia::render('properties/overview', [
            'property' => $property,
        ]);
    }

    public function index(Request $request): Response
    {
        $table = Table::make()
            ->columns([
                Column::make('name', 'Name')->sortable()->searchable(),
                Column::make('type', 'Type')->sortable(),
                Column::make('kecamatan', 'Kecamatan')->sortable()->searchable(function (Builder $q, string $search): void {
                    $q->orWhere('properties.kecamatan', 'like', '%'.$search.'%')
                        ->orWhereHas('city', fn (Builder $q) => $q->where(
                            DB::raw('lower(name)'), 'like', '%'.mb_strtolower($search).'%',
                        ))
                        ->orWhereHas('region', fn (Builder $q) => $q->where(
                            DB::raw('lower(name)'), 'like', '%'.mb_strtolower($search).'%',
                        ));
                }),
                Column::make('units_count', 'Total Units')->sortable(),
                Column::make('occupied_units_count', 'Occupied')->sortable(),
                Column::make('tenants_count', 'Tenants')->sortable(),
            ])
            ->filters([
                Filter::select('status', 'Status', ['active', 'archived'])
                    ->query(fn (Builder $q, string $value) => match ($value) {
                        'active' => $q->where('properties.is_active', true)->whereNull('properties.deleted_at'),
                        'archived' => $q->onlyTrashed(),
                        default => $q,
                    }),
                Filter::select('type', 'Type', PropertyType::ordered()->pluck('slug')->all())
                    ->query(fn (Builder $q, string $value) => $q->where('type', $value)),
            ])
            ->defaultSort('name');

        $query = Property::query()
            ->withTrashed()
            ->when(! $request->user()->isOwner(), fn (Builder $q) => $q->whereHas(
                'users',
                fn (Builder $q) => $q->whereKey($request->user()->id),
            ))
            ->with(['city', 'region', 'propertyType'])
            ->withCount('units')
            ->withOccupiedUnitsCount()
            ->withTenantsCount();

        $result = $table->paginate($query, $request, 'properties');

        $countryCode = Setting::get('country_code');
        $regions = Region::where('country_code', $countryCode)
            ->with('cities')
            ->orderBy('name')
            ->get();

        return Inertia::render('properties/index', [
            ...$result,
            'regions' => $regions,
            'propertyTypes' => PropertyType::active()->ordered()->get(['slug', 'label']),
        ]);
    }

    public function store(StorePropertyRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('properties', 'public');
        }

        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $imgFile) {
                $imagePaths[] = $imgFile->store('properties', 'public');
            }
        }
        if (! empty($imagePaths)) {
            $data['images'] = $imagePaths;
            if (empty($data['image'])) {
                $data['image'] = $imagePaths[0];
            }
        }

        if ($request->hasFile('video')) {
            $data['video'] = $request->file('video')->store('properties/videos', 'public');
        }

        $property = Property::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Property created.')]);

        return back();
    }

    public function update(UpdatePropertyRequest $request, Property $property): RedirectResponse
    {
        $this->authorize('update', $property);

        $data = $request->validated();

        if ($request->boolean('remove_image')) {
            if ($property->image && ! str_starts_with($property->image, 'http')) {
                Storage::disk('public')->delete($property->image);
            }
            $data['image'] = null;
        } elseif ($request->hasFile('image')) {
            if ($property->image && ! str_starts_with($property->image, 'http')) {
                Storage::disk('public')->delete($property->image);
            }
            $data['image'] = $request->file('image')->store('properties', 'public');
        } else {
            unset($data['image']);
        }

        $existingImages = $property->images ?? [];
        if (is_string($existingImages)) {
            $existingImages = json_decode($existingImages, true) ?? [];
        }

        if (! empty($data['removed_images']) && is_array($data['removed_images'])) {
            foreach ($data['removed_images'] as $pathToRemove) {
                if (($key = array_search($pathToRemove, $existingImages)) !== false) {
                    unset($existingImages[$key]);
                    if (! str_starts_with($pathToRemove, 'http')) {
                        Storage::disk('public')->delete($pathToRemove);
                    }
                }
            }
            $existingImages = array_values($existingImages);
        }

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $imgFile) {
                $existingImages[] = $imgFile->store('properties', 'public');
            }
        }

        $data['images'] = array_values($existingImages);

        if ($request->boolean('remove_video')) {
            if ($property->video && ! str_starts_with($property->video, 'http')) {
                Storage::disk('public')->delete($property->video);
            }
            $data['video'] = null;
        } elseif ($request->hasFile('video')) {
            if ($property->video && ! str_starts_with($property->video, 'http')) {
                Storage::disk('public')->delete($property->video);
            }
            $data['video'] = $request->file('video')->store('properties/videos', 'public');
        } elseif (! array_key_exists('video', $data)) {
            unset($data['video']);
        }

        unset($data['remove_image'], $data['remove_video'], $data['removed_images']);

        $property->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Property updated.')]);

        return back();
    }

    public function destroy(Request $request, Property $property): RedirectResponse
    {
        $this->authorize('delete', $property);

        $force = $request->boolean('force') || $property->trashed();

        $deleted = DB::transaction(function () use ($property, $force) {
            $locked = Property::withTrashed()->lockForUpdate()->findOrFail($property->id);

            if (Lease::whereHas('unit', fn ($q) => $q->withTrashed()->where('property_id', $locked->id))
                ->where('status', LeaseStatus::Active)
                ->exists()
            ) {
                return false;
            }

            if ($force) {
                if ($locked->image && ! str_starts_with($locked->image, 'http')) {
                    Storage::disk('public')->delete($locked->image);
                }
                $images = $locked->images ?? [];
                if (is_string($images)) {
                    $images = json_decode($images, true) ?? [];
                }
                if (is_array($images)) {
                    foreach ($images as $img) {
                        if ($img && ! str_starts_with($img, 'http')) {
                            Storage::disk('public')->delete($img);
                        }
                    }
                }
                if ($locked->video && ! str_starts_with($locked->video, 'http')) {
                    Storage::disk('public')->delete($locked->video);
                }

                foreach ($locked->units()->withTrashed()->get() as $unit) {
                    if ($unit->image && ! str_starts_with($unit->image, 'http')) {
                        Storage::disk('public')->delete($unit->image);
                    }
                    if ($unit->video && ! str_starts_with($unit->video, 'http')) {
                        Storage::disk('public')->delete($unit->video);
                    }
                    $unit->rates()->delete();
                    $unit->forceDelete();
                }

                $locked->users()->detach();
                $locked->forceDelete();
            } else {
                $locked->update(['is_active' => false]);
                $locked->units()->delete();
                $locked->delete();
            }

            return true;
        });

        if (! $deleted) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Cannot delete a property with active leases.')]);

            return back();
        }

        $message = $force ? __('Property permanently deleted.') : __('Property deleted.');
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('properties.index');
    }

    public function restore(Property $property): RedirectResponse
    {
        $this->authorize('restore', $property);

        DB::transaction(function () use ($property) {
            $locked = Property::withTrashed()->lockForUpdate()->findOrFail($property->id);
            $locked->restore();
            $locked->update(['is_active' => true]);
            $locked->units()->onlyTrashed()->restore();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Property restored.')]);

        return back();
    }
}
